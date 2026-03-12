<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('add_reservations')) {
    flash('error', 'You do not have permission to create reservations.');
    redirect('index.php');
}
require_once __DIR__ . '/../includes/voucher_helpers.php';
require_once __DIR__ . '/../includes/reservation_payment_helpers.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
$pdo = db();

voucher_ensure_schema($pdo);
reservation_payment_ensure_schema($pdo);
ledger_ensure_schema($pdo);
settings_ensure_table($pdo);
$activeBankAccounts = array_values(array_filter(ledger_get_accounts($pdo), fn($a) => (int)($a['is_active'] ?? 0) === 1));
// Default delivery charge collected at reservation
$deliveryChargeDefault = (float) settings_get($pdo, 'delivery_charge_default', '0');

$clients = $pdo->query("SELECT id, name, voucher_balance FROM clients WHERE is_blacklisted=0 ORDER BY name")->fetchAll();
$hiddenCount = $pdo->query("SELECT COUNT(*) FROM clients WHERE is_blacklisted=1")->fetchColumn();
$errors = [];
$startDate = '';
$endDate = '';
$vehicles = [];

function assembleReservationDateTime(string $date, int $hour12, int $minute, string $ampm): string
{
    $date = trim($date);
    if ($date === '') {
        return '';
    }
    $hour12 = max(1, min(12, $hour12));
    $minute = max(0, min(59, $minute));
    $ampm = strtoupper($ampm) === 'PM' ? 'PM' : 'AM';
    $hour24 = $hour12 % 12;
    if ($ampm === 'PM') {
        $hour24 += 12;
    }
    return sprintf('%s %02d:%02d:00', $date, $hour24, $minute);
}

function fetchAvailableVehiclesForRange(PDO $pdo, string $startDate, string $endDate): array
{
    $sql = "SELECT v.id, v.brand, v.model, v.license_plate, v.daily_rate, v.monthly_rate,
                   v.rate_1day, v.rate_7day, v.rate_15day, v.rate_30day
            FROM vehicles v
            WHERE v.status <> 'maintenance'
              AND NOT EXISTS (
                  SELECT 1
                  FROM reservations r
                  WHERE r.vehicle_id = v.id
                    AND r.status IN ('pending','confirmed','active')
                    AND r.start_date < ?
                    AND r.end_date > ?
              )
            ORDER BY v.brand, v.model, v.license_plate";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$endDate, $startDate]);
    return $stmt->fetchAll();
}

