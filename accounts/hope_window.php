<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
$_currentUser = current_user();
if (!auth_has_perm('view_finances') && ($_currentUser['role'] ?? '') !== 'admin') {
    flash('error', 'Access denied.');
    redirect('../index.php');
}
require_once __DIR__ . '/../includes/ledger_helpers.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

$pdo = db();
ledger_ensure_schema($pdo);

$isAdmin = ($_currentUser['role'] ?? '') === 'admin';
$userId = (int) ($_currentUser['id'] ?? 0);
$hasHopeTable = false;
$hasPredTable = false;
try {
    $hasHopeTable = (bool) $pdo->query("SHOW TABLES LIKE 'hope_daily_targets'")->fetchColumn();
} catch (Throwable $e) {
    $hasHopeTable = false;
}
try {
    $hasPredTable = (bool) $pdo->query("SHOW TABLES LIKE 'hope_daily_predictions'")->fetchColumn();
} catch (Throwable $e) {
    $hasPredTable = false;
}

$tz = new DateTimeZone('Asia/Kolkata');
$now = new DateTime('now', $tz);
$selM = (int) ($_GET['m'] ?? $now->format('n'));
$selY = (int) ($_GET['y'] ?? $now->format('Y'));
if ($selM < 1 || $selM > 12 || $selY < 2020 || $selY > 2099) {
    $selM = (int) $now->format('n');
    $selY = (int) $now->format('Y');
}

$startDate = DateTimeImmutable::createFromFormat('!Y-m-d', sprintf('%04d-%02d-01', $selY, $selM), $tz);
if (!$startDate) {
    $startDate = new DateTimeImmutable('first day of this month', $tz);
}
$endDate = $startDate->modify('last day of this month');
$rangeStart = $startDate->format('Y-m-d');
$rangeEnd = $endDate->format('Y-m-d');
$today = $now->format('Y-m-d');

