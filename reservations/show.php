<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/reservation_payment_helpers.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

// ── Runtime schema guard for new held-deposit resolution columns ──────────────
try {
    $pdo->exec("ALTER TABLE reservations
        ADD COLUMN IF NOT EXISTS deposit_held_resolved_at DATETIME    DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS deposit_held_action      VARCHAR(20) DEFAULT NULL");
} catch (Throwable $e) { /* already exist */ }

// ── Runtime schema guard for booking discount ─────────────────────────────────
try {
    $pdo->exec("ALTER TABLE reservations
        ADD COLUMN IF NOT EXISTS booking_discount_type  VARCHAR(10)   NULL,
        ADD COLUMN IF NOT EXISTS booking_discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00");
} catch (Throwable $e) { /* already exist */ }

// ── POST: save booking discount ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_booking_discount') {
    $discountReservationId = (int) ($_POST['reservation_id'] ?? 0);
    if ($discountReservationId !== $id) {
        flash('error', 'Invalid request.');
        redirect("show.php?id=$id");
    }
    // Fetch current reservation to validate status
    $chkStmt = $pdo->prepare('SELECT status, total_price, advance_paid, voucher_applied FROM reservations WHERE id=?');
    $chkStmt->execute([$id]);
    $chkR = $chkStmt->fetch();
    if (!$chkR || !in_array($chkR['status'], ['pending', 'confirmed'])) {
        flash('error', 'Booking discount can only be set on pending or confirmed reservations.');
        redirect("show.php?id=$id");
    }
    $bdType  = in_array($_POST['booking_discount_type'] ?? '', ['percent', 'amount']) ? $_POST['booking_discount_type'] : null;
    $bdValue = max(0, (float) ($_POST['booking_discount_value'] ?? 0));
    $totalPrice = (float) $chkR['total_price'];
    // Compute discount amount for validation
    $bdAmt = 0;
    if ($bdType === 'percent') {
        $bdAmt = round($totalPrice * min($bdValue, 100) / 100, 2);
    } elseif ($bdType === 'amount') {
        $bdAmt = min($bdValue, $totalPrice);
        $bdValue = $bdAmt; // cap stored value too
    }
    // Warn if advance already paid exceeds discounted total
    $discountedBase = max(0, $totalPrice - $bdAmt);
    $advancePaidChk = (float) ($chkR['advance_paid'] ?? 0);
    $voucherAppliedChk = (float) ($chkR['voucher_applied'] ?? 0);
    if ($advancePaidChk + $voucherAppliedChk > $discountedBase) {
        flash('error', 'Discount cannot be applied: advance + voucher already collected ($' . number_format($advancePaidChk + $voucherAppliedChk, 2) . ') exceeds the discounted total ($' . number_format($discountedBase, 2) . ').');
        redirect("show.php?id=$id");
    }
    if ($bdType === null) {
        $bdValue = 0;
    }
    $pdo->prepare('UPDATE reservations SET booking_discount_type=?, booking_discount_value=? WHERE id=?')
        ->execute([$bdType, $bdValue, $id]);
    flash('success', $bdType ? 'Booking discount saved.' : 'Booking discount removed.');
    redirect("show.php?id=$id");
}

$rStmt = $pdo->prepare('SELECT r.*, c.name AS client_name, c.id AS cid, v.brand, v.model, v.license_plate, v.daily_rate, v.image_url FROM reservations r JOIN clients c ON r.client_id=c.id JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();
if (!$r) {
    flash('error', 'Reservation not found.');
    redirect('index.php');
}

$iStmt = $pdo->prepare('SELECT * FROM vehicle_inspections WHERE reservation_id=? ORDER BY created_at');
$iStmt->execute([$id]);
$inspections = $iStmt->fetchAll();
$delivery = null;
$return = null;
foreach ($inspections as &$ins) {
    if ($ins['type'] === 'delivery')
        $delivery = &$ins;
    if ($ins['type'] === 'return')
        $return = &$ins;

    // Fetch photos for this inspection
    $pStmt = $pdo->prepare('SELECT * FROM inspection_photos WHERE inspection_id=?');
    $pStmt->execute([$ins['id']]);
    $ins['photos'] = $pStmt->fetchAll();
}
unset($ins);

$days = durationDays($r['start_date'], $r['end_date']);
$overdue = isOverdue($r['end_date'], $r['status']);

// Totals calculation (match bill.php)
$basePrice = (float) $r['total_price'];
$extensionPaid = max(0, (float) ($r['extension_paid_amount'] ?? 0));
$basePriceForDelivery = max(0, $basePrice - $extensionPaid);
$advancePaid = max(0, (float) ($r['advance_paid'] ?? 0));
// Booking discount (applied to base price before voucher/advance)
$bookingDiscType  = $r['booking_discount_type'] ?? null;
$bookingDiscValue = (float) ($r['booking_discount_value'] ?? 0);
$bookingDiscAmt   = 0;
if ($bookingDiscType === 'percent') {
    $bookingDiscAmt = round($basePriceForDelivery * min($bookingDiscValue, 100) / 100, 2);
} elseif ($bookingDiscType === 'amount') {
    $bookingDiscAmt = min($bookingDiscValue, $basePriceForDelivery);
}
$basePriceAfterBookingDiscount = max(0, $basePriceForDelivery - $bookingDiscAmt);
$deliveryCharge = max(0, (float) ($r['delivery_charge'] ?? 0));
$deliveryManualAmount = max(0, (float) ($r['delivery_manual_amount'] ?? 0));
$voucherApplied = max(0, (float) ($r['voucher_applied'] ?? 0));
$deliveryPrepaid = max(0, (float) ($r['delivery_charge_prepaid'] ?? 0));
// Delivery discount
$delivDiscType = $r['delivery_discount_type'] ?? null;
$delivDiscVal = (float) ($r['delivery_discount_value'] ?? 0);
$delivBaseWithCharge = max(0, $basePriceAfterBookingDiscount - $voucherApplied - $advancePaid) + $deliveryCharge + $deliveryManualAmount;
$delivDiscountAmt = 0;
if ($delivDiscType === 'percent') {
    $delivDiscountAmt = round($delivBaseWithCharge * min($delivDiscVal, 100) / 100, 2);
} elseif ($delivDiscType === 'amount') {
    $delivDiscountAmt = min($delivDiscVal, $delivBaseWithCharge);
}
$baseCollectedAtDelivery = max(0, $delivBaseWithCharge - $delivDiscountAmt);
$returnVoucherApplied = max(0, (float) ($r['return_voucher_applied'] ?? 0));
// overdue_amount in DB already includes late charge (combined on save in return.php)
$overdueAmt = (float) $r['overdue_amount'];
$kmOverageChg = (float) ($r['km_overage_charge'] ?? 0);
$damageChg = (float) ($r['damage_charge'] ?? 0);
$additionalChg = (float) ($r['additional_charge'] ?? 0);
$chellanChg = (float) ($r['chellan_amount'] ?? 0);
$discType = $r['discount_type'] ?? null;
$discVal = (float) ($r['discount_value'] ?? 0);
$earlyVoucherCredit = max(0, (float) ($r['voucher_credit_issued'] ?? ($r['early_return_credit'] ?? 0)));

$returnChargesBeforeDiscount = $overdueAmt + $kmOverageChg + $damageChg + $additionalChg + $chellanChg;
$discountAmt = 0;
if ($discType === 'percent') {
    $discountAmt = round($returnChargesBeforeDiscount * min($discVal, 100) / 100, 2);
} elseif ($discType === 'amount') {
    $discountAmt = min($discVal, $returnChargesBeforeDiscount);
}
$amountDueAtReturn = max(0, $returnChargesBeforeDiscount - $discountAmt);
$cashDueAtReturn = max(0, $amountDueAtReturn - $returnVoucherApplied);
$totalCollected = $advancePaid + $deliveryPrepaid + $extensionPaid + $baseCollectedAtDelivery + $cashDueAtReturn;
$refundAmount = max(0, (float) ($r['refund_amount'] ?? 0));
$netCollected = max(0, $totalCollected - $refundAmount);

// ── Held deposit resolution data ─────────────────────────────────────────────
$depHeld        = max(0, (float) ($r['deposit_held'] ?? 0));
$depHoldReason  = trim($r['deposit_hold_reason'] ?? '');
$depHeldAction  = $r['deposit_held_action'] ?? null;
$depHeldResolvedAt = $r['deposit_held_resolved_at'] ?? null;
$showHeldActions = $r['status'] === 'completed' && $depHeld > 0 && $depHeldAction === null;

// Is the hold older than 3 days?
$heldIsExpired = false;
if ($showHeldActions && $return) {
    $returnTs = strtotime($return['created_at'] ?? 'now');
    $heldIsExpired = (time() - $returnTs) > (3 * 24 * 3600);
}

$success = getFlash('success');
$pageTitle = 'Reservation #' . $id;
require_once __DIR__ . '/../includes/header.php';

$statusColors = ['pending' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/30', 'confirmed' => 'bg-sky-500/10 text-sky-400 border-sky-500/30', 'active' => 'bg-green-500/10 text-green-400 border-green-500/30', 'completed' => 'bg-mb-subtle/10 text-mb-subtle border-mb-subtle/30', 'cancelled' => 'bg-red-500/10 text-red-400 border-red-500/30'];
$sc = $statusColors[$r['status']] ?? '';

function fuelBar(int $pct): string
{
    $color = $pct >= 75 ? 'bg-green-500' : ($pct >= 50 ? 'bg-yellow-400' : ($pct >= 25 ? 'bg-orange-400' : 'bg-red-500'));
    return "<div class='w-full h-2 bg-mb-black/60 rounded-full overflow-hidden'><div class='h-2 $color rounded-full' style='width:{$pct}%'></div></div>";
}
?>

<div class="space-y-6">
    <?php if ($success): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-center gap-3 text-sm text-mb-subtle">
            <a href="index.php" class="hover:text-white transition-colors">Reservations</a>
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-white">Reservation #<?= $id ?></span>
        </div>
        <!-- Actions -->
        <div class="flex items-center gap-3 flex-wrap">
            <?php if (in_array($r['status'], ['pending', 'confirmed', 'active'])): ?>
                <a href="edit.php?id=<?= $id ?>"
                    class="border border-mb-subtle/30 text-mb-silver px-4 py-2 rounded-full hover:border-white/30 hover:text-white transition-all text-sm">Edit</a>
            <?php endif; ?>
            <?php if ($r['status'] === 'confirmed'): ?>
                <a href="deliver.php?id=<?= $id ?>"
                    class="bg-green-600 text-white px-5 py-2 rounded-full hover:bg-green-500 transition-colors text-sm font-medium">▶
                    Deliver Vehicle</a>
            <?php endif; ?>
            <?php if ($r['status'] === 'active'): ?>
                <a href="return.php?id=<?= $id ?>"
                    class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">⏎
                    Process Return</a>
            <?php endif; ?>
            <?php if ($r['status'] === 'active' && (auth_has_perm('add_reservations') || auth_has_perm('do_delivery') || auth_has_perm('do_return'))): ?>
                <a href="extend.php?id=<?= $id ?>"
                    class="border border-mb-subtle/30 text-mb-silver px-4 py-2 rounded-full hover:border-white/30 hover:text-white transition-all text-sm">Extend</a>
            <?php endif; ?>
            <?php if ($r['status'] === 'active'): ?>
                <a href="cancel.php?id=<?= $id ?>" onclick="return confirm('Cancel this active reservation?')" class="border border-red-500/30 text-red-400 px-4 py-2 rounded-full hover:bg-red-500/10 transition-colors text-sm"> Cancel Reservation</a>
            <?php endif; ?>
            <?php if (in_array($r['status'], ['pending', 'confirmed'])): ?>
                <a href="bill.php?id=<?= $id ?>" target="_blank"
                    class="border border-sky-500/40 text-sky-400 px-5 py-2 rounded-full hover:bg-sky-500/10 transition-colors text-sm font-medium">📋
                    View Bill (Quotation)</a>
                <button onclick="shareBill(<?= $id ?>)"
                    class="border border-purple-500/40 text-purple-400 px-5 py-2 rounded-full hover:bg-purple-500/10 transition-colors text-sm font-medium">↗
                    Share</button>
            <?php endif; ?>
            <?php if (in_array($r['status'], ['active', 'completed'])): ?>
                <a href="bill.php?id=<?= $id ?>" target="_blank"
                    class="border border-yellow-500/40 text-yellow-400 px-5 py-2 rounded-full hover:bg-yellow-500/10 transition-colors text-sm font-medium">🧾
                    View Bill</a>
                <button onclick="downloadBill(<?= $id ?>)"
                    class="border border-sky-500/40 text-sky-400 px-5 py-2 rounded-full hover:bg-sky-500/10 transition-colors text-sm font-medium">⬇
                    Download</button>
                <button onclick="shareBill(<?= $id ?>)"
                    class="border border-purple-500/40 text-purple-400 px-5 py-2 rounded-full hover:bg-purple-500/10 transition-colors text-sm font-medium">↗
                    Share</button>
            <?php endif; ?>
            <?php if (!in_array($r['status'], ['active', 'completed'])): ?>
                <a href="delete.php?id=<?= $id ?>" onclick="return confirm('Cancel this reservation?')"
                    class="border border-red-500/30 text-red-400 px-4 py-2 rounded-full hover:bg-red-500/10 transition-colors text-sm">Cancel</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Info -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div class="flex items-start justify-between">
                <h2 class="text-white text-xl font-light">Reservation #<?= $id ?></h2>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1.5 rounded-full text-sm border capitalize <?= $sc ?>">
                        <?= e($r['status']) ?>
                    </span>
                    <?php if ($overdue): ?>
                        <span
                            class="px-3 py-1.5 rounded-full text-sm border bg-red-500/20 text-red-400 border-red-500/30 animate-pulse">⚠
                            Overdue</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 text-sm">
                <div class="bg-mb-black/40 rounded-xl p-4">
                    <p class="text-mb-subtle text-xs uppercase mb-1">Client</p>
                    <a href="../clients/show.php?id=<?= $r['cid'] ?>"
                        class="text-white hover:text-mb-accent transition-colors">
                        <?= e($r['client_name']) ?>
                    </a>
                </div>
                <div class="bg-mb-black/40 rounded-xl p-4">
                    <p class="text-mb-subtle text-xs uppercase mb-1">Vehicle</p>
                    <a href="../vehicles/show.php?id=<?= $r['vehicle_id'] ?>"
                        class="text-white hover:text-mb-accent transition-colors">
                        <?= e($r['brand']) ?> <?= e($r['model']) ?>
                    </a>
                    <p class="text-xs text-mb-subtle"><?= e($r['license_plate']) ?></p>
                </div>
                <div class="bg-mb-black/40 rounded-xl p-4">
                    <p class="text-mb-subtle text-xs uppercase mb-1">Period</p>
                    <p class="text-white text-sm"><?= date('d M Y, h:i A', strtotime($r['start_date'])) ?></p>
                    <p class="text-mb-subtle text-xs my-0.5">→</p>
                    <p class="text-white text-sm"><?= date('d M Y, h:i A', strtotime($r['end_date'])) ?></p>
                    <p class="text-xs text-mb-subtle mt-1"><?= $days ?> days &bull; <?= ucfirst($r['rental_type']) ?></p>
                </div>
                <div class="bg-mb-black/40 rounded-xl p-4">
                    <p class="text-mb-subtle text-xs uppercase mb-1">Daily Rate</p>
                    <p class="text-mb-accent text-xl font-light">$<?= number_format($r['daily_rate'], 0) ?></p>
                </div>
            </div>
            <?php if (!empty($r['note'])): ?>
            <div class="mt-4 bg-mb-black/40 rounded-xl p-4 border-l-2 border-mb-accent">
                <p class="text-mb-subtle text-xs uppercase mb-1">Reservation Note</p>
                <p class="text-white text-sm whitespace-pre-wrap"><?= e($r['note']) ?></p>
            </div>
            <?php endif; ?>

            <!-- Pricing Summary -->
            <div class="border-t border-mb-subtle/10 pt-4 space-y-2">
                <?php if ($bookingDiscAmt > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-400">🎫 Booking Discount<?= $bookingDiscType === 'percent' ? " ({$bookingDiscValue}%)" : '' ?></span>
                        <span class="text-green-400">-$<?= number_format($bookingDiscAmt, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($voucherApplied > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-500/80">Voucher Used on Booking</span>
                        <span class="text-green-500/80">-$<?= number_format($voucherApplied, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($advancePaid > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-purple-400/80">Advance Collected</span>
                        <span class="text-purple-400/80">-$<?= number_format($advancePaid, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($deliveryPrepaid > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-400/80">Delivery Charge Collected at Booking</span>
                        <span class="text-blue-400/80">+$<?= number_format($deliveryPrepaid, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($deliveryCharge > 0 && $r['status'] === 'confirmed'): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-300/70">Delivery Charge (quoted – due at delivery)</span>
                        <span class="text-blue-300/70">$<?= number_format($deliveryCharge, 2) ?></span>
                    </div>
                <?php elseif ($deliveryCharge > 0 && in_array($r['status'], ['active', 'returned'])): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-blue-400/80">Delivery Charge Collected</span>
                        <span class="text-blue-400/80">+$<?= number_format($deliveryCharge, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($extensionPaid > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-sky-400/80">Extension Collected (Grace)</span>
                        <span class="text-sky-400/80">+$<?= number_format($extensionPaid, 2) ?></span>
                    </div>
                    <?php
                    // Show per-extension payment source breakdown
                    $extStmt = $pdo->prepare("SELECT * FROM reservation_extensions WHERE reservation_id = ? ORDER BY created_at ASC");
                    $extStmt->execute([$id]);
                    $extensions = $extStmt->fetchAll();
                    $hasPaidFromDeposit = column_exists($pdo, 'reservation_extensions', 'paid_from_deposit');
                    $hasSourceType = column_exists($pdo, 'reservation_extensions', 'payment_source_type');
                    $totalDepositUsedForExt = 0.0;
                    foreach ($extensions as $ext):
                        $srcType = $hasSourceType ? ($ext['payment_source_type'] ?? null) : null;
                        $paidDep = $hasPaidFromDeposit ? (float) ($ext['paid_from_deposit'] ?? 0) : 0.0;
                        $totalDepositUsedForExt += $paidDep;
                    ?>
                        <div class="flex justify-between text-xs text-mb-subtle pl-3 border-l border-mb-subtle/20">
                            <span>
                                Ext #<?= (int) $ext['id'] ?>
                                (<?= e($ext['rental_type'] ?? 'daily') ?>, <?= (int) ($ext['days'] ?? 0) ?> day<?= (int) ($ext['days'] ?? 0) !== 1 ? 's' : '' ?>)
                                —
                                <?php if ($srcType === 'deposit'): ?>
                                    <span class="text-mb-accent">Deposit</span>
                                <?php elseif ($srcType === 'split'): ?>
                                    <span class="text-mb-accent">Split</span>
                                    ($<?= number_format($paidDep, 2) ?> deposit + $<?= number_format((float) ($ext['paid_cash'] ?? 0), 2) ?> <?= e(ucfirst($ext['payment_method'] ?? 'cash')) ?>)
                                <?php else: ?>
                                    <?= e(ucfirst($srcType ?? $ext['payment_method'] ?? 'cash')) ?>
                                <?php endif; ?>
                            </span>
                            <span>$<?= number_format((float) $ext['amount'], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($totalDepositUsedForExt > 0): ?>
                        <div class="flex justify-between text-xs text-mb-accent/70 pl-3 border-l border-mb-accent/20">
                            <span>Total deposit used for extensions</span>
                            <span>$<?= number_format($totalDepositUsedForExt, 2) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($delivDiscountAmt > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-500/80">Delivery Discount<?= $delivDiscType === 'percent' ? " ({$delivDiscVal}%)" : '' ?></span>
                        <span class="text-green-500/80">-$<?= number_format($delivDiscountAmt, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($deliveryManualAmount > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-orange-400/80">Manual Additional at Delivery</span>
                        <span class="text-orange-400/80">+$<?= number_format($deliveryManualAmount, 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="flex justify-between text-sm">
                    <span class="text-mb-subtle">Base Collected at Delivery</span>
                    <span class="text-white">$<?= number_format($baseCollectedAtDelivery, 2) ?></span>
                </div>

                <?php if ((float) ($r['deposit_amount'] ?? 0) > 0): ?>
                    <div class="flex justify-between text-sm border-l-2 border-mb-accent/30 pl-2">
                        <span class="text-mb-subtle italic">Security Deposit Collected</span>
                        <span class="text-white">$<?= number_format((float) $r['deposit_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($r['status'] === 'completed' && (float) ($r['deposit_amount'] ?? 0) > 0): ?>
                    <?php
                    $depReturned = (float) ($r['deposit_returned'] ?? 0);
                    $depDeducted = (float) ($r['deposit_deducted'] ?? 0);
                    ?>
                    <?php if ($depReturned > 0): ?>
                        <div class="flex justify-between text-sm border-l-2 border-green-500/30 pl-2">
                            <span class="text-green-400">Returned to Client</span>
                            <span class="text-green-400">-$<?= number_format($depReturned, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($depDeducted > 0): ?>
                        <div class="flex justify-between text-sm border-l-2 border-red-500/30 pl-2">
                            <span class="text-red-400">Converted to Income (Deducted)</span>
                            <span class="text-red-400">-$<?= number_format($depDeducted, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($depHeld > 0): ?>
                        <div class="flex justify-between text-sm border-l-2 border-yellow-500/30 pl-2">
                            <span class="text-yellow-400">
                                Deposit on Hold
                                <?php if ($depHoldReason): ?>
                                    <span class="text-mb-subtle text-xs ml-1">— <?= e($depHoldReason) ?></span>
                                <?php endif; ?>
                                <?php if ($depHeldAction): ?>
                                    <span class="text-mb-subtle text-xs ml-1 capitalize">(<?= e($depHeldAction) ?>)</span>
                                <?php endif; ?>
                            </span>
                            <span class="text-yellow-400">-$<?= number_format($depHeld, 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($depReturned === 0.0 && $depDeducted === 0.0 && $depHeld === 0.0): ?>
                        <div class="flex justify-between text-sm border-l-2 border-orange-500/30 pl-2">
                            <span class="text-orange-400 italic">Security Deposit Status</span>
                            <span class="text-orange-400">Pending</span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($overdueAmt > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-red-400">Overdue / Late Charge</span>
                        <span class="text-red-400">+$<?= number_format($overdueAmt, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($kmOverageChg > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-yellow-500/80">KM Overage</span>
                        <span class="text-yellow-500/80">+$<?= number_format($kmOverageChg, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($damageChg > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-orange-500/80">Damage Charges</span>
                        <span class="text-orange-500/80">+$<?= number_format($damageChg, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($additionalChg > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-orange-400/80">Return Pickup Charge</span>
                        <span class="text-orange-400/80">+$<?= number_format($additionalChg, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($chellanChg > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-red-400/80">Chellan</span>
                        <span class="text-red-400/80">+$<?= number_format($chellanChg, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($discountAmt > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-500/80">Return Discount<?= $discType === 'percent' ? " ($discVal%)" : "" ?></span>
                        <span class="text-green-500/80">-$<?= number_format($discountAmt, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($returnVoucherApplied > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-emerald-300">Voucher Applied on Return</span>
                        <span class="text-emerald-300">-$<?= number_format($returnVoucherApplied, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($earlyVoucherCredit > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-emerald-300">Early Return Voucher Credit (next booking)</span>
                        <span class="text-emerald-300">+$<?= number_format($earlyVoucherCredit, 2) ?></span>
                    </div>
                <?php endif; ?>

                <div class="flex justify-between font-medium text-sm border-t border-mb-subtle/10 pt-2">
                    <span class="text-mb-silver">Amount Due at Return</span>
                    <span class="text-mb-accent font-semibold">$<?= number_format($cashDueAtReturn, 2) ?></span>
                </div>
                <?php if ($r['status'] === 'cancelled'): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-mb-subtle">Gross Collected Before Refund</span>
                        <span class="text-white">$<?= number_format($totalCollected, 2) ?></span>
                    </div>
                    <?php if ($refundAmount > 0): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-red-400">Cancellation Refund</span>
                            <span class="text-red-400">-$<?= number_format($refundAmount, 2) ?></span>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="flex justify-between items-center mt-2 bg-mb-accent/10 border border-mb-accent/30 rounded-lg px-4 py-3">
                    <span class="text-white font-semibold text-sm">💰 <?= $r['status'] === 'cancelled' ? 'Net Retained After Refund' : 'Total Collected for This Rental' ?></span>
                    <span class="text-mb-accent font-bold text-lg">$<?= number_format($r['status'] === 'cancelled' ? $netCollected : $totalCollected, 2) ?></span>
                </div>

                <?php if (in_array($r['status'], ['pending', 'confirmed']) && ($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                <!-- Booking Discount Widget -->
                <div class="mt-4 border-t border-mb-subtle/10 pt-4">
                    <p class="text-mb-subtle text-xs uppercase tracking-wider mb-3">Booking Discount</p>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="save_booking_discount">
                        <input type="hidden" name="reservation_id" value="<?= $id ?>">
                        <div class="flex gap-2">
                            <select name="booking_discount_type" id="bdType"
                                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent w-28 flex-shrink-0"
                                onchange="document.getElementById('bdValueWrap').classList.toggle('hidden', this.value === '')">
                                <option value="">None</option>
                                <option value="percent" <?= ($r['booking_discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percent %</option>
                                <option value="amount"  <?= ($r['booking_discount_type'] ?? '') === 'amount'  ? 'selected' : '' ?>>Fixed $</option>
                            </select>
                            <div id="bdValueWrap" class="flex-1 <?= empty($r['booking_discount_type']) ? 'hidden' : '' ?>">
                                <input type="number" name="booking_discount_value" step="0.01" min="0"
                                    value="<?= number_format((float)($r['booking_discount_value'] ?? 0), 2, '.', '') ?>"
                                    placeholder="0.00"
                                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
                            </div>
                        </div>
                        <?php if ($bookingDiscAmt > 0): ?>
                            <p class="text-green-400 text-xs">Currently applied: -$<?= number_format($bookingDiscAmt, 2) ?></p>
                        <?php endif; ?>
                        <button type="submit"
                            class="bg-mb-accent/20 border border-mb-accent/40 text-mb-accent px-4 py-2 rounded-lg text-sm hover:bg-mb-accent/30 transition-colors">
                            Save Discount
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Vehicle side info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <?php if ($r['image_url']): ?>
                <img src="<?= e($r['image_url']) ?>" class="w-full h-36 object-cover">
            <?php endif; ?>
            <div class="p-5">
                <p class="text-white font-light"><?= e($r['brand']) ?> <?= e($r['model']) ?></p>
                <p class="text-mb-subtle text-xs mt-1"><?= e($r['license_plate']) ?></p>
                <p class="text-mb-accent text-sm mt-2">$<?= number_format($r['daily_rate'], 0) ?>/day</p>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════
         HELD DEPOSIT MANAGEMENT SECTION
         Shown only on completed reservations where deposit_held > 0 and not yet resolved
    ════════════════════════════════════════════════════════════════════════════ -->
    <?php if ($showHeldActions): ?>
        <div class="bg-yellow-500/5 border <?= $heldIsExpired ? 'border-red-500/50' : 'border-yellow-500/30' ?> rounded-xl p-6 space-y-4">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h3 class="text-yellow-400 font-light text-lg flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        Held Deposit Pending Resolution
                    </h3>
                    <p class="text-mb-subtle text-xs mt-1 ml-7">This deposit amount is still on hold and has not been released or converted.</p>
                </div>
                <span class="text-yellow-400 font-bold text-xl">$<?= number_format($depHeld, 2) ?></span>
            </div>

            <?php if ($heldIsExpired): ?>
                <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 text-sm text-red-400">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    ⚠ This hold is more than 3 days old. Please resolve it as soon as possible.
                </div>
            <?php endif; ?>

            <?php if ($depHoldReason): ?>
                <div class="bg-mb-black/30 rounded-lg px-4 py-3 text-sm">
                    <span class="text-mb-subtle text-xs uppercase tracking-wider">Reason for Hold</span>
                    <p class="text-white mt-1"><?= e($depHoldReason) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($return): ?>
                <p class="text-mb-subtle text-xs">
                    Hold placed on: <?= date('d M Y, h:i A', strtotime($return['created_at'])) ?>
                </p>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                <!-- Release to Client -->
                <form method="POST" action="resolve_held_deposit.php"
                    onsubmit="return confirm('Release $<?= number_format($depHeld, 2) ?> back to the client? This will be posted as a deposit return in the ledger.')">
                    <input type="hidden" name="reservation_id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="release">
                    <button type="submit"
                        class="w-full flex items-center justify-center gap-2 bg-green-600/20 border border-green-500/40 text-green-400 px-5 py-3 rounded-xl hover:bg-green-600/30 hover:border-green-500/60 transition-all text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 11l3 3L22 4M3 12v7a2 2 0 002 2h14a2 2 0 002-2v-7" />
                        </svg>
                        Release to Client
                        <span class="text-green-300 text-xs font-normal">($<?= number_format($depHeld, 2) ?>)</span>
                    </button>
                </form>

                <!-- Convert to Income -->
                <form method="POST" action="resolve_held_deposit.php"
                    onsubmit="return confirm('Convert $<?= number_format($depHeld, 2) ?> to income? This will be posted as damage income in the ledger.')">
                    <input type="hidden" name="reservation_id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="convert">
                    <button type="submit"
                        class="w-full flex items-center justify-center gap-2 bg-red-500/10 border border-red-500/30 text-red-400 px-5 py-3 rounded-xl hover:bg-red-500/20 hover:border-red-500/50 transition-all text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Convert to Income
                        <span class="text-red-300 text-xs font-normal">($<?= number_format($depHeld, 2) ?>)</span>
                    </button>
                </form>
            </div>
        </div>
    <?php elseif ($r['status'] === 'completed' && $depHeld > 0 && $depHeldAction !== null): ?>
        <!-- Already resolved — show summary only -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl px-5 py-4 flex items-center justify-between gap-4 text-sm">
            <div class="flex items-center gap-3">
                <svg class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <div>
                    <p class="text-mb-silver">
                        Held deposit of <span class="text-white font-medium">$<?= number_format($depHeld, 2) ?></span>
                        was <span class="font-medium <?= $depHeldAction === 'released' ? 'text-green-400' : 'text-red-400' ?>">
                            <?= $depHeldAction === 'released' ? 'released to client' : 'converted to income' ?>
                        </span>
                    </p>
                    <?php if ($depHeldResolvedAt): ?>
                        <p class="text-mb-subtle text-xs mt-0.5"><?= date('d M Y, h:i A', strtotime($depHeldResolvedAt)) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <span class="text-xs text-mb-subtle capitalize border border-mb-subtle/20 rounded-full px-3 py-1"><?= e($depHeldAction) ?></span>
        </div>
    <?php endif; ?>

    <!-- Inspections -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Delivery Inspection -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10 bg-green-500/5">
                <h3 class="text-green-400 font-light flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Delivery Inspection
                </h3>
            </div>
            <?php if ($delivery): ?>
                <div class="p-5 space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-mb-subtle">Mileage</span><span class="text-white"><?= number_format($delivery['mileage']) ?> km</span></div>
                    <div>
                        <div class="flex justify-between mb-1"><span class="text-mb-subtle">Fuel Level</span><span class="text-white"><?= $delivery['fuel_level'] ?>%</span></div>
                        <?= fuelBar($delivery['fuel_level']) ?>
                    </div>
                    <?php if ($delivery['notes']): ?>
                        <p class="text-mb-subtle text-xs italic"><?= e($delivery['notes']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($delivery['photos'])): ?>
                        <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-mb-subtle/5">
                            <?php foreach ($delivery['photos'] as $p): ?>
                                <div class="group relative aspect-square rounded-lg overflow-hidden bg-mb-black/40 border border-mb-subtle/10 hover:border-mb-accent/30 transition-all">
                                    <img src="../<?= e($p['file_path']) ?>" class="w-full h-full object-cover cursor-pointer" onclick="openLightbox(this.src)">
                                    <div class="absolute inset-x-0 bottom-0 bg-black/60 py-1 px-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <p class="text-[10px] text-white/80 lowercase truncate text-center"><?= e(ucwords(str_replace('_', ' ', (string) $p['view_name']))) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <p class="text-mb-subtle text-xs"><?= e($delivery['created_at']) ?></p>
                </div>
            <?php else: ?>
                <p class="py-8 text-center text-mb-subtle text-sm italic">Not delivered yet.</p>
            <?php endif; ?>
        </div>

        <!-- Return Inspection -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10 bg-mb-accent/5">
                <h3 class="text-mb-accent font-light flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                    Return Inspection
                </h3>
            </div>
            <?php if ($return): ?>
                <div class="p-5 space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-mb-subtle">Mileage</span><span class="text-white"><?= number_format($return['mileage']) ?> km</span></div>
                    <div>
                        <div class="flex justify-between mb-1"><span class="text-mb-subtle">Fuel Level</span><span class="text-white"><?= $return['fuel_level'] ?>%</span></div>
                        <?= fuelBar($return['fuel_level']) ?>
                    </div>
                    <?php if ($return['notes']): ?>
                        <p class="text-mb-subtle text-xs italic"><?= e($return['notes']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($return['photos'])): ?>
                        <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-mb-subtle/5">
                            <?php foreach ($return['photos'] as $p): ?>
                                <div class="group relative aspect-square rounded-lg overflow-hidden bg-mb-black/40 border border-mb-subtle/10 hover:border-mb-accent/30 transition-all">
                                    <img src="../<?= e($p['file_path']) ?>" class="w-full h-full object-cover cursor-pointer" onclick="openLightbox(this.src)">
                                    <div class="absolute inset-x-0 bottom-0 bg-black/60 py-1 px-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <p class="text-[10px] text-white/80 lowercase truncate text-center"><?= e(ucwords(str_replace('_', ' ', (string) $p['view_name']))) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <p class="text-mb-subtle text-xs"><?= e($return['created_at']) ?></p>
                </div>
            <?php else: ?>
                <p class="py-8 text-center text-mb-subtle text-sm italic">Not returned yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($r['status'] === 'cancelled'): ?>
    <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-6 space-y-3">
        <h3 class="text-red-400 font-light text-base border-l-2 border-red-400 pl-3">&#x2715; Reservation Cancelled</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div><p class="text-mb-subtle text-xs uppercase mb-1">Cancelled At</p><p class="text-white"><?= $r['cancelled_at'] ? date('d M Y, h:i A', strtotime($r['cancelled_at'])) : '—' ?></p></div>
            <div><p class="text-mb-subtle text-xs uppercase mb-1">Refund Amount</p><p class="text-<?= ($r['refund_amount']??0)>0?'red-400':'mb-subtle' ?> font-medium">$<?= number_format((float)($r['refund_amount']??0),2) ?></p></div>
            <div><p class="text-mb-subtle text-xs uppercase mb-1">Net Retained</p><p class="text-<?= $netCollected>0?'mb-accent':'mb-subtle' ?> font-medium">$<?= number_format($netCollected,2) ?></p></div>
            <div class="col-span-2"><p class="text-mb-subtle text-xs uppercase mb-1">Reason</p><p class="text-white"><?= e($r['cancellation_reason']??'—') ?></p></div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script>
    function downloadBill(id) {
        const w = window.open('bill.php?id=' + id, '_blank');
        w.addEventListener('load', function () {
            w.document.title = 'Invoice-' + String(id).padStart(5, '0');
            w.print();
        });
    }
    function shareBill(id) {
        const status = '<?= e($r['status']) ?>';
        const docLabel = (status === 'pending' || status === 'confirmed') ? 'Quotation' : 'Invoice';
        const url = location.origin + '/reservations/bill.php?id=' + id;
        if (navigator.share) {
            navigator.share({ title: docLabel + ' #' + String(id).padStart(5, '0') + ' - O Rent', url: url }).catch(() => { });
        } else {
            navigator.clipboard.writeText(url).then(() => showToast(docLabel + ' link copied!'));
        }
    }
    function showToast(msg) {
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#22c55e;color:#fff;padding:10px 20px;border-radius:50px;font-size:13px;font-weight:600;z-index:9999;box-shadow:0 4px 15px rgba(0,0,0,.3);opacity:1;transition:opacity .4s';
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 2500);
    }
    function openLightbox(src) {
        const lb = document.createElement('div');
        lb.className = 'fixed inset-0 z-[9999] bg-black/95 backdrop-blur-sm flex items-center justify-center p-4 cursor-zoom-out';
        lb.innerHTML = `<img src="${src}" class="max-w-full max-h-full rounded-lg shadow-2xl animate-scale-in">`;
        lb.onclick = () => lb.remove();
        document.body.appendChild(lb);
    }
</script>
<style>
    @keyframes scale-in {
        from { opacity: 0; transform: scale(0.95); }
        to   { opacity: 1; transform: scale(1); }
    }
    .animate-scale-in { animation: scale-in 0.2s ease-out forwards; }
</style>