function vehicleHasOverlappingReservation(PDO $pdo, int $vehicleId, string $startDate, string $endDate): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
                           FROM reservations
                           WHERE vehicle_id = ?
                             AND status IN ('pending','confirmed','active')
                             AND start_date < ?
                             AND end_date > ?");
    $stmt->execute([$vehicleId, $endDate, $startDate]);
    return (int) $stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
    $rentalType = $_POST['rental_type'] ?? 'daily';

    $startDate = assembleReservationDateTime(
        $_POST['start_date'] ?? '',
        (int) ($_POST['start_hour'] ?? 12),
        (int) ($_POST['start_min'] ?? 0),
        (string) ($_POST['start_ampm'] ?? 'AM')
    );
    $endDate = assembleReservationDateTime(
        $_POST['end_date'] ?? '',
        (int) ($_POST['end_hour'] ?? 12),
        (int) ($_POST['end_min'] ?? 0),
        (string) ($_POST['end_ampm'] ?? 'AM')
    );

    $totalPrice = (float) ($_POST['total_price'] ?? 0);
    $voucherRequest = max(0, (float) ($_POST['voucher_amount'] ?? 0));
    $voucherApplied = 0.0;

    // Advance Payment fields
    $advancePaid   = max(0, (float) ($_POST['advance_paid'] ?? 0));
    $advanceMethod = reservation_payment_method_normalize($_POST['advance_payment_method'] ?? null);
    $advanceBankId = (int) ($_POST['advance_bank_account_id'] ?? 0);
    $advanceBankId = $advanceBankId > 0 ? $advanceBankId : null;
    // Delivery charge collected at reservation
    $deliveryPrepaidInput = (float) ($_POST['delivery_charge_prepaid'] ?? $deliveryChargeDefault);
    $deliveryPrepaid = max(0, $deliveryPrepaidInput);
    $deliveryPrepaidMethod = reservation_payment_method_normalize($_POST['delivery_prepaid_payment_method'] ?? null);
    $deliveryPrepaidBankId = (int) ($_POST['delivery_prepaid_bank_account_id'] ?? 0);
    $deliveryPrepaidBankId = $deliveryPrepaidBankId > 0 ? $deliveryPrepaidBankId : null;
    $reservationNote = trim($_POST['reservation_note'] ?? '');
    $clientVoucherBalance = 0.0;

    if (!$clientId)
        $errors['client_id'] = 'Please select a client.';
    if (!$vehicleId)
        $errors['vehicle_id'] = 'Please select a vehicle.';
    if (!$startDate)
        $errors['start_date'] = 'Start date is required.';
    if (!$endDate)
        $errors['end_date'] = 'End date is required.';
    if ($startDate && $endDate && $endDate <= $startDate)
        $errors['end_date'] = 'End date must be after start date.';
    if ($totalPrice <= 0)
        $errors['total_price'] = 'Total price must be greater than 0.';

    // Guard: reject blacklisted
    if (!isset($errors['client_id'])) {
        $chk = $pdo->prepare('SELECT is_blacklisted, voucher_balance FROM clients WHERE id=?');
        $chk->execute([$clientId]);
        $cl = $chk->fetch();
        if ($cl && $cl['is_blacklisted'])
            $errors['client_id'] = 'This client is blacklisted and cannot make reservations.';
        if ($cl) {
            $clientVoucherBalance = max(0, (float) ($cl['voucher_balance'] ?? 0));
        }
    }

    if (!isset($errors['client_id']) && $voucherRequest > 0) {
        if ($voucherRequest > $totalPrice) {
            $errors['voucher_amount'] = 'Voucher amount cannot exceed total price.';
        } elseif ($voucherRequest > $clientVoucherBalance) {
            $errors['voucher_amount'] = 'Voucher amount cannot exceed client voucher balance ($' . number_format($clientVoucherBalance, 2) . ').';
        } else {
            $voucherApplied = round(min($voucherRequest, $totalPrice, $clientVoucherBalance), 2);
        }
    }

    // Advance validation
    if ($advancePaid > 0) {
        $remainingAfterVoucher = max(0, $totalPrice - $voucherApplied);
        if ($advancePaid > $remainingAfterVoucher) {
            $errors['advance_paid'] = 'Advance cannot exceed amount after voucher ($' . number_format($remainingAfterVoucher, 2) . ').';
        }
        if (!isset($errors['advance_paid']) && $advanceMethod === null) {
            $errors['advance_payment_method'] = 'Please select how the advance was received.';
        }
        if (!isset($errors['advance_paid']) && $advanceMethod === 'account') {
            if ($advanceBankId === null) {
                $errors['advance_bank_account_id'] = 'Please select the bank account for the advance.';
            }
        } elseif ($advanceMethod !== 'account') {
            $advanceBankId = null;
        }
    } else {
        $advanceMethod = null;
        $advanceBankId = null;
    }

    // Delivery prepaid validation
    if ($deliveryPrepaidInput < 0) {
        $errors['delivery_charge_prepaid'] = 'Delivery charge cannot be negative.';
    }
    if ($deliveryPrepaid > 0) {
        if ($deliveryPrepaidMethod === null) {
            $errors['delivery_prepaid_payment_method'] = 'Please select how the delivery charge was received.';
        }
        if (!isset($errors['delivery_prepaid_payment_method']) && $deliveryPrepaidMethod === 'account') {
            if ($deliveryPrepaidBankId === null) {
                $errors['delivery_prepaid_bank_account_id'] = 'Please select the bank account for the delivery charge.';
            }
        } elseif ($deliveryPrepaidMethod !== 'account') {
            $deliveryPrepaidBankId = null;
        }
    } else {
        $deliveryPrepaidMethod = null;
        $deliveryPrepaidBankId = null;
    }

    if (!isset($errors['vehicle_id']) && !isset($errors['start_date']) && !isset($errors['end_date'])) {
        $vehicleStmt = $pdo->prepare("SELECT status FROM vehicles WHERE id=?");
        $vehicleStmt->execute([$vehicleId]);
        $vehicleStatus = $vehicleStmt->fetchColumn();
        if ($vehicleStatus === false) {
            $errors['vehicle_id'] = 'Selected vehicle does not exist.';
        } elseif ($vehicleStatus === 'maintenance') {
            $errors['vehicle_id'] = 'Selected vehicle is under maintenance.';
        } elseif (vehicleHasOverlappingReservation($pdo, $vehicleId, $startDate, $endDate)) {
            $errors['vehicle_id'] = 'Selected vehicle is already reserved during the selected period.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if (vehicleHasOverlappingReservation($pdo, $vehicleId, $startDate, $endDate)) {
                throw new RuntimeException('Vehicle overlap');
            }

            $stmt = $pdo->prepare('INSERT INTO reservations (client_id,vehicle_id,rental_type,start_date,end_date,total_price,voucher_applied,advance_paid,advance_payment_method,advance_bank_account_id,delivery_charge_prepaid,delivery_prepaid_payment_method,delivery_prepaid_bank_account_id,status,note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $clientId,
                $vehicleId,
                $rentalType,
                $startDate,
                $endDate,
                $totalPrice,
                $voucherApplied,
                $advancePaid,
                $advanceMethod,
                $advanceBankId,
                $deliveryPrepaid,
                $deliveryPrepaidMethod,
                $deliveryPrepaidBankId,
                'confirmed',
                $reservationNote ?: null
            ]);
            $id = (int) $pdo->lastInsertId();

            if ($voucherApplied > 0) {
                voucher_apply_debit($pdo, $clientId, $voucherApplied, $id, 'Applied on reservation #' . $id);
            }

            $pdo->commit();

            // Ledger: post advance payment (outside transaction for safety)
            if ($advancePaid > 0 && $advanceMethod !== null) {
                ledger_post_reservation_event($pdo, $id, 'advance', $advancePaid, $advanceMethod, (int)$_SESSION['user']['id'], $advanceBankId);
            }
            if ($deliveryPrepaid > 0 && $deliveryPrepaidMethod !== null) {
                ledger_post_reservation_event($pdo, $id, 'delivery_prepaid', $deliveryPrepaid, $deliveryPrepaidMethod, (int)$_SESSION['user']['id'], $deliveryPrepaidBankId);
            }

            // Get client name
            $cn = $pdo->prepare('SELECT name FROM clients WHERE id=?');
            $cn->execute([$clientId]);
            $clientName = $cn->fetchColumn();

            // Get vehicle name
            $vn = $pdo->prepare('SELECT CONCAT(brand, " ", model) FROM vehicles WHERE id=?');
            $vn->execute([$vehicleId]);
            $vehicleName = $vn->fetchColumn() ?: 'Unknown Vehicle';

            // Create notification
            notif_create_reservation_event($pdo, $id, 'created', $clientName, $vehicleName);

            $msg = "Reservation confirmed for $clientName.";
            if ($voucherApplied > 0) {
                $msg .= ' Voucher used: $' . number_format($voucherApplied, 2) . '.';
            }
            if ($advancePaid > 0) {
                $msg .= ' Advance of $' . number_format($advancePaid, 2) . ' recorded.';
            }
            if ($deliveryPrepaid > 0) {
                $msg .= ' Delivery charge of $' . number_format($deliveryPrepaid, 2) . ' collected.';
            }
            $logMsg = "Created reservation (ID: $id)";
            if ($advancePaid > 0) {
                $logMsg .= " [Advance: \$$advancePaid ($advanceMethod)]";
            }
            if ($deliveryPrepaid > 0) {
                $logMsg .= " [Delivery Prepaid: \$$deliveryPrepaid ($deliveryPrepaidMethod)]";
            }
            app_log('ACTION', $logMsg);
            flash('success', $msg);
            redirect("show.php?id=$id");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e instanceof RuntimeException && $e->getMessage() === 'Vehicle overlap') {
                $errors['vehicle_id'] = 'Selected vehicle is no longer available for the selected period.';
            } else {
                app_log('ERROR', 'Reservation creation failed: ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine()]);
                $errors['db'] = 'Could not create reservation: ' . $e->getMessage();
            }
        }
    }
}