$defaultTarget = (float) settings_get($pdo, 'daily_target', '0');
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_default') {
        if (!$isAdmin) {
            $error = 'Only admin can update the default daily target.';
        } else {
            $newTarget = max(0, (float) ($_POST['default_target'] ?? 0));
            settings_set($pdo, 'daily_target', (string) $newTarget);
            app_log('ACTION', "Updated Hope Window daily target to $newTarget");
            flash('success', 'Default daily target updated.');
            redirect("hope_window.php?m={$selM}&y={$selY}");
        }
    }

    if ($action === 'save_overrides') {
        if (!$isAdmin) {
            $error = 'Only admin can update per-day targets.';
        } elseif (!$hasHopeTable) {
            $error = 'Hope Window target table missing. Please apply the latest migration.';
        } else {
            $targets = $_POST['target'] ?? [];
            if (!is_array($targets)) {
                $error = 'Invalid target payload.';
            } else {
                $existingStmt = $pdo->prepare("SELECT target_date, target_amount FROM hope_daily_targets WHERE target_date BETWEEN ? AND ?");
                $existingStmt->execute([$rangeStart, $rangeEnd]);
                $existing = [];
                foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $existing[$row['target_date']] = (float) $row['target_amount'];
                }

                $upsert = $pdo->prepare("INSERT INTO hope_daily_targets (target_date, target_amount, created_by)
                    VALUES (?,?,?) ON DUPLICATE KEY UPDATE target_amount=VALUES(target_amount), created_by=VALUES(created_by), updated_at=?");
                $delete = $pdo->prepare("DELETE FROM hope_daily_targets WHERE target_date = ?");

                $updated = 0;
                $deleted = 0;

                foreach ($targets as $date => $value) {
                    $date = trim((string) $date);
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                        continue;
                    }
                    if ($date < $rangeStart || $date > $rangeEnd) {
                        continue;
                    }
                    $raw = trim((string) $value);
                    if ($raw === '') {
                        continue;
                    }
                    if (!is_numeric($raw)) {
                        continue;
                    }
                    $amount = max(0, (float) $raw);
                    if (abs($amount - $defaultTarget) < 0.005) {
                        if (isset($existing[$date])) {
                            $delete->execute([$date]);
                            $deleted++;
                        }
                        continue;
                    }
                    $upsert->execute([$date, $amount, $userId ?: null, app_now_sql()]);
                    $updated++;
                }

                $msg = 'Targets saved.';
                if ($updated || $deleted) {
                    $msg = "Targets saved. {$updated} updated, {$deleted} reset.";
                }
                flash('success', $msg);
            }
        }

        if ($error === '' && $isAdmin && $hasPredTable) {
            $predExistingStmt = $pdo->prepare("SELECT id, target_date FROM hope_daily_predictions WHERE target_date BETWEEN ? AND ?");
            $predExistingStmt->execute([$rangeStart, $rangeEnd]);
            $predExisting = [];
            foreach ($predExistingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $predExisting[(int) $row['id']] = $row['target_date'];
            }

            $predUpdate = $pdo->prepare("UPDATE hope_daily_predictions SET label=?, amount=?, updated_at=? WHERE id=?");
            $predDelete = $pdo->prepare("DELETE FROM hope_daily_predictions WHERE id=?");
            $predInsert = $pdo->prepare("INSERT INTO hope_daily_predictions (target_date, label, amount, created_by) VALUES (?,?,?,?)");

            $predLabels = $_POST['pred_label'] ?? [];
            $predAmounts = $_POST['pred_amount'] ?? [];
            $predDeletes = $_POST['pred_delete'] ?? [];
            $predNewLabels = $_POST['pred_new_label'] ?? [];
            $predNewAmounts = $_POST['pred_new_amount'] ?? [];

            foreach ($predDeletes as $id => $flag) {
                $id = (int) $id;
                if ($id > 0 && isset($predExisting[$id])) {
                    $predDelete->execute([$id]);
                }
            }

            foreach ($predLabels as $id => $label) {
                $id = (int) $id;
                if ($id <= 0 || !isset($predExisting[$id])) {
                    continue;
                }
                if (isset($predDeletes[$id])) {
                    continue;
                }
                $label = trim((string) $label);
                $amountRaw = $predAmounts[$id] ?? '';
                if (!is_numeric($amountRaw)) {
                    continue;
                }
                $amount = max(0, (float) $amountRaw);
                if ($label === '' || $amount <= 0) {
                    $predDelete->execute([$id]);
                    continue;
                }
                $predUpdate->execute([$label, $amount, app_now_sql(), $id]);
            }

            foreach ($predNewLabels as $date => $label) {
                $date = trim((string) $date);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }
                if ($date < $rangeStart || $date > $rangeEnd) {
                    continue;
                }
                $label = trim((string) $label);
                $amountRaw = $predNewAmounts[$date] ?? '';
                if ($label === '' || !is_numeric($amountRaw)) {
                    continue;
                }
                $amount = max(0, (float) $amountRaw);
                if ($amount <= 0) {
                    continue;
                }
                $predInsert->execute([$date, $label, $amount, $userId ?: null]);
            }
        } elseif ($error === '' && $isAdmin && !$hasPredTable) {
            $hasPredInputs = !empty($_POST['pred_new_label']) || !empty($_POST['pred_label']) || !empty($_POST['pred_amount']);
            if ($hasPredInputs) {
                $error = 'Hope Window predictions table missing. Please apply the latest migration.';
            }
        }

        if ($error === '') {
            redirect("hope_window.php?m={$selM}&y={$selY}");
        }
    }
}

// Load overrides
$overrideMap = [];
if ($hasHopeTable) {
    $overrideStmt = $pdo->prepare("SELECT target_date, target_amount FROM hope_daily_targets WHERE target_date BETWEEN ? AND ?");
    $overrideStmt->execute([$rangeStart, $rangeEnd]);
    foreach ($overrideStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $overrideMap[$row['target_date']] = (float) $row['target_amount'];
    }
}

// Expected income (reservation-scheduled)
$expectedMap = [];
// Prediction map
$predictionsByDate = [];
$predSumByDate = [];
$predCountByDate = [];

