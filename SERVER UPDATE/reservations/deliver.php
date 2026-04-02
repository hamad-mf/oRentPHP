<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('do_delivery')) {
    flash('error', 'You do not have permission to deliver vehicles.');
    redirect('index.php');
}
require_once __DIR__ . '/../includes/reservation_payment_helpers.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();
reservation_payment_ensure_schema($pdo);
ledger_ensure_schema($pdo);

$rStmt = $pdo->prepare('SELECT r.*, c.name AS client_name, v.brand, v.model, v.license_plate FROM reservations r JOIN clients c ON r.client_id=c.id JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();
if (!$r || $r['status'] !== 'confirmed') {
    flash('error', 'Only confirmed reservations can be delivered. Please confirm the reservation first.');
    redirect('index.php');
}

// Deposit Migration/Schema Check
try {
    $hasDepositCol = (int) $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'deposit_amount'")->fetchColumn();
    if ($hasDepositCol === 0) {
        $pdo->exec("ALTER TABLE reservations ADD COLUMN deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        $pdo->exec("ALTER TABLE reservations ADD COLUMN deposit_returned DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
    $hasDeliveryChargeCol = (int) $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'delivery_charge'")->fetchColumn();
    if ($hasDeliveryChargeCol === 0) {
        $pdo->exec("ALTER TABLE reservations ADD COLUMN delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
    $hasDeliveryLocationCol = (int) $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'delivery_location'")->fetchColumn();
    if ($hasDeliveryLocationCol === 0) {
        $pdo->exec("ALTER TABLE reservations ADD COLUMN delivery_location VARCHAR(255) DEFAULT NULL");
    }
} catch (Throwable $e) {
    app_log('ERROR', 'Reservation deliver: runtime schema check failed - ' . $e->getMessage(), [
    'reservation_id' => $id,
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

}

require_once __DIR__ . '/../includes/settings_helpers.php';
settings_ensure_table($pdo);
$depositPct = (float) settings_get($pdo, 'deposit_percentage', '0');
$deliveryPrepaid = max(0, (float) ($r['delivery_charge_prepaid'] ?? 0));
$deliveryPrepaidMethod = $r['delivery_prepaid_payment_method'] ?? null;
$reservationDeliveryCharge = max(0, (float) ($r['delivery_charge'] ?? 0));
$reservationDeliveryMethod = $r['delivery_prepaid_payment_method'] ?? null;
$reservationDeliveryBankId = (int) ($r['delivery_prepaid_bank_account_id'] ?? 0);
$reservationDeliveryBankId = $reservationDeliveryBankId > 0 ? $reservationDeliveryBankId : null;
$deliveryChargeDefault = $deliveryPrepaid > 0 ? 0.0 : ($reservationDeliveryCharge > 0 ? $reservationDeliveryCharge : (float) settings_get($pdo, 'delivery_charge_default', '0'));

$voucherApplied = max(0, (float) ($r['voucher_applied'] ?? 0));
$advancePaid = max(0, (float) ($r['advance_paid'] ?? 0));
$extensionPaid = max(0, (float) ($r['extension_paid_amount'] ?? 0));
$basePriceForDelivery = max(0, (float) $r['total_price'] - $extensionPaid);
// Booking discount
$bookingDiscType  = $r['booking_discount_type'] ?? null;
$bookingDiscValue = (float) ($r['booking_discount_value'] ?? 0);
$bookingDiscAmt   = 0;
if ($bookingDiscType === 'percent') {
    $bookingDiscAmt = round($basePriceForDelivery * min($bookingDiscValue, 100) / 100, 2);
} elseif ($bookingDiscType === 'amount') {
    $bookingDiscAmt = min($bookingDiscValue, $basePriceForDelivery);
}
$basePriceAfterBookingDiscount = max(0, $basePriceForDelivery - $bookingDiscAmt);
$baseCollectNow = max(0, $basePriceAfterBookingDiscount - $voucherApplied - $advancePaid);
$existingDeliveryCharge = max(0, (float) ($r['delivery_charge'] ?? 0));
$deliveryCharge = max(0, (float) ($_POST['delivery_charge'] ?? ($existingDeliveryCharge > 0 ? $existingDeliveryCharge : $deliveryChargeDefault)));
$existingDeliveryManualAmount = max(0, (float) ($r['delivery_manual_amount'] ?? 0));
$deliveryManualAmount = max(0, (float) ($_POST['delivery_manual_amount'] ?? $existingDeliveryManualAmount));
$deliveryPaymentMethod = reservation_payment_method_normalize($_POST['delivery_payment_method'] ?? ($r['delivery_payment_method'] ?? 'cash')) ?? 'cash';
// Delivery charge has its OWN separate payment method/bank
$deliveryChargePaymentMethod = reservation_payment_method_normalize($_POST['delivery_charge_payment_method'] ?? $reservationDeliveryMethod ?? 'cash') ?? 'cash';
$deliveryChargeBankAccountId = (int) ($_POST['delivery_charge_bank_account_id'] ?? $reservationDeliveryBankId ?? 0);
$deliveryChargeBankAccountId = $deliveryChargeBankAccountId > 0 ? $deliveryChargeBankAccountId : null;
// Delivery discount (applied to base rent only — not delivery charge)
$delivDiscType = in_array($_POST['delivery_discount_type'] ?? '', ['percent', 'amount']) ? $_POST['delivery_discount_type'] : ($r['delivery_discount_type'] ?? null);
$delivDiscVal = max(0, (float) ($_POST['delivery_discount_value'] ?? ($r['delivery_discount_value'] ?? 0)));
$baseWithCharge = $baseCollectNow + $deliveryManualAmount;
$delivDiscountAmt = 0;
if ($delivDiscType === 'percent') {
    $delivDiscountAmt = round($baseWithCharge * min($delivDiscVal, 100) / 100, 2);
} elseif ($delivDiscType === 'amount') {
    $delivDiscountAmt = min($delivDiscVal, $baseWithCharge);
}
$collectNowAtDelivery = max(0, $baseWithCharge - $delivDiscountAmt);
$suggestedDeposit = round(($collectNowAtDelivery + $deliveryCharge) * ($depositPct / 100), 2);
$deliveryBankAccountId = (int) ($_POST['delivery_bank_account_id'] ?? ($r['delivery_bank_account_id'] ?? 0));
$deliveryBankAccountId = $deliveryBankAccountId > 0 ? $deliveryBankAccountId : null;
$activeBankAccounts = array_values(array_filter(ledger_get_accounts($pdo), fn($a) => (int) ($a['is_active'] ?? 0) === 1));
$configuredSecurityDepositBankId = ledger_get_active_bank_account_id(
    $pdo,
    (int) settings_get($pdo, 'security_deposit_bank_account_id', '0')
);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fuel = (int) ($_POST['fuel_level'] ?? 100);
    $miles = (int) ($_POST['mileage'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $deliveryLocation = trim($_POST['delivery_location'] ?? '');
    $kmLimit = $_POST['km_limit'] !== '' ? (int) $_POST['km_limit'] : null;
    $extraKmPrice = $_POST['extra_km_price'] !== '' ? (float) $_POST['extra_km_price'] : null;
    $depositAmt = max(0, (float) ($_POST['deposit_amount'] ?? 0));
    $deliveryCharge = (float) ($_POST['delivery_charge'] ?? $deliveryCharge);
    $deliveryManualAmountInput = (float) ($_POST['delivery_manual_amount'] ?? $deliveryManualAmount);
    $deliveryManualAmount = max(0, $deliveryManualAmountInput);
    $deliveryPaymentMethod = reservation_payment_method_normalize($_POST['delivery_payment_method'] ?? null);
    $deliveryBankAccountId = (int) ($_POST['delivery_bank_account_id'] ?? 0);
    $deliveryBankAccountId = $deliveryBankAccountId > 0 ? $deliveryBankAccountId : null;
    // Delivery charge separate payment
    $deliveryChargePaymentMethod = reservation_payment_method_normalize($_POST['delivery_charge_payment_method'] ?? null);
    $deliveryChargeBankAccountId = (int) ($_POST['delivery_charge_bank_account_id'] ?? 0);
    $deliveryChargeBankAccountId = $deliveryChargeBankAccountId > 0 ? $deliveryChargeBankAccountId : null;
    $paymentSourceType = trim($_POST['payment_source_type'] ?? 'single');
    $multiCashAmount = max(0, (float) ($_POST['multi_cash_amount'] ?? 0));
    $multiCreditAmount = max(0, (float) ($_POST['multi_credit_amount'] ?? 0));
    $multiBankAmount = max(0, (float) ($_POST['multi_bank_amount'] ?? 0));
    $multiBankAccountId = (int) ($_POST['multi_bank_account_id'] ?? 0);
    $multiBankAccountId = $multiBankAccountId > 0 ? $multiBankAccountId : null;
    $delivDiscType = in_array($_POST['delivery_discount_type'] ?? '', ['percent', 'amount']) ? $_POST['delivery_discount_type'] : null;
    $delivDiscVal = max(0, (float) ($_POST['delivery_discount_value'] ?? 0));
    if ($deliveryCharge < 0) {
        $errors['delivery_charge'] = 'Delivery charge cannot be negative.';
    }
    if ($deliveryManualAmountInput < 0) {
        $errors['delivery_manual_amount'] = 'Manual delivery amount cannot be negative.';
    }
    $deliveryCharge = max(0, $deliveryCharge);
    // Rent-only collect-now (delivery charge is separate)
    $baseWithCharge = $baseCollectNow + $deliveryManualAmount;
    $delivDiscountAmt = 0;
    if ($delivDiscType === 'percent') {
        $delivDiscountAmt = round($baseWithCharge * min($delivDiscVal, 100) / 100, 2);
    } elseif ($delivDiscType === 'amount') {
        $delivDiscountAmt = min($delivDiscVal, $baseWithCharge);
    }
    $collectNowAtDelivery = max(0, $baseWithCharge - $delivDiscountAmt);
    // Validate rent payment method
    if ($collectNowAtDelivery > 0 && $paymentSourceType === 'single') {
        if ($deliveryPaymentMethod === null) {
            $errors['delivery_payment_method'] = 'Please select how the rental payment was received.';
        }
        if ($deliveryPaymentMethod === 'account') {
            if ($deliveryBankAccountId === null) {
                $errors['delivery_bank_account_id'] = 'Please select the bank account for the rental payment.';
            } else {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id = ? AND is_active = 1");
                $chk->execute([$deliveryBankAccountId]);
                if ((int) $chk->fetchColumn() === 0) {
                    $errors['delivery_bank_account_id'] = 'Selected bank account is invalid or inactive.';
                }
            }
        } else {
            $deliveryBankAccountId = null;
        }
    } elseif ($collectNowAtDelivery > 0 && $paymentSourceType === 'multi') {
        $multiTotal = round($multiCashAmount + $multiCreditAmount + $multiBankAmount, 2);
        if (abs($multiTotal - $collectNowAtDelivery) > 0.01) {
            $errors['multi_total'] = 'Split amounts must total exactly $' . number_format($collectNowAtDelivery, 2) . '. Current total: $' . number_format($multiTotal, 2);
        }
        if ($multiBankAmount > 0 && $multiBankAccountId === null) {
            $errors['multi_bank_account_id'] = 'Please select a bank account for the bank payment portion.';
        }
        if ($multiBankAmount > 0 && $multiBankAccountId !== null) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id = ? AND is_active = 1");
            $chk->execute([$multiBankAccountId]);
            if ((int) $chk->fetchColumn() === 0) {
                $errors['multi_bank_account_id'] = 'Selected bank account is invalid or inactive.';
            }
        }
        $deliveryPaymentMethod = 'multi';
    }
    // Validate delivery charge payment method separately
    if ($deliveryCharge > 0) {
        if ($deliveryChargePaymentMethod === null) {
            $errors['delivery_charge_payment_method'] = 'Please select how the delivery charge was received.';
        }
        if ($deliveryChargePaymentMethod === 'account') {
            if ($deliveryChargeBankAccountId === null) {
                $errors['delivery_charge_bank_account_id'] = 'Please select the bank account for the delivery charge.';
            } else {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id = ? AND is_active = 1");
                $chk->execute([$deliveryChargeBankAccountId]);
                if ((int) $chk->fetchColumn() === 0) {
                    $errors['delivery_charge_bank_account_id'] = 'Selected bank account is invalid or inactive.';
                }
            }
        } else {
            $deliveryChargeBankAccountId = null;
        }
    }

    if ($fuel < 0 || $fuel > 100)
        $errors['fuel_level'] = 'Fuel level must be 0–100.';
    if ($miles < 0)
        $errors['mileage'] = 'Mileage must be a positive number.';

    // KM limit is required
    if ($kmLimit === null || $kmLimit <= 0)
        $errors['km_limit'] = 'KM limit is required.';
    if (($extraKmPrice === null || $extraKmPrice <= 0) && $kmLimit !== null && $kmLimit > 0)
        $errors['extra_km_price'] = 'Extra price per KM is required when KM limit is set.';

    // All photos are required
    $requiredPhotos = ['front','back','left','right','odometer','with_customer'];
    foreach ($requiredPhotos as $photoKey) {
        if (empty($_FILES['photos']['name'][$photoKey]) || $_FILES['photos']['error'][$photoKey] !== UPLOAD_ERR_OK) {
            $errors['photo_' . $photoKey] = ucfirst(str_replace('_', ' ', $photoKey)) . ' photo is required.';
        }
    }
    // Interior: require at least 1, max 15
    $interiorOk = 0;
    for ($n = 1; $n <= 15; $n++) {
        $key = "interior_$n";
        if (!empty($_FILES['photos']['name'][$key]) && $_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
            $interiorOk++;
        }
    }
    if ($interiorOk === 0) {
        $errors['photo_interior'] = 'At least one interior photo is required.';
    }

    // Scratch photo validation (max 15, optional)
    $scratchAttempted = 0;
    for ($n = 1; $n <= 15; $n++) {
        if (!empty($_FILES['scratch_photos']['name'][$n] ?? '')
            && ($_FILES['scratch_photos']['name'][$n] ?? '') !== '') {
            $scratchAttempted++;
        }
    }
    if ($scratchAttempted > 15) {
        $errors['scratch_photos'] = 'A maximum of 15 scratch photos is allowed.';
    }
    
    // Validate bank account is configured when collecting security deposit
    if ($depositAmt > 0 && $configuredSecurityDepositBankId === null) {
        $errors['deposit_bank_account'] = 'Security deposit bank account must be configured in Settings before collecting deposits.';
    }

    if (empty($errors)) {
        $iStmt = $pdo->prepare('INSERT INTO vehicle_inspections (reservation_id,type,fuel_level,mileage,notes) VALUES (?,?,?,?,?)');
        $iStmt->execute([$id, 'delivery', $fuel, $miles, $notes]);
        $inspectionId = $pdo->lastInsertId();

        // Handle Photos
        if (isset($_FILES['photos'])) {
            $dir = __DIR__ . '/../uploads/inspections/';
            if (!is_dir($dir))
                mkdir($dir, 0777, true);
            foreach ($_FILES['photos']['name'] as $area => $name) {
                if ($_FILES['photos']['error'][$area] === UPLOAD_ERR_OK) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $filename = 'insp_' . $inspectionId . '_' . $area . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['photos']['tmp_name'][$area], $dir . $filename)) {
                        $pStmt = $pdo->prepare('INSERT INTO inspection_photos (inspection_id, view_name, file_path) VALUES (?,?,?)');
                        $pStmt->execute([$inspectionId, $area, 'uploads/inspections/' . $filename]);
                    }
                }
            }
        }

        // Save scratch photos
        $scratchDir = __DIR__ . '/../uploads/scratch_photos/';
        if (!is_dir($scratchDir)) {
            mkdir($scratchDir, 0777, true);
        }
        if (!empty($_FILES['scratch_photos']['name'])) {
            for ($n = 1; $n <= 15; $n++) {
                if (empty($_FILES['scratch_photos']['name'][$n] ?? '')
                    || ($_FILES['scratch_photos']['error'][$n] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                $spName = $_FILES['scratch_photos']['name'][$n];
                $spExt  = strtolower(pathinfo($spName, PATHINFO_EXTENSION));
                $spFilename = 'scratch_' . $id . '_delivery_' . $n . '_' . time() . '.' . $spExt;
                if (move_uploaded_file($_FILES['scratch_photos']['tmp_name'][$n], $scratchDir . $spFilename)) {
                    $pdo->prepare(
                        'INSERT INTO reservation_scratch_photos (reservation_id, event_type, slot_index, file_path) VALUES (?, ?, ?, ?)'
                    )->execute([$id, 'delivery', $n, 'uploads/scratch_photos/' . $spFilename]);
                }
            }
        }

        $deliveryPaymentMethodSave = $collectNowAtDelivery > 0 ? $deliveryPaymentMethod : null;
        $nowSql = app_now_sql();
        $pdo->prepare("UPDATE reservations
    SET status='active',
        delivered_at=?,
        km_limit=?,
        extra_km_price=?,
        deposit_amount=?,
        delivery_charge=?,
        delivery_manual_amount=?,
        delivery_payment_method=?,
        delivery_paid_amount=?,
        delivery_discount_type=?,
        delivery_discount_value=?,
        delivery_location=?
    WHERE id=?")
            ->execute([
    $nowSql,
    $kmLimit,
    $extraKmPrice,
    $depositAmt,
    $deliveryCharge,
    $deliveryManualAmount,
    $deliveryPaymentMethodSave,
    $collectNowAtDelivery,
    $delivDiscType ?: null,
    $delivDiscVal,
    $deliveryLocation !== '' ? $deliveryLocation : null,
    $id
]);
        $pdo->prepare("UPDATE vehicles SET status='rented' WHERE id=?")->execute([$r['vehicle_id']]);
        $msg = 'Vehicle delivered.';
        if ($collectNowAtDelivery > 0) {
            $msg .= ' Rental collected: $' . number_format($collectNowAtDelivery, 2) . ' (' . reservation_payment_method_label($deliveryPaymentMethodSave) . ').';
        }
        if ($deliveryCharge > 0) {
            $msg .= ' Delivery charge: $' . number_format($deliveryCharge, 2) . ' (' . reservation_payment_method_label($deliveryChargePaymentMethod) . ').';
        }
        if ($voucherApplied > 0) {
            $msg .= ' Voucher used: $' . number_format($voucherApplied, 2) . '.';
        }
        if ($advancePaid > 0) {
            $msg .= ' Advance already collected: $' . number_format($advancePaid, 2) . '.';
        }
        if ($deliveryManualAmount > 0) {
            $msg .= ' Manual additional amount: $' . number_format($deliveryManualAmount, 2) . '.';
        }
        $msg .= ' Reservation is now active.';
        app_log('ACTION', "Delivered reservation (ID: $id)");
        $ledgerUserId = (int) ($_SESSION['user']['id'] ?? 0);
        // ── Post RENT to ledger ──────────────────────────────────
        if ($paymentSourceType === 'multi' && $collectNowAtDelivery > 0) {
            $splits = [];
            if ($multiCashAmount > 0)   $splits[] = ['mode' => 'cash',    'amount' => $multiCashAmount,   'bank_id' => null];
            if ($multiCreditAmount > 0) $splits[] = ['mode' => 'credit',  'amount' => $multiCreditAmount, 'bank_id' => null];
            if ($multiBankAmount > 0)   $splits[] = ['mode' => 'account', 'amount' => $multiBankAmount,   'bank_id' => $multiBankAccountId];
            ledger_post_reservation_event_multi($pdo, $id, 'delivery', $splits, $ledgerUserId);
        } else {
            ledger_post_reservation_event($pdo, $id, 'delivery', $collectNowAtDelivery, $deliveryPaymentMethodSave, $ledgerUserId, $deliveryBankAccountId);
        }
        // ── Post DELIVERY CHARGE to ledger separately ──────────
        if ($deliveryCharge > 0 && $deliveryChargePaymentMethod !== null) {
            ledger_post_reservation_event($pdo, $id, 'delivery_charge', $deliveryCharge, $deliveryChargePaymentMethod, $ledgerUserId, $deliveryChargeBankAccountId);
        }
        if ($depositAmt > 0) {
            if ($configuredSecurityDepositBankId !== null) {
                ledger_post_security_deposit(
                    $pdo,
                    $id,
                    'in',
                    $depositAmt,
                    $configuredSecurityDepositBankId,
                    $ledgerUserId
                );
            } else {
                $msg .= ' Security deposit ledger not posted (configure Security Deposit Bank Account in Settings > General).';
                app_log('ERROR', 'Security deposit ledger skipped for reservation #' . $id . ': no active configured bank account.');
            }
        }
        // Auto-create GPS tracking entry with delivery location
        try {
            require_once __DIR__ . '/../includes/gps_helpers.php';
            gps_tracking_ensure_schema($pdo);
          $gpsStmt = $pdo->prepare("INSERT INTO gps_tracking
    (reservation_id, vehicle_id, last_location, tracking_active, notes, last_seen, updated_by, updated_at)
    VALUES (?, ?, ?, 1, 'Initial delivery', ?, ?, ?)");
$gpsStmt->execute([
    $id,
    (int) $r['vehicle_id'],
    $deliveryLocation !== '' ? $deliveryLocation : null,
    $nowSql,
    $_SESSION['user']['id'] ?? null,
    $nowSql
]);

        } catch (Throwable $e) {
            app_log('ERROR', 'Auto GPS entry failed for reservation #' . $id . ': ' . $e->getMessage());
        }
        flash('success', $msg);
        // Log staff activity
        require_once __DIR__ . '/../includes/activity_log.php';
        log_activity(
            db(),
            'delivery',
            'reservation',
            $id,
            "Delivered reservation #{$id} — {$r['client_name']} → {$r['brand']} {$r['model']} ({$r['license_plate']}). Collected: \$" . number_format($collectNowAtDelivery, 2) . "."
        );

        // Create notification
        $vehicleName = $r['brand'] . ' ' . $r['model'];
        notif_create_reservation_event($pdo, $id, 'delivered', $r['client_name'], $vehicleName);

        redirect("show.php?id=$id");
    }
}

$pageTitle = 'Deliver Vehicle';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors">Reservation #<?= $id ?></a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Deliver Vehicle</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400">
            <?php foreach ($errors as $e): ?>
                <p>&bull; <?= e($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-8">
        <!-- Header Info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Client</span>
                <p class="text-white text-lg font-light"><?= e($r['client_name']) ?></p>
            </div>
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Vehicle</span>
                <p class="text-white text-lg font-light"><?= e($r['brand']) ?> <?= e($r['model']) ?></p>
                <p class="text-mb-silver text-sm"><?= e($r['license_plate']) ?></p>
            </div>
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Start → End</span>
                <p class="text-white font-light"><?= date('d M y, h:i A', strtotime($r['start_date'])) ?></p>
                <p class="text-mb-silver text-sm">→ <?= date('d M y, h:i A', strtotime($r['end_date'])) ?></p>
            </div>
        </div>

        <!-- Delivery Location -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-6">
            <h3 class="text-white font-light border-l-2 border-mb-accent pl-3 mb-4">Delivery Location</h3>
            <p class="text-xs text-mb-subtle mb-3">Where is the vehicle being delivered? This will be used for GPS tracking.</p>
            <input type="text" name="delivery_location" id="delivery_location"
                value="<?= e($_POST['delivery_location'] ?? '') ?>"
                placeholder="e.g. Client's office, Airport parking lot B3..." required
                class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors"
                maxlength="255">
        </div>
        <?php if ($deliveryPrepaid > 0): ?>
            <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4">
                <p class="text-xs uppercase tracking-wider text-blue-400 mb-1">Collected at Reservation</p>
                <div class="flex items-center justify-between text-sm text-blue-200">
                    <span>Delivery Charge</span>
                    <span>$<?= number_format($deliveryPrepaid, 2) ?></span>
                </div>
                <?php if ($deliveryPrepaidMethod): ?>
                    <p class="text-xs text-mb-subtle mt-1">Method: <?= e(reservation_payment_method_label($deliveryPrepaidMethod)) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4">
            <p class="text-xs uppercase tracking-wider text-green-400 mb-1">Charge At Delivery</p>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between text-mb-silver">
                    <span>Base Rental Value</span>
                    <span>$<?= number_format((float) $r['total_price'], 2) ?></span>
                </div>
                <?php if ($bookingDiscAmt > 0): ?>
                    <div class="flex justify-between text-green-400">
                        <span>Booking Discount<?= $bookingDiscType === 'percent' ? " ({$bookingDiscValue}%)" : '' ?></span>
                        <span>-$<?= number_format($bookingDiscAmt, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($voucherApplied > 0): ?>
                    <div class="flex justify-between text-green-400">
                        <span>Voucher Applied</span>
                        <span>-$<?= number_format($voucherApplied, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($advancePaid > 0): ?>
                    <div class="flex justify-between text-purple-400">
                        <span>Advance Collected</span>
                        <span>-$<?= number_format($advancePaid, 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex items-center justify-between text-mb-silver gap-3">
                    <span>Delivery Charge</span>
                    <div class="relative w-44">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mb-subtle text-xs">$</span>
                        <input type="number" name="delivery_charge" id="deliveryChargeInput" step="0.01" min="0"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-7 pr-3 py-2 text-white focus:outline-none focus:border-mb-accent text-sm"
                            placeholder="0.00"
                            value="<?= e($_POST['delivery_charge'] ?? number_format($deliveryCharge, 2, '.', '')) ?>"
                            oninput="toggleDeliveryChargeMethodSection()">
                    </div>
                </div>
                <!-- Delivery Charge: Separate Payment Method -->
                <div id="deliveryChargeMethodSection" class="border border-blue-500/20 bg-blue-500/5 rounded-lg p-3 space-y-2 <?= $deliveryCharge > 0 ? '' : 'hidden' ?>">
                    <p class="text-xs text-blue-400 font-medium uppercase tracking-wider">Delivery Charge Payment</p>
                    <div class="flex items-center justify-between text-mb-silver gap-3">
                        <span class="text-xs">Method</span>
                        <div class="w-44">
                            <select name="delivery_charge_payment_method" id="deliveryChargeMethodSel"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500 text-sm"
                                onchange="toggleDeliveryChargeBankField()">
                                <option value="cash" <?= $deliveryChargePaymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="account" <?= $deliveryChargePaymentMethod === 'account' ? 'selected' : '' ?>>Account</option>
                                <option value="credit" <?= $deliveryChargePaymentMethod === 'credit' ? 'selected' : '' ?>>Credit</option>
                            </select>
                        </div>
                    </div>
                    <div id="deliveryChargeBankWrap" class="items-center justify-between text-mb-silver gap-3 <?= $deliveryChargePaymentMethod === 'account' ? 'flex' : 'hidden' ?>">
                        <span class="text-xs">Bank Account</span>
                        <div class="w-44">
                            <select name="delivery_charge_bank_account_id" id="deliveryChargeBankSel"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500 text-sm">
                                <option value="">Select account</option>
                                <?php foreach ($activeBankAccounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>" <?= (int) ($deliveryChargeBankAccountId ?? 0) === (int) $acc['id'] ? 'selected' : '' ?>>
                                        <?= e($acc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if (isset($errors['delivery_charge_payment_method'])): ?>
                        <p class="text-red-400 text-xs"><?= e($errors['delivery_charge_payment_method']) ?></p>
                    <?php endif; ?>
                    <?php if (isset($errors['delivery_charge_bank_account_id'])): ?>
                        <p class="text-red-400 text-xs"><?= e($errors['delivery_charge_bank_account_id']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center justify-between text-mb-silver gap-3">
                    <span>Manual Additional Amount</span>
                    <div class="relative w-44">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mb-subtle text-xs">$</span>
                        <input type="number" name="delivery_manual_amount" id="deliveryManualAmountInput" step="0.01" min="0"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-7 pr-3 py-2 text-white focus:outline-none focus:border-mb-accent text-sm"
                            placeholder="0.00"
                            value="<?= e($_POST['delivery_manual_amount'] ?? number_format($deliveryManualAmount, 2, '.', '')) ?>">
                    </div>
                </div>
                <div id="deliveryManualRow"
                    class="justify-between text-orange-300 <?= $deliveryManualAmount > 0 ? 'flex' : 'hidden' ?>">
                    <span>Manual Additional Amount</span>
                    <span
                        id="deliveryManualAmt"><?= $deliveryManualAmount > 0 ? '+$' . number_format($deliveryManualAmount, 2) : '' ?></span>
                </div>
                <!-- Delivery Discount -->
                <div class="flex items-center justify-between text-mb-silver gap-3">
                    <span>Discount</span>
                    <div class="flex gap-1.5 w-44">
                        <select name="delivery_discount_type" id="delivDiscountType"
                            class="bg-mb-black border border-mb-subtle/20 rounded-lg px-1.5 py-2 text-white focus:outline-none focus:border-mb-accent text-xs w-16 flex-shrink-0"
                            onchange="updateDeliveryCollectNow()">
                            <option value="" <?= ($delivDiscType ?? '') === '' ? 'selected' : '' ?>>None</option>
                            <option value="percent" <?= ($delivDiscType ?? '') === 'percent' ? 'selected' : '' ?>>%
                            </option>
                            <option value="amount" <?= ($delivDiscType ?? '') === 'amount' ? 'selected' : '' ?>>$</option>
                        </select>
                        <div id="delivDiscountValueWrap"
                            class="flex-1 min-w-0 <?= in_array(($delivDiscType ?? ''), ['percent', 'amount'], true) ? '' : 'hidden' ?>">
                            <input type="number" name="delivery_discount_value" id="delivDiscountValue" step="0.01" min="0"
                                placeholder="0"
                                value="<?= e($_POST['delivery_discount_value'] ?? number_format($delivDiscVal, 2, '.', '')) ?>"
                                oninput="updateDeliveryCollectNow()"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-2 text-white focus:outline-none focus:border-mb-accent text-xs">
                        </div>
                    </div>
                </div>
                <div id="delivDiscountRow"
                    class="justify-between text-green-400 <?= $delivDiscountAmt > 0 ? 'flex' : 'hidden' ?>">
                    <span id="delivDiscountLabel">Discount</span>
                    <span
                        id="delivDiscountAmt"><?= $delivDiscountAmt > 0 ? '-$' . number_format($delivDiscountAmt, 2) : '' ?></span>
                </div>
                <!-- Payment Source Type Toggle -->
                <div class="flex items-center justify-between text-mb-silver gap-3">
                    <span>Payment Source</span>
                    <div class="flex bg-mb-black rounded-lg border border-mb-subtle/20 overflow-hidden w-44">
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="radio" name="payment_source_type" value="single" class="hidden peer" checked onchange="togglePaymentSourceType()">
                            <span class="block py-2 text-xs font-medium peer-checked:bg-mb-accent peer-checked:text-white text-mb-subtle transition-colors">Single</span>
                        </label>
                        <label class="flex-1 text-center cursor-pointer">
                            <input type="radio" name="payment_source_type" value="multi" class="hidden peer" onchange="togglePaymentSourceType()">
                            <span class="block py-2 text-xs font-medium peer-checked:bg-mb-accent peer-checked:text-white text-mb-subtle transition-colors">Multi</span>
                        </label>
                    </div>
                </div>
                <!-- Single Source Panel -->
                <div id="singleSourcePanel">
                    <div class="flex items-center justify-between text-mb-silver gap-3">
                        <span>Payment Method</span>
                        <div class="w-44">
                            <select name="delivery_payment_method" id="deliveryPaymentMethod" onchange="toggleDeliveryBankField()"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-mb-accent text-sm">
                                <option value="cash" <?= $deliveryPaymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="account" <?= $deliveryPaymentMethod === 'account' ? 'selected' : '' ?>>Account</option>
                                <option value="credit" <?= $deliveryPaymentMethod === 'credit' ? 'selected' : '' ?>>Credit</option>
                            </select>
                        </div>
                    </div>
                    <div id="deliveryBankWrap"
                        class="items-center justify-between text-mb-silver gap-3 mt-2 <?= $deliveryPaymentMethod === 'account' ? 'flex' : 'hidden' ?>">
                        <span>Bank Account</span>
                        <div class="w-44">
                            <select name="delivery_bank_account_id" id="deliveryBankAccount"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-mb-accent text-sm">
                                <option value="">Select account</option>
                                <?php foreach ($activeBankAccounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>" <?= (int) ($deliveryBankAccountId ?? 0) === (int) $acc['id'] ? 'selected' : '' ?>>
                                        <?= e($acc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <!-- Multi Source Panel -->
                <div id="multiSourcePanel" class="hidden space-y-2">
                    <div class="flex items-center justify-between text-mb-silver gap-3">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>Cash</span>
                        <div class="relative w-44">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mb-subtle text-xs">$</span>
                            <input type="number" name="multi_cash_amount" id="multiCashAmount" step="0.01" min="0" placeholder="0.00"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-7 pr-3 py-2 text-white focus:outline-none focus:border-green-500 text-sm"
                                oninput="validateMultiTotal()">
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-mb-silver gap-3">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>Credit</span>
                        <div class="relative w-44">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mb-subtle text-xs">$</span>
                            <input type="number" name="multi_credit_amount" id="multiCreditAmount" step="0.01" min="0" placeholder="0.00"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-7 pr-3 py-2 text-white focus:outline-none focus:border-amber-500 text-sm"
                                oninput="validateMultiTotal()">
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-mb-silver gap-3">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>Bank Account</span>
                        <div class="relative w-44">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mb-subtle text-xs">$</span>
                            <input type="number" name="multi_bank_amount" id="multiBankAmount" step="0.01" min="0" placeholder="0.00"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-7 pr-3 py-2 text-white focus:outline-none focus:border-blue-500 text-sm"
                                oninput="validateMultiTotal()">
                        </div>
                    </div>
                    <div id="multiBankSelectWrap" class="hidden flex items-center justify-between text-mb-silver gap-3">
                        <span class="text-xs">Select Bank</span>
                        <div class="w-44">
                            <select name="multi_bank_account_id" id="multiBankAccountId"
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500 text-sm">
                                <option value="">Select account</option>
                                <?php foreach ($activeBankAccounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>"><?= e($acc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div id="multiTotalValidation" class="hidden text-xs pt-1 font-medium"></div>
                </div>
                <div class="flex justify-between text-white font-semibold pt-1 border-t border-green-500/20">
                    <span>Collect Now</span>
                    <span id="collectNowValue">$<?= number_format($collectNowAtDelivery, 2) ?></span>
                </div>
            </div>
            <?php if (isset($errors['delivery_charge'])): ?>
                <p class="text-red-400 text-xs mt-2"><?= e($errors['delivery_charge']) ?></p>
            <?php endif; ?>
            <?php if (isset($errors['delivery_manual_amount'])): ?>
                <p class="text-red-400 text-xs mt-2"><?= e($errors['delivery_manual_amount']) ?></p>
            <?php endif; ?>
            <?php if (isset($errors['delivery_payment_method'])): ?>
                <p class="text-red-400 text-xs mt-2"><?= e($errors['delivery_payment_method']) ?></p>
            <?php endif; ?>
            <?php if (isset($errors['delivery_bank_account_id'])): ?>
                <p class="text-red-400 text-xs mt-2"><?= e($errors['delivery_bank_account_id']) ?></p>
            <?php endif; ?>
            <?php if (empty($activeBankAccounts)): ?>
                <p class="text-red-400 text-xs mt-2">No active bank account found. Go to Accounts and add one for account mode.</p>
            <?php endif; ?>
            <p class="text-xs text-mb-subtle mt-1">At return, only extra charges (late, KM overage, damage, etc.) will
                be calculated.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Readings -->
            <div class="space-y-6">
                <h3 class="text-white text-lg font-light border-l-2 border-mb-accent pl-3">Odometer &amp; Fuel</h3>
                <div>
                    <label for="mileage" class="block text-sm font-medium text-mb-silver mb-2">Current Mileage
                        (km)</label>
                    <input type="number" name="mileage" id="mileage" required
                        class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors"
                        placeholder="e.g. 15000" value="<?= e($_POST['mileage'] ?? '') ?>">
                </div>
                <div>
                    <label for="fuel_level" class="block text-sm font-medium text-mb-silver mb-2">Fuel Level (%)</label>
                    <div class="relative pt-1">
                        <input type="range" name="fuel_level" id="fuelSlider" min="0" max="100"
                            value="<?= e($_POST['fuel_level'] ?? 100) ?>"
                            class="w-full h-2 bg-mb-subtle/50 rounded-lg appearance-none cursor-pointer accent-mb-accent"
                            oninput="document.getElementById('fuel-val').innerText = this.value + '%'">
                        <span id="fuel-val"
                            class="absolute right-0 top-0 text-mb-accent text-sm font-bold"><?= e($_POST['fuel_level'] ?? 100) ?>%</span>
                    </div>
                    <div class="h-2 bg-mb-black/60 rounded-full overflow-hidden mt-3">
                        <div id="fuelBar" class="h-2 bg-green-500 rounded-full transition-all"
                            style="width:<?= e($_POST['fuel_level'] ?? 100) ?>%"></div>
                    </div>
                </div>

                <!-- Deposit Section -->
                <div class="pt-4 border-t border-mb-subtle/10 space-y-4">
                    <h3 class="text-white font-light border-l-2 border-mb-accent pl-3">Security Deposit</h3>
                    <p class="text-xs text-mb-subtle">Enter the security deposit amount collected from the client.</p>
                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Security Deposit Collected ($)</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                            <input type="number" name="deposit_amount" id="depositAmount" step="0.01" min="0" required
                                class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                                placeholder="0.00" value="<?= e($_POST['deposit_amount'] ?? $suggestedDeposit) ?>">
                        </div>
                        <?php if ($depositPct > 0): ?>
                            <p class="text-xs text-mb-accent/60 mt-1" id="depositSuggestionText">Suggested rate:
                                <?= $depositPct ?>% of delivery
                                collection ($<?= number_format($collectNowAtDelivery, 2) ?>)
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <label for="notes" class="block text-sm font-medium text-mb-silver mb-2">Inspection Notes</label>
                    <textarea name="notes" id="notes" rows="4"
                        class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors"
                        placeholder="Note any existing scratches, dents, or issues..."><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>
                <!-- KM Limit Section -->
                <div class="pt-4 border-t border-mb-subtle/10 space-y-4">
                    <h4 class="text-white font-light border-l-2 border-yellow-500 pl-3">KM Limit <span
                            class="text-mb-subtle text-xs font-normal">(optional)</span></h4>
                    <p class="text-xs text-mb-subtle">Client will be charged extra per KM if they exceed this limit on
                        return.</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-mb-silver mb-2">KM Limit</label>
                            <input type="number" name="km_limit" min="0" placeholder="e.g. 500" required
                                value="<?= e($_POST['km_limit'] ?? '') ?>"
                                class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-yellow-500/50 transition-colors">
                        </div>
                        <div>
                            <label class="block text-sm text-mb-silver mb-2">Extra Price / KM ($)</label>
                            <input type="number" name="extra_km_price" min="0" step="0.01" placeholder="e.g. 0.50" required
                                value="<?= e($_POST['extra_km_price'] ?? '') ?>"
                                class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-yellow-500/50 transition-colors">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photos -->
            <div class="space-y-4">
                <h3 class="text-white text-lg font-light border-l-2 border-mb-accent pl-3">Vehicle Condition Photos</h3>
                <p class="text-xs text-mb-subtle">Upload clear photos for each area.</p>
                <?php
                $photoViews = [
                    'front' => 'Front',
                    'back' => 'Back',
                    'left' => 'Left',
                    'right' => 'Right',
                    'odometer' => 'Photo of Odometer',
                    'with_customer' => 'Photo with Customer',
                ];
                foreach ($photoViews as $areaKey => $areaLabel):
                    ?>
                    <div
                        class="bg-mb-black/30 p-4 rounded-lg border border-mb-subtle/10 hover:border-mb-accent/30 transition-colors">
                        <label class="block text-sm font-medium text-mb-silver mb-2"><?= $areaLabel ?> View</label>
                        <input type="file" name="photos[<?= $areaKey ?>]" accept="image/*" required class="block w-full text-sm text-mb-silver
                                   file:mr-4 file:py-2 file:px-4
                                   file:rounded-full file:border-0
                                   file:text-xs file:font-semibold
                                   file:bg-mb-surface file:text-mb-accent
                                   hover:file:bg-mb-surface/80 cursor-pointer">
                    </div>
                <?php endforeach; ?>

                <!-- Dynamic Interior Photos -->
                <div class="bg-mb-black/30 p-4 rounded-lg border border-mb-subtle/10">
                    <div class="flex items-center justify-between mb-3">
                        <label class="block text-sm font-medium text-mb-silver">Interior Photos <span class="text-mb-subtle text-xs">(1–15)</span></label>
                        <?php if (isset($errors['photo_interior'])): ?>
                            <p class="text-red-400 text-xs"><?= e($errors['photo_interior']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div id="interior-slots-container" class="space-y-2">
                        <div class="interior-slot flex items-center gap-2" data-slot="1">
                            <input type="file" name="photos[interior_1]" accept="image/*" class="block flex-1 text-sm text-mb-silver
                                       file:mr-4 file:py-2 file:px-4
                                       file:rounded-full file:border-0
                                       file:text-xs file:font-semibold
                                       file:bg-mb-surface file:text-mb-accent
                                       hover:file:bg-mb-surface/80 cursor-pointer">
                        </div>
                    </div>
                    <button type="button" id="add-interior-btn"
                        class="mt-3 text-xs text-mb-accent hover:text-white border border-mb-accent/30 hover:border-mb-accent/60 px-3 py-1.5 rounded-full transition-colors">
                        + Add another interior photo
                    </button>
                </div>
            </div>
        </div>

        <!-- Scratch / Damage Photos -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-6">
            <h3 class="text-white font-light border-l-2 border-orange-500 pl-3 mb-4">
                Scratch / Damage Photos <span class="text-mb-subtle text-xs font-normal">(optional, max 15)</span>
            </h3>
            <?php if (!empty($errors['scratch_photos'])): ?>
                <p class="text-red-400 text-xs mb-3"><?= e($errors['scratch_photos']) ?></p>
            <?php endif; ?>
            <div id="scratch-slots-container" class="space-y-2">
                <div class="scratch-slot flex items-center gap-2" data-slot="1">
                    <input type="file" name="scratch_photos[1]" accept="image/*"
                           class="block flex-1 text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-orange-400 hover:file:bg-mb-surface/80 cursor-pointer">
                </div>
            </div>
            <button type="button" id="add-scratch-btn"
                    class="mt-3 text-xs text-orange-400 hover:text-white border border-orange-500/30 hover:border-orange-500/60 px-3 py-1.5 rounded-full transition-colors">
                + Add another scratch photo
            </button>
        </div>

        <div class="flex items-center justify-between pt-8 border-t border-mb-subtle/10">
            <a href="bill.php?id=<?= $id ?>" target="_blank"
                class="border border-yellow-500/40 text-yellow-400 px-5 py-2.5 rounded-full hover:bg-yellow-500/10 transition-colors text-sm font-medium">🧾
                Preview Bill</a>
            <div class="flex items-center gap-4">
                <a href="show.php?id=<?= $id ?>"
                    class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
                <button type="submit"
                    class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                    Confirm Delivery
                </button>
            </div>
        </div>
    </form>
</div>
<?php
$extraScripts = <<<JS
<script>
const slider = document.getElementById("fuelSlider");
const valEl = document.getElementById("fuel-val");
const barEl = document.getElementById("fuelBar");
const deliveryChargeInput = document.getElementById("deliveryChargeInput");
const deliveryManualAmountInput = document.getElementById("deliveryManualAmountInput");
const delivDiscountTypeEl = document.getElementById("delivDiscountType");
const delivDiscountValueEl = document.getElementById("delivDiscountValue");
const delivDiscountValueWrapEl = document.getElementById("delivDiscountValueWrap");
const collectNowValue = document.getElementById("collectNowValue");
const depositSuggestionText = document.getElementById("depositSuggestionText");
const deliveryPaymentMethodEl = document.getElementById("deliveryPaymentMethod");
const deliveryBankWrapEl = document.getElementById("deliveryBankWrap");
const deliveryBankAccountEl = document.getElementById("deliveryBankAccount");
const BASE_COLLECT_NOW = {$baseCollectNow};
const DEPOSIT_PCT = {$depositPct};

function getCollectNowAmount() {
    if (!collectNowValue) return 0;
    const raw = String(collectNowValue.textContent || "0").replace(/[^0-9.-]/g, "");
    return parseFloat(raw) || 0;
}

function toggleDeliveryBankField() {
    if (!deliveryPaymentMethodEl || !deliveryBankWrapEl || !deliveryBankAccountEl) return;
    const needsBank = deliveryPaymentMethodEl.value === "account" && getCollectNowAmount() > 0;
    deliveryBankWrapEl.classList.toggle("hidden", !needsBank);
    deliveryBankWrapEl.classList.toggle("flex", needsBank);
    if (needsBank) {
        deliveryBankAccountEl.setAttribute("required", "required");
    } else {
        deliveryBankAccountEl.removeAttribute("required");
        deliveryBankAccountEl.value = "";
    }
}

function updateFuel(v) {
    valEl.textContent = v + "%";
    barEl.style.width = v + "%";
    barEl.className = "h-2 rounded-full " + (v >= 75 ? "bg-green-500" : v >= 50 ? "bg-yellow-400" : v >= 25 ? "bg-orange-400" : "bg-red-500");
}

function toggleDeliveryDiscountValueField() {
    if (!delivDiscountTypeEl || !delivDiscountValueWrapEl) return;
    const hasDiscount = delivDiscountTypeEl.value === "percent" || delivDiscountTypeEl.value === "amount";
    delivDiscountValueWrapEl.classList.toggle("hidden", !hasDiscount);
}

function updateDeliveryCollectNow() {
    if (!deliveryChargeInput || !deliveryManualAmountInput || !collectNowValue) return;
    toggleDeliveryDiscountValueField();
    let deliveryCharge = parseFloat(deliveryChargeInput.value || "0");
    if (!isFinite(deliveryCharge) || deliveryCharge < 0) deliveryCharge = 0;
    let deliveryManual = parseFloat(deliveryManualAmountInput.value || "0");
    if (!isFinite(deliveryManual) || deliveryManual < 0) deliveryManual = 0;
    // Rent only — delivery charge is separate
    const baseWithCharge = BASE_COLLECT_NOW + deliveryManual;

    const manualRow = document.getElementById("deliveryManualRow");
    const manualAmtEl = document.getElementById("deliveryManualAmt");
    if (manualRow && manualAmtEl) {
        if (deliveryManual > 0) {
            manualRow.classList.remove("hidden");
            manualRow.classList.add("flex");
            manualAmtEl.textContent = "+$" + deliveryManual.toFixed(2);
        } else {
            manualRow.classList.add("hidden");
            manualRow.classList.remove("flex");
            manualAmtEl.textContent = "";
        }
    }

    // Discount
    const discType = delivDiscountTypeEl ? delivDiscountTypeEl.value : "";
    const discValRaw = parseFloat(delivDiscountValueEl ? delivDiscountValueEl.value : "0") || 0;
    const discVal = (discType === "percent" || discType === "amount") ? discValRaw : 0;
    let discAmt = 0;
    if (discType === "percent" && discVal > 0) {
        discAmt = Math.round(baseWithCharge * Math.min(discVal, 100) / 100 * 100) / 100;
    } else if (discType === "amount" && discVal > 0) {
        discAmt = Math.min(discVal, baseWithCharge);
    }

    // Update discount preview row
    const discRow   = document.getElementById("delivDiscountRow");
    const discAmtEl = document.getElementById("delivDiscountAmt");
    const discLblEl = document.getElementById("delivDiscountLabel");
    if (discRow && discAmtEl && discLblEl) {
        if (discAmt > 0) {
            discRow.classList.remove("hidden"); discRow.classList.add("flex");
            discLblEl.textContent = discType === "percent" ? `Discount (\${discVal}%)` : "Discount";
            discAmtEl.textContent = "-$" + discAmt.toFixed(2);
        } else {
            discRow.classList.add("hidden"); discRow.classList.remove("flex");
        }
    }

    const collectNow = Math.max(0, baseWithCharge - discAmt);
    collectNowValue.textContent = "$" + collectNow.toFixed(2);
    if (depositSuggestionText && DEPOSIT_PCT > 0) {
        depositSuggestionText.textContent = "Suggested rate: " + DEPOSIT_PCT + "% of delivery collection ($" + collectNow.toFixed(2) + ")";
    }
    toggleDeliveryBankField();
}

slider.addEventListener("input", () => updateFuel(slider.value));

function toggleDeliveryChargeMethodSection() {
    const chargeInput = document.getElementById('deliveryChargeInput');
    const section = document.getElementById('deliveryChargeMethodSection');
    if (!chargeInput || !section) return;
    const val = parseFloat(chargeInput.value || '0');
    section.classList.toggle('hidden', !(val > 0));
    updateDeliveryCollectNow();
}
function toggleDeliveryChargeBankField() {
    const sel = document.getElementById('deliveryChargeMethodSel');
    const bankWrap = document.getElementById('deliveryChargeBankWrap');
    const bankSel = document.getElementById('deliveryChargeBankSel');
    if (!sel || !bankWrap) return;
    const needsBank = sel.value === 'account';
    bankWrap.classList.toggle('hidden', !needsBank);
    bankWrap.classList.toggle('flex', needsBank);
    if (bankSel && !needsBank) bankSel.value = '';
}

if (deliveryChargeInput) {
    deliveryChargeInput.addEventListener("input", updateDeliveryCollectNow);
    deliveryChargeInput.addEventListener("change", updateDeliveryCollectNow);
}
if (deliveryManualAmountInput) {
    deliveryManualAmountInput.addEventListener("input", updateDeliveryCollectNow);
    deliveryManualAmountInput.addEventListener("change", updateDeliveryCollectNow);
}
if (deliveryPaymentMethodEl) {
    deliveryPaymentMethodEl.addEventListener("change", toggleDeliveryBankField);
}
updateFuel(slider.value);
updateDeliveryCollectNow();
toggleDeliveryBankField();
// Multi-source payment logic
function togglePaymentSourceType() {
    const sourceType = document.querySelector('input[name="payment_source_type"]:checked')?.value || 'single';
    const singlePanel = document.getElementById('singleSourcePanel');
    const multiPanel = document.getElementById('multiSourcePanel');
    if (singlePanel) singlePanel.style.display = sourceType === 'single' ? 'block' : 'none';
    if (multiPanel) multiPanel.classList.toggle('hidden', sourceType !== 'multi');
    if (sourceType === 'multi') validateMultiTotal();
}

function validateMultiTotal() {
    const cashEl = document.getElementById('multiCashAmount');
    const creditEl = document.getElementById('multiCreditAmount');
    const bankEl = document.getElementById('multiBankAmount');
    const bankSelectWrap = document.getElementById('multiBankSelectWrap');
    const validationEl = document.getElementById('multiTotalValidation');
    if (!cashEl || !creditEl || !bankEl || !validationEl) return;

    const cashAmt = parseFloat(cashEl.value || '0') || 0;
    const creditAmt = parseFloat(creditEl.value || '0') || 0;
    const bankAmt = parseFloat(bankEl.value || '0') || 0;
    const total = Math.round((cashAmt + creditAmt + bankAmt) * 100) / 100;
    const required = getCollectNowAmount();

    // Show/hide bank account selector
    if (bankSelectWrap) {
        bankSelectWrap.style.display = bankAmt > 0 ? 'flex' : 'none';
    }

    validationEl.classList.remove('hidden');
    const diff = Math.round((required - total) * 100) / 100;
    if (Math.abs(diff) < 0.01) {
        validationEl.className = 'text-xs pt-1 font-medium text-green-400';
        validationEl.textContent = 'Total matches: $' + total.toFixed(2);
    } else if (diff > 0) {
        validationEl.className = 'text-xs pt-1 font-medium text-amber-400';
        validationEl.textContent = 'Remaining: $' + diff.toFixed(2) + ' (Need: $' + required.toFixed(2) + ')';
    } else {
        validationEl.className = 'text-xs pt-1 font-medium text-red-400';
        validationEl.textContent = 'Over by: $' + Math.abs(diff).toFixed(2) + ' (Max: $' + required.toFixed(2) + ')';
    }
}

// Interior photo slots
(function() {
    const container = document.getElementById('interior-slots-container');
    const addBtn = document.getElementById('add-interior-btn');
    const MAX = 15;

    function updateAddBtn() {
        const count = container.querySelectorAll('.interior-slot').length;
        addBtn.disabled = count >= MAX;
        addBtn.classList.toggle('opacity-40', count >= MAX);
        addBtn.classList.toggle('cursor-not-allowed', count >= MAX);
    }

    function makeRemoveBtn(slot) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = '✕';
        btn.className = 'text-red-400 hover:text-red-300 text-xs px-2 py-1 rounded transition-colors flex-shrink-0';
        btn.onclick = function() {
            slot.remove();
            reindex();
            updateAddBtn();
        };
        return btn;
    }

    function reindex() {
        container.querySelectorAll('.interior-slot').forEach(function(slot, i) {
            const n = i + 1;
            slot.dataset.slot = n;
            const input = slot.querySelector('input[type=file]');
            if (input) input.name = 'photos[interior_' + n + ']';
        });
    }

    addBtn.addEventListener('click', function() {
        const count = container.querySelectorAll('.interior-slot').length;
        if (count >= MAX) return;
        const n = count + 1;
        const slot = document.createElement('div');
        slot.className = 'interior-slot flex items-center gap-2';
        slot.dataset.slot = n;
        const input = document.createElement('input');
        input.type = 'file';
        input.name = 'photos[interior_' + n + ']';
        input.accept = 'image/*';
        input.className = 'block flex-1 text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-mb-accent hover:file:bg-mb-surface/80 cursor-pointer';
        slot.appendChild(input);
        slot.appendChild(makeRemoveBtn(slot));
        container.appendChild(slot);
        updateAddBtn();
    });

    updateAddBtn();
})();

// Scratch photo slots
(function() {
    var container = document.getElementById('scratch-slots-container');
    var addBtn    = document.getElementById('add-scratch-btn');
    if (!container || !addBtn) return;
    var MAX = 15;

    function updateAddBtn() {
        var count = container.querySelectorAll('.scratch-slot').length;
        addBtn.disabled = count >= MAX;
        addBtn.style.opacity = count >= MAX ? '0.4' : '1';
    }

    function reindex() {
        container.querySelectorAll('.scratch-slot').forEach(function(slot, i) {
            var n = i + 1;
            slot.dataset.slot = n;
            var input = slot.querySelector('input[type=file]');
            if (input) input.name = 'scratch_photos[' + n + ']';
        });
    }

    addBtn.addEventListener('click', function() {
        var count = container.querySelectorAll('.scratch-slot').length;
        if (count >= MAX) return;
        var n = count + 1;
        var slot = document.createElement('div');
        slot.className = 'scratch-slot flex items-center gap-2';
        slot.dataset.slot = n;
        var input = document.createElement('input');
        input.type = 'file';
        input.name = 'scratch_photos[' + n + ']';
        input.accept = 'image/*';
        input.className = 'block flex-1 text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-orange-400 hover:file:bg-mb-surface/80 cursor-pointer';
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.textContent = 'Remove';
        removeBtn.className = 'text-xs text-red-400 hover:text-red-300 border border-red-500/30 px-2 py-1 rounded-full transition-colors';
        removeBtn.addEventListener('click', function() {
            slot.remove();
            reindex();
            updateAddBtn();
        });
        slot.appendChild(input);
        slot.appendChild(removeBtn);
        container.appendChild(slot);
        updateAddBtn();
    });

    updateAddBtn();
})();
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>