if ($startDate && $endDate && $endDate > $startDate) {
    $vehicles = fetchAvailableVehiclesForRange($pdo, $startDate, $endDate);
}

$pageTitle = 'New Reservation';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Reservations</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">New Reservation</span>
    </div>

    <?php if ($hiddenCount > 0): ?>
        <div
            class="bg-yellow-500/10 border border-yellow-500/20 rounded-lg px-5 py-3 text-yellow-400 text-sm flex items-center gap-2">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <?= $hiddenCount ?> blacklisted client(s) are hidden from selection.
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400 space-y-1">
            <?php foreach ($errors as $e): ?>
                <p>&bull;
                    <?= e($e) ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6" id="resForm">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form -->
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5 flex flex-col">
                    <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Reservation Details</h3>

                    <div class="order-1">
                        <label class="block text-sm text-mb-silver mb-2">Client <span
                                class="text-red-400">*</span></label>
                        <select name="client_id" id="clientSelect"
                            class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm"
                            required>
                            <option value="">-- Select Client --</option>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= $cl['id'] ?>"
                                    data-voucher-balance="<?= number_format((float) ($cl['voucher_balance'] ?? 0), 2, '.', '') ?>"
                                    <?= (($_POST['client_id'] ?? '') == $cl['id']) ? 'selected' : '' ?>>
                                    <?= e($cl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['client_id'])): ?>
                            <p class="text-red-400 text-xs mt-1">
                                <?= e($errors['client_id']) ?>
                            </p>
                        <?php endif; ?>
                        <p class="text-xs text-green-400 mt-2" id="clientVoucherInfo">Client voucher balance: $0.00</p>
                    </div>

                    <?php $hasDateRange = $startDate && $endDate && $endDate > $startDate; ?>
                    <div class="order-3">
                        <label class="block text-sm text-mb-silver mb-2">Vehicle <span
                                class="text-red-400">*</span></label>
                        <select name="vehicle_id" id="vehicleSelect"
                            class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm"
                            required <?= $hasDateRange ? '' : 'disabled' ?>>
                            <option value="">
                                <?= $hasDateRange ? '-- Select Available Vehicle --' : '-- Select Client + Rental Period First --' ?>
                            </option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>" data-daily="<?= $v['daily_rate'] ?>"
                                    data-monthly="<?= $v['monthly_rate'] ?? '' ?>"
                                    data-1day="<?= $v['rate_1day'] ?? '' ?>"
                                    data-7day="<?= $v['rate_7day'] ?? '' ?>"
                                    data-15day="<?= $v['rate_15day'] ?? '' ?>"
                                    data-30day="<?= $v['rate_30day'] ?? '' ?>"
                                    <?= (($_POST['vehicle_id'] ?? '') == $v['id']) ? 'selected' : '' ?>>
                                    <?= e($v['brand']) ?>
                                    <?= e($v['model']) ?> —
                                    <?= e($v['license_plate']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-mb-subtle mt-2" id="vehicleAvailabilityHint">
                            <?= $hasDateRange ? 'Showing vehicles available for the selected rental period.' : 'Select client and rental period to load available vehicles.' ?>
                        </p>
                        <?php if (isset($errors['vehicle_id'])): ?>
                            <p class="text-red-400 text-xs mt-1">
                                <?= e($errors['vehicle_id']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="order-4">
                        <label class="block text-sm text-mb-silver mb-2">Rental Type</label>
                        <div class="grid grid-cols-3 gap-2">
                            <?php
                            $rentalTypes = [
                                'daily'   => 'Daily',
                                '1day'    => '1 Day',
                                '7day'    => '7 Days',
                                '15day'   => '15 Days',
                                '30day'   => '30 Days',
                                'monthly' => 'Monthly',
                            ];
                            foreach ($rentalTypes as $val => $lbl): ?>
                                <label class="flex items-center gap-2 cursor-pointer bg-mb-black/30 border border-mb-subtle/10 rounded-lg px-3 py-2 hover:border-mb-accent/40 transition-all">
                                    <input type="radio" name="rental_type" value="<?= $val ?>"
                                        <?= (($_POST['rental_type'] ?? 'daily') === $val) ? 'checked' : '' ?>
                                    class="accent-mb-accent" onchange="onRentalTypeChange()">
                                    <span class="text-mb-silver text-sm"><?= $lbl ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 order-2">
                        <!-- Start Date & Time -->
                        <div class="space-y-3">
                            <label class="block text-sm text-mb-silver">Start Date & Time <span class="text-red-400">*</span></label>
                            <div class="flex flex-col gap-2">
                                <input type="date" name="start_date" id="startDate" 
                                    value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>" onchange="calcPrice()"
                                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white text-sm focus:border-mb-accent outline-none">
                                
                                <div class="flex items-center bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-3 py-1.5 focus-within:border-mb-accent transition-all hover:border-mb-subtle/40 shadow-inner">
                                    <select name="start_hour" id="startHour" onchange="calcPrice()"
                                        class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>" <?= (($_POST['start_hour'] ?? date('g')) == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="text-mb-subtle font-bold px-0.5">:</span>
                                    <select name="start_min" id="startMin" onchange="calcPrice()"
                                        class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                        <?php for ($i = 0; $i < 60; $i += 5): ?>
                                            <option value="<?= $i ?>" <?= (($_POST['start_min'] ?? floor(date('i') / 5) * 5) == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= sprintf('%02d', $i) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="w-px h-5 bg-mb-subtle/20 mx-2"></div>
                                    <select name="start_ampm" id="startAMPM" onchange="calcPrice()"
                                        class="bg-transparent text-mb-accent font-bold text-xs focus:outline-none px-2 py-1.5 cursor-pointer uppercase tracking-tight appearance-none">
                                        <option value="AM" <?= (($_POST['start_ampm'] ?? date('A')) == 'AM') ? 'selected' : '' ?> class="bg-mb-surface text-white">AM</option>
                                        <option value="PM" <?= (($_POST['start_ampm'] ?? date('A')) == 'PM') ? 'selected' : '' ?> class="bg-mb-surface text-white">PM</option>
                                    </select>
                                </div>
                            </div>
                            <?php if (isset($errors['start_date'])): ?>
                                <p class="text-red-400 text-xs mt-1"><?= e($errors['start_date']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- End Date & Time -->
                        <div class="space-y-3">
                            <label class="block text-sm text-mb-silver">End Date & Time <span class="text-red-400">*</span></label>
                            <div class="flex flex-col gap-2">
                                <input type="date" name="end_date" id="endDate" 
                                    value="<?= e($_POST['end_date'] ?? '') ?>" onchange="calcPrice()"
                                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white text-sm focus:border-mb-accent outline-none">
                                
                                <div class="flex items-center bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-3 py-1.5 focus-within:border-mb-accent transition-all hover:border-mb-subtle/40 shadow-inner">
                                    <select name="end_hour" id="endHour" onchange="calcPrice()"
                                        class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?= $i ?>" <?= (($_POST['end_hour'] ?? '') == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= $i ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <span class="text-mb-subtle font-bold px-0.5">:</span>
                                    <select name="end_min" id="endMin" onchange="calcPrice()"
                                        class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                                        <?php for ($i = 0; $i < 60; $i += 5): ?>
                                            <option value="<?= $i ?>" <?= (($_POST['end_min'] ?? '') === (string)$i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= sprintf('%02d', $i) ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="w-px h-5 bg-mb-subtle/20 mx-2"></div>
                                    <select name="end_ampm" id="endAMPM" onchange="calcPrice()"
                                        class="bg-transparent text-mb-accent font-bold text-xs focus:outline-none px-2 py-1.5 cursor-pointer uppercase tracking-tight appearance-none">
                                        <option value="AM" <?= (($_POST['end_ampm'] ?? '') == 'AM') ? 'selected' : '' ?> class="bg-mb-surface text-white">AM</option>
                                        <option value="PM" <?= (($_POST['end_ampm'] ?? '') == 'PM') ? 'selected' : '' ?> class="bg-mb-surface text-white">PM</option>
                                    </select>
                                </div>
                            </div>
                            <?php if (isset($errors['end_date'])): ?>
                                <p class="text-red-400 text-xs mt-1"><?= e($errors['end_date']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="order-5">
                        <label class="block text-sm text-mb-silver mb-2">Total Price (USD) <span
                                class="text-red-400">*</span></label>
                        <input type="number" name="total_price" id="totalPrice"
                            value="<?= e($_POST['total_price'] ?? '') ?>" step="0.01" min="0" required
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                    </div>

                    <div class="order-6">
                        <label class="block text-sm text-mb-silver mb-2">Use Voucher (Optional)</label>
                        <input type="number" name="voucher_amount" id="voucherAmount"
                            value="<?= e($_POST['voucher_amount'] ?? '') ?>" step="0.01" min="0"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-green-500/60 text-sm"
                            placeholder="0.00">
                        <p class="text-xs text-mb-subtle mt-1">
                            Voucher reduces amount collected at delivery. Remaining voucher stays in client account.
                        </p>
                        <?php if (isset($errors['voucher_amount'])): ?>
                            <p class="text-red-400 text-xs mt-1">
                                <?= e($errors['voucher_amount']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Advance Collected -->
                    <div class="order-8">
                        <label class="block text-sm text-mb-silver mb-2">Advance Collected (Optional)</label>
                        <input type="number" name="advance_paid" id="advancePaid"
                            value="<?= e($_POST['advance_paid'] ?? '') ?>" step="0.01"
                            min="0" placeholder="0.00"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                        <p class="text-xs text-mb-subtle mt-1">
                            Advance reduces the amount to be collected at delivery.
                        </p>
                        <?php if (isset($errors['advance_paid'])): ?>
                            <p class="text-red-400 text-xs mt-1"><?= e($errors['advance_paid']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div id="advanceMethodWrap" class="order-9 hidden space-y-2">
                        <label class="block text-xs text-mb-silver">Advance Payment Method</label>
                        <div class="grid grid-cols-3 gap-2">
                            <?php
                            $advanceMethods = ['cash' => 'Cash', 'account' => 'Account', 'credit' => 'Credit'];
                            foreach ($advanceMethods as $val => $label):
                                ?>
                                <label class="flex items-center gap-2 cursor-pointer bg-mb-black/30 border border-mb-subtle/10 rounded-lg px-3 py-2 hover:border-mb-accent/40 transition-all">
                                    <input type="radio" name="advance_payment_method" value="<?= $val ?>"
                                        <?= (($_POST['advance_payment_method'] ?? '') === $val) ? 'checked' : '' ?>
                                        class="accent-mb-accent" onchange="toggleAdvanceBankField()">
                                    <span class="text-mb-silver text-sm"><?= $label ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($errors['advance_payment_method'])): ?>
                            <p class="text-red-400 text-xs mt-1"><?= e($errors['advance_payment_method']) ?></p>
                        <?php endif; ?>
                        <div id="advanceBankWrap" class="hidden">
                            <label class="block text-xs text-mb-silver mb-1">Bank Account</label>
                            <select name="advance_bank_account_id" id="advanceBankAccount"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-mb-accent text-sm">
                                <option value="">Select account</option>
                                <?php foreach ($activeBankAccounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>" <?= ((string)($_POST['advance_bank_account_id'] ?? '')) === (string)$acc['id'] ? 'selected' : '' ?>>
                                        <?= e($acc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['advance_bank_account_id'])): ?>
                                <p class="text-red-400 text-xs mt-1"><?= e($errors['advance_bank_account_id']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Delivery Charge Collected at Booking -->
                    <div class="order-10">
                        <label class="block text-sm text-mb-silver mb-2">Delivery Charge Collected (Booking)</label>
                        <input type="number" name="delivery_charge_prepaid" id="deliveryChargePrepaid"
                            value="<?= e($_POST['delivery_charge_prepaid'] ?? number_format($deliveryChargeDefault, 2, '.', '')) ?>" step="0.01"
                            min="0" placeholder="0.00"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-blue-500/60 text-sm">
                        <p class="text-xs text-mb-subtle mt-1">Collected now; shown as read-only on delivery.</p>
                        <?php if (isset($errors['delivery_charge_prepaid'])): ?>
                            <p class="text-red-400 text-xs mt-1"><?= e($errors['delivery_charge_prepaid']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div id="deliveryPrepaidMethodWrap" class="order-11 hidden space-y-2">
                        <label class="block text-xs text-mb-silver">Delivery Charge Payment Method</label>
                        <div class="grid grid-cols-3 gap-2">
                            <?php
                            $deliveryPrepaidMethods = ['cash' => 'Cash', 'account' => 'Account', 'credit' => 'Credit'];
                            foreach ($deliveryPrepaidMethods as $val => $label):
                                ?>
                                <label class="flex items-center gap-2 cursor-pointer bg-mb-black/30 border border-mb-subtle/10 rounded-lg px-3 py-2 hover:border-mb-accent/40 transition-all">
                                    <input type="radio" name="delivery_prepaid_payment_method" value="<?= $val ?>"
                                        <?= (($_POST['delivery_prepaid_payment_method'] ?? '') === $val) ? 'checked' : '' ?>
                                        class="accent-mb-accent" onchange="toggleDeliveryPrepaidBankField()">
                                    <span class="text-mb-silver text-sm"><?= $label ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($errors['delivery_prepaid_payment_method'])): ?>
                            <p class="text-red-400 text-xs mt-1"><?= e($errors['delivery_prepaid_payment_method']) ?></p>
                        <?php endif; ?>
                        <div id="deliveryPrepaidBankWrap" class="hidden">
                            <label class="block text-xs text-mb-silver mb-1">Bank Account</label>
                            <select name="delivery_prepaid_bank_account_id" id="deliveryPrepaidBankAccount"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-mb-accent text-sm">
                                <option value="">Select account</option>
                                <?php foreach ($activeBankAccounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>" <?= ((string)($_POST['delivery_prepaid_bank_account_id'] ?? '')) === (string)$acc['id'] ? 'selected' : '' ?>>
                                        <?= e($acc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['delivery_prepaid_bank_account_id'])): ?>
                                <p class="text-red-400 text-xs mt-1"><?= e($errors['delivery_prepaid_bank_account_id']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Reservation Note -->
                    <div class="order-12">
                        <label class="block text-sm text-mb-silver mb-2">Reservation Note (Optional)</label>
                        <textarea name="reservation_note" rows="2" placeholder="Add any special instructions or notes..."
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent"><?= e($_POST['reservation_note'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Price Calculator -->
            <div class="space-y-4">
                <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 space-y-3" id="vehicleInfo"
                    style="display:none">
                    <h4 class="text-white font-light text-sm border-l-2 border-mb-accent pl-3">Vehicle Rates</h4>
                    <p id="vName" class="text-mb-silver text-sm"></p>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>Daily</span><span id="vDaily" class="text-mb-accent">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>1-Day Pkg</span><span id="v1day" class="text-white">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>7-Day Pkg</span><span id="v7day" class="text-white">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>15-Day Pkg</span><span id="v15day" class="text-white">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>30-Day Pkg</span><span id="v30day" class="text-white">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>Monthly</span><span id="vMonthly" class="text-white">—</span></div>
                </div>

                <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 space-y-3" id="priceCalc">
                    <h4 class="text-white font-light text-sm border-l-2 border-yellow-400 pl-3">Price Breakdown</h4>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>Days</span><span
                            id="calcDays">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle"><span>Rate/Day</span><span
                            id="calcRate">—</span></div>
                    <div class="flex justify-between text-xs text-mb-subtle">
                        <span>Available Voucher</span>
                        <span id="calcVoucherBalance">$0.00</span>
                    </div>
                    <div id="calcVoucherRow" class="hidden justify-between text-xs text-green-400">
                        <span>Voucher Applied</span>
                        <span id="calcVoucherApplied">-$0.00</span>
                    </div>
                    <div id="calcAdvanceRow" class="hidden justify-between text-xs text-purple-400">
                        <span>Advance Collected</span>
                        <span id="calcAdvancePaid">-$0.00</span>
                    </div>
                    <div id="calcDeliveryPrepaidRow" class="hidden justify-between text-xs text-blue-400">
                        <span>Delivery Charge Collected</span>
                        <span id="calcDeliveryPrepaid">-$0.00</span>
                    </div>
                    <div class="border-t border-mb-subtle/20 pt-2 flex justify-between text-sm font-medium">
                        <span class="text-mb-silver">Estimated Total</span>
                        <span class="text-mb-accent" id="calcTotal">$0</span>
                    </div>
                    <div class="flex justify-between text-sm font-medium">
                        <span class="text-mb-silver">Collect at Delivery</span>
                        <span class="text-green-400" id="calcCollectNow">$0</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="index.php" class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Confirm Reservation
            </button>
        </div>
    </form>
</div>

<?php
$extraScripts = <<<JS
<script>
const vehicleSelect = document.getElementById('vehicleSelect');
const clientSelect = document.getElementById('clientSelect');
const startDate = document.getElementById('startDate');
const endDate = document.getElementById('endDate');
const totalPrice = document.getElementById('totalPrice');
const voucherAmount = document.getElementById('voucherAmount');
const deliveryChargePrepaid = document.getElementById('deliveryChargePrepaid');
const calcVoucherBalance = document.getElementById('calcVoucherBalance');
const calcVoucherRow = document.getElementById('calcVoucherRow');
const calcVoucherApplied = document.getElementById('calcVoucherApplied');
const calcCollectNow = document.getElementById('calcCollectNow');
const clientVoucherInfo = document.getElementById('clientVoucherInfo');
const vehicleAvailabilityHint = document.getElementById('vehicleAvailabilityHint');
let availabilityRequestId = 0;

function getTimestamp(prefix) {
    const dVal = document.getElementById(prefix + 'Date').value;
    if (!dVal) return null;
    let h = parseInt(document.getElementById(prefix + 'Hour').value);
    const m = parseInt(document.getElementById(prefix + 'Min').value);
    const ampm = document.getElementById(prefix + 'AMPM').value;
    if (ampm === 'PM' && h < 12) h += 12;
    if (ampm === 'AM' && h === 12) h = 0;
    const d = new Date(dVal);
    d.setHours(h, m, 0, 0);
    return d;
}

function formatDateTimeForApi(dt) {
    const pad = (n) => String(n).padStart(2, '0');
    return dt.getFullYear() + '-' +
        pad(dt.getMonth() + 1) + '-' +
        pad(dt.getDate()) + ' ' +
        pad(dt.getHours()) + ':' +
        pad(dt.getMinutes()) + ':00';
}

function setVehicleAvailabilityHint(text, isError = false) {
    if (!vehicleAvailabilityHint) return;
    vehicleAvailabilityHint.textContent = text;
    vehicleAvailabilityHint.classList.remove('text-red-400', 'text-mb-subtle');
    vehicleAvailabilityHint.classList.add(isError ? 'text-red-400' : 'text-mb-subtle');
}

function resetVehicleOptions(placeholderText) {
    vehicleSelect.innerHTML = '';
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = placeholderText;
    vehicleSelect.appendChild(opt);
    vehicleSelect.value = '';
}

function getScheduleRange() {
    if (!clientSelect.value) {
        return { valid: false, hint: 'Select client and rental period to load available vehicles.' };
    }
    const s = getTimestamp('start');
    const e = getTimestamp('end');
    if (!s || !e || e <= s) {
        return { valid: false, hint: 'Select a valid start and end date/time to load available vehicles.' };
    }
    return { valid: true, start: s, end: e };
}

async function refreshAvailableVehicles(options = {}) {
    const preserveSelection = options.preserveSelection !== false;
    const selectedVehicle = preserveSelection ? String(vehicleSelect.value || '') : '';
    const schedule = getScheduleRange();

    if (!schedule.valid) {
        resetVehicleOptions('-- Select Client + Rental Period First --');
        vehicleSelect.disabled = true;
        setVehicleAvailabilityHint(schedule.hint);
        updateVehicleInfo();
        return;
    }

    const requestId = ++availabilityRequestId;
    resetVehicleOptions('-- Loading Available Vehicles --');
    vehicleSelect.disabled = true;
    setVehicleAvailabilityHint('Checking vehicle availability...');

    const qs = new URLSearchParams({
        start: formatDateTimeForApi(schedule.start),
        end: formatDateTimeForApi(schedule.end),
    });

    try {
        const resp = await fetch('available_vehicles.php?' + qs.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await resp.json();
        if (requestId !== availabilityRequestId) {
            return;
        }
        if (!resp.ok || !data || !data.ok) {
            throw new Error(data && data.message ? data.message : 'Could not load available vehicles.');
        }

        const vehicles = Array.isArray(data.vehicles) ? data.vehicles : [];
        resetVehicleOptions('-- Select Available Vehicle --');
        for (const v of vehicles) {
            const opt = document.createElement('option');
            opt.value = String(v.id);
            opt.dataset.daily = v.daily_rate || '';
            opt.dataset.monthly = v.monthly_rate || '';
            opt.dataset['1day'] = v.rate_1day || '';
            opt.dataset['7day'] = v.rate_7day || '';
            opt.dataset['15day'] = v.rate_15day || '';
            opt.dataset['30day'] = v.rate_30day || '';
            opt.textContent = (v.brand || '') + ' ' + (v.model || '') + ' - ' + (v.license_plate || '');
            vehicleSelect.appendChild(opt);
        }

        const canKeepSelection = vehicles.some((v) => String(v.id) === selectedVehicle);
        vehicleSelect.value = canKeepSelection ? selectedVehicle : '';
        vehicleSelect.disabled = vehicles.length === 0;

        if (vehicles.length === 0) {
            setVehicleAvailabilityHint('No vehicles are available for this rental period.');
        } else {
            setVehicleAvailabilityHint('Showing vehicles available for the selected rental period.');
        }

        $(vehicleSelect).trigger('change');
    } catch (err) {
        if (requestId !== availabilityRequestId) {
            return;
        }
        resetVehicleOptions('-- Unable to Load Vehicles --');
        vehicleSelect.disabled = true;
        setVehicleAvailabilityHint(err && err.message ? err.message : 'Unable to load vehicles right now.', true);
        updateVehicleInfo();
    }
}

function calcPrice() {
    const opt = vehicleSelect.options[vehicleSelect.selectedIndex];
    if (!opt || !opt.value) { updateVoucherPreview(); return; }
    const daily = parseFloat(opt.dataset.daily || 0);
    const type  = getSelectedType();
    const FIXED_DAYS = { '1day': 1, '7day': 7, '15day': 15, '30day': 30 };

    const s = getTimestamp('start');
    const e = getTimestamp('end');
    let days = 0, rate = daily, total = 0;

    if (FIXED_DAYS[type]) {
        // Package types: fixed day count — NO +1
        days = FIXED_DAYS[type];
        const pkgKey = type.replace('day','') + 'day';
        const pkgRate = parseFloat(opt.dataset[pkgKey] || 0);
        if (pkgRate > 0) { total = pkgRate; rate = pkgRate / days; }
        else             { total = days * daily; rate = daily; }

        if (s && !isNaN(s.getTime())) {
            const autoEnd = new Date(s);
            autoEnd.setDate(autoEnd.getDate() + days);
            document.getElementById('endDate').value = autoEnd.toISOString().split('T')[0];
            let eh = autoEnd.getHours();
            const em = autoEnd.getMinutes();
            const eampm = eh >= 12 ? 'PM' : 'AM';
            eh = eh % 12 || 12;
            document.getElementById('endHour').value = eh;
            document.getElementById('endMin').value = Math.floor(em / 5) * 5;
            document.getElementById('endAMPM').value = eampm;
        }
    } else if (type === 'monthly') {
        const monthly = parseFloat(opt.dataset.monthly || 0);
        // Inclusive: count both start & end day
        if (s && e && e > s) days = (Math.ceil((e - s) / 86400000) || 1) + 1;
        rate  = monthly > 0 ? monthly : daily;
        total = rate * (days / 30 || 1);
    } else {
        // Daily: inclusive counting — 20th to 25th = 6 days
        if (!s || !e || e <= s) return;
        days  = (Math.ceil((e - s) / 86400000) || 1) + 1;
        rate  = daily;
        total = days * daily;
    }
    document.getElementById('calcDays').textContent  = days;
    document.getElementById('calcRate').textContent  = '$' + parseFloat(rate).toFixed(2);
    document.getElementById('calcTotal').textContent = '$' + total.toFixed(2);
    totalPrice.value = total.toFixed(2);
    updateVoucherPreview();
}

function onRentalTypeChange() {
    calcPrice();
    refreshAvailableVehicles();
}
function getSelectedType() {
    return document.querySelector('input[name="rental_type"]:checked')?.value || 'daily';
}

function getSelectedClientVoucherBalance() {
    const opt = clientSelect.options[clientSelect.selectedIndex];
    if (!opt || !opt.value) {
        return 0;
    }
    return parseFloat(opt.dataset.voucherBalance || 0);
}

const voucherError = document.getElementById('voucherError');

function showVoucherError(msg) {
    if (!voucherError) return;
    voucherError.textContent = msg;
    voucherError.style.display = 'block';
    if (voucherAmount) voucherAmount.style.borderColor = 'rgb(239 68 68 / 0.6)';
}

function clearVoucherError() {
    if (!voucherError) return;
    voucherError.textContent = '';
    voucherError.style.display = 'none';
    if (voucherAmount) voucherAmount.style.borderColor = '';
}

function getVoucherRequested() {
    const v = parseFloat(voucherAmount?.value || 0);
    return isFinite(v) && v > 0 ? v : 0;
}

function validateVoucher() {
    if (!voucherAmount) return true;
    const total = parseFloat(totalPrice.value || 0) || 0;
    const balance = getSelectedClientVoucherBalance();
    const requested = getVoucherRequested();

    voucherAmount.max = Math.max(0, Math.min(total, balance)).toFixed(2);

    if (requested <= 0) { clearVoucherError(); return true; }
    if (requested > total) {
        showVoucherError('Voucher cannot exceed total price ($' + total.toFixed(2) + ')');
        return false;
    }
    if (requested > balance) {
        showVoucherError('Voucher exceeds client balance ($' + balance.toFixed(2) + ')');
        return false;
    }
    clearVoucherError();
    return true;
}

function updateVoucherPreview() {
    const total = parseFloat(totalPrice.value || 0) || 0;
    const balance = getSelectedClientVoucherBalance();
    const requested = getVoucherRequested();
    const isValid = validateVoucher();
    const applied = isValid ? Math.min(requested, total, balance) : 0;

    if (calcVoucherBalance) {
        calcVoucherBalance.textContent = '$' + balance.toFixed(2);
    }
    if (clientVoucherInfo) {
        clientVoucherInfo.textContent = 'Client voucher balance: $' + balance.toFixed(2);
    }
    if (calcVoucherRow && calcVoucherApplied) {
        if (applied > 0) {
            calcVoucherRow.classList.remove('hidden');
            calcVoucherRow.classList.add('flex');
            calcVoucherApplied.textContent = '-$' + applied.toFixed(2);
        } else {
            calcVoucherRow.classList.add('hidden');
            calcVoucherRow.classList.remove('flex');
            calcVoucherApplied.textContent = '-$0.00';
        }
    }

    // Advance row
    const advEl = document.getElementById('advancePaid');
    const adv = advEl ? (parseFloat(advEl.value) || 0) : 0;
    const advRow = document.getElementById('calcAdvanceRow');
    const advSpan = document.getElementById('calcAdvancePaid');
    if (advRow && advSpan) {
        if (adv > 0) {
            advRow.classList.remove('hidden');
            advRow.classList.add('flex');
            advSpan.textContent = '-$' + adv.toFixed(2);
        } else {
            advRow.classList.add('hidden');
            advRow.classList.remove('flex');
        }
    }

    // Delivery prepaid row
    const delPre = deliveryChargePrepaid ? (parseFloat(deliveryChargePrepaid.value) || 0) : 0;
    const delPreRow = document.getElementById('calcDeliveryPrepaidRow');
    const delPreSpan = document.getElementById('calcDeliveryPrepaid');
    if (delPreRow && delPreSpan) {
        if (delPre > 0) {
            delPreRow.classList.remove('hidden');
            delPreRow.classList.add('flex');
            delPreSpan.textContent = '-$' + delPre.toFixed(2);
        } else {
            delPreRow.classList.add('hidden');
            delPreRow.classList.remove('flex');
        }
    }

    if (calcCollectNow) {
        calcCollectNow.textContent = '$' + Math.max(0, total - applied - adv).toFixed(2);
    }
}

function updateVehicleInfo() {
    const opt = vehicleSelect.options[vehicleSelect.selectedIndex];
    const info = document.getElementById('vehicleInfo');
    if (!opt.value) { info.style.display='none'; return; }
    info.style.display='block';
    document.getElementById('vName').textContent = opt.text;
    document.getElementById('vDaily').textContent = '$' + parseFloat(opt.dataset.daily||0).toFixed(0) + '/day';
    const fmt = (k, suffix) => opt.dataset[k] ? '$' + parseFloat(opt.dataset[k]).toFixed(0) + suffix : '—';
    document.getElementById('v1day').textContent  = fmt('1day',  ' flat');
    document.getElementById('v7day').textContent  = fmt('7day',  ' flat');
    document.getElementById('v15day').textContent = fmt('15day', ' flat');
    document.getElementById('v30day').textContent = fmt('30day', ' flat');
    document.getElementById('vMonthly').textContent = fmt('monthly', '/mo');
    calcPrice();
}

$(vehicleSelect).on('change', updateVehicleInfo);
$(clientSelect).on('change', function () {
    updateVoucherPreview();
    refreshAvailableVehicles({ preserveSelection: false });
});

['start', 'end'].forEach(p => {
    ['Date', 'Hour', 'Min', 'AMPM'].forEach(c => {
        document.getElementById(p + c).addEventListener('change', function () {
            refreshAvailableVehicles();
        });
    });
});
if (voucherAmount) {
    voucherAmount.addEventListener('input', updateVoucherPreview);
    voucherAmount.addEventListener('blur', function () {
        validateVoucher();
        updateVoucherPreview();
    });
}
totalPrice.addEventListener('input', updateVoucherPreview);

// Advance section JS
function updateAdvanceSection() {
    const el = document.getElementById('advancePaid');
    const wrap = document.getElementById('advanceMethodWrap');
    if (!el || !wrap) return;
    const adv = parseFloat(el.value) || 0;
    if (adv > 0) { wrap.classList.remove('hidden'); }
    else { wrap.classList.add('hidden'); }
    updateVoucherPreview();
}
function toggleAdvanceBankField() {
    const method = document.querySelector('input[name="advance_payment_method"]:checked');
    const bankWrap = document.getElementById('advanceBankWrap');
    if (!bankWrap) return;
    bankWrap.classList.toggle('hidden', !method || method.value !== 'account');
}
const advInput = document.getElementById('advancePaid');
if (advInput) { advInput.addEventListener('input', updateAdvanceSection); }
updateAdvanceSection();

// Delivery prepaid section JS
function updateDeliveryPrepaidSection() {
    const el = document.getElementById('deliveryChargePrepaid');
    const wrap = document.getElementById('deliveryPrepaidMethodWrap');
    if (!el || !wrap) return;
    const val = parseFloat(el.value || '0') || 0;
    wrap.classList.toggle('hidden', !(val > 0));
    if (!(val > 0)) {
        document.querySelectorAll('input[name="delivery_prepaid_payment_method"]').forEach(r => r.checked = false);
    }
    toggleDeliveryPrepaidBankField();
    updateVoucherPreview();
}
function toggleDeliveryPrepaidBankField() {
    const method = document.querySelector('input[name="delivery_prepaid_payment_method"]:checked');
    const bankWrap = document.getElementById('deliveryPrepaidBankWrap');
    const bankSelect = document.getElementById('deliveryPrepaidBankAccount');
    const val = parseFloat(document.getElementById('deliveryChargePrepaid')?.value || '0');
    const needsBank = method && method.value === 'account' && val > 0;
    if (bankWrap) {
        bankWrap.classList.toggle('hidden', !needsBank);
    }
    if (bankSelect) {
        if (needsBank) {
            bankSelect.setAttribute('required', 'required');
        } else {
            bankSelect.removeAttribute('required');
            bankSelect.value = '';
        }
    }
}
if (deliveryChargePrepaid) {
    deliveryChargePrepaid.addEventListener('input', updateDeliveryPrepaidSection);
}
document.querySelectorAll('input[name="delivery_prepaid_payment_method"]').forEach(r => {
    r.addEventListener('change', toggleDeliveryPrepaidBankField);
});
updateDeliveryPrepaidSection();
document.getElementById('resForm').addEventListener('submit', function (e) {
    if (!validateVoucher()) {
        e.preventDefault();
        voucherAmount && voucherAmount.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
});
updateVehicleInfo();
updateVoucherPreview();
refreshAvailableVehicles();
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>