if ($hasPredTable) {
    $predStmt = $pdo->prepare("SELECT id, target_date, label, amount FROM hope_daily_predictions WHERE target_date BETWEEN ? AND ? ORDER BY target_date, id");
    $predStmt->execute([$rangeStart, $rangeEnd]);
    foreach ($predStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $date = $row['target_date'];
        if (!isset($predictionsByDate[$date])) {
            $predictionsByDate[$date] = [];
            $predSumByDate[$date] = 0.0;
            $predCountByDate[$date] = 0;
        }
        $predictionsByDate[$date][] = [
            'id' => (int) $row['id'],
            'label' => (string) ($row['label'] ?? ''),
            'amount' => (float) ($row['amount'] ?? 0),
        ];
        $predSumByDate[$date] += (float) ($row['amount'] ?? 0);
        $predCountByDate[$date] += 1;
    }
}

function hope_add_expected(array &$map, string $date, float $amount, string $rangeStart, string $rangeEnd): void
{
    if ($amount <= 0) {
        return;
    }
    if ($date < $rangeStart || $date > $rangeEnd) {
        return;
    }
    if (!isset($map[$date])) {
        $map[$date] = 0.0;
    }
    $map[$date] += $amount;
}

function hope_calc_delivery_due(array $r): float
{
    $basePrice = (float) ($r['total_price'] ?? 0);
    $extensionPaid = max(0, (float) ($r['extension_paid_amount'] ?? 0));
    $basePriceForDelivery = max(0, $basePrice - $extensionPaid);
    $voucherApplied = max(0, (float) ($r['voucher_applied'] ?? 0));
    $advancePaid = max(0, (float) ($r['advance_paid'] ?? 0));
    $deliveryCharge = max(0, (float) ($r['delivery_charge'] ?? 0));
    $deliveryManualAmount = max(0, (float) ($r['delivery_manual_amount'] ?? 0));
    $delivDiscType = $r['delivery_discount_type'] ?? null;
    $delivDiscVal = (float) ($r['delivery_discount_value'] ?? 0);

    $delivBaseWithCharge = max(0, $basePriceForDelivery - $voucherApplied - $advancePaid)
        + $deliveryCharge + $deliveryManualAmount;
    $delivDiscountAmt = 0.0;
    if ($delivDiscType === 'percent') {
        $delivDiscountAmt = round($delivBaseWithCharge * min($delivDiscVal, 100) / 100, 2);
    } elseif ($delivDiscType === 'amount') {
        $delivDiscountAmt = min($delivDiscVal, $delivBaseWithCharge);
    }
    return max(0, $delivBaseWithCharge - $delivDiscountAmt);
}

function hope_calc_return_due(array $r): float
{
    $returnVoucherApplied = max(0, (float) ($r['return_voucher_applied'] ?? 0));
    $overdueAmt = (float) ($r['overdue_amount'] ?? 0);
    $kmOverageChg = (float) ($r['km_overage_charge'] ?? 0);
    $damageChg = (float) ($r['damage_charge'] ?? 0);
    $additionalChg = (float) ($r['additional_charge'] ?? 0);
    $chellanChg = (float) ($r['chellan_amount'] ?? 0);
    $discType = $r['discount_type'] ?? null;
    $discVal = (float) ($r['discount_value'] ?? 0);

    $returnChargesBeforeDiscount = $overdueAmt + $kmOverageChg + $damageChg + $additionalChg + $chellanChg;
    $discountAmt = 0.0;
    if ($discType === 'percent') {
        $discountAmt = round($returnChargesBeforeDiscount * min($discVal, 100) / 100, 2);
    } elseif ($discType === 'amount') {
        $discountAmt = min($discVal, $returnChargesBeforeDiscount);
    }
    $amountDueAtReturn = max(0, $returnChargesBeforeDiscount - $discountAmt);
    return max(0, $amountDueAtReturn - $returnVoucherApplied);
}

