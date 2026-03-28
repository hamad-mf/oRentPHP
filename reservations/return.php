<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('do_return')) {
    flash('error', 'You do not have permission to process returns.');
    redirect('index.php');
}
require_once __DIR__ . '/../includes/voucher_helpers.php';
require_once __DIR__ . '/../includes/reservation_payment_helpers.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
require_once __DIR__ . '/../includes/notifications.php';
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();
voucher_ensure_schema($pdo);
reservation_payment_ensure_schema($pdo);
ledger_ensure_schema($pdo);
settings_ensure_table($pdo);

$rStmt = $pdo->prepare('SELECT r.*, c.name AS client_name, c.rating AS client_rating_current, c.voucher_balance AS client_voucher_balance, v.brand, v.model, v.license_plate, v.daily_rate FROM reservations r JOIN clients c ON r.client_id=c.id JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();
if (!$r || $r['status'] !== 'active') {
    flash('error', 'Only active reservations can be returned.');
    redirect('index.php');
}

$iStmt = $pdo->prepare("SELECT * FROM vehicle_inspections WHERE reservation_id=? AND type='delivery' LIMIT 1");
$iStmt->execute([$id]);
$delivery = $iStmt->fetch();

// Fetch predefined damage costs
$damageItems = $pdo->query("SELECT * FROM damage_costs ORDER BY item_name ASC")->fetchAll();

// Fetch late return hourly rate from system_settings
$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (`key` VARCHAR(100) NOT NULL PRIMARY KEY, `value` TEXT DEFAULT NULL) ENGINE=InnoDB");
$pdo->exec("INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('late_return_rate_per_hour', '0')");
$lateRatePerHour = (float) $pdo->query("SELECT `value` FROM system_settings WHERE `key`='late_return_rate_per_hour'")->fetchColumn();
$returnPickupChargeDefault = (float) settings_get($pdo, 'return_pickup_charge_default', '0');

$startDt = new DateTime($r['start_date']);
$scheduledEndDt = new DateTime($r['end_date']);
$voucherApplied = max(0, (float) ($r['voucher_applied'] ?? 0));
$advancePaid = max(0, (float) ($r['advance_paid'] ?? 0));
$extensionPaid = max(0, (float) ($r['extension_paid_amount'] ?? 0));
$basePriceForDelivery = max(0, (float) $r['total_price'] - $extensionPaid);
$deliveryCharge = max(0, (float) ($r['delivery_charge'] ?? 0));
$deliveryManualAmount = max(0, (float) ($r['delivery_manual_amount'] ?? 0));
$deliveryPrepaid = max(0, (float) ($r['delivery_charge_prepaid'] ?? 0));
// Account for delivery discount when showing what was actually collected at delivery
$delivDiscType_ = $r['delivery_discount_type'] ?? null;
$delivDiscVal_ = (float) ($r['delivery_discount_value'] ?? 0);
$delivBase_ = max(0, $basePriceForDelivery - $voucherApplied - $advancePaid) + $deliveryCharge + $deliveryManualAmount;
$delivDiscAmt_ = 0;
if ($delivDiscType_ === 'percent') {
    $delivDiscAmt_ = round($delivBase_ * min($delivDiscVal_, 100) / 100, 2);
} elseif ($delivDiscType_ === 'amount') {
    $delivDiscAmt_ = min($delivDiscVal_, $delivBase_);
}
$baseCollectedAtDelivery = max(0, $delivBase_ - $delivDiscAmt_);
$clientVoucherBalance = max(0, (float) ($r['client_voucher_balance'] ?? 0));
$configuredSecurityDepositBankId = ledger_get_active_bank_account_id(
    $pdo,
    (int) settings_get($pdo, 'security_deposit_bank_account_id', '0')
);


$parseActualReturnDateTime = static function (string $date, int $hour12, int $minute, string $ampm): DateTime {
    $date = trim($date);
    if ($date === '') {
        return new DateTime();
    }

    $hour12 = max(1, min(12, $hour12));
    $minute = max(0, min(59, $minute));
    $ampm = strtoupper($ampm) === 'PM' ? 'PM' : 'AM';

    $hour24 = $hour12 % 12;
    if ($ampm === 'PM') {
        $hour24 += 12;
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', sprintf('%s %02d:%02d:00', $date, $hour24, $minute));
    if (!$dt) {
        return new DateTime();
    }
    return $dt;
};

$calculateOverdue = static function (DateTime $scheduledEnd, DateTime $actualReturn, float $dailyRate): array {
    $scheduledDate = new DateTime($scheduledEnd->format('Y-m-d 00:00:00'));
    $actualDate = new DateTime($actualReturn->format('Y-m-d 00:00:00'));

    if ($actualDate <= $scheduledDate) {
        return ['days' => 0, 'amount' => 0.0];
    }

    $days = (int) $scheduledDate->diff($actualDate)->days;
    return ['days' => $days, 'amount' => round($days * $dailyRate, 2)];
};

$calculateEarlyReturnCredit = static function (DateTime $startDate, DateTime $scheduledEnd, DateTime $actualReturn, float $basePrice): float {
    if ($actualReturn >= $scheduledEnd || $basePrice <= 0) {
        return 0.0;
    }

    $effectiveActual = $actualReturn < $startDate ? clone $startDate : clone $actualReturn;
    $totalSeconds = max(1, $scheduledEnd->getTimestamp() - $startDate->getTimestamp());
    $unusedSeconds = max(0, $scheduledEnd->getTimestamp() - $effectiveActual->getTimestamp());
    if ($unusedSeconds <= 0) {
        return 0.0;
    }

    return round($basePrice * ($unusedSeconds / $totalSeconds), 2);
};

$initialActualDt = $parseActualReturnDateTime(
    trim($_POST['actual_return_date'] ?? date('Y-m-d')),
    (int) ($_POST['actual_return_hour'] ?? date('g')),
    (int) ($_POST['actual_return_min'] ?? floor(date('i') / 5) * 5),
    (string) ($_POST['actual_return_ampm'] ?? date('A'))
);
$initialOverdue = $calculateOverdue($scheduledEndDt, $initialActualDt, (float) $r['daily_rate']);
$overdueDays = (int) ($initialOverdue['days'] ?? 0);
$overdueAmt = (float) ($initialOverdue['amount'] ?? 0);
$initialEarlyVoucherCredit = $calculateEarlyReturnCredit($startDt, $scheduledEndDt, $initialActualDt, (float) $r['total_price']);
$existingAdditionalChg = max(0, (float) ($r['additional_charge'] ?? 0));
$additionalChg = max(0, (float) ($_POST['additional_charge'] ?? ($existingAdditionalChg > 0 ? $existingAdditionalChg : $returnPickupChargeDefault)));
$chellanAmt = max(0, (float) ($_POST['chellan_amount'] ?? ($r['chellan_amount'] ?? 0)));
$returnPaymentMethod = reservation_payment_method_normalize($_POST['return_payment_method'] ?? ($r['return_payment_method'] ?? 'cash')) ?? 'cash';
$returnBankAccountId = (int) ($_POST['return_bank_account_id'] ?? 0);
$returnBankAccountId = $returnBankAccountId > 0 ? $returnBankAccountId : null;
$returnPaymentSourceType = trim((string) ($_POST['return_payment_source_type'] ?? 'single'));
if (!in_array($returnPaymentSourceType, ['single', 'multi'], true)) {
    $returnPaymentSourceType = 'single';
}
$activeBankAccounts = array_values(array_filter(ledger_get_accounts($pdo), fn($a) => (int) ($a['is_active'] ?? 0) === 1));

// Initialise damage / km / late so they exist for the deposit block on GET
$kmOverageChg = 0;
$damageChg    = 0;
$lateChg      = 0;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fuel = (int) ($_POST['fuel_level'] ?? 100);
    $miles = (int) ($_POST['mileage'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $kmDriven = $_POST['km_driven'] !== '' ? (int) $_POST['km_driven'] : null;
    $damageChg = max(0, (float) ($_POST['damage_charge'] ?? 0));
    $additionalChgInput = (float) ($_POST['additional_charge'] ?? $returnPickupChargeDefault);
    $additionalChg = max(0, $additionalChgInput);
    $chellanAmt = max(0, (float) ($_POST['chellan_amount'] ?? 0));
    $discType = in_array($_POST['discount_type'] ?? '', ['percent', 'amount']) ? $_POST['discount_type'] : null;
    $discVal = max(0, (float) ($_POST['discount_value'] ?? 0));
    $actualReturnDate = trim($_POST['actual_return_date'] ?? '');
    $actualReturnHour = (int) ($_POST['actual_return_hour'] ?? 12);
    $actualReturnMin = (int) ($_POST['actual_return_min'] ?? 0);
    $actualReturnAMPM = $_POST['actual_return_ampm'] ?? 'AM';
    $clientRating = (int) ($_POST['client_rating'] ?? 0);
    $clientRatingReview = trim($_POST['client_rating_review'] ?? '');
    $clientSatisfied = isset($_POST['client_satisfied']) && in_array($_POST['client_satisfied'], ['yes', 'no'], true) 
        ? $_POST['client_satisfied'] 
        : null;
    $clientComment = isset($_POST['client_comment']) && trim($_POST['client_comment']) !== '' 
        ? trim(substr($_POST['client_comment'], 0, 255)) 
        : null;
    $returnVoucherRequest = max(0, round((float) ($_POST['return_voucher_amount'] ?? 0), 2));
    $depositReturned = max(0, (float) ($_POST['deposit_returned'] ?? 0));
    $depositDeducted = max(0, (float) ($_POST['deposit_deducted'] ?? 0));
    $depositHeld = max(0, (float) ($_POST['deposit_held'] ?? 0));
    $depositHoldReason = trim($_POST['deposit_hold_reason'] ?? '');
    $returnPaymentMethod = reservation_payment_method_normalize($_POST['return_payment_method'] ?? null);
    $returnBankAccountId = (int) ($_POST['return_bank_account_id'] ?? 0);
    $returnBankAccountId = $returnBankAccountId > 0 ? $returnBankAccountId : null;
    $returnVoucherApplied = 0.0;
    $returnPaymentSourceType = trim((string) ($_POST['return_payment_source_type'] ?? 'single'));
    if (!in_array($returnPaymentSourceType, ['single', 'multi'], true)) {
        $returnPaymentSourceType = 'single';
    }
    $returnMultiCashAmount = max(0, (float) ($_POST['return_multi_cash_amount'] ?? 0));
    $returnMultiCreditAmount = max(0, (float) ($_POST['return_multi_credit_amount'] ?? 0));
    $returnMultiBankAmount = max(0, (float) ($_POST['return_multi_bank_amount'] ?? 0));
    $returnMultiBankAccountId = (int) ($_POST['return_multi_bank_account_id'] ?? 0);
    $returnMultiBankAccountId = $returnMultiBankAccountId > 0 ? $returnMultiBankAccountId : null;

    $actualDt = $parseActualReturnDateTime($actualReturnDate, $actualReturnHour, $actualReturnMin, $actualReturnAMPM);
    $actualEndSave = $actualDt->format('Y-m-d H:i:s');

    // Overdue daily charge (calendar-day based)
    $calcOverdue = $calculateOverdue($scheduledEndDt, $actualDt, (float) $r['daily_rate']);
    $overdueDays = (int) ($calcOverdue['days'] ?? 0);
    $overdueAmt = (float) ($calcOverdue['amount'] ?? 0);

    // Late return charge (time-based with grace period; switch to daily rate at 6+ hours)
    $lateChg = 0;
    if ($actualDt > $scheduledEndDt) {
        $lateMinutes = (int) round(($actualDt->getTimestamp() - $scheduledEndDt->getTimestamp()) / 60);
        if ($lateMinutes >= 30) {
            if ($lateMinutes >= 360) {
                $lateChg = round((float) $r['daily_rate'], 2);
            } elseif ($lateRatePerHour > 0) {
                $lateChg = round($lateMinutes * ($lateRatePerHour / 60), 2);
            }
        }
    }

    // KM overage calc
    $kmOverageChg = 0;
    if ($kmDriven !== null && $r['km_limit'] && $r['extra_km_price'] && $kmDriven > $r['km_limit']) {
        $kmOverageChg = ($kmDriven - $r['km_limit']) * (float) $r['extra_km_price'];
    }

    // Discount applies only to return-time charges (base rental is collected at delivery)
    $returnChargesBeforeDiscount = $overdueAmt + $kmOverageChg + $damageChg + $additionalChg + $chellanAmt + $lateChg;
    $discountAmt = 0;
    if ($discType === 'percent') {
        $discountAmt = round($returnChargesBeforeDiscount * min($discVal, 100) / 100, 2);
    } elseif ($discType === 'amount') {
        $discountAmt = min($discVal, $returnChargesBeforeDiscount);
    }
    $amountDueAtReturn = max(0, $returnChargesBeforeDiscount - $discountAmt);
    if ($returnVoucherRequest > 0) {
        // Auto-cap voucher to the maximum usable value instead of failing validation.
        $returnVoucherApplied = round(min($returnVoucherRequest, $clientVoucherBalance, $amountDueAtReturn), 2);
    }
    $cashDueAtReturn = max(0, $amountDueAtReturn - $returnVoucherApplied - $depositDeducted);
    $earlyVoucherCredit = $calculateEarlyReturnCredit($startDt, $scheduledEndDt, $actualDt, (float) $r['total_price']);

    if ($fuel < 0 || $fuel > 100)
        $errors['fuel_level'] = 'Fuel level must be 0–100.';
    if ($miles < 0)
        $errors['mileage'] = 'Mileage must be 0 or more.';

    // All photos are required
    $requiredPhotos = ['front','back','left','right','odometer'];
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
    if ($additionalChgInput < 0)
        $errors['additional_charge'] = 'Return pickup charge cannot be negative.';
    
    // Deposit validation
    $maxDepositCollected = max(0, (float) ($r['deposit_amount'] ?? 0));
    $alreadyDeducted = (float) ($r['deposit_deducted'] ?? 0);
    $alreadyHeld = (float) ($r['deposit_held'] ?? 0);
    $alreadyReturned = (float) ($r['deposit_returned'] ?? 0);
    // Include deposit used for extensions (graceful degradation)
    $depositUsedForExtension = 0.0;
    if (column_exists($pdo, 'reservations', 'deposit_used_for_extension')) {
        $depositUsedForExtension = max(0, (float) ($r['deposit_used_for_extension'] ?? 0));
    }
    $remainingDeposit = $maxDepositCollected - $alreadyReturned - $alreadyDeducted - $alreadyHeld - $depositUsedForExtension;
    $maxReturnable = $remainingDeposit - $depositDeducted - $depositHeld;
    
    if ($depositReturned > $maxReturnable) {
        $errors['deposit_returned'] = 'Deposit returned cannot exceed $' . number_format($maxReturnable, 2) . ' (Remaining deposit minus deduct/hold).';
    }
    if ($depositReturned < 0) $depositReturned = 0;
    if ($depositDeducted < 0) $depositDeducted = 0;
    if ($depositHeld < 0) $depositHeld = 0;
    if ($depositHeld > 0 && $depositHoldReason === '') {
        $errors['deposit_hold_reason'] = 'Please provide a reason for holding the deposit.';
    }
    // Validate bank account is configured when deposit transactions are needed
    if (($depositReturned > 0 || $depositDeducted > 0 || $depositHeld > 0) && $configuredSecurityDepositBankId === null) {
        $errors['deposit_bank_account'] = 'Security deposit bank account must be configured in Settings before processing deposit transactions.';
    }
    if ($cashDueAtReturn > 0 && $returnPaymentSourceType === 'single') {
        if ($returnPaymentMethod === null)
            $errors['return_payment_method'] = 'Please select how the return payment was received.';
        if ($returnPaymentMethod === 'account') {
            if ($returnBankAccountId === null) {
                $errors['return_bank_account_id'] = 'Please select the bank account that received this payment.';
            } else {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id = ? AND is_active = 1");
                $chk->execute([$returnBankAccountId]);
                if ((int) $chk->fetchColumn() === 0) {
                    $errors['return_bank_account_id'] = 'Selected bank account is invalid or inactive.';
                }
            }
        } else {
            $returnBankAccountId = null;
        }
    } elseif ($cashDueAtReturn > 0 && $returnPaymentSourceType === 'multi') {
        $returnMultiTotal = round($returnMultiCashAmount + $returnMultiCreditAmount + $returnMultiBankAmount, 2);
        if (abs($returnMultiTotal - $cashDueAtReturn) > 0.01) {
            $errors['return_multi_total'] = 'Split amounts must total exactly $' . number_format($cashDueAtReturn, 2) . '. Current total: $' . number_format($returnMultiTotal, 2);
        }
        if ($returnMultiBankAmount > 0 && $returnMultiBankAccountId === null) {
            $errors['return_multi_bank_account_id'] = 'Please select a bank account for the bank payment portion.';
        }
        if ($returnMultiBankAmount > 0 && $returnMultiBankAccountId !== null) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM bank_accounts WHERE id = ? AND is_active = 1");
            $chk->execute([$returnMultiBankAccountId]);
            if ((int) $chk->fetchColumn() === 0) {
                $errors['return_multi_bank_account_id'] = 'Selected bank account is invalid or inactive.';
            }
        }
        $returnPaymentMethod = null;
        $returnBankAccountId = null;
    }
    if ($clientRating < 1 || $clientRating > 5)
        $errors['client_rating'] = 'Please rate the client (1 to 5 stars) before completing return.';

    if (empty($errors)) {
        $voucherCreditIssued = 0.0;
        $returnVoucherAppliedActual = 0.0;
        $newVoucherBalance = null;

        try {
            $pdo->beginTransaction();

            $iStmt = $pdo->prepare('INSERT INTO vehicle_inspections (reservation_id,type,fuel_level,mileage,notes) VALUES (?,?,?,?,?)');
            $iStmt->execute([$id, 'return', $fuel, $miles, $notes]);
            $inspectionId = (int) $pdo->lastInsertId();

            // Handle Photos
            if (isset($_FILES['photos'])) {
                $dir = __DIR__ . '/../uploads/inspections/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
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
                    $spFilename = 'scratch_' . $id . '_return_' . $n . '_' . time() . '.' . $spExt;
                    if (move_uploaded_file($_FILES['scratch_photos']['tmp_name'][$n], $scratchDir . $spFilename)) {
                        $pdo->prepare(
                            'INSERT INTO reservation_scratch_photos (reservation_id, event_type, slot_index, file_path) VALUES (?, ?, ?, ?)'
                        )->execute([$id, 'return', $n, 'uploads/scratch_photos/' . $spFilename]);
                    }
                }
            }

            if ($returnVoucherApplied > 0) {
                $returnVoucherAppliedActual = voucher_apply_debit(
                    $pdo,
                    (int) $r['client_id'],
                    $returnVoucherApplied,
                    $id,
                    'Applied on return charges for reservation #' . $id
                );
                $cashDueAtReturn = max(0, $amountDueAtReturn - $returnVoucherAppliedActual);
            }

            if ($earlyVoucherCredit > 0) {
                $voucherCreditIssued = $earlyVoucherCredit;
                $newVoucherBalance = voucher_add_credit(
                    $pdo,
                    (int) $r['client_id'],
                    $voucherCreditIssued,
                    $id,
                    'Early return credit for reservation #' . $id
                );
            }

            $returnPaymentMethodSave = ($cashDueAtReturn > 0 && $returnPaymentSourceType === 'single') ? $returnPaymentMethod : null;
            $depositHeldAt = $depositHeld > 0 ? $actualEndSave : null;
            $pdo->prepare("UPDATE reservations SET status='completed', actual_end_date=?, overdue_amount=?,
                km_driven=?, km_overage_charge=?, damage_charge=?, additional_charge=?, chellan_amount=?, discount_type=?, discount_value=?,
                return_voucher_applied=?, return_payment_method=?, return_paid_amount=?, early_return_credit=?, voucher_credit_issued=?, 
                deposit_returned=?, deposit_deducted=?, deposit_held=?, deposit_hold_reason=?, deposit_held_at=?, 
                client_satisfied=?, client_comment=? WHERE id=?")
                ->execute([
                    $actualEndSave,
                    $overdueAmt + $lateChg,
                    $kmDriven,
                    $kmOverageChg,
                    $damageChg,
                    $additionalChg,
                    $chellanAmt,
                    $discType,
                    $discVal,
                    $returnVoucherAppliedActual,
                    $returnPaymentMethodSave,
                    $cashDueAtReturn,
                    $earlyVoucherCredit,
                    $voucherCreditIssued,
                    $depositReturned,
                    $depositDeducted,
                    $depositHeld,
                    $depositHoldReason ?: null,
                    $depositHeldAt,
                    $clientSatisfied,
                    $clientComment,
                    $id,
                ]);
            // Only free up the vehicle if no other active reservation exists for it
            $pdo->prepare("UPDATE vehicles SET status='available' 
                           WHERE id=? 
                           AND NOT EXISTS (
                               SELECT 1 FROM reservations 
                               WHERE vehicle_id = ? AND status = 'active' AND id != ?
                           )")->execute([$r['vehicle_id'], $r['vehicle_id'], $id]);

            $pdo->prepare("UPDATE clients SET rating=?, rating_review=? WHERE id=?")->execute([$clientRating, $clientRatingReview, $r['client_id']]);
            // Save per-reservation review history (history preserved per return)
            $pdo->prepare("
                INSERT INTO client_reviews (client_id, reservation_id, rating, review, created_by)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE rating=VALUES(rating), review=VALUES(review), created_by=VALUES(created_by)
            ")->execute([$r['client_id'], $id, $clientRating, $clientRatingReview ?: null, $_SESSION['user']['id'] ?? null]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log('ERROR', 'Return process failed (res #' . $id . '): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $errors['db'] = 'Could not complete return. Please try again.';
        }

        if (empty($errors)) {
            $msg = 'Vehicle returned. Amount due at return: $' . number_format($cashDueAtReturn, 2);
            if ($cashDueAtReturn > 0 && $returnPaymentSourceType === 'single' && $returnPaymentMethodSave !== null) {
                $msg .= ' | Method: ' . reservation_payment_method_label($returnPaymentMethodSave);
            } elseif ($cashDueAtReturn > 0 && $returnPaymentSourceType === 'multi') {
                $msg .= ' | Method: Multi Source';
            }
            $msg .= ' | Base collected at delivery: $' . number_format($baseCollectedAtDelivery, 2);
            if ($extensionPaid > 0) {
                $msg .= ' | Extension collected: $' . number_format($extensionPaid, 2);
            }
            if ($advancePaid > 0) {
                $msg .= ' | Advance collected: $' . number_format($advancePaid, 2);
            }
            if ($r['deposit_amount'] > 0) {
                $depositParts = [];
                $depositParts[] = number_format((float) $r['deposit_amount'], 2);
                if ($depositDeducted > 0) $depositParts[] = '-$' . number_format($depositDeducted, 2);
                if ($depositHeld > 0) $depositParts[] = '-$' . number_format($depositHeld, 2);
                if ($depositReturned > 0) $depositParts[] = '-$' . number_format($depositReturned, 2);
                $msg .= ' | Deposit: $' . implode(' / ', $depositParts);
            }
            if ($voucherApplied > 0) {
                $msg .= ' | Voucher used on booking: $' . number_format($voucherApplied, 2);
            }
            if ($returnVoucherAppliedActual > 0) {
                $msg .= ' | Voucher used on return: -$' . number_format($returnVoucherAppliedActual, 2);
            }
            if ($voucherCreditIssued > 0) {
                $msg .= ' | Early return voucher credit: +$' . number_format($voucherCreditIssued, 2);
                if ($newVoucherBalance !== null) {
                    $msg .= ' (Balance: $' . number_format((float) $newVoucherBalance, 2) . ')';
                }
            }
            $msg .= ' | Client Rating: ' . $clientRating . '/5';
            if ($overdueAmt > 0)
                $msg .= " | Overdue: +$" . number_format($overdueAmt, 2);
            if ($lateChg > 0)
                $msg .= " | Late Return: +$" . number_format($lateChg, 2);
            if ($kmOverageChg > 0)
                $msg .= " | KM Overage: +$" . number_format($kmOverageChg, 2);
            if ($damageChg > 0)
                $msg .= " | Damage: +$" . number_format($damageChg, 2);
            if ($additionalChg > 0)
                $msg .= " | Additional: +$" . number_format($additionalChg, 2);
            if ($discountAmt > 0)
                $msg .= " | Discount: -$" . number_format($discountAmt, 2);
            app_log('ACTION', "Returned reservation (ID: $id)");
            $ledgerUserId = (int) ($_SESSION['user']['id'] ?? 0);
            // ── Auto-post income to ledger ──────────────────────────────
            if ($returnPaymentSourceType === 'multi' && $cashDueAtReturn > 0) {
            $splits = [];
            if ($returnMultiCashAmount > 0)   $splits[] = ['mode' => 'cash',    'amount' => $returnMultiCashAmount,   'bank_id' => null];
            if ($returnMultiCreditAmount > 0) $splits[] = ['mode' => 'credit',  'amount' => $returnMultiCreditAmount, 'bank_id' => null];
            if ($returnMultiBankAmount > 0)   $splits[] = ['mode' => 'account', 'amount' => $returnMultiBankAmount,   'bank_id' => $returnMultiBankAccountId];
            ledger_post_reservation_event_multi($pdo, $id, 'return', $splits, $ledgerUserId);
        } else {
            ledger_post_reservation_event($pdo, $id, 'return', $cashDueAtReturn, $returnPaymentMethodSave, $ledgerUserId, $returnBankAccountId);
        }
            
            // ── Security Deposit Handling ──────────────────────────────────
            $depositBankAccountId = ledger_get_security_deposit_account_id($pdo, $id) ?? $configuredSecurityDepositBankId;
            
            // 1. Post deducted amount as REAL INCOME (counts toward KPI)
            if ($depositDeducted > 0 && $depositBankAccountId !== null) {
                // Move out of deposit tracking
                ledger_post($pdo, 'expense', 'Security Deposit', $depositDeducted, 'account', $depositBankAccountId,
                    'reservation', $id, 'security_deposit_deducted',
                    "Reservation #$id - Deposit deducted (damage/charges)",
                    $ledgerUserId, "reservation:security_deposit_deducted:$id");
                // Post as real income
                ledger_post($pdo, 'income', 'Damage Charges', $depositDeducted, 'account', $depositBankAccountId,
                    'reservation', $id, 'damage_from_deposit',
                    "Reservation #$id - Damage charges from deposit",
                    $ledgerUserId, "reservation:damage_from_deposit:$id");
            }
            
            // 2. Post amount being HELD (stays excluded from KPI)
            if ($depositHeld > 0 && $depositBankAccountId !== null) {
                ledger_post($pdo, 'expense', 'Security Deposit', $depositHeld, 'account', $depositBankAccountId,
                    'reservation', $id, 'security_deposit_held',
                    "Reservation #$id - Deposit held: " . ($depositHoldReason ?: 'No reason provided'),
                    $ledgerUserId, "reservation:security_deposit_held:$id");
            }
            
            // 3. Post amount RETURNED to client
            if ($depositReturned > 0) {
                if ($depositBankAccountId !== null) {
                    ledger_post_security_deposit($pdo, $id, 'out', $depositReturned, $depositBankAccountId, $ledgerUserId);
                } else {
                    $msg .= ' | Security deposit return ledger not posted (no bank account)';
                    app_log('ERROR', 'Security deposit return ledger skipped for reservation #' . $id . ': no bank account available.');
                }
            }
            
            // Deposit summary for message
            if ($maxDepositCollected > 0) {
                $depositSummary = '';
                if ($depositDeducted > 0) $depositSummary .= ' | Deducted: $' . number_format($depositDeducted, 2);
                if ($depositHeld > 0) $depositSummary .= ' | Held: $' . number_format($depositHeld, 2);
                if ($depositReturned > 0) $depositSummary .= ' | Returned: $' . number_format($depositReturned, 2);
                if ($depositSummary) $msg .= $depositSummary;
            }
            
            flash('success', $msg);
            // Log staff activity
            require_once __DIR__ . '/../includes/activity_log.php';
            log_activity(
                db(),
                'return',
                'reservation',
                $id,
                "Returned reservation #{$id} — {$r['client_name']} → {$r['brand']} {$r['model']}. Due at return: \$" . number_format($cashDueAtReturn, 2) . "."
            );

            // Create notification
            $vehicleName = $r['brand'] . ' ' . $r['model'];
            notif_create_reservation_event($pdo, $id, 'returned', $r['client_name'], $vehicleName);

            redirect("bill.php?id=$id");
        }
    }
}

$pageTitle = 'Process Return';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors">Reservation #<?= $id ?></a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Process Return</span>
    </div>

    <?php if ($overdueDays > 0): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 text-red-400 text-sm">
            <p class="font-medium">⚠ Overdue by <?= $overdueDays ?> day(s)</p>
            <p class="mt-1">Additional charge: <strong>$<?= number_format($overdueAmt, 2) ?></strong> will be applied.</p>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400">
            <?php foreach ($errors as $e): ?>
                <p>&bull; <?= e($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-8" id="returnForm">
        <input type="hidden" name="client_rating" id="clientRatingInput"
            value="<?= e($_POST['client_rating'] ?? ($r['client_rating_current'] ?? '')) ?>">
        <input type="hidden" name="client_rating_review" id="clientRatingReviewInput"
            value="<?= e($_POST['client_rating_review'] ?? '') ?>">
        
        <!-- Client Satisfaction Section -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-mb-silver mb-3">
                    Client Satisfied?
                </label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="client_satisfied" value="yes" 
                               <?= (($_POST['client_satisfied'] ?? '') === 'yes') ? 'checked' : '' ?>
                               class="accent-green-500">
                        <span class="text-white">Yes</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="client_satisfied" value="no" 
                               <?= (($_POST['client_satisfied'] ?? '') === 'no') ? 'checked' : '' ?>
                               class="accent-red-500">
                        <span class="text-white">No</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-mb-silver mb-2">
                    Client Comment (Optional)
                </label>
                <textarea name="client_comment" rows="2" maxlength="255"
                          class="w-full bg-mb-surface border border-mb-subtle/20 
                                 rounded-lg px-4 py-3 text-white focus:outline-none 
                                 focus:border-mb-accent transition-colors"
                          placeholder="Brief feedback from client..."><?= e($_POST['client_comment'] ?? '') ?></textarea>
                <p class="text-xs text-mb-subtle mt-1">Maximum 255 characters</p>
            </div>
        </div>
        
        <!-- Header Info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-6 grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Client</span>
                <p class="text-white text-lg font-light"><?= e($r['client_name']) ?></p>
            </div>
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Vehicle</span>
                <p class="text-white text-lg font-light"><?= e($r['brand']) ?> <?= e($r['model']) ?></p>
            </div>
            <div>
                <span class="block text-mb-subtle text-xs uppercase mb-1">Due Date & Time</span>
                <p class="text-white text-lg font-light"><?= date('d M Y', strtotime($r['end_date'])) ?></p>
                <p class="text-mb-silver text-sm"><?= date('h:i A', strtotime($r['end_date'])) ?></p>
            </div>
            <div class="md:col-span-2 space-y-4">
                <span class="block text-mb-subtle text-xs uppercase mb-1">Actual Return Date &amp; Time</span>
                <?php if ($lateRatePerHour > 0): ?>
                    <p class="text-xs text-mb-subtle mb-1 flex items-center gap-1.5 opacity-80">
                        <svg class="w-3.5 h-3.5 text-mb-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Late charge: $<?= number_format($lateRatePerHour, 2) ?>/hr (30m grace). For 6h+ late:
                        $<?= number_format((float) $r['daily_rate'], 2) ?>/day.
                    </p>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Date Input -->
                    <div class="flex-1">
                        <input type="date" name="actual_return_date" id="retDate"
                            value="<?= e($_POST['actual_return_date'] ?? date('Y-m-d')) ?>" onchange="updateSummary()"
                            class="w-full bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-4 py-3 text-white text-sm focus:border-mb-accent outline-none appearance-none transition-all hover:border-mb-subtle/40 font-medium">
                    </div>

                    <!-- Time Compound Picker -->
                    <div
                        class="flex items-center bg-mb-black/40 border border-mb-subtle/20 rounded-xl px-3 py-1.5 focus-within:border-mb-accent transition-all hover:border-mb-subtle/40 shadow-inner">
                        <select name="actual_return_hour" id="retHour" onchange="updateSummary()"
                            class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                <option value="<?= $i ?>" <?= (($_POST['actual_return_hour'] ?? date('g')) == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                        <span class="text-mb-subtle font-bold px-0.5">:</span>
                        <select name="actual_return_min" id="retMin" onchange="updateSummary()"
                            class="bg-transparent text-white text-sm focus:outline-none px-2 py-1.5 cursor-pointer w-12 text-center font-medium appearance-none">
                            <?php for ($i = 0; $i < 60; $i += 5): ?>
                                <option value="<?= $i ?>" <?= (($_POST['actual_return_min'] ?? floor(date('i') / 5) * 5) == $i) ? 'selected' : '' ?> class="bg-mb-surface text-white"><?= sprintf('%02d', $i) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <div class="w-px h-5 bg-mb-subtle/20 mx-2"></div>
                        <select name="actual_return_ampm" id="retAMPM" onchange="updateSummary()"
                            class="bg-transparent text-mb-accent font-bold text-xs focus:outline-none px-2 py-1.5 cursor-pointer uppercase tracking-tight appearance-none">
                            <option value="AM" <?= (($_POST['actual_return_ampm'] ?? date('A')) == 'AM') ? 'selected' : '' ?> class="bg-mb-surface text-white">AM</option>
                            <option value="PM" <?= (($_POST['actual_return_ampm'] ?? date('A')) == 'PM') ? 'selected' : '' ?> class="bg-mb-surface text-white">PM</option>
                        </select>
                    </div>
                </div>
                <p id="lateGraceHint" class="text-xs text-green-400 mt-2 font-medium hidden">✓ Within 30-min grace
                    period — no charge</p>
            </div>
        </div>

        <?php if ($delivery): ?>
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-4 text-sm">
                <p class="text-mb-subtle text-xs uppercase mb-2">At Delivery</p>
                <div class="flex gap-6">
                    <div>
                        <p class="text-mb-subtle text-xs">Mileage</p>
                        <p class="text-white"><?= number_format($delivery['mileage']) ?> km</p>
                    </div>
                    <div>
                        <p class="text-mb-subtle text-xs">Fuel</p>
                        <p class="text-white"><?= $delivery['fuel_level'] ?>%</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Readings -->
            <div class="space-y-6">
                <h3 class="text-white text-lg font-light border-l-2 border-green-500 pl-3">Return Readings</h3>
                <div>
                    <label for="mileage" class="block text-sm font-medium text-mb-silver mb-2">Return Mileage
                        (km)</label>
                    <input type="number" name="mileage" id="mileage" required
                        class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors"
                        placeholder="e.g. 15500" value="<?= e($_POST['mileage'] ?? '') ?>" 
                        oninput="calculateKmDriven()">
                    <?php if ($delivery): ?>
                        <p class="text-xs text-mb-silver mt-2">
                            Delivery mileage: <span class="text-white font-medium"><?= number_format($delivery['mileage']) ?> km</span>
                            <span id="kmDrivenDisplay" class="ml-3 text-green-400 font-medium"></span>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="fuel_level" class="block text-sm font-medium text-mb-silver mb-2">Return Fuel Level
                        (%)</label>
                    <div class="relative pt-1">
                        <input type="range" name="fuel_level" id="fuelSlider" min="0" max="100"
                            value="<?= e($_POST['fuel_level'] ?? 100) ?>"
                            class="w-full h-2 bg-mb-subtle/50 rounded-lg appearance-none cursor-pointer accent-green-500"
                            oninput="document.getElementById('fuel-val').innerText = this.value + '%'">
                        <span id="fuel-val"
                            class="absolute right-0 top-0 text-green-500 text-sm font-bold"><?= e($_POST['fuel_level'] ?? 100) ?>%</span>
                    </div>
                    <div class="h-2 bg-mb-black/60 rounded-full overflow-hidden mt-3">
                        <div id="fuelBar" class="h-2 bg-green-500 rounded-full transition-all"
                            style="width:<?= e($_POST['fuel_level'] ?? 100) ?>%"></div>
                    </div>
                </div>
                <div>
                    <label for="notes" class="block text-sm font-medium text-mb-silver mb-2">Return Inspection
                        Notes</label>
                    <textarea name="notes" id="notes" rows="4"
                        class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors"
                        placeholder="Any new damage or issues found?"><?= e($_POST['notes'] ?? '') ?></textarea>
                </div>

                <!-- Return Charges & Discount -->
                <div class="pt-4 border-t border-mb-subtle/10 space-y-5">
                    <h3 class="text-white text-lg font-light border-l-2 border-orange-500 pl-3">Return Charges &amp;
                        Discount</h3>

                    <?php if (!empty($r['km_limit'])): ?>
                        <div class="bg-yellow-500/5 border border-yellow-500/20 rounded-lg p-4 space-y-3">
                            <p class="text-yellow-400 text-xs uppercase font-medium">
                                KM Limit: <?= number_format($r['km_limit']) ?> km &mdash;
                                $<?= number_format((float) $r['extra_km_price'], 2) ?>/extra km
                            </p>
                            <div>
                                <label class="block text-sm text-mb-silver mb-2">KM Driven by Client</label>
                                <input type="number" name="km_driven" id="kmDriven" min="0"
                                    placeholder="Enter total KM driven" value="<?= e($_POST['km_driven'] ?? '') ?>"
                                    oninput="updateSummary()"
                                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-yellow-500 transition-colors">
                            </div>
                            <p id="kmOverageMsg" class="text-yellow-400 text-sm font-medium hidden"></p>
                        </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-sm text-mb-silver mb-3">Damage Charges</label>
                        <div class="space-y-3">
                            <?php if (empty($damageItems)): ?>
                                <p class="text-xs text-mb-subtle italic">No predefined damage costs. Add them in <a
                                        href="../settings/damage_costs.php" class="text-mb-accent underline">Settings</a>.
                                </p>
                                <input type="number" name="damage_charge" id="damageCharge" min="0" step="0.01"
                                    placeholder="0.00" value="<?= e($_POST['damage_charge'] ?? '0') ?>"
                                    oninput="updateSummary()"
                                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-orange-500/50 transition-colors">
                            <?php else: ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <?php foreach ($damageItems as $di): ?>
                                        <label
                                            class="flex items-center gap-3 p-3 bg-mb-black/30 border border-mb-subtle/10 rounded-xl cursor-pointer hover:border-orange-500/30 transition-all group">
                                            <input type="checkbox"
                                                class="damage-checkbox w-5 h-5 rounded border-mb-subtle/30 text-orange-500 focus:ring-orange-500 bg-mb-surface"
                                                data-cost="<?= e($di['cost']) ?>" onchange="updateSummary()">
                                            <div class="flex-1">
                                                <p class="text-sm text-white group-hover:text-orange-500 transition-colors">
 <?= e($di['item_name']) ?>
                                                </p>
                                                <p class="text-xs text-mb-subtle">$<?= number_format($di['cost'], 2) ?></p>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="damage_charge" id="damageCharge" value="0">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Return Pickup Charge</label>
                        <input type="number" name="additional_charge" id="additionalCharge" min="0" step="0.01"
                            placeholder="0.00"
                            value="<?= e($_POST['additional_charge'] ?? ($existingAdditionalChg > 0 ? (string) $existingAdditionalChg : (string) $returnPickupChargeDefault)) ?>"
                            oninput="updateSummary()"
                            class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-orange-500/50 transition-colors">
                        <?php if (isset($errors['additional_charge'])): ?>
                            <p class="text-red-400 text-xs mt-1"><?= e($errors['additional_charge']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Chellan (Traffic Fine)</label>
                        <input type="number" name="chellan_amount" id="chellanAmount" min="0" step="0.01"
                            placeholder="0.00"
                            value="<?= e($_POST['chellan_amount'] ?? ($r['chellan_amount'] ?? '0')) ?>"
                            oninput="updateSummary()"
                            class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-red-500/50 transition-colors">
                        <p class="text-xs text-mb-subtle mt-1">Traffic fines / challans issued during the rental period.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Discount</label>
                        <div class="flex gap-3">
                            <select name="discount_type" id="discountType" onchange="updateSummary()"
                                class="bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors">
                                <option value="">No Discount</option>
                                <option value="percent" <?= ($_POST['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>% Percentage</option>
                                <option value="amount" <?= ($_POST['discount_type'] ?? '') === 'amount' ? 'selected' : '' ?>>$ Fixed Amount</option>
                            </select>
                            <div id="returnDiscountValueWrap"
                                class="flex-1 <?= in_array(($_POST['discount_type'] ?? ''), ['percent', 'amount'], true) ? '' : 'hidden' ?>">
                                <input type="number" name="discount_value" id="discountValue" min="0" step="0.01"
                                    placeholder="0" value="<?= e($_POST['discount_value'] ?? '0') ?>"
                                    oninput="updateSummary()"
                                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors">
                            </div>
                        </div>
                    </div>

                    <div class="bg-emerald-500/5 border border-emerald-500/20 rounded-lg p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="block text-sm text-mb-silver">Apply Voucher on Return Charges</label>
                            <span class="text-xs text-emerald-300">Available:
                                $<?= number_format($clientVoucherBalance, 2) ?></span>
                        </div>
                        <input type="number" name="return_voucher_amount" id="returnVoucherAmount" min="0" step="0.01"
                            placeholder="0.00" value="<?= e($_POST['return_voucher_amount'] ?? '') ?>"
                            oninput="updateSummary()"
                            class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition-colors">
                        <p class="text-xs text-mb-subtle">This reduces return dues using voucher balance. If requested
                            amount is higher than allowed, it is auto-capped.</p>
                        <p id="returnVoucherAppliedHint" class="hidden text-emerald-300 text-xs"></p>
                        <p id="returnVoucherValidation" class="hidden text-red-400 text-xs"></p>
                        <?php if (isset($errors['return_voucher_amount'])): ?>
                            <p class="text-red-400 text-xs mt-1">
                                <?= e($errors['return_voucher_amount']) ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Return Payment Method</label>
                        <!-- Payment Source Type Toggle -->
                        <div class="flex bg-mb-black rounded-lg border border-mb-subtle/20 overflow-hidden mb-3 max-w-xs">
                            <label class="flex-1 text-center cursor-pointer">
                                <input type="radio" name="return_payment_source_type" value="single" class="hidden peer" <?= $returnPaymentSourceType === 'single' ? 'checked' : '' ?> onchange="toggleReturnPaymentSourceType()">
                                <span class="block py-2 text-xs font-medium peer-checked:bg-mb-accent peer-checked:text-white text-mb-subtle transition-colors">Single Source</span>
                            </label>
                            <label class="flex-1 text-center cursor-pointer">
                                <input type="radio" name="return_payment_source_type" value="multi" class="hidden peer" <?= $returnPaymentSourceType === 'multi' ? 'checked' : '' ?> onchange="toggleReturnPaymentSourceType()">
                                <span class="block py-2 text-xs font-medium peer-checked:bg-mb-accent peer-checked:text-white text-mb-subtle transition-colors">Multi Source</span>
                            </label>
                        </div>
                        <!-- Single Source -->
                        <div id="returnSingleSourcePanel">
                            <select name="return_payment_method" id="returnPaymentMethod" onchange="toggleReturnBankField()"
                                class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors">
                                <option value="cash" <?= $returnPaymentMethod === 'cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="account" <?= $returnPaymentMethod === 'account' ? 'selected' : '' ?>>Account</option>
                                <option value="credit" <?= $returnPaymentMethod === 'credit' ? 'selected' : '' ?>>Credit</option>
                            </select>
                            <div id="returnBankWrap" class="mt-2 <?= $returnPaymentMethod === 'account' ? '' : 'hidden' ?>">
                                <label class="block text-sm text-mb-silver mb-2">Bank Account</label>
                                <select name="return_bank_account_id" id="returnBankAccount"
                                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors">
                                    <option value="">Select account</option>
                                    <?php foreach ($activeBankAccounts as $acc): ?>
                                        <option value="<?= (int) $acc['id'] ?>" <?= (int) ($returnBankAccountId ?? 0) === (int) $acc['id'] ? 'selected' : '' ?>>
                                            <?= e($acc['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <!-- Multi Source -->
                        <div id="returnMultiSourcePanel" class="hidden space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-mb-silver text-sm flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>Cash</span>
                                <div class="relative w-44">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mb-subtle text-xs">$</span>
                                    <input type="number" name="return_multi_cash_amount" id="returnMultiCashAmount" step="0.01" min="0" placeholder="0.00"
                                        value="<?= e($_POST['return_multi_cash_amount'] ?? '') ?>"
                                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-7 pr-3 py-2 text-white focus:outline-none focus:border-green-500 text-sm"
                                        oninput="validateReturnMultiTotal()">
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-mb-silver text-sm flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400 inline-block"></span>Credit</span>
                                <div class="relative w-44">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mb-subtle text-xs">$</span>
                                    <input type="number" name="return_multi_credit_amount" id="returnMultiCreditAmount" step="0.01" min="0" placeholder="0.00"
                                        value="<?= e($_POST['return_multi_credit_amount'] ?? '') ?>"
                                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-7 pr-3 py-2 text-white focus:outline-none focus:border-amber-500 text-sm"
                                        oninput="validateReturnMultiTotal()">
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-mb-silver text-sm flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span>Bank</span>
                                <div class="relative w-44">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-mb-subtle text-xs">$</span>
                                    <input type="number" name="return_multi_bank_amount" id="returnMultiBankAmount" step="0.01" min="0" placeholder="0.00"
                                        value="<?= e($_POST['return_multi_bank_amount'] ?? '') ?>"
                                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-7 pr-3 py-2 text-white focus:outline-none focus:border-blue-500 text-sm"
                                        oninput="validateReturnMultiTotal()">
                                </div>
                            </div>
                            <div id="returnMultiBankSelectWrap" class="hidden" style="display:none">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-mb-silver text-xs">Select Bank</span>
                                    <div class="w-44">
                                        <select name="return_multi_bank_account_id" id="returnMultiBankAccountId"
                                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-blue-500 text-sm">
                                            <option value="">Select account</option>
                                            <?php foreach ($activeBankAccounts as $acc): ?>
                                                <option value="<?= (int) $acc['id'] ?>" <?= (int) ($_POST['return_multi_bank_account_id'] ?? 0) === (int) $acc['id'] ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div id="returnMultiTotalValidation" class="hidden text-xs pt-1 font-medium"></div>
                        </div>
                        <p class="text-xs text-mb-subtle mt-1">Applied only when there is an amount due at return.</p>
                        <?php if (isset($errors['return_payment_method'])): ?>
                            <p class="text-red-400 text-xs mt-1"><?= e($errors['return_payment_method']) ?></p>
                        <?php endif; ?>
                        <?php if (isset($errors['return_multi_total'])): ?>
                            <p class="text-red-400 text-xs mt-1"><?= e($errors['return_multi_total']) ?></p>
                        <?php endif; ?>
                        <?php if (isset($errors['return_multi_bank_account_id'])): ?>
                            <p class="text-red-400 text-xs mt-1"><?= e($errors['return_multi_bank_account_id']) ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ((float) ($r['deposit_amount'] ?? 0) > 0):
                        $depositAmount    = (float) ($r['deposit_amount'] ?? 0);
                        $alreadyReturned  = (float) ($r['deposit_returned'] ?? 0);
                        $alreadyDeducted  = (float) ($r['deposit_deducted'] ?? 0);
                        $alreadyHeld      = (float) ($r['deposit_held'] ?? 0);
                        // Graceful degradation: include extension usage if column exists
                        $depositUsedForExt = 0.0;
                        if (column_exists($pdo, 'reservations', 'deposit_used_for_extension')) {
                            $depositUsedForExt = max(0, (float) ($r['deposit_used_for_extension'] ?? 0));
                        }
                        $remainingDeposit = $depositAmount - $alreadyReturned - $alreadyDeducted - $alreadyHeld - $depositUsedForExt;

                        $_addChg  = isset($_POST['additional_charge'])
                            ? max(0, (float) $_POST['additional_charge'])
                            : $additionalChg;
                        $_dmgChg  = isset($_POST['damage_charge'])
                            ? max(0, (float) $_POST['damage_charge'])
                            : $damageChg;
                        $_chellan = isset($_POST['chellan_amount'])
                            ? max(0, (float) $_POST['chellan_amount'])
                            : $chellanAmt;
                        $_kmDriven = (isset($_POST['km_driven']) && $_POST['km_driven'] !== '')
                            ? (int) $_POST['km_driven']
                            : null;
                        $_kmOverage = 0;
                        if ($_kmDriven !== null && $r['km_limit'] && $r['extra_km_price'] && $_kmDriven > $r['km_limit']) {
                            $_kmOverage = ($_kmDriven - $r['km_limit']) * (float) $r['extra_km_price'];
                        }

                        $totalCharges  = $overdueAmt + $_kmOverage + $_dmgChg + $_addChg + $_chellan + $lateChg;
                        $maxDeductible = min($totalCharges, $remainingDeposit);
                        $totalCharges  = max(0, $totalCharges);
                        $maxDeductible = max(0, $maxDeductible);
                    ?>
                        <div class="bg-mb-black/50 border border-mb-subtle/20 rounded-xl p-6 space-y-4">
                            <div class="flex items-center justify-between">
                                <h3 class="text-white text-lg font-light border-l-2 border-mb-accent pl-3">Security Deposit</h3>
                                <span class="text-mb-subtle text-sm">Collected: <span class="text-white font-medium">$<?= number_format($depositAmount, 2) ?></span></span>
                            </div>
                            
                            <?php if ($alreadyReturned > 0 || $alreadyDeducted > 0 || $alreadyHeld > 0 || $depositUsedForExt > 0): ?>
                                <div class="bg-mb-surface rounded-lg p-3 text-xs space-y-1 border border-mb-subtle/20">
                                    <p class="text-mb-silver font-medium mb-2">Already processed:</p>
                                    <?php if ($depositUsedForExt > 0): ?>
                                        <p class="text-sky-400">Used for extensions: $<?= number_format($depositUsedForExt, 2) ?></p>
                                    <?php endif; ?>
                                    <?php if ($alreadyReturned > 0): ?>
                                        <p class="text-green-400">Returned: $<?= number_format($alreadyReturned, 2) ?></p>
                                    <?php endif; ?>
                                    <?php if ($alreadyDeducted > 0): ?>
                                        <p class="text-red-400">Deducted: $<?= number_format($alreadyDeducted, 2) ?></p>
                                    <?php endif; ?>
                                    <?php if ($alreadyHeld > 0): ?>
                                        <p class="text-yellow-400">Held: $<?= number_format($alreadyHeld, 2) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="bg-mb-surface rounded-xl p-4 space-y-4">
                                <p class="text-mb-subtle text-xs">Remaining deposit: <span class="text-white font-medium">$<span id="remainingDepositDisplay"><?= number_format($remainingDeposit, 2) ?></span></span></p>
                                
                                <!-- Amount to Deduct — placed first so it drives the Return field -->
                                <div>
                                    <label class="block text-sm text-mb-silver mb-2">
                                        Amount to Deduct from Deposit
                                        <span class="text-mb-subtle text-xs ml-2">(Becomes real income)</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                                        <input type="number" name="deposit_deducted" id="depositDeducted" step="0.01" min="0"
                                            max="<?= $maxDeductible ?>"
                                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                                            placeholder="0.00"
                                            value="<?= e($_POST['deposit_deducted'] ?? '0') ?>"
                                            oninput="updateDepositSummary('deducted')">
                                    </div>
                                    <p id="depositDeductHint" class="text-mb-subtle text-xs mt-1">Total charges: $<?= number_format($totalCharges, 2) ?>. Max deductible: $<?= number_format($maxDeductible, 2) ?></p>
                                </div>

                                <!-- Amount to Hold -->
                                <div>
                                    <label class="block text-sm text-mb-silver mb-2">
                                        Amount to Hold
                                        <span class="text-mb-subtle text-xs ml-2">(Not returned yet)</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                                        <input type="number" name="deposit_held" id="depositHeld" step="0.01" min="0"
                                            max="<?= $remainingDeposit ?>"
                                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                                            placeholder="0.00"
                                            value="<?= e($_POST['deposit_held'] ?? '0') ?>"
                                            oninput="updateDepositSummary('held')">
                                    </div>
                                    <?php if (isset($errors['deposit_hold_reason'])): ?>
                                        <p class="text-red-400 text-xs mt-1"><?= e($errors['deposit_hold_reason']) ?></p>
                                    <?php endif; ?>
                                </div>

                                <!-- Amount to Return — auto-calculated, but editable -->
                                <div>
                                    <label class="block text-sm text-mb-silver mb-2">Amount to Return to Client
                                        <span class="text-mb-subtle text-xs ml-2">(Auto-calculated)</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                                        <input type="number" name="deposit_returned" id="depositReturned" step="0.01" min="0"
                                            max="<?= $remainingDeposit ?>"
                                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                                            placeholder="0.00"
                                            value="<?= e($_POST['deposit_returned'] ?? $remainingDeposit) ?>"
                                            oninput="updateDepositSummary('returned')">
                                    </div>
                                    <?php if (isset($errors['deposit_returned'])): ?>
                                        <p class="text-red-400 text-xs mt-1"><?= e($errors['deposit_returned']) ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Hold Reason -->
                                <div id="holdReasonSection" class="<?= ((float)($_POST['deposit_held'] ?? 0) > 0 || isset($errors['deposit_hold_reason'])) ? '' : 'hidden' ?>">
                                    <label class="block text-sm text-mb-silver mb-2">Reason for Holding Deposit</label>
                                    <input type="text" name="deposit_hold_reason"
                                        placeholder="e.g., Pending damage assessment, Investigation ongoing"
                                        value="<?= e($_POST['deposit_hold_reason'] ?? '') ?>"
                                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                                </div>
                                
                                <!-- Summary -->
                                <div class="bg-mb-accent/10 border border-mb-accent/30 rounded-lg p-3">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-mb-silver">Total Deposit:</span>
                                        <span class="text-white font-medium">$<?= number_format($depositAmount, 2) ?></span>
                                    </div>
                                    <div class="flex justify-between text-sm mt-1">
                                        <span class="text-mb-silver">Converting to Income:</span>
                                        <span class="text-red-400">-$<span id="summaryDeduct">0.00</span></span>
                                    </div>
                                    <div class="flex justify-between text-sm mt-1">
                                        <span class="text-mb-silver">Holding:</span>
                                        <span class="text-yellow-400">-$<span id="summaryHold">0.00</span></span>
                                    </div>
                                    <div class="flex justify-between text-sm mt-1">
                                        <span class="text-mb-silver">Returning:</span>
                                        <span class="text-green-400">-$<span id="summaryReturn">0.00</span></span>
                                    </div>
                                    <!-- ═══ NEW: live unallocated / over-allocation warning ═══ -->
                                    <p id="depositSummaryWarning" class="hidden text-amber-400 text-xs mt-2 font-medium"></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Live Total Preview -->
                    <div class="bg-mb-black/50 border border-mb-subtle/20 rounded-lg p-4 space-y-2 text-sm">
                        <?php if ($voucherApplied > 0): ?>
                            <div class="flex justify-between text-green-400">
                                <span>Voucher Used on Booking</span>
                                <span>-$<?= number_format($voucherApplied, 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($advancePaid > 0): ?>
                            <div class="flex justify-between text-purple-300">
                                <span>Advance Collected</span>
                                <span>-$<?= number_format($advancePaid, 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($deliveryPrepaid > 0): ?>
                            <div class="flex justify-between text-blue-300">
                                <span>Delivery Charge Collected at Booking</span>
                                <span>+$<?= number_format($deliveryPrepaid, 2) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($extensionPaid > 0): ?>
                            <div class="flex justify-between text-sky-300">
                                <span>Extension Collected (Grace)</span>
                                <span>+$<?= number_format($extensionPaid, 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="flex justify-between text-mb-silver">
                            <span>Base Collected at Delivery</span>
                            <span>$<?= number_format($baseCollectedAtDelivery, 2) ?></span>
                        </div>
                        <div id="previewOverdueRow"
                            class="justify-between text-red-400 <?= $overdueAmt > 0 ? 'flex' : 'hidden' ?>">
                            <span id="previewOverdueLabel">Overdue (<?= $overdueDays ?>
                                day<?= $overdueDays === 1 ? '' : 's' ?>
                                @ $<?= number_format((float) $r['daily_rate'], 2) ?>/day)</span>
                            <span
                                id="previewOverdueAmt"><?= $overdueAmt > 0 ? '+$' . number_format($overdueAmt, 2) : '' ?></span>
                        </div>
                        <div id="previewLateRow" class="justify-between text-orange-400 hidden"><span
                                id="previewLateLabel">Late Return</span><span id="previewLateAmt"></span></div>
                        <div id="previewKmRow" class="justify-between text-yellow-400 hidden"><span
                                id="previewKmLabel">KM Overage</span><span id="previewKmAmt"></span></div>
                        <div id="previewDmgRow" class="justify-between text-orange-400 hidden"><span>Damage
                                Charges</span><span id="previewDmgAmt"></span></div>
                        <div id="previewAdditionalRow"
                            class="justify-between text-orange-300 <?= $additionalChg > 0 ? 'flex' : 'hidden' ?>">
                            <span>Return Pickup Charge</span>
                            <span
                                id="previewAdditionalAmt"><?= $additionalChg > 0 ? '+$' . number_format($additionalChg, 2) : '' ?></span>
                        </div>
                        <div id="previewChellanRow"
                            class="justify-between text-red-300 <?= $chellanAmt > 0 ? 'flex' : 'hidden' ?>">
                            <span>Chellan</span>
                            <span
                                id="previewChellanAmt"><?= $chellanAmt > 0 ? '+$' . number_format($chellanAmt, 2) : '' ?></span>
                        </div>
                        <div id="previewDiscRow" class="justify-between text-green-400 hidden"><span
                                id="previewDiscLabel">Discount</span><span id="previewDiscAmt"></span></div>
                        <div id="previewReturnVoucherRow" class="justify-between text-emerald-300 hidden">
                            <span id="previewReturnVoucherLabel">Voucher Applied on Return</span>
                            <span id="previewReturnVoucherAmt"></span>
                        </div>
                        <div id="previewEarlyCreditRow"
                            class="justify-between text-emerald-300 <?= $initialEarlyVoucherCredit > 0 ? 'flex' : 'hidden' ?>">
                            <span>Early Return Voucher Credit (next booking)</span>
                            <span
                                id="previewEarlyCreditAmt"><?= $initialEarlyVoucherCredit > 0 ? '+$' . number_format($initialEarlyVoucherCredit, 2) : '' ?></span>
                        </div>
                        <div
                            class="flex justify-between text-white font-semibold pt-2 border-t border-mb-subtle/10 text-base">
                            <span>Amount Due at Return</span>
                            <span id="grandTotalDisplay">$<?= number_format($overdueAmt + $additionalChg, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photos -->
            <div class="space-y-4">
                <h3 class="text-white text-lg font-light border-l-2 border-green-500 pl-3">Return Condition Photos</h3>
                <p class="text-xs text-mb-subtle">Upload clear photos to document return condition.</p>
                <?php
                $photoViews = [
                    'front' => 'Front',
                    'back' => 'Back',
                    'left' => 'Left',
                    'right' => 'Right',
                    'odometer' => 'Photo of Odometer',
                ];
                foreach ($photoViews as $areaKey => $areaLabel):
                    ?>
                    <div
                        class="bg-mb-black/30 p-4 rounded-lg border border-mb-subtle/10 hover:border-green-500/30 transition-colors">
                        <label class="block text-sm font-medium text-mb-silver mb-2"><?= $areaLabel ?> View</label>
                        <input type="file" name="photos[<?= $areaKey ?>]" accept="image/*" required class="block w-full text-sm text-mb-silver
                                   file:mr-4 file:py-2 file:px-4
                                   file:rounded-full file:border-0
                                   file:text-xs file:font-semibold
                                   file:bg-mb-surface file:text-green-500
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
                                       file:bg-mb-surface file:text-green-500
                                       hover:file:bg-mb-surface/80 cursor-pointer">
                        </div>
                    </div>
                    <button type="button" id="add-interior-btn"
                        class="mt-3 text-xs text-green-500 hover:text-white border border-green-500/30 hover:border-green-500/60 px-3 py-1.5 rounded-full transition-colors">
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

        <div class="flex items-center justify-end gap-4 pt-8 border-t border-mb-subtle/10">
            <a href="show.php?id=<?= $id ?>" class="text-mb-silver hover:text-white transition-colors">Cancel</a>
            <button type="button" id="openRatingModalBtn"
                class="bg-green-600 text-white px-8 py-3 rounded-full hover:bg-green-500 transition-colors font-medium shadow-lg shadow-green-900/20">
                Complete Return
            </button>
        </div>
    </form>

    <div id="ratingModal" class="hidden fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white text-lg font-light border-l-2 border-green-500 pl-3">Rate Client</h3>
                <p class="text-sm text-mb-subtle mt-2">Before completing return, rate this client for this rental.</p>
            </div>

            <div class="space-y-2">
                <?php for ($star = 5; $star >= 1; $star--): ?>
                    <label
                        class="flex items-center justify-between bg-mb-black/40 border border-mb-subtle/20 rounded-lg px-4 py-3 cursor-pointer hover:border-mb-accent transition-colors">
                        <div class="flex items-center gap-3">
                            <input type="radio" name="client_rating_modal" value="<?= $star ?>" class="accent-mb-accent">
                            <span class="text-white"><?= $star ?> Star<?= $star > 1 ? 's' : '' ?></span>
                        </div>
                        <span class="text-yellow-400 text-sm"><?= str_repeat('★', $star) ?></span>
                    </label>
                <?php endfor; ?>
            </div>

            <div class="space-y-2 mt-4">
                <label class="block text-xs font-medium text-mb-silver uppercase tracking-wider">Review / Observations (Optional)</label>
                <textarea id="modalRatingReview" rows="3" placeholder="Enter specific feedback about the client..."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent transition-colors resize-none placeholder-mb-subtle/50"></textarea>
            </div>

            <p id="ratingError" class="hidden text-red-400 text-sm">Select a client rating to continue.</p>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button"
                    class="closeRatingModalBtn text-mb-silver hover:text-white transition-colors px-4 py-2">Cancel</button>
                <button type="button" id="confirmRatingBtn"
                    class="bg-green-600 text-white px-6 py-2 rounded-full hover:bg-green-500 transition-colors font-medium">Save
                    Rating &amp; Complete</button>
            </div>
        </div>
    </div>
</div>
<?php
$kmLimit = (int) ($r['km_limit'] ?? 0);
$extraKmPrice = (float) ($r['extra_km_price'] ?? 0);
$baseRentalValue = (float) $r['total_price'];
$extraScripts = '<script>
const KM_LIMIT=' . $kmLimit . ', KM_PRICE=' . $extraKmPrice . ', DAILY_RATE=' . ((float) $r['daily_rate']) . ';
const LATE_RATE=' . $lateRatePerHour . ';
const LATE_TO_DAILY_THRESHOLD_MIN = 360;
const BASE_PRICE=' . $baseRentalValue . ';
const CLIENT_VOUCHER_BALANCE=' . $clientVoucherBalance . ';
const DEPOSIT_REMAINING=' . max(0, (float)($r['deposit_amount'] ?? 0) - (float)($r['deposit_returned'] ?? 0) - (float)($r['deposit_deducted'] ?? 0) - (float)($r['deposit_held'] ?? 0) - (column_exists($pdo, 'reservations', 'deposit_used_for_extension') ? max(0, (float)($r['deposit_used_for_extension'] ?? 0)) : 0)) . ';
const START_DATE = new Date("' . $r['start_date'] . '".replace(" ","T"));
const SCHEDULED_END = new Date("' . $r['end_date'] . '".replace(" ","T"));
const DELIVERY_MILEAGE = ' . ($delivery ? (int)$delivery['mileage'] : 0) . ';

function calculateKmDriven() {
    var returnMileage = parseInt(document.getElementById("mileage").value) || 0;
    var kmDrivenDisplay = document.getElementById("kmDrivenDisplay");
    var kmDrivenInput = document.getElementById("kmDriven");
    
    if (returnMileage > 0 && DELIVERY_MILEAGE > 0) {
        var kmDriven = returnMileage - DELIVERY_MILEAGE;
        if (kmDriven >= 0) {
            kmDrivenDisplay.textContent = "→ " + kmDriven.toLocaleString() + " km driven";
            // Auto-fill the KM Driven input if it exists
            if (kmDrivenInput) {
                kmDrivenInput.value = kmDriven;
                updateSummary(); // Trigger summary update for overage calculation
            }
        } else {
            kmDrivenDisplay.textContent = "⚠ Return mileage is less than delivery mileage";
            kmDrivenDisplay.classList.add("text-red-400");
            kmDrivenDisplay.classList.remove("text-green-400");
        }
    } else {
        kmDrivenDisplay.textContent = "";
    }
}

function getActualDate() {
    var d = document.getElementById("retDate").value;
    var h = parseInt(document.getElementById("retHour").value);
    var m = parseInt(document.getElementById("retMin").value);
    var ampm = document.getElementById("retAMPM").value;
    
    if (ampm === "PM" && h < 12) h += 12;
    if (ampm === "AM" && h === 12) h = 0;
    
    return new Date(d + "T" + String(h).padStart(2,"0") + ":" + String(m).padStart(2,"0") + ":00");
}

function calcLateCharge() {
    var actual = getActualDate();
    if (actual <= SCHEDULED_END) return 0;
    var lateMinutes = Math.round((actual - SCHEDULED_END) / 60000);
    if (lateMinutes < 30) return 0; // grace period
    if (lateMinutes >= LATE_TO_DAILY_THRESHOLD_MIN) return DAILY_RATE;
    if (LATE_RATE <= 0) return 0;
    return lateMinutes * (LATE_RATE / 60);
}

function calcOverdueCharge() {
    var actual = getActualDate();
    var scheduledDate = new Date(SCHEDULED_END.getFullYear(), SCHEDULED_END.getMonth(), SCHEDULED_END.getDate());
    var actualDate = new Date(actual.getFullYear(), actual.getMonth(), actual.getDate());
    if (actualDate <= scheduledDate) return { days: 0, amount: 0 };
    var dayMs = 24 * 60 * 60 * 1000;
    var days = Math.round((actualDate - scheduledDate) / dayMs);
    if (days < 0) days = 0;
    return { days: days, amount: days * DAILY_RATE };
}

function calcEarlyReturnCredit() {
    var actual = getActualDate();
    if (actual >= SCHEDULED_END || BASE_PRICE <= 0) return 0;
    var effectiveActual = actual < START_DATE ? START_DATE : actual;
    var totalMs = SCHEDULED_END - START_DATE;
    if (totalMs <= 0) return 0;
    var unusedMs = SCHEDULED_END - effectiveActual;
    if (unusedMs <= 0) return 0;
    return BASE_PRICE * (unusedMs / totalMs);
}

function getReturnDueAmount() {
    var el = document.getElementById("grandTotalDisplay");
    if (!el) return 0;
    var raw = String(el.textContent || "0").replace(/[^0-9.-]/g, "");
    return parseFloat(raw) || 0;
}

function toggleReturnPaymentSourceType() {
    var sourceType = document.querySelector(\'input[name="return_payment_source_type"]:checked\');
    sourceType = sourceType ? sourceType.value : \'single\';
    var singlePanel = document.getElementById(\'returnSingleSourcePanel\');
    var multiPanel = document.getElementById(\'returnMultiSourcePanel\');
    if (singlePanel) singlePanel.style.display = sourceType === \'single\' ? \'block\' : \'none\';
    if (multiPanel) multiPanel.classList.toggle(\'hidden\', sourceType !== \'multi\');
    if (sourceType === \'multi\') validateReturnMultiTotal();
}

function validateReturnMultiTotal() {
    var cashEl = document.getElementById(\'returnMultiCashAmount\');
    var creditEl = document.getElementById(\'returnMultiCreditAmount\');
    var bankEl = document.getElementById(\'returnMultiBankAmount\');
    var bankSelectWrap = document.getElementById(\'returnMultiBankSelectWrap\');
    var validationEl = document.getElementById(\'returnMultiTotalValidation\');
    if (!cashEl || !creditEl || !bankEl || !validationEl) return;

    var cashAmt = parseFloat(cashEl.value || \'0\') || 0;
    var creditAmt = parseFloat(creditEl.value || \'0\') || 0;
    var bankAmt = parseFloat(bankEl.value || \'0\') || 0;
    var total = Math.round((cashAmt + creditAmt + bankAmt) * 100) / 100;
    var grandEl = document.getElementById(\'grandTotalDisplay\');
    var required = 0;
    if (grandEl) {
        var raw = String(grandEl.textContent || \'0\').replace(/[^0-9.-]/g, \'\');
        required = parseFloat(raw) || 0;
    }

    if (bankSelectWrap) {
        bankSelectWrap.style.display = bankAmt > 0 ? \'flex\' : \'none\';
    }

    validationEl.classList.remove(\'hidden\');
    var diff = Math.round((required - total) * 100) / 100;
    if (Math.abs(diff) < 0.01) {
        validationEl.className = \'text-xs pt-1 font-medium text-green-400\';
        validationEl.textContent = \'Total matches: $\' + total.toFixed(2);
    } else if (diff > 0) {
        validationEl.className = \'text-xs pt-1 font-medium text-amber-400\';
        validationEl.textContent = \'Remaining: $\' + diff.toFixed(2) + \' (Need: $\' + required.toFixed(2) + \')\';
    } else {
        validationEl.className = \'text-xs pt-1 font-medium text-red-400\';
        validationEl.textContent = \'Over by: $\' + Math.abs(diff).toFixed(2) + \' (Max: $\' + required.toFixed(2) + \')\';
    }
}

function toggleReturnBankField() {
    var modeEl = document.getElementById("returnPaymentMethod");
    var wrapEl = document.getElementById("returnBankWrap");
    var bankEl = document.getElementById("returnBankAccount");
    if (!modeEl || !wrapEl || !bankEl) return;

    var needsBank = modeEl.value === "account" && getReturnDueAmount() > 0;
    wrapEl.classList.toggle("hidden", !needsBank);
    if (needsBank) {
        bankEl.setAttribute("required", "required");
    } else {
        bankEl.removeAttribute("required");
        bankEl.value = "";
    }
}

function toggleReturnDiscountValueField() {
    var typeEl = document.getElementById("discountType");
    var valueWrapEl = document.getElementById("returnDiscountValueWrap");
    if (!typeEl || !valueWrapEl) return;
    var hasDiscount = typeEl.value === "percent" || typeEl.value === "amount";
    valueWrapEl.classList.toggle("hidden", !hasDiscount);
}

// ═══════════════════════════════════════════════════════════════════════════════
// FIXED: updateDepositSummary — bidirectional sync with auto-calculation
//
// Rules:
//   • Changing Deducted or Held  → Return is auto-set to (DEPOSIT_REMAINING - Deducted - Held)
//   • Changing Return manually   → Return is silently clamped; warning shown if gap remains
//   • Initial load (no arg)      → Return pre-filled to full remaining balance
// ═══════════════════════════════════════════════════════════════════════════════
function updateDepositSummary(changedField) {
    var depositReturnedEl = document.getElementById(\'depositReturned\');
    var depositDeductedEl = document.getElementById(\'depositDeducted\');
    var depositHeldEl     = document.getElementById(\'depositHeld\');
    var holdReasonSection = document.getElementById(\'holdReasonSection\');
    var depositWarningEl  = document.getElementById(\'depositSummaryWarning\');

    if (!depositReturnedEl || !depositDeductedEl || !depositHeldEl) return;

    // Parse and clamp negatives
    var deductAmt = Math.max(0, parseFloat(depositDeductedEl.value) || 0);
    var holdAmt   = Math.max(0, parseFloat(depositHeldEl.value)     || 0);
    var returnAmt = Math.max(0, parseFloat(depositReturnedEl.value) || 0);

    // Prevent any single field exceeding the total remaining deposit
    if (deductAmt > DEPOSIT_REMAINING) {
        deductAmt = DEPOSIT_REMAINING;
        depositDeductedEl.value = deductAmt.toFixed(2);
    }
    if (holdAmt > DEPOSIT_REMAINING) {
        holdAmt = DEPOSIT_REMAINING;
        depositHeldEl.value = holdAmt.toFixed(2);
    }

    if (changedField === \'deducted\' || changedField === \'held\') {
        // Auto-recalculate: Return = Remaining - Deducted - Held
        returnAmt = Math.max(0, DEPOSIT_REMAINING - deductAmt - holdAmt);
        depositReturnedEl.value = returnAmt.toFixed(2);
    } else if (changedField === \'returned\') {
        // Manual edit on Return: clamp to maximum allowed value
        var maxReturn = Math.max(0, DEPOSIT_REMAINING - deductAmt - holdAmt);
        if (returnAmt > maxReturn) {
            returnAmt = maxReturn;
            depositReturnedEl.value = returnAmt.toFixed(2);
        }
    } else {
        // Initial page load: pre-fill Return with full remaining balance
        returnAmt = Math.max(0, DEPOSIT_REMAINING - deductAmt - holdAmt);
        depositReturnedEl.value = returnAmt.toFixed(2);
    }

    // Calculate unallocated balance for warning display
    var totalAllocated = Math.round((returnAmt + deductAmt + holdAmt) * 100) / 100;
    var diff = Math.round((DEPOSIT_REMAINING - totalAllocated) * 100) / 100;

    // Update live summary labels
    var remainingDisplay = document.getElementById(\'remainingDepositDisplay\');
    var summaryReturn    = document.getElementById(\'summaryReturn\');
    var summaryDeduct    = document.getElementById(\'summaryDeduct\');
    var summaryHold      = document.getElementById(\'summaryHold\');

    if (remainingDisplay) remainingDisplay.textContent = DEPOSIT_REMAINING.toFixed(2);
    if (summaryDeduct)    summaryDeduct.textContent    = deductAmt.toFixed(2);
    if (summaryHold)      summaryHold.textContent      = holdAmt.toFixed(2);
    if (summaryReturn)    summaryReturn.textContent    = returnAmt.toFixed(2);

    // Show/hide unallocated balance warning
    if (depositWarningEl) {
        if (Math.abs(diff) > 0.01) {
            depositWarningEl.textContent = diff > 0
                ? \'⚠ $\' + diff.toFixed(2) + \' unallocated — will remain with deposit\'
                : \'⚠ Over by $\' + Math.abs(diff).toFixed(2) + \' — reduce deduct, hold, or return amount\';
            depositWarningEl.className = diff > 0
                ? \'text-amber-400 text-xs mt-2 font-medium\'
                : \'text-red-400 text-xs mt-2 font-medium\';
        } else {
            depositWarningEl.className = \'hidden\';
            depositWarningEl.textContent = \'\';
        }
    }

    // Show/hide hold reason field
    if (holdReasonSection) {
        holdReasonSection.classList.toggle(\'hidden\', holdAmt <= 0);
    }
    if (typeof updateSummary === \'function\') updateSummary();
}

function _updateDepositMaxDeductible(totalChargesLive) {
    var depositDeductedEl = document.getElementById(\'depositDeducted\');
    if (!depositDeductedEl) return;

    var maxDeductible = Math.min(totalChargesLive, DEPOSIT_REMAINING);
    if (maxDeductible < 0) maxDeductible = 0;

    depositDeductedEl.max = maxDeductible.toFixed(2);

    var hintEl = document.getElementById(\'depositDeductHint\');
    if (hintEl) {
        hintEl.textContent = \'Total charges: $\' + totalChargesLive.toFixed(2)
            + \'. Max deductible: $\' + maxDeductible.toFixed(2);
    }
}

function updateSummary(){
    var kmDrivenEl=document.getElementById("kmDriven");
    var dmgHiddenEl=document.getElementById("damageCharge");
    var additionalChgEl=document.getElementById("additionalCharge");
    var chellanEl=document.getElementById("chellanAmount");
    var discountTypeEl=document.getElementById("discountType");
    var discountValueEl=document.getElementById("discountValue");
    var discType=discountTypeEl?discountTypeEl.value:"";
    toggleReturnDiscountValueField();
    var discValRaw=parseFloat(discountValueEl?discountValueEl.value:"0")||0;
    var discVal=(discType==="percent"||discType==="amount")?discValRaw:0;
    var kmDriven=kmDrivenEl?parseFloat(kmDrivenEl.value)||0:0;
    var additionalChg=additionalChgEl?parseFloat(additionalChgEl.value)||0:0;
    if(additionalChg<0) additionalChg=0;
    var chellanChg=chellanEl?parseFloat(chellanEl.value)||0:0;
    if(chellanChg<0) chellanChg=0;
    var returnVoucherInput=document.getElementById("returnVoucherAmount");

    // Damage from checkboxes or manual input
    var dmg = 0;
    var checkboxes = document.querySelectorAll(".damage-checkbox");
    if (checkboxes.length > 0) {
        checkboxes.forEach(function(cb) {
            if (cb.checked) dmg += parseFloat(cb.getAttribute("data-cost")) || 0;
        });
        if(dmgHiddenEl) dmgHiddenEl.value = dmg;
    } else {
        dmg = parseFloat(dmgHiddenEl ? dmgHiddenEl.value : 0) || 0;
    }

    var kmOverage=0;
    if(KM_LIMIT>0 && kmDriven>KM_LIMIT){ kmOverage=(kmDriven-KM_LIMIT)*KM_PRICE; }

    var lateChg = calcLateCharge();
    var overdue = calcOverdueCharge();
    var overdueChg = overdue.amount;
    var earlyCredit = calcEarlyReturnCredit();

    var totalBeforeDiscount=overdueChg+kmOverage+dmg+additionalChg+chellanChg+lateChg;
    var disc=0;
    if(discType==="percent") disc=Math.round(totalBeforeDiscount*Math.min(discVal,100)/100*100)/100;
    else if(discType==="amount") disc=Math.min(discVal,totalBeforeDiscount);
    var totalAfterDiscount=Math.max(0,totalBeforeDiscount-disc);

    var returnVoucherReq=returnVoucherInput?(parseFloat(returnVoucherInput.value)||0):0;
    if(returnVoucherReq<0) returnVoucherReq=0;
    var returnVoucherMax=Math.max(0,Math.min(CLIENT_VOUCHER_BALANCE,totalAfterDiscount));
    var returnVoucherApplied=Math.min(returnVoucherReq,returnVoucherMax);
    var voucherAutoCapped = returnVoucherReq > returnVoucherApplied;
    var voucherInfoEl=document.getElementById("returnVoucherAppliedHint");
    if(returnVoucherInput){
        returnVoucherInput.max=returnVoucherMax.toFixed(2);
        returnVoucherInput.setCustomValidity("");
    }
    if(voucherInfoEl){
        if(returnVoucherReq>0){
            var voucherInfoText = "Requested: $" + returnVoucherReq.toFixed(2) + " | Applied: $" + returnVoucherApplied.toFixed(2);
            if(voucherAutoCapped){
                voucherInfoText += " (auto-capped)";
            }
            voucherInfoEl.textContent=voucherInfoText;
            voucherInfoEl.classList.remove("hidden");
        }else{
            voucherInfoEl.textContent="";
            voucherInfoEl.classList.add("hidden");
        }
    }
    var depositDeductedInput = document.getElementById(\'depositDeducted\');
    var depositDeductedAmt = depositDeductedInput ? (parseFloat(depositDeductedInput.value) || 0) : 0;
    var total=Math.max(0,totalAfterDiscount - returnVoucherApplied - depositDeductedAmt);

    // Overdue row
    var overdueRow=document.getElementById("previewOverdueRow");
    if(overdueRow){
        if(overdueChg>0){
            overdueRow.classList.remove("hidden"); overdueRow.classList.add("flex");
            document.getElementById("previewOverdueLabel").textContent="Overdue ("+overdue.days+" day"+(overdue.days===1?"":"s")+" @ $"+DAILY_RATE.toFixed(2)+"/day)";
            document.getElementById("previewOverdueAmt").textContent="+$"+overdueChg.toFixed(2);
        }else{
            overdueRow.classList.add("hidden"); overdueRow.classList.remove("flex");
        }
    }

    // Late charge row
    var lateRow=document.getElementById("previewLateRow");
    if(lateRow){
        if(lateChg>0){
            lateRow.classList.remove("hidden"); lateRow.classList.add("flex");
            var actualDt=getActualDate();
            var lateMin=Math.round((actualDt-SCHEDULED_END)/60000);
            if(lateMin >= LATE_TO_DAILY_THRESHOLD_MIN){
                document.getElementById("previewLateLabel").textContent="Late Return (>= 6h, Daily Rate Applied)";
                document.getElementById("previewLateAmt").textContent="+$"+DAILY_RATE.toFixed(2);
            }else{
                var hrs = Math.floor(lateMin/60);
                var mins = lateMin%60;
                var timeStr = hrs > 0 ? hrs + "h " + mins + "m" : mins + "m";
                document.getElementById("previewLateLabel").textContent="Late Return ("+timeStr+" @ $"+LATE_RATE.toFixed(2)+"/hr)";
                document.getElementById("previewLateAmt").textContent="+$"+lateChg.toFixed(2);
            }
        }else{
            lateRow.classList.add("hidden"); lateRow.classList.remove("flex");
            var actualDt = getActualDate();
            var lateMin  = Math.round((actualDt - SCHEDULED_END)/60000);
            // Grace period hint
            var hint = document.getElementById("lateGraceHint");
            if(hint){ if(actualDt > SCHEDULED_END && lateMin < 30){ hint.classList.remove("hidden"); } else { hint.classList.add("hidden"); } }
        }
    }
    // KM row
    var kmRow=document.getElementById("previewKmRow");
    if(kmRow){if(kmOverage>0){kmRow.classList.remove("hidden");kmRow.classList.add("flex");document.getElementById("previewKmAmt").textContent="+$"+kmOverage.toFixed(2);}else{kmRow.classList.add("hidden");kmRow.classList.remove("flex");}}
    var kmMsg=document.getElementById("kmOverageMsg");
    if(kmMsg){if(kmOverage>0){kmMsg.textContent="⚠ Overage: "+(kmDriven-KM_LIMIT)+" km × $"+KM_PRICE.toFixed(2)+" = $"+kmOverage.toFixed(2);kmMsg.classList.remove("hidden");}else{kmMsg.classList.add("hidden");}}
    // Damage row
    var dmgRow=document.getElementById("previewDmgRow");
    if(dmgRow){if(dmg>0){dmgRow.classList.remove("hidden");dmgRow.classList.add("flex");document.getElementById("previewDmgAmt").textContent="+$"+dmg.toFixed(2);}else{dmgRow.classList.add("hidden");dmgRow.classList.remove("flex");}}
    // Additional charge row
    var additionalRow=document.getElementById("previewAdditionalRow");
    if(additionalRow){
        if(additionalChg>0){
            additionalRow.classList.remove("hidden");
            additionalRow.classList.add("flex");
            document.getElementById("previewAdditionalAmt").textContent="+$"+additionalChg.toFixed(2);
        }else{
            additionalRow.classList.add("hidden");
            additionalRow.classList.remove("flex");
            document.getElementById("previewAdditionalAmt").textContent="";
        }
    }
    // Chellan row
    var chellanRow=document.getElementById("previewChellanRow");
    if(chellanRow){
        if(chellanChg>0){
            chellanRow.classList.remove("hidden");
            chellanRow.classList.add("flex");
            document.getElementById("previewChellanAmt").textContent="+$"+chellanChg.toFixed(2);
        }else{
            chellanRow.classList.add("hidden");
            chellanRow.classList.remove("flex");
            document.getElementById("previewChellanAmt").textContent="";
        }
    }
    // Discount row
    var discRow=document.getElementById("previewDiscRow");
    if(discRow){if(disc>0){discRow.classList.remove("hidden");discRow.classList.add("flex");document.getElementById("previewDiscLabel").textContent=discType==="percent"?"Discount ("+discVal+"%):":"Discount:";document.getElementById("previewDiscAmt").textContent="-$"+disc.toFixed(2);}else{discRow.classList.add("hidden");discRow.classList.remove("flex");}}
    // Return voucher row
    var returnVoucherRow=document.getElementById("previewReturnVoucherRow");
    var returnVoucherLabel=document.getElementById("previewReturnVoucherLabel");
    if(returnVoucherRow){
        if(returnVoucherApplied>0){
            returnVoucherRow.classList.remove("hidden");
            returnVoucherRow.classList.add("flex");
            if(returnVoucherLabel){
                returnVoucherLabel.textContent = returnVoucherReq > 0
                    ? "Voucher Applied on Return (Requested $" + returnVoucherReq.toFixed(2) + ")"
                    : "Voucher Applied on Return";
            }
            document.getElementById("previewReturnVoucherAmt").textContent="-$"+returnVoucherApplied.toFixed(2);
        }else{
            returnVoucherRow.classList.add("hidden");
            returnVoucherRow.classList.remove("flex");
            if(returnVoucherLabel){
                returnVoucherLabel.textContent="Voucher Applied on Return";
            }
            document.getElementById("previewReturnVoucherAmt").textContent="";
        }
    }
    var earlyCreditRow=document.getElementById("previewEarlyCreditRow");
    if(earlyCreditRow){
        if(earlyCredit>0){
            earlyCreditRow.classList.remove("hidden");
            earlyCreditRow.classList.add("flex");
            document.getElementById("previewEarlyCreditAmt").textContent="+$"+earlyCredit.toFixed(2);
        }else{
            earlyCreditRow.classList.add("hidden");
            earlyCreditRow.classList.remove("flex");
            document.getElementById("previewEarlyCreditAmt").textContent="";
        }
    }
    document.getElementById("grandTotalDisplay").textContent="$"+total.toFixed(2);
    toggleReturnBankField();
    var sourceTypeEl = document.querySelector("input[name=\"return_payment_source_type\"]:checked");
    if (sourceTypeEl && sourceTypeEl.value === "multi") {
        validateReturnMultiTotal();
    }
    // Keep deposit max-deductible hint in sync with live charge totals
    _updateDepositMaxDeductible(totalBeforeDiscount);
}
const slider=document.getElementById("fuelSlider");
const valEl=document.getElementById("fuel-val");
const barEl=document.getElementById("fuelBar");
function updateFuel(v){valEl.textContent=v+"%";barEl.style.width=v+"%";barEl.className="h-2 rounded-full "+(v>=75?"bg-green-500":v>=50?"bg-yellow-400":v>=25?"bg-orange-400":"bg-red-500");}
slider.addEventListener("input",()=>updateFuel(slider.value));
updateFuel(slider.value);

const returnVoucherInput=document.getElementById("returnVoucherAmount");
if(returnVoucherInput){
    returnVoucherInput.addEventListener("blur", function(){
        var val=parseFloat(returnVoucherInput.value || 0);
        if(!isFinite(val) || val <= 0){
            returnVoucherInput.value="";
        }else{
            returnVoucherInput.value=val.toFixed(2);
        }
        updateSummary();
    });
}
const returnPaymentMethodEl=document.getElementById("returnPaymentMethod");
if(returnPaymentMethodEl){
    returnPaymentMethodEl.addEventListener("change", toggleReturnBankField);
}
toggleReturnPaymentSourceType();

const returnForm=document.getElementById("returnForm");
const ratingModal=document.getElementById("ratingModal");
const openRatingModalBtn=document.getElementById("openRatingModalBtn");
const closeRatingModalBtns=document.querySelectorAll(".closeRatingModalBtn");
const confirmRatingBtn=document.getElementById("confirmRatingBtn");
const ratingError=document.getElementById("ratingError");
const clientRatingInput=document.getElementById("clientRatingInput");

function openRatingModal(){
    if(!ratingModal) return;
    ratingModal.classList.remove("hidden");
    if(ratingError) ratingError.classList.add("hidden");
}
function closeRatingModal(){
    if(!ratingModal) return;
    ratingModal.classList.add("hidden");
    if(ratingError) ratingError.classList.add("hidden");
}

if(returnForm){
    returnForm.addEventListener("submit", function(e){
        updateSummary();
        if(!returnForm.checkValidity()){
            e.preventDefault();
            returnForm.reportValidity();
            return;
        }
        if(!clientRatingInput || !clientRatingInput.value){
            e.preventDefault();
            openRatingModal();
        }
    });
}
if(openRatingModalBtn){
    openRatingModalBtn.addEventListener("click", function(){
        openRatingModal();
    });
}
closeRatingModalBtns.forEach(function(btn){
    btn.addEventListener("click", closeRatingModal);
});
if(ratingModal){
    ratingModal.addEventListener("click", function(e){
        if(e.target===ratingModal) closeRatingModal();
    });
}

var presetRating = clientRatingInput ? clientRatingInput.value : "";
if(presetRating){
    var presetRadio = document.querySelector("input[name=\\"client_rating_modal\\"][value=\\""+presetRating+"\\"]");
    if(presetRadio) presetRadio.checked = true;
}
if(confirmRatingBtn){
    confirmRatingBtn.addEventListener("click", function(){
        var selected = document.querySelector("input[name=\\"client_rating_modal\\"]:checked");
        if(!selected){
            if(ratingError) ratingError.classList.remove("hidden");
            return;
        }
        if(clientRatingInput) clientRatingInput.value = selected.value;
        const reviewVal = document.getElementById("modalRatingReview") ? document.getElementById("modalRatingReview").value : "";
        if(document.getElementById("clientRatingReviewInput")) document.getElementById("clientRatingReviewInput").value = reviewVal;
        closeRatingModal();
        if(returnForm){
            if(returnForm.requestSubmit){
                returnForm.requestSubmit();
            }else{
                returnForm.submit();
            }
        }
    });
}

updateSummary();
updateDepositSummary();

// Interior photo slots
(function() {
    const container = document.getElementById("interior-slots-container");
    const addBtn = document.getElementById("add-interior-btn");
    const MAX = 15;

    function updateAddBtn() {
        const count = container.querySelectorAll(".interior-slot").length;
        addBtn.disabled = count >= MAX;
        addBtn.classList.toggle("opacity-40", count >= MAX);
        addBtn.classList.toggle("cursor-not-allowed", count >= MAX);
    }

    function makeRemoveBtn(slot) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = "✕";
        btn.className = "text-red-400 hover:text-red-300 text-xs px-2 py-1 rounded transition-colors flex-shrink-0";
        btn.onclick = function() {
            slot.remove();
            reindex();
            updateAddBtn();
        };
        return btn;
    }

    function reindex() {
        container.querySelectorAll(".interior-slot").forEach(function(slot, i) {
            const n = i + 1;
            slot.dataset.slot = n;
            const input = slot.querySelector("input[type=file]");
            if (input) input.name = "photos[interior_" + n + "]";
        });
    }

    addBtn.addEventListener("click", function() {
        const count = container.querySelectorAll(".interior-slot").length;
        if (count >= MAX) return;
        const n = count + 1;
        const slot = document.createElement("div");
        slot.className = "interior-slot flex items-center gap-2";
        slot.dataset.slot = n;
        const input = document.createElement("input");
        input.type = "file";
        input.name = "photos[interior_" + n + "]";
        input.accept = "image/*";
        input.className = "block flex-1 text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-green-500 hover:file:bg-mb-surface/80 cursor-pointer";
        slot.appendChild(input);
        slot.appendChild(makeRemoveBtn(slot));
        container.appendChild(slot);
        updateAddBtn();
    });

    updateAddBtn();
})();

(function() {
    var container = document.getElementById("scratch-slots-container");
    var addBtn    = document.getElementById("add-scratch-btn");
    if (!container || !addBtn) return;
    var MAX = 15;

    function updateAddBtn() {
        var count = container.querySelectorAll(".scratch-slot").length;
        addBtn.disabled = count >= MAX;
        addBtn.style.opacity = count >= MAX ? "0.4" : "1";
    }

    function reindex() {
        container.querySelectorAll(".scratch-slot").forEach(function(slot, i) {
            var n = i + 1;
            slot.dataset.slot = n;
            var input = slot.querySelector("input[type=file]");
            if (input) input.name = "scratch_photos[" + n + "]";
        });
    }

    addBtn.addEventListener("click", function() {
        var count = container.querySelectorAll(".scratch-slot").length;
        if (count >= MAX) return;
        var n = count + 1;
        var slot = document.createElement("div");
        slot.className = "scratch-slot flex items-center gap-2";
        slot.dataset.slot = n;
        var input = document.createElement("input");
        input.type = "file";
        input.name = "scratch_photos[" + n + "]";
        input.accept = "image/*";
        input.className = "block flex-1 text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-orange-400 hover:file:bg-mb-surface/80 cursor-pointer";
        var removeBtn = document.createElement("button");
        removeBtn.type = "button";
        removeBtn.textContent = "Remove";
        removeBtn.className = "text-xs text-red-400 hover:text-red-300 border border-red-500/30 px-2 py-1 rounded-full transition-colors";
        removeBtn.addEventListener("click", function() {
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

// Initialize KM driven calculation on page load
if (document.getElementById("mileage")) {
    calculateKmDriven();
}
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>