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

function hope_period_from_my(int $m, int $y): array
{
    $start = sprintf('%04d-%02d-15', $y, $m);
    $nM = $m === 12 ? 1 : $m + 1;
    $nY = $m === 12 ? $y + 1 : $y;
    return ['start' => $start, 'end' => sprintf('%04d-%02d-14', $nY, $nM)];
}

function hope_period_for_today(): array
{
    $d = (int) date('d');
    $m = (int) date('m');
    $y = (int) date('Y');
    if ($d >= 15) {
        return hope_period_from_my($m, $y);
    }
    $pm = $m === 1 ? 12 : $m - 1;
    $py = $m === 1 ? $y - 1 : $y;
    return hope_period_from_my($pm, $py);
}

function hope_format_currency(float $amount): string
{
    return '$' . number_format($amount, 2);
}

function hope_fetch_breakdown_data(PDO $pdo, string $rangeStart, string $rangeEnd): array
{
    $data = ['reservations' => [], 'extensions' => [], 'predictions' => []];
    
    try {
        $resStmt = $pdo->prepare(
            "SELECT r.id, r.status, r.created_at, r.start_date, r.end_date,
                    r.total_price, r.extension_paid_amount, r.voucher_applied, r.advance_paid,
                    r.delivery_charge, r.delivery_manual_amount, r.delivery_charge_prepaid,
                    r.delivery_discount_type, r.delivery_discount_value,
                    r.return_voucher_applied, r.overdue_amount, r.km_overage_charge, 
                    r.damage_charge, r.additional_charge, r.chellan_amount, 
                    r.discount_type, r.discount_value,
                    r.client_id,
                    COALESCE(c.name, 'Unknown Client') AS client_name
             FROM reservations r
             LEFT JOIN clients c ON r.client_id = c.id
             WHERE r.status <> 'cancelled'
               AND (
                   DATE(r.created_at) BETWEEN ? AND ?
                   OR DATE(r.start_date) BETWEEN ? AND ?
                   OR DATE(r.end_date) BETWEEN ? AND ?
               )
             ORDER BY r.created_at"
        );
        $resStmt->execute([$rangeStart, $rangeEnd, $rangeStart, $rangeEnd, $rangeStart, $rangeEnd]);
        $data['reservations'] = $resStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $data['reservations'] = [];
    }
    
    try {
        $hasExtTable = (bool) $pdo->query("SHOW TABLES LIKE 'reservation_extensions'")->fetchColumn();
        if ($hasExtTable) {
            $extStmt = $pdo->prepare(
                "SELECT e.id, e.reservation_id, e.amount, e.created_at,
                        COALESCE(c.name, 'Unknown Client') AS client_name
                 FROM reservation_extensions e
                 INNER JOIN reservations r ON e.reservation_id = r.id
                 LEFT JOIN clients c ON r.client_id = c.id
                 WHERE r.status <> 'cancelled'
                   AND DATE(e.created_at) BETWEEN ? AND ?
                 ORDER BY e.created_at"
            );
            $extStmt->execute([$rangeStart, $rangeEnd]);
            $data['extensions'] = $extStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $data['extensions'] = [];
    }
    
    try {
        $hasPredTable = (bool) $pdo->query("SHOW TABLES LIKE 'hope_daily_predictions'")->fetchColumn();
        if ($hasPredTable) {
            $predStmt = $pdo->prepare(
                "SELECT target_date, label, amount
                 FROM hope_daily_predictions
                 WHERE target_date BETWEEN ? AND ?
                 ORDER BY target_date, id"
            );
            $predStmt->execute([$rangeStart, $rangeEnd]);
            $data['predictions'] = $predStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        $data['predictions'] = [];
    }
    
    return $data;
}

function hope_build_breakdown_map(array $data, string $rangeStart, string $rangeEnd): array
{
    $breakdownMap = [];
    
    foreach ($data['reservations'] as $r) {
        $bookingDate = substr((string) $r['created_at'], 0, 10);
        $bookingAmt = max(0, (float) ($r['advance_paid'] ?? 0)) + max(0, (float) ($r['delivery_charge_prepaid'] ?? 0));
        if ($bookingAmt > 0 && $bookingDate >= $rangeStart && $bookingDate <= $rangeEnd) {
            if (!isset($breakdownMap[$bookingDate])) {
                $breakdownMap[$bookingDate] = ['booking' => [], 'delivery' => [], 'return' => [], 'extension' => [], 'prediction' => [], 'total' => 0.0];
            }
            $breakdownMap[$bookingDate]['booking'][] = [
                'res_id' => (int) $r['id'],
                'client_name' => (string) $r['client_name'],
                'amount' => $bookingAmt,
            ];
            $breakdownMap[$bookingDate]['total'] += $bookingAmt;
        }
        
        $deliveryDate = substr((string) $r['start_date'], 0, 10);
        $deliveryAmt = hope_calc_delivery_due($r);
        if ($deliveryAmt > 0 && $deliveryDate >= $rangeStart && $deliveryDate <= $rangeEnd) {
            if (!isset($breakdownMap[$deliveryDate])) {
                $breakdownMap[$deliveryDate] = ['booking' => [], 'delivery' => [], 'return' => [], 'extension' => [], 'prediction' => [], 'total' => 0.0];
            }
            $breakdownMap[$deliveryDate]['delivery'][] = [
                'res_id' => (int) $r['id'],
                'client_name' => (string) $r['client_name'],
                'amount' => $deliveryAmt,
            ];
            $breakdownMap[$deliveryDate]['total'] += $deliveryAmt;
        }
        
        $returnDate = substr((string) $r['end_date'], 0, 10);
        $returnAmt = hope_calc_return_due($r);
        if ($returnAmt > 0 && $returnDate >= $rangeStart && $returnDate <= $rangeEnd) {
            if (!isset($breakdownMap[$returnDate])) {
                $breakdownMap[$returnDate] = ['booking' => [], 'delivery' => [], 'return' => [], 'extension' => [], 'prediction' => [], 'total' => 0.0];
            }
            $breakdownMap[$returnDate]['return'][] = [
                'res_id' => (int) $r['id'],
                'client_name' => (string) $r['client_name'],
                'amount' => $returnAmt,
            ];
            $breakdownMap[$returnDate]['total'] += $returnAmt;
        }
    }
    
    foreach ($data['extensions'] as $ext) {
        $extDate = substr((string) $ext['created_at'], 0, 10);
        $extAmt = max(0, (float) ($ext['amount'] ?? 0));
        if ($extAmt > 0 && $extDate >= $rangeStart && $extDate <= $rangeEnd) {
            if (!isset($breakdownMap[$extDate])) {
                $breakdownMap[$extDate] = ['booking' => [], 'delivery' => [], 'return' => [], 'extension' => [], 'prediction' => [], 'total' => 0.0];
            }
            $breakdownMap[$extDate]['extension'][] = [
                'ext_id' => (int) $ext['id'],
                'res_id' => (int) $ext['reservation_id'],
                'client_name' => (string) $ext['client_name'],
                'amount' => $extAmt,
            ];
            $breakdownMap[$extDate]['total'] += $extAmt;
        }
    }
    
    foreach ($data['predictions'] as $pred) {
        $predDate = (string) $pred['target_date'];
        $predAmt = max(0, (float) ($pred['amount'] ?? 0));
        if ($predAmt > 0 && $predDate >= $rangeStart && $predDate <= $rangeEnd) {
            if (!isset($breakdownMap[$predDate])) {
                $breakdownMap[$predDate] = ['booking' => [], 'delivery' => [], 'return' => [], 'extension' => [], 'prediction' => [], 'total' => 0.0];
            }
            $breakdownMap[$predDate]['prediction'][] = [
                'label' => (string) $pred['label'],
                'amount' => $predAmt,
            ];
            $breakdownMap[$predDate]['total'] += $predAmt;
        }
    }
    
    return $breakdownMap;
}

function hope_render_breakdown(array $breakdownMap, string $date): string
{
    if (!isset($breakdownMap[$date])) {
        return '<p class="text-xs text-mb-subtle italic">No expected income sources for this date.</p>';
    }
    
    $breakdown = $breakdownMap[$date];
    $items = [];
    
    foreach ($breakdown['booking'] as $item) {
        $items[] = [
            'label' => 'Res #' . $item['res_id'] . ' - ' . e($item['client_name']) . ' - Booking',
            'amount' => $item['amount'],
            'link' => '../reservations/show.php?id=' . $item['res_id'],
        ];
    }
    
    foreach ($breakdown['delivery'] as $item) {
        $items[] = [
            'label' => 'Res #' . $item['res_id'] . ' - ' . e($item['client_name']) . ' - Delivery',
            'amount' => $item['amount'],
            'link' => '../reservations/show.php?id=' . $item['res_id'],
        ];
    }
    
    foreach ($breakdown['return'] as $item) {
        $items[] = [
            'label' => 'Res #' . $item['res_id'] . ' - ' . e($item['client_name']) . ' - Return',
            'amount' => $item['amount'],
            'link' => '../reservations/show.php?id=' . $item['res_id'],
        ];
    }
    
    foreach ($breakdown['extension'] as $item) {
        $items[] = [
            'label' => 'Extension #' . $item['ext_id'] . ' (Res #' . $item['res_id'] . ' - ' . e($item['client_name']) . ')',
            'amount' => $item['amount'],
            'link' => '../reservations/show.php?id=' . $item['res_id'],
        ];
    }
    
    foreach ($breakdown['prediction'] as $item) {
        $items[] = [
            'label' => 'Custom: ' . e($item['label']),
            'amount' => $item['amount'],
            'link' => null,
        ];
    }
    
    usort($items, fn($a, $b) => $b['amount'] <=> $a['amount']);
    
    $html = '<div class="space-y-1.5">';
    foreach ($items as $item) {
        $html .= '<div class="flex items-center justify-between text-sm group">';
        if ($item['link']) {
            $html .= '<a href="' . e($item['link']) . '" class="text-mb-silver hover:text-mb-accent transition-colors hover:underline">' . $item['label'] . '</a>';
        } else {
            $html .= '<span class="text-mb-silver">' . $item['label'] . '</span>';
        }
        $html .= '<span class="text-green-400 font-medium">' . hope_format_currency($item['amount']) . '</span>';
        $html .= '</div>';
    }
    $html .= '<div class="flex items-center justify-between text-sm pt-2 mt-2 border-t border-mb-subtle/20">';
    $html .= '<span class="text-white font-medium">Total Expected</span>';
    $html .= '<span class="text-green-400 font-bold">' . hope_format_currency($breakdown['total']) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

function hope_fetch_actual_breakdown(PDO $pdo, string $date): array
{
    $items = [];
    
    try {
        $hasLedgerTable = (bool) $pdo->query("SHOW TABLES LIKE 'ledger_entries'")->fetchColumn();
        if (!$hasLedgerTable) {
            return $items;
        }
        
        $kpiClause = ledger_kpi_exclusion_clause();
        $stmt = $pdo->prepare(
            "SELECT le.id, le.amount, le.description, le.source_type, le.source_id, le.source_event,
                    COALESCE(c.name, 'Unknown Client') AS client_name
             FROM ledger_entries le
             LEFT JOIN reservations r ON le.source_type = 'reservation' AND le.source_id = r.id
             LEFT JOIN clients c ON r.client_id = c.id
             WHERE le.txn_type = 'income'
               AND $kpiClause
               AND DATE(le.posted_at) = ?
             ORDER BY le.amount DESC"
        );
        $stmt->execute([$date]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $items = [];
    }
    
    return $items;
}

function hope_render_actual_breakdown(array $items): string
{
    if (empty($items)) {
        return '<p class="text-xs text-mb-subtle italic">No actual income recorded for this date.</p>';
    }
    
    $html = '<div class="space-y-1.5">';
    $total = 0.0;
    
    foreach ($items as $item) {
        $amount = (float) $item['amount'];
        $total += $amount;
        
        $label = '';
        $link = null;
        
        if ($item['source_type'] === 'reservation' && $item['source_id']) {
            $resId = (int) $item['source_id'];
            $clientName = $item['client_name'] ?? 'Unknown Client';
            $event = $item['source_event'] ?? 'payment';
            
            $eventLabel = match($event) {
                'advance', 'delivery_prepaid' => 'Booking',
                'delivery' => 'Delivery',
                'return' => 'Return',
                'extension' => 'Extension',
                'cancellation' => 'Cancellation',
                default => ucfirst($event),
            };
            
            $label = "Res #{$resId} - " . e($clientName) . " - {$eventLabel}";
            $link = '../reservations/show.php?id=' . $resId;
        } else {
            $desc = $item['description'] ?? 'Manual Entry';
            $label = e($desc);
        }
        
        $html .= '<div class="flex items-center justify-between text-sm group">';
        if ($link) {
            $html .= '<a href="' . e($link) . '" class="text-mb-silver hover:text-mb-accent transition-colors hover:underline">' . $label . '</a>';
        } else {
            $html .= '<span class="text-mb-silver">' . $label . '</span>';
        }
        $html .= '<span class="text-blue-400 font-medium">' . hope_format_currency($amount) . '</span>';
        $html .= '</div>';
    }
    
    $html .= '<div class="flex items-center justify-between text-sm pt-2 mt-2 border-t border-mb-subtle/20">';
    $html .= '<span class="text-white font-medium">Total Actual</span>';
    $html .= '<span class="text-blue-400 font-bold">' . hope_format_currency($total) . '</span>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

$defP = hope_period_for_today();
$selM = isset($_GET['m']) ? (int) $_GET['m'] : (int) date('m', strtotime($defP['start']));
$selY = isset($_GET['y']) ? (int) $_GET['y'] : (int) date('Y', strtotime($defP['start']));
if ($selM < 1 || $selM > 12 || $selY < 2020 || $selY > 2099) {
    $selM = (int) date('m', strtotime($defP['start']));
    $selY = (int) date('Y', strtotime($defP['start']));
}

$period = hope_period_from_my($selM, $selY);
$startDate = DateTimeImmutable::createFromFormat('!Y-m-d', $period['start'], $tz);
$endDate = DateTimeImmutable::createFromFormat('!Y-m-d', $period['end'], $tz);
if (!$startDate || !$endDate) {
    $startDate = new DateTimeImmutable($defP['start'], $tz);
    $endDate = new DateTimeImmutable($defP['end'], $tz);
}
$rangeStart = $startDate->format('Y-m-d');
$rangeEnd = $endDate->format('Y-m-d');
$today = $now->format('Y-m-d');
$view = ($_GET['view'] ?? 'list');
$view = $view === 'day' ? 'day' : 'list';
$selectedDate = $today;
if ($view === 'day') {
    $selectedDate = trim((string) ($_GET['d'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
        $selectedDate = '';
    }
    if ($selectedDate === '' || $selectedDate < $rangeStart || $selectedDate > $rangeEnd) {
        $selectedDate = ($today >= $rangeStart && $today <= $rangeEnd) ? $today : $rangeStart;
    }
}

$defaultTarget = (float) settings_get($pdo, 'daily_target', '0');
$success = '';
$error = '';
$redirectBase = "hope_window.php?m={$selM}&y={$selY}";
if ($view === 'day') {
    $redirectBase .= "&view=day&d={$selectedDate}";
} else {
    $redirectBase .= "&view=list";
}

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
            redirect($redirectBase);
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
            redirect($redirectBase);
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

// Load actual income per day from ledger
$actualMap = [];
$hasLedgerTable = false;
try {
    $hasLedgerTable = (bool) $pdo->query("SHOW TABLES LIKE 'ledger_entries'")->fetchColumn();
} catch (Throwable $e) {
    $hasLedgerTable = false;
}
if ($hasLedgerTable) {
    try {
        $kpiClause = ledger_kpi_exclusion_clause();
        $actualStmt = $pdo->prepare(
            "SELECT DATE(posted_at) AS day, COALESCE(SUM(amount), 0) AS total
             FROM ledger_entries
             WHERE txn_type = 'income'
               AND $kpiClause
               AND DATE(posted_at) BETWEEN ? AND ?
             GROUP BY DATE(posted_at)"
        );
        $actualStmt->execute([$rangeStart, $rangeEnd]);
        foreach ($actualStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actualMap[$row['day']] = (float) $row['total'];
        }
    } catch (Throwable $e) {
        $actualMap = [];
    }
}

// Fetch breakdown data and build breakdown map
$breakdownData = hope_fetch_breakdown_data($pdo, $rangeStart, $rangeEnd);
$breakdownMap = hope_build_breakdown_map($breakdownData, $rangeStart, $rangeEnd);

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
        'actual' => $actualMap[$ds] ?? 0.0,
    ];
    $cursor = $cursor->modify('+1 day');
}

$dayByDate = [];
foreach ($days as $d) {
    $dayByDate[$d['date']] = $d;
}

$selectedDay = $dayByDate[$selectedDate] ?? null;
if ($view === 'day' && $selectedDay === null) {
    $selectedDate = $rangeStart;
    $selectedDay = $dayByDate[$selectedDate] ?? null;
}

$prevDate = null;
$nextDate = null;
if ($view === 'day' && $selectedDay) {
    $selObj = DateTimeImmutable::createFromFormat('!Y-m-d', $selectedDate, $tz);
    if ($selObj) {
        $prevObj = $selObj->modify('-1 day');
        if ($prevObj->getTimestamp() >= $startDate->getTimestamp()) {
            $prevDate = $prevObj->format('Y-m-d');
        }
        $nextObj = $selObj->modify('+1 day');
        if ($nextObj->getTimestamp() <= $endDate->getTimestamp()) {
            $nextDate = $nextObj->format('Y-m-d');
        }
    }
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
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <?php if ($view === 'day'): ?>
                <input type="hidden" name="d" value="<?= e($selectedDate) ?>">
            <?php endif; ?>
            <select name="m" class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                <?php
                foreach (range(1, 12) as $mVal):
                    $mNext = $mVal === 12 ? 1 : $mVal + 1;
                    $mLabel = '15 ' . date('M', mktime(0,0,0,$mVal,1)) . ' – 14 ' . date('M', mktime(0,0,0,$mNext,1));
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
        <div class="flex items-center bg-mb-black/60 border border-mb-subtle/20 rounded-lg p-1">
            <a href="hope_window.php?m=<?= $selM ?>&y=<?= $selY ?>&view=list"
               class="px-3 py-1.5 text-xs rounded-md <?= $view === 'list' ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white' ?>">
                List View
            </a>
            <a href="hope_window.php?m=<?= $selM ?>&y=<?= $selY ?>&view=day&d=<?= e($selectedDate) ?>"
               class="px-3 py-1.5 text-xs rounded-md <?= $view === 'day' ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white' ?>">
                Day View
            </a>
        </div>
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
        <a href="vehicle_targets.php?m=<?= $selM ?>&y=<?= $selY ?>" 
           class="block bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 hover:border-mb-accent/50 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Vehicle Breakdown</p>
                    <p class="text-white text-sm">View targets by vehicle</p>
                </div>
                <svg class="w-5 h-5 text-mb-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </div>
        </a>
    </div>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-mb-subtle/10 flex items-center justify-between flex-wrap gap-3">
            <div>
                <h3 class="text-white font-light text-lg">Daily Targets</h3>
                <p class="text-mb-subtle text-xs mt-1">Edit targets per day. Click a row to add custom predictions.</p>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($view === 'day' && $selectedDay): ?>
                    <?php if ($prevDate): ?>
                        <a href="hope_window.php?m=<?= $selM ?>&y=<?= $selY ?>&view=day&d=<?= e($prevDate) ?>"
                           class="px-3 py-1.5 text-xs rounded-lg bg-mb-accent text-white shadow-md shadow-mb-accent/20 hover:bg-mb-accent/80 transition-colors">Prev</a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 text-xs rounded-lg border border-mb-subtle/10 text-mb-subtle/60 cursor-not-allowed">Prev</span>
                    <?php endif; ?>
                    <span class="text-xs text-mb-subtle px-2"> <?= e($selectedDay['label'] ?? '') ?> </span>
                    <?php if ($nextDate): ?>
                        <a href="hope_window.php?m=<?= $selM ?>&y=<?= $selY ?>&view=day&d=<?= e($nextDate) ?>"
                           class="px-3 py-1.5 text-xs rounded-lg bg-mb-accent text-white shadow-md shadow-mb-accent/20 hover:bg-mb-accent/80 transition-colors">Next</a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 text-xs rounded-lg border border-mb-subtle/10 text-mb-subtle/60 cursor-not-allowed">Next</span>
                    <?php endif; ?>
                <?php elseif ($isAdmin): ?>
                    <span class="text-xs text-mb-subtle">Overrides are saved below</span>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" class="p-4 space-y-3">
            <input type="hidden" name="action" value="save_overrides">
            <?php if ($view === 'list'): ?>
                <div class="grid grid-cols-1 gap-2">
                    <div class="grid grid-cols-12 text-xs text-mb-subtle px-3">
                        <div class="col-span-2">Date</div>
                        <div class="col-span-2">Target</div>
                        <div class="col-span-2">Expected</div>
                        <div class="col-span-2">Actual</div>
                        <div class="col-span-2">Predictions</div>
                        <div class="col-span-2 text-right">Variance</div>
                    </div>
                    <?php 
                    // Render today's row first if it's in the selected range
                    if (isset($dayByDate[$today]) && $today >= $rangeStart && $today <= $rangeEnd):
                        $row = $dayByDate[$today];
                        $gap = $row['expected'] - $row['target'];
                        $gapClass = $gap >= 0 ? 'text-green-400' : 'text-red-400';
                        $rowClass = 'border-mb-accent/60 bg-mb-accent/10';
                    ?>
                        <div class="grid grid-cols-12 items-center border <?= $rowClass ?> rounded-lg px-3 py-2 text-sm hope-row cursor-pointer" data-pred-toggle="<?= e($row['date']) ?>">
                            <div class="col-span-2">
                                <p class="text-white"><?= e($row['label']) ?></p>
                                <span class="text-xs text-mb-accent">Today</span>
                            </div>
                            <div class="col-span-2">
                                <?php if ($isAdmin): ?>
                                    <input type="number" step="0.01" min="0" name="target[<?= e($row['date']) ?>]"
                                        value="<?= number_format($row['target'], 2, '.', '') ?>"
                                        class="w-28 bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-1 text-sm text-white focus:outline-none focus:border-mb-accent">
                                <?php else: ?>
                                    <span class="text-white">$<?= number_format($row['target'], 2) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="col-span-2">
                                <span class="text-green-400">$<?= number_format($row['expected'], 2) ?></span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-blue-400">$<?= number_format($row['actual'], 2) ?></span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-white font-medium"><?= (int) $row['prediction_count'] ?></span>
                                <?php if ($row['prediction_sum'] > 0): ?>
                                    <span class="text-xs text-mb-subtle ml-1">($<?= number_format($row['prediction_sum'], 2) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-span-2 text-right">
                                <?php
                                    $variance = $row['actual'] - $row['expected'];
                                    $varianceClass = $variance >= 0 ? 'text-green-400' : 'text-red-400';
                                    $varianceSign  = $variance >= 0 ? '+' : '-';
                                ?>
                                <span class="<?= $varianceClass ?>">
                                    <?= $varianceSign ?>$<?= number_format(abs($variance), 2) ?>
                                </span>
                            </div>
                        </div>
                        <div id="pred-<?= e($row['date']) ?>" class="hidden border border-mb-subtle/10 bg-mb-black/40 rounded-lg px-4 py-3 text-sm">
                            <!-- Expected Income Breakdown -->
                            <div class="mb-4 pb-4 border-b border-mb-subtle/10">
                                <p class="text-white font-medium mb-3">Expected Income Breakdown</p>
                                <?= hope_render_breakdown($breakdownMap, $row['date']) ?>
                            </div>
                            
                            <!-- Actual Income Breakdown -->
                            <div class="mb-4 pb-4 border-b border-mb-subtle/10">
                                <p class="text-white font-medium mb-3">Actual Income Breakdown</p>
                                <?= hope_render_actual_breakdown(hope_fetch_actual_breakdown($pdo, $row['date'])) ?>
                            </div>
                            
                            <!-- Predictions Section -->
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
                                    <div class="col-span-2">
                                        <button type="submit"
                                            class="w-full bg-mb-accent text-white px-3 py-2 rounded-lg text-xs hover:bg-mb-accent/80 transition-colors">
                                            Add & Save
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($days as $row):
                        $gap = $row['expected'] - $row['target'];
                        $gapClass = $gap >= 0 ? 'text-green-400' : 'text-red-400';
                        $rowClass = $row['is_today'] ? 'border-mb-accent/60 bg-mb-accent/10' : 'border-mb-subtle/10 bg-mb-black/30';
                    ?>
                        <div class="grid grid-cols-12 items-center border <?= $rowClass ?> rounded-lg px-3 py-2 text-sm hope-row cursor-pointer" data-pred-toggle="<?= e($row['date']) ?>">
                            <div class="col-span-2">
                                <p class="text-white"><?= e($row['label']) ?></p>
                                <?php if ($row['override']): ?>
                                    <span class="text-xs text-mb-accent">Custom</span>
                                <?php elseif ($row['is_today']): ?>
                                    <span class="text-xs text-mb-accent">Today</span>
                                <?php else: ?>
                                    <span class="text-xs text-mb-subtle">Default</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-span-2">
                                <?php if ($isAdmin): ?>
                                    <input type="number" step="0.01" min="0" name="target[<?= e($row['date']) ?>]"
                                        value="<?= number_format($row['target'], 2, '.', '') ?>"
                                        class="w-28 bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-1 text-sm text-white focus:outline-none focus:border-mb-accent">
                                <?php else: ?>
                                    <span class="text-white">$<?= number_format($row['target'], 2) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="col-span-2">
                                <span class="text-green-400">$<?= number_format($row['expected'], 2) ?></span>
                            </div>
                            <div class="col-span-2">
                                <?php if ($row['date'] <= $today): ?>
                                    <span class="text-blue-400">$<?= number_format($row['actual'], 2) ?></span>
                                <?php else: ?>
                                    <span class="text-mb-subtle/40">—</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-span-2">
                                <span class="text-white font-medium"><?= (int) $row['prediction_count'] ?></span>
                                <?php if ($row['prediction_sum'] > 0): ?>
                                    <span class="text-xs text-mb-subtle ml-1">($<?= number_format($row['prediction_sum'], 2) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div class="col-span-2 text-right">
                                <?php if ($row['date'] <= $today): ?>
                                    <?php
                                        $variance = $row['actual'] - $row['expected'];
                                        $varianceClass = $variance >= 0 ? 'text-green-400' : 'text-red-400';
                                        $varianceSign  = $variance >= 0 ? '+' : '-';
                                    ?>
                                    <span class="<?= $varianceClass ?>">
                                        <?= $varianceSign ?>$<?= number_format(abs($variance), 2) ?>
                                    </span>
                                <?php else: ?>
                                    <?php
                                        $gap = $row['expected'] - $row['target'];
                                        $gapClass = $gap >= 0 ? 'text-green-400' : 'text-red-400';
                                    ?>
                                    <span class="<?= $gapClass ?>">
                                        <?= $gap >= 0 ? '+' : '-' ?>$<?= number_format(abs($gap), 2) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div id="pred-<?= e($row['date']) ?>" class="hidden border border-mb-subtle/10 bg-mb-black/40 rounded-lg px-4 py-3 text-sm">
                            <!-- Expected Income Breakdown -->
                            <div class="mb-4 pb-4 border-b border-mb-subtle/10">
                                <p class="text-white font-medium mb-3">Expected Income Breakdown</p>
                                <?= hope_render_breakdown($breakdownMap, $row['date']) ?>
                            </div>
                            
                            <?php if ($row['date'] <= $today): ?>
                            <!-- Actual Income Breakdown -->
                            <div class="mb-4 pb-4 border-b border-mb-subtle/10">
                                <p class="text-white font-medium mb-3">Actual Income Breakdown</p>
                                <?= hope_render_actual_breakdown(hope_fetch_actual_breakdown($pdo, $row['date'])) ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Predictions Section -->
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
                                    <div class="col-span-2">
                                        <button type="submit"
                                            class="w-full bg-mb-accent text-white px-3 py-2 rounded-lg text-xs hover:bg-mb-accent/80 transition-colors">
                                            Add & Save
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($selectedDay): ?>
                <?php
                    $gap = $selectedDay['expected'] - $selectedDay['target'];
                    $gapClass = $gap >= 0 ? 'text-green-400' : 'text-red-400';
                ?>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="bg-mb-black/40 rounded-lg p-4">
                        <p class="text-xs text-mb-subtle uppercase">Target</p>
                        <?php if ($isAdmin): ?>
                            <input type="number" step="0.01" min="0" name="target[<?= e($selectedDay['date']) ?>]"
                                value="<?= number_format($selectedDay['target'], 2, '.', '') ?>"
                                class="mt-2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                        <?php else: ?>
                            <p class="text-white text-lg mt-2">$<?= number_format($selectedDay['target'], 2) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="bg-mb-black/40 rounded-lg p-4">
                        <p class="text-xs text-mb-subtle uppercase">Expected Income</p>
                        <p class="text-green-400 text-lg mt-2">$<?= number_format($selectedDay['expected'], 2) ?></p>
                    </div>
                    <?php if ($selectedDay['date'] <= $today): ?>
                    <div class="bg-mb-black/40 border border-mb-subtle/10 rounded-xl p-4">
                        <p class="text-xs text-mb-subtle uppercase tracking-wider">Actual Income</p>
                        <p class="text-blue-400 text-xl mt-2">$<?= number_format($selectedDay['actual'], 2) ?></p>
                        <p class="text-mb-subtle text-xs mt-1">Collected on this day.</p>
                    </div>
                    <?php endif; ?>
                    <?php if ($selectedDay['date'] <= $today): ?>
                        <?php
                            $dayVariance = $selectedDay['actual'] - $selectedDay['expected'];
                            $dayVarianceClass = $dayVariance >= 0 ? 'text-green-400' : 'text-red-400';
                            $dayVarianceSign  = $dayVariance >= 0 ? '+' : '-';
                        ?>
                        <div class="bg-mb-black/40 border border-mb-subtle/10 rounded-xl p-4">
                            <p class="text-xs text-mb-subtle uppercase tracking-wider">Variance</p>
                            <p class="<?= $dayVarianceClass ?> text-xl mt-2"><?= $dayVarianceSign ?>$<?= number_format(abs($dayVariance), 2) ?></p>
                            <p class="text-mb-subtle text-xs mt-1">Actual vs expected income.</p>
                        </div>
                    <?php endif; ?>
                    <div class="bg-mb-black/40 rounded-lg p-4">
                        <p class="text-xs text-mb-subtle uppercase">Predictions</p>
                        <p class="text-white text-lg mt-2"><?= (int) $selectedDay['prediction_count'] ?></p>
                        <?php if ($selectedDay['prediction_sum'] > 0): ?>
                            <p class="text-xs text-mb-subtle mt-1">$<?= number_format($selectedDay['prediction_sum'], 2) ?> total</p>
                        <?php endif; ?>
                    </div>
                    <div class="bg-mb-black/40 rounded-lg p-4">
                        <p class="text-xs text-mb-subtle uppercase">Gap</p>
                        <p class="<?= $gapClass ?> text-lg mt-2">
                            <?= $gap >= 0 ? '+' : '-' ?>$<?= number_format(abs($gap), 2) ?>
                        </p>
                    </div>
                </div>
                
                <!-- Expected Income Breakdown -->
                <div class="mt-4 border border-mb-subtle/10 bg-mb-black/40 rounded-lg px-4 py-3">
                    <p class="text-white font-medium text-sm mb-3">Expected Income Breakdown</p>
                    <?= hope_render_breakdown($breakdownMap, $selectedDay['date']) ?>
                </div>
                
                <?php if ($selectedDay['date'] <= $today): ?>
                <!-- Actual Income Breakdown -->
                <div class="mt-4 border border-mb-subtle/10 bg-mb-black/40 rounded-lg px-4 py-3">
                    <p class="text-white font-medium text-sm mb-3">Actual Income Breakdown</p>
                    <?= hope_render_actual_breakdown(hope_fetch_actual_breakdown($pdo, $selectedDay['date'])) ?>
                </div>
                <?php endif; ?>
                
                <div class="mt-4 border border-mb-subtle/10 bg-mb-black/40 rounded-lg px-4 py-3 text-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-white font-medium">Predictions for <?= e($selectedDay['label']) ?></p>
                            <p class="text-mb-subtle text-xs">Add your own expected deals to include in projected income.</p>
                        </div>
                    </div>
                    <div class="mt-3 space-y-2">
                        <?php if (!empty($selectedDay['predictions'])): ?>
                            <?php foreach ($selectedDay['predictions'] as $pred): ?>
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
                                <input type="text" name="pred_new_label[<?= e($selectedDay['date']) ?>]" placeholder="Prediction note (e.g., Tesla booking)"
                                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                            </div>
                            <div class="col-span-3">
                                <input type="number" step="0.01" min="0" name="pred_new_amount[<?= e($selectedDay['date']) ?>]" placeholder="0.00"
                                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                            </div>
                            <div class="col-span-2">
                                <button type="submit"
                                    class="w-full bg-mb-accent text-white px-3 py-2 rounded-lg text-xs hover:bg-mb-accent/80 transition-colors">
                                    Add & Save
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

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