$reservationStmt = $pdo->prepare(
    "SELECT id, status, created_at, start_date, end_date,
            total_price, extension_paid_amount, voucher_applied, advance_paid,
            delivery_charge, delivery_manual_amount, delivery_charge_prepaid,
            delivery_discount_type, delivery_discount_value,
            return_voucher_applied, overdue_amount, km_overage_charge, damage_charge,
            additional_charge, chellan_amount, discount_type, discount_value
     FROM reservations
     WHERE status <> 'cancelled'
       AND (
           DATE(created_at) BETWEEN ? AND ?
           OR DATE(start_date) BETWEEN ? AND ?
           OR DATE(end_date) BETWEEN ? AND ?
       )"
);
$reservationStmt->execute([$rangeStart, $rangeEnd, $rangeStart, $rangeEnd, $rangeStart, $rangeEnd]);
$reservations = $reservationStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($reservations as $r) {
    $bookingDate = substr((string) $r['created_at'], 0, 10);
    $advancePaid = max(0, (float) ($r['advance_paid'] ?? 0));
    $deliveryPrepaid = max(0, (float) ($r['delivery_charge_prepaid'] ?? 0));
    hope_add_expected($expectedMap, $bookingDate, $advancePaid + $deliveryPrepaid, $rangeStart, $rangeEnd);

    $deliveryDate = substr((string) $r['start_date'], 0, 10);
    $deliveryDue = hope_calc_delivery_due($r);
    hope_add_expected($expectedMap, $deliveryDate, $deliveryDue, $rangeStart, $rangeEnd);

    $returnDate = substr((string) $r['end_date'], 0, 10);
    $returnDue = hope_calc_return_due($r);
    hope_add_expected($expectedMap, $returnDate, $returnDue, $rangeStart, $rangeEnd);
}

foreach ($predSumByDate as $date => $sum) {
    if (!isset($expectedMap[$date])) {
        $expectedMap[$date] = 0.0;
    }
    $expectedMap[$date] += $sum;
}

// Extension payments (collected on extension date)
$hasExtTable = false;
try {
    $hasExtTable = (bool) $pdo->query("SHOW TABLES LIKE 'reservation_extensions'")->fetchColumn();
} catch (Throwable $e) {
    $hasExtTable = false;
}
if ($hasExtTable) {
    $extStmt = $pdo->prepare("SELECT DATE(created_at) AS day, COALESCE(SUM(amount),0) AS total
        FROM reservation_extensions
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)");
    $extStmt->execute([$rangeStart, $rangeEnd]);
    foreach ($extStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        hope_add_expected($expectedMap, $row['day'], (float) $row['total'], $rangeStart, $rangeEnd);
    }
}

$days = [];
$cursor = $startDate;
while ($cursor <= $endDate) {
    $ds = $cursor->format('Y-m-d');
    $days[] = [
        'date' => $ds,
        'label' => $cursor->format('D, d M'),
        'target' => $overrideMap[$ds] ?? $defaultTarget,
        'override' => array_key_exists($ds, $overrideMap),
        'expected' => $expectedMap[$ds] ?? 0.0,
        'prediction_count' => $predCountByDate[$ds] ?? 0,
        'prediction_sum' => $predSumByDate[$ds] ?? 0.0,
        'predictions' => $predictionsByDate[$ds] ?? [],
        'is_today' => $ds === $today,
    ];
    $cursor = $cursor->modify('+1 day');
}

