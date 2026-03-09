<?php
require_once __DIR__ . '/../config/db.php';
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

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
$voucherApplied = max(0, (float) ($r['voucher_applied'] ?? 0));
$deliveryCharge = max(0, (float) ($r['delivery_charge'] ?? 0));
$deliveryManualAmount = max(0, (float) ($r['delivery_manual_amount'] ?? 0));
// Delivery discount
$delivDiscType = $r['delivery_discount_type'] ?? null;
$delivDiscVal = (float) ($r['delivery_discount_value'] ?? 0);
$delivBaseWithCharge = max(0, $basePrice - $voucherApplied) + $deliveryCharge + $deliveryManualAmount;
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
$totalCollected = $baseCollectedAtDelivery + $cashDueAtReturn;
$refundAmount = max(0, (float) ($r['refund_amount'] ?? 0));
$netCollected = max(0, $totalCollected - $refundAmount);

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
            <span class="text-white">Reservation #
                <?= $id ?>
            </span>
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
                <h2 class="text-white text-xl font-light">Reservation #
                    <?= $id ?>
                </h2>
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
                        <?= e($r['brand']) ?>
                        <?= e($r['model']) ?>
                    </a>
                    <p class="text-xs text-mb-subtle">
                        <?= e($r['license_plate']) ?>
                    </p>
                </div>
                <div class="bg-mb-black/40 rounded-xl p-4">
                    <p class="text-mb-subtle text-xs uppercase mb-1">Period</p>
                    <p class="text-white text-sm">
                        <?= date('d M Y, h:i A', strtotime($r['start_date'])) ?>
                    </p>
                    <p class="text-mb-subtle text-xs my-0.5">→</p>
                    <p class="text-white text-sm">
                        <?= date('d M Y, h:i A', strtotime($r['end_date'])) ?>
                    </p>
                    <p class="text-xs text-mb-subtle mt-1">
                        <?= $days ?> days &bull;
                        <?= ucfirst($r['rental_type']) ?>
                    </p>
                </div>
                <div class="bg-mb-black/40 rounded-xl p-4">
                    <p class="text-mb-subtle text-xs uppercase mb-1">Daily Rate</p>
                    <p class="text-mb-accent text-xl font-light">$
                        <?= number_format($r['daily_rate'], 0) ?>
                    </p>
                </div>
            </div>

            <!-- Pricing Summary -->
            <div class="border-t border-mb-subtle/10 pt-4 space-y-2">
                <?php if ($voucherApplied > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-500/80">Voucher Used on Booking</span>
                        <span class="text-green-500/80">-$<?= number_format($voucherApplied, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($delivDiscountAmt > 0): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-green-500/80">Delivery
                            Discount<?= $delivDiscType === 'percent' ? " ({$delivDiscVal}%)" : '' ?></span>
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
                    <div class="flex justify-between text-sm border-l-2 border-orange-500/30 pl-2">
                        <span class="text-mb-subtle italic">Security Deposit Returned</span>
                        <span
                            class="text-orange-400">-$<?= number_format((float) ($r['deposit_returned'] ?? 0), 2) ?></span>
                    </div>
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
                        <span class="text-green-500/80">Return
                            Discount<?= $discType === 'percent' ? " ($discVal%)" : "" ?></span>
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
                <div
                    class="flex justify-between items-center mt-2 bg-mb-accent/10 border border-mb-accent/30 rounded-lg px-4 py-3">
                    <span class="text-white font-semibold text-sm">💰 <?= $r['status'] === 'cancelled' ? 'Net Retained After Refund' : 'Total Collected for This Rental' ?></span>
                    <span class="text-mb-accent font-bold text-lg">$<?= number_format($r['status'] === 'cancelled' ? $netCollected : $totalCollected, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Vehicle side info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <?php if ($r['image_url']): ?>
                <img src="<?= e($r['image_url']) ?>" class="w-full h-36 object-cover">
            <?php endif; ?>
            <div class="p-5">
                <p class="text-white font-light">
                    <?= e($r['brand']) ?>
                    <?= e($r['model']) ?>
                </p>
                <p class="text-mb-subtle text-xs mt-1">
                    <?= e($r['license_plate']) ?>
                </p>
                <p class="text-mb-accent text-sm mt-2">$
                    <?= number_format($r['daily_rate'], 0) ?>/day
                </p>
            </div>
        </div>
    </div>

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
                    <div class="flex justify-between"><span class="text-mb-subtle">Mileage</span><span class="text-white">
                            <?= number_format($delivery['mileage']) ?> km
                        </span></div>
                    <div>
                        <div class="flex justify-between mb-1"><span class="text-mb-subtle">Fuel Level</span><span
                                class="text-white">
                                <?= $delivery['fuel_level'] ?>%
                            </span></div>
                        <?= fuelBar($delivery['fuel_level']) ?>
                    </div>
                    <?php if ($delivery['notes']): ?>
                        <p class="text-mb-subtle text-xs italic">
                            <?= e($delivery['notes']) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Delivery Photos -->
                    <?php if (!empty($delivery['photos'])): ?>
                        <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-mb-subtle/5">
                            <?php foreach ($delivery['photos'] as $p): ?>
                                <div
                                    class="group relative aspect-square rounded-lg overflow-hidden bg-mb-black/40 border border-mb-subtle/10 hover:border-mb-accent/30 transition-all">
                                    <img src="../<?= e($p['file_path']) ?>" class="w-full h-full object-cover cursor-pointer"
                                        onclick="openLightbox(this.src)">
                                    <div
                                        class="absolute inset-x-0 bottom-0 bg-black/60 py-1 px-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <p class="text-[10px] text-white/80 lowercase truncate text-center">
                                            <?= e(ucwords(str_replace('_', ' ', (string) $p['view_name']))) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-mb-subtle text-xs">
                        <?= e($delivery['created_at']) ?>
                    </p>
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
                    <div class="flex justify-between"><span class="text-mb-subtle">Mileage</span><span class="text-white">
                            <?= number_format($return['mileage']) ?> km
                        </span></div>
                    <div>
                        <div class="flex justify-between mb-1"><span class="text-mb-subtle">Fuel Level</span><span
                                class="text-white">
                                <?= $return['fuel_level'] ?>%
                            </span></div>
                        <?= fuelBar($return['fuel_level']) ?>
                    </div>
                    <?php if ($return['notes']): ?>
                        <p class="text-mb-subtle text-xs italic">
                            <?= e($return['notes']) ?>
                        </p>
                    <?php endif; ?>

                    <!-- Return Photos -->
                    <?php if (!empty($return['photos'])): ?>
                        <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-mb-subtle/5">
                            <?php foreach ($return['photos'] as $p): ?>
                                <div
                                    class="group relative aspect-square rounded-lg overflow-hidden bg-mb-black/40 border border-mb-subtle/10 hover:border-mb-accent/30 transition-all">
                                    <img src="../<?= e($p['file_path']) ?>" class="w-full h-full object-cover cursor-pointer"
                                        onclick="openLightbox(this.src)">
                                    <div
                                        class="absolute inset-x-0 bottom-0 bg-black/60 py-1 px-1.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <p class="text-[10px] text-white/80 lowercase truncate text-center">
                                            <?= e(ucwords(str_replace('_', ' ', (string) $p['view_name']))) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-mb-subtle text-xs">
                        <?= e($return['created_at']) ?>
                    </p>
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
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .animate-scale-in {
        animation: scale-in 0.2s ease-out forwards;
    }
</style>