$monthLabel = $startDate->format('F Y');
$pageTitle = 'Hope Window';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6 max-w-7xl mx-auto">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-white text-2xl font-light">Hope Window</h2>
            <p class="text-mb-subtle text-sm mt-1">Expected income is projected from reservation schedule (booking, delivery, return, extensions) plus custom predictions.</p>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <select name="m" class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                <?php
                $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                foreach ($months as $idx => $mLabel):
                    $mVal = $idx + 1;
                ?>
                    <option value="<?= $mVal ?>" <?= $selM === $mVal ? 'selected' : '' ?>><?= $mLabel ?></option>
                <?php endforeach; ?>
            </select>
            <select name="y" class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                <?php for ($y = (int) $now->format('Y') - 1; $y <= (int) $now->format('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $selY === $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="bg-mb-accent text-white px-4 py-2 rounded-lg text-sm hover:bg-mb-accent/80 transition-colors">Go</button>
        </form>
    </div>

    <?php if ($success = getFlash('success')): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            OK - <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <?= e($error) ?>
        </div>
    <?php endif; ?>
    <?php if (!$hasHopeTable): ?>
        <div class="flex items-center gap-3 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded-lg px-5 py-3 text-sm">
            Hope Window targets table is missing. Run `migrations/releases/2026-03-17_hope_window_daily_targets.sql` on the database.
        </div>
    <?php endif; ?>
    <?php if (!$hasPredTable): ?>
        <div class="flex items-center gap-3 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded-lg px-5 py-3 text-sm">
            Hope Window predictions table is missing. Run `migrations/releases/2026-03-18_hope_window_predictions.sql` on the database.
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-xs text-mb-subtle uppercase tracking-wider">Month</p>
            <p class="text-white text-lg mt-1"><?= e($monthLabel) ?></p>
            <p class="text-mb-subtle text-xs mt-2">Range: <?= e($rangeStart) ?> to <?= e($rangeEnd) ?></p>
        </div>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-xs text-mb-subtle uppercase tracking-wider">Default Daily Target</p>
            <?php if ($isAdmin): ?>
                <form method="POST" class="mt-2 flex items-center gap-2">
                    <input type="hidden" name="action" value="save_default">
                    <input type="number" step="0.01" min="0" name="default_target" value="<?= number_format($defaultTarget, 2, '.', '') ?>"
                        class="w-36 bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                    <button type="submit" class="bg-mb-accent text-white px-3 py-2 rounded-lg text-xs hover:bg-mb-accent/80 transition-colors">Save</button>
                </form>
            <?php else: ?>
                <p class="text-white text-lg mt-2">$<?= number_format($defaultTarget, 2) ?></p>
            <?php endif; ?>
            <p class="text-mb-subtle text-xs mt-2">Used when no per-day override exists.</p>
        </div>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <p class="text-xs text-mb-subtle uppercase tracking-wider">Total Expected Income</p>
            <?php $totalExpected = array_sum(array_map(static fn($d) => $d['expected'], $days)); ?>
            <p class="text-green-400 text-lg mt-2">$<?= number_format($totalExpected, 2) ?></p>
            <p class="text-mb-subtle text-xs mt-2">Sum of scheduled reservation collections + predictions for <?= e($monthLabel) ?>.</p>
        </div>
    </div>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
            <div>
                <h3 class="text-white font-light text-lg">Daily Targets</h3>
                <p class="text-mb-subtle text-xs mt-1">Edit targets per day. Click a row to add custom predictions.</p>
            </div>
            <?php if ($isAdmin): ?>
                <span class="text-xs text-mb-subtle">Overrides are saved below</span>
            <?php endif; ?>
        </div>

        <form method="POST" class="p-4 space-y-3">
            <input type="hidden" name="action" value="save_overrides">
            <div class="grid grid-cols-1 gap-2">
                <div class="grid grid-cols-12 text-xs text-mb-subtle px-3">
                    <div class="col-span-3">Date</div>
                    <div class="col-span-3">Target</div>
                    <div class="col-span-2">Expected Income</div>
                    <div class="col-span-2">Predictions</div>
                    <div class="col-span-2 text-right">Gap</div>
                </div>
                <?php foreach ($days as $row):
                    $gap = $row['expected'] - $row['target'];
                    $gapClass = $gap >= 0 ? 'text-green-400' : 'text-red-400';
                    $rowClass = $row['is_today'] ? 'border-mb-accent/60 bg-mb-accent/10' : 'border-mb-subtle/10 bg-mb-black/30';
                ?>
                    <div class="grid grid-cols-12 items-center border <?= $rowClass ?> rounded-lg px-3 py-2 text-sm hope-row cursor-pointer" data-pred-toggle="<?= e($row['date']) ?>">
                        <div class="col-span-3">
                            <p class="text-white"><?= e($row['label']) ?></p>
                            <?php if ($row['override']): ?>
                                <span class="text-xs text-mb-accent">Custom</span>
                            <?php elseif ($row['is_today']): ?>
                                <span class="text-xs text-mb-accent">Today</span>
                            <?php else: ?>
                                <span class="text-xs text-mb-subtle">Default</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-3">
                            <?php if ($isAdmin): ?>
                                <input type="number" step="0.01" min="0" name="target[<?= e($row['date']) ?>]"
                                    value="<?= number_format($row['target'], 2, '.', '') ?>"
                                    class="w-32 bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-1 text-sm text-white focus:outline-none focus:border-mb-accent">
                            <?php else: ?>
                                <span class="text-white">$<?= number_format($row['target'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-2">
                            <span class="text-green-400">$<?= number_format($row['expected'], 2) ?></span>
                        </div>
                        <div class="col-span-2">
                            <span class="text-white font-medium"><?= (int) $row['prediction_count'] ?></span>
                            <?php if ($row['prediction_sum'] > 0): ?>
                                <span class="text-xs text-mb-subtle ml-1">($<?= number_format($row['prediction_sum'], 2) ?>)</span>
                            <?php endif; ?>
                        </div>
                        <div class="col-span-2 text-right">
                            <span class="<?= $gapClass ?>">
                                <?= $gap >= 0 ? '+' : '-' ?>$<?= number_format(abs($gap), 2) ?>
                            </span>
                        </div>
                    </div>
                    <div id="pred-<?= e($row['date']) ?>" class="hidden border border-mb-subtle/10 bg-mb-black/40 rounded-lg px-4 py-3 text-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white font-medium">Predictions for <?= e($row['label']) ?></p>
                                <p class="text-mb-subtle text-xs">Add your own expected deals to include in projected income.</p>
                            </div>
                        </div>
                        <div class="mt-3 space-y-2">
                            <?php if (!empty($row['predictions'])): ?>
                                <?php foreach ($row['predictions'] as $pred): ?>
                                    <?php if ($isAdmin): ?>
                                        <div class="grid grid-cols-12 gap-2 items-center">
                                            <div class="col-span-7">
                                                <input type="text" name="pred_label[<?= (int) $pred['id'] ?>]" value="<?= e($pred['label']) ?>"
                                                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                                            </div>
                                            <div class="col-span-3">
                                                <input type="number" step="0.01" min="0" name="pred_amount[<?= (int) $pred['id'] ?>]" value="<?= number_format($pred['amount'], 2, '.', '') ?>"
                                                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                                            </div>
                                            <div class="col-span-2 flex items-center gap-2">
                                                <label class="flex items-center gap-2 text-xs text-mb-subtle">
                                                    <input type="checkbox" name="pred_delete[<?= (int) $pred['id'] ?>]" class="accent-red-500">
                                                    Remove
                                                </label>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-white"><?= e($pred['label']) ?></span>
                                            <span class="text-green-400">$<?= number_format($pred['amount'], 2) ?></span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-xs text-mb-subtle">No predictions yet for this date.</p>
                            <?php endif; ?>
                        </div>
                        <?php if ($isAdmin): ?>
                            <div class="mt-4 grid grid-cols-12 gap-2 items-center">
                                <div class="col-span-7">
                                    <input type="text" name="pred_new_label[<?= e($row['date']) ?>]" placeholder="Prediction note (e.g., Tesla booking)"
                                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                                </div>
                                <div class="col-span-3">
                                    <input type="number" step="0.01" min="0" name="pred_new_amount[<?= e($row['date']) ?>]" placeholder="0.00"
                                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                                </div>
                                <div class="col-span-2 text-xs text-mb-subtle">
                                    Add & save
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($isAdmin): ?>
                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="bg-mb-accent text-white px-5 py-2 rounded-lg text-sm hover:bg-mb-accent/80 transition-colors">
                        Save Daily Targets
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.hope-row').forEach(row => {
    row.addEventListener('click', (event) => {
        const tag = event.target.tagName;
        if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON', 'A', 'LABEL'].includes(tag)) {
            return;
        }
        const key = row.dataset.predToggle;
        const panel = document.getElementById('pred-' + key);
        if (panel) {
            panel.classList.toggle('hidden');
        }
    });
});
</script>
