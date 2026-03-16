<?php
require_once __DIR__ . '/../config/db.php';
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$rStmt = $pdo->prepare('SELECT r.*, c.name AS client_name, c.phone AS client_phone, c.email AS client_email, c.address AS client_address, v.brand, v.model, v.license_plate, v.color, v.year, v.daily_rate, v.monthly_rate FROM reservations r JOIN clients c ON r.client_id=c.id JOIN vehicles v ON r.vehicle_id=v.id WHERE r.id=?');
$rStmt->execute([$id]);
$r = $rStmt->fetch();
if (!$r) {
    die('<p style="font-family:sans-serif;padding:2rem;color:red;">Reservation not found.</p>');
}

$iStmt = $pdo->prepare("SELECT * FROM vehicle_inspections WHERE reservation_id=? ORDER BY created_at");
$iStmt->execute([$id]);
$inspections = $iStmt->fetchAll();
$delivery = null;
$return = null;
foreach ($inspections as $ins) {
    if ($ins['type'] === 'delivery')
        $delivery = $ins;
    if ($ins['type'] === 'return')
        $return = $ins;
}

// Calculate duration & totals
$start = strtotime($r['start_date']);
$end = strtotime($r['end_date']);
$days = max(1, (int) ceil(($end - $start) / 86400) + 1); // inclusive: count both start & end day
$basePrice = (float) $r['total_price'];
$extensionPaid = max(0, (float) ($r['extension_paid_amount'] ?? 0));
$basePriceForDelivery = max(0, $basePrice - $extensionPaid);
$voucherApplied = max(0, (float) ($r['voucher_applied'] ?? 0));
$advancePaid = max(0, (float) ($r['advance_paid'] ?? 0));
$deliveryCharge = max(0, (float) ($r['delivery_charge'] ?? 0));
$deliveryManualAmount = max(0, (float) ($r['delivery_manual_amount'] ?? 0));
$deliveryPrepaid = max(0, (float) ($r['delivery_charge_prepaid'] ?? 0));
// Keep manual additional amount hidden in bill text, but include it in totals.
// Delivery discount
$delivDiscType = $r['delivery_discount_type'] ?? null;
$delivDiscVal = (float) ($r['delivery_discount_value'] ?? 0);
$delivBase = max(0, $basePriceForDelivery - $voucherApplied - $advancePaid) + $deliveryCharge + $deliveryManualAmount;
$delivDiscountAmt = 0;
if ($delivDiscType === 'percent') {
    $delivDiscountAmt = round($delivBase * min($delivDiscVal, 100) / 100, 2);
} elseif ($delivDiscType === 'amount') {
    $delivDiscountAmt = min($delivDiscVal, $delivBase);
}
$baseCollectedAtDelivery = max(0, $delivBase - $delivDiscountAmt);
$returnVoucherApplied = max(0, (float) ($r['return_voucher_applied'] ?? 0));
$overdueAmt = (float) $r['overdue_amount'];
$kmOverageChg = (float) ($r['km_overage_charge'] ?? 0);
$damageChg = (float) ($r['damage_charge'] ?? 0);
$additionalChg = (float) ($r['additional_charge'] ?? 0);
$chellanChg = (float) ($r['chellan_amount'] ?? 0);
$discType = $r['discount_type'] ?? null;
$discVal = (float) ($r['discount_value'] ?? 0);
$earlyVoucherCredit = max(0, (float) ($r['voucher_credit_issued'] ?? ($r['early_return_credit'] ?? 0)));

$isQuotation = in_array($r['status'], ['pending', 'confirmed']);

// Recalculate totals: base rental is collected at delivery, return collects only extra charges
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


// Format datetime helper
function fdt(?string $dt): string
{
    if (!$dt)
        return '—';
    $ts = strtotime($dt);
    return date('d M Y, h:i A', $ts);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #
        <?= $id ?> — O Rent CRM
    </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            color: #1a1a1a;
        }

        /* Action Bar (hidden when printing) */
        .action-bar {
            background: #111;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .action-bar .back {
            color: #aaa;
            text-decoration: none;
            font-size: 13px;
        }

        .action-bar .back:hover {
            color: #fff;
        }

        .action-bar .title {
            color: #fff;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: opacity .2s;
        }

        .btn:hover {
            opacity: .85;
        }

        .btn-print {
            background: #3b82f6;
            color: #fff;
        }

        .btn-pdf {
            background: #10b981;
            color: #fff;
        }

        /* Invoice Container */
        .invoice-wrap {
            max-width: 820px;
            margin: 32px auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .12);
        }

        /* Header */
        .inv-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 36px 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .company-name {
            font-size: 28px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .company-sub {
            color: #64748b;
            font-size: 13px;
            margin-top: 4px;
        }

        .inv-badge {
            text-align: right;
        }

        .inv-badge .label {
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .inv-badge .num {
            font-size: 32px;
            font-weight: 300;
            color: #38bdf8;
            line-height: 1;
        }

        .inv-badge .status-pill {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .status-completed {
            background: #e2e8f0;
            color: #475569;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-confirmed {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        /* Meta Row */
        .inv-meta {
            background: #f8fafc;
            padding: 20px 40px;
            display: flex;
            gap: 40px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }

        .inv-meta .meta-item .label {
            color: #94a3b8;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: .8px;
            margin-bottom: 3px;
        }

        .inv-meta .meta-item .val {
            color: #1e293b;
            font-weight: 500;
        }

        /* Body */
        .inv-body {
            padding: 36px 40px;
        }

        /* Grid: client + vehicle */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
        }

        .info-card h4 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 12px;
        }

        .info-card .name {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .info-card .detail {
            font-size: 13px;
            color: #475569;
            line-height: 1.7;
        }

        /* Rental Period */
        .period-row {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        .period-row .period-item .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #60a5fa;
            margin-bottom: 3px;
        }

        .period-row .period-item .val {
            font-size: 14px;
            font-weight: 600;
            color: #1e40af;
        }

        .period-row .arrow {
            color: #60a5fa;
            font-size: 20px;
        }

        .period-row .days-badge {
            margin-left: auto;
            background: #3b82f6;
            color: #fff;
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Inspection Info */
        .inspection-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 28px;
        }

        .insp-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
        }

        .insp-card h4 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .insp-card .row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #475569;
            padding: 3px 0;
        }

        .insp-card .row strong {
            color: #1e293b;
        }

        /* Price Table */
        .price-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .price-table th {
            background: #f1f5f9;
            text-align: left;
            padding: 10px 16px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .8px;
            color: #64748b;
        }

        .price-table td {
            padding: 12px 16px;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .price-table .val {
            text-align: right;
            font-weight: 500;
        }

        .total-row td {
            font-weight: 700;
            font-size: 18px;
            color: #0f172a;
            border-top: 2px solid #e2e8f0;
        }

        .total-row .val {
            color: #059669;
        }

        .overdue-row td {
            color: #dc2626;
        }

        /* Footer */
        .inv-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 24px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #94a3b8;
        }

        .inv-footer .generated {
            font-style: italic;
        }

        /* PRINT STYLES */
        @media print {
            .action-bar {
                display: none !important;
            }

            body {
                background: #fff;
            }

            .invoice-wrap {
                box-shadow: none;
                border-radius: 0;
                margin: 0;
                max-width: 100%;
            }

            .inv-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .inv-badge .status-pill,
            .period-row,
            .info-card,
            .insp-card,
            .price-table th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>

<body>

    <!-- Action Bar -->
    <div class="action-bar no-print">
        <div class="btn-group" style="justify-content:flex-start">
            <a href="show.php?id=<?= $id ?>" class="back">← Back to Reservation</a>
        </div>
        <span class="title"><?= $isQuotation ? 'Quotation Preview' : 'Invoice Preview' ?></span>

        <div class="btn-group">
            <button class="btn btn-print" onclick="window.print()">🖨 Print</button>
            <button class="btn btn-pdf" onclick="downloadPDF()">⬇ Download PDF</button>
        </div>
    </div>

    <!-- Invoice -->
    <div class="invoice-wrap">

        <!-- Header -->
        <div class="inv-header">
            <div>
                <div class="company-name">Orentincars</div>
            </div>
            <div class="inv-badge">
                <div class="label"><?= $isQuotation ? 'Quotation' : 'Invoice' ?></div>

                <div class="num">#
                    <?= str_pad($id, 5, '0', STR_PAD_LEFT) ?>
                </div>
                <?php
                $sc = ['pending' => 'status-pending', 'confirmed' => 'status-confirmed', 'active' => 'status-active', 'completed' => 'status-completed'];
                $cls = $sc[$r['status']] ?? '';
                ?>
                <span class="status-pill <?= $cls ?>">
                    <?= ucfirst($r['status']) ?>
                </span>
            </div>
        </div>

        <!-- Meta -->
        <div class="inv-meta">
            <div class="meta-item">
                <div class="label">Issue Date</div>
                <div class="val">
                    <?= date('d M Y') ?>
                </div>
            </div>
            <div class="meta-item">
                <div class="label">Rental Type</div>
                <div class="val">
                    <?= ucfirst($r['rental_type']) ?>
                </div>
            </div>
            <div class="meta-item">
                <div class="label">Duration</div>
                <div class="val">
                    <?= $days ?> Day
                    <?= $days > 1 ? 's' : '' ?>
                </div>
            </div>
            <div class="meta-item">
                <div class="label">Reserved On</div>
                <div class="val">
                    <?= date('d M Y', strtotime($r['created_at'])) ?>
                </div>
            </div>
        </div>

        <div class="inv-body">

            <!-- Client + Vehicle -->
            <div class="two-col">
                <div class="info-card">
                    <h4>Client</h4>
                    <div class="name">
                        <?= e($r['client_name']) ?>
                    </div>
                    <div class="detail">
                        <?php if ($r['client_phone']): ?>📞
                            <?= e($r['client_phone']) ?><br>
                        <?php endif; ?>
                        <?php if ($r['client_email']): ?>✉
                            <?= e($r['client_email']) ?><br>
                        <?php endif; ?>
                        <?php if ($r['client_address']): ?>📍
                            <?= e($r['client_address']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-card">
                    <h4>Vehicle</h4>
                    <div class="name">
                        <?= e($r['brand']) ?>
                        <?= e($r['model']) ?>
                    </div>
                    <div class="detail">
                        🔖 Plate:
                        <?= e($r['license_plate']) ?><br>
                        <?php if ($r['year']): ?>📅 Year:
                            <?= e($r['year']) ?><br>
                        <?php endif; ?>
                        <?php if ($r['color']): ?>🎨 Color:
                            <?= e($r['color']) ?><br>
                        <?php endif; ?>
                        💰 Daily Rate: $
                        <?= number_format($r['daily_rate'], 2) ?>/day
                    </div>
                </div>
            </div>

            <!-- Rental Period -->
            <div class="period-row">
                <div class="period-item">
                    <div class="label">Start</div>
                    <div class="val">
                        <?= fdt($r['start_date']) ?>
                    </div>
                </div>
                <div class="arrow">→</div>
                <div class="period-item">
                    <div class="label">End</div>
                    <div class="val">
                        <?= fdt($r['end_date']) ?>
                    </div>
                </div>
                <div class="days-badge">
                    <?= $days ?> Day
                    <?= $days > 1 ? 's' : '' ?>
                </div>
            </div>

            <!-- Inspections -->
            <?php if ($delivery || $return): ?>
                <div class="inspection-row">
                    <?php if ($delivery): ?>
                        <div class="insp-card">
                            <h4>✅ Delivery Inspection</h4>
                            <div class="row"><span>Mileage</span><strong>
                                    <?= number_format($delivery['mileage']) ?> km
                                </strong></div>
                            <div class="row"><span>Fuel Level</span><strong>
                                    <?= $delivery['fuel_level'] ?>%
                                </strong></div>
                            <div class="row"><span>Date</span><strong>
                                    <?= fdt($delivery['created_at']) ?>
                                </strong></div>
                            <?php if ($delivery['notes']): ?>
                                <div class="row" style="flex-direction:column;gap:2px"><span>Notes</span><span
                                        style="color:#475569;font-size:12px">
                                        <?= e($delivery['notes']) ?>
                                    </span></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($return): ?>
                        <div class="insp-card">
                            <h4>🔄 Return Inspection</h4>
                            <div class="row"><span>Mileage</span><strong>
                                    <?= number_format($return['mileage']) ?> km
                                </strong></div>
                            <div class="row"><span>Fuel Level</span><strong>
                                    <?= $return['fuel_level'] ?>%
                                </strong></div>
                            <div class="row"><span>Date</span><strong>
                                    <?= fdt($return['created_at']) ?>
                                </strong></div>
                            <?php if ($return['notes']): ?>
                                <div class="row" style="flex-direction:column;gap:2px"><span>Notes</span><span
                                        style="color:#475569;font-size:12px">
                                        <?= e($return['notes']) ?>
                                    </span></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Price Breakdown -->
            <table class="price-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th style="text-align:right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            Base Rental:
                            <?= e($r['brand']) ?>
                            <?= e($r['model']) ?> ×
                            <?= $days ?> day
                            <?= $days > 1 ? 's' : '' ?> @ $
                            <?= number_format($r['daily_rate'], 2) ?>/day
                        </td>
                        <td class="val">$
                            <?= number_format($basePrice, 2) ?>
                        </td>
                    </tr>
                    <?php if ($voucherApplied > 0): ?>
                        <tr style="color:#16a34a">
                            <td>Voucher Used on Booking</td>
                            <td class="val">-$<?= number_format($voucherApplied, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($advancePaid > 0): ?>
                        <tr style="color:#7c3aed">
                            <td>Advance Collected</td>
                            <td class="val">-$<?= number_format($advancePaid, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($deliveryPrepaid > 0): ?>
                        <tr style="color:#0284c7">
                            <td>Delivery Charge Collected at Booking</td>
                            <td class="val">+$<?= number_format($deliveryPrepaid, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($extensionPaid > 0): ?>
                        <tr style="color:#0ea5e9">
                            <td>Extension Collected (Grace)</td>
                            <td class="val">+$<?= number_format($extensionPaid, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($deliveryCharge > 0): ?>
                        <tr style="color:#0369a1">
                            <td>Delivery Charge</td>
                            <td class="val">+$<?= number_format($deliveryCharge, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($delivDiscountAmt > 0): ?>
                        <tr style="color:#16a34a">
                            <td>🎫 Delivery Discount<?= $delivDiscType === 'percent' ? ' (' . $delivDiscVal . '%)' : '' ?>
                            </td>
                            <td class="val">-$<?= number_format($delivDiscountAmt, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr style="background:#f8fafc;font-weight:600">
                        <td>Base Collected at Delivery</td>
                        <td class="val">$<?= number_format($baseCollectedAtDelivery, 2) ?></td>
                    </tr>
                    <?php if (!$isQuotation): ?>
                        <?php if ($overdueAmt > 0): ?>
                            <tr class="overdue-row">
                                <td>⚠ Overdue / Late Charges</td>
                                <td class="val">+$
                                    <?= number_format($overdueAmt, 2) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($kmOverageChg > 0): ?>
                            <tr style="color:#d97706">
                                <td>🚗 KM Overage (<?= number_format($r['km_driven']) ?> km driven, limit
                                    <?= number_format($r['km_limit']) ?> km)
                                </td>
                                <td class="val">+$<?= number_format($kmOverageChg, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($damageChg > 0): ?>
                            <tr style="color:#ea580c">
                                <td>🔧 Damage Charges</td>
                                <td class="val">+$<?= number_format($damageChg, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($additionalChg > 0): ?>
                            <tr style="color:#c2410c">
                                <td>Return Pickup Charge</td>
                                <td class="val">+$<?= number_format($additionalChg, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($chellanChg > 0): ?>
                            <tr style="color:#dc2626">
                                <td>🚔 Chellan</td>
                                <td class="val">+$<?= number_format($chellanChg, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($discountAmt > 0): ?>
                            <tr style="color:#16a34a">
                                <td>🎫 Return Discount<?= $discType === 'percent' ? ' (' . $discVal . '%)' : '' ?></td>
                                <td class="val">-$<?= number_format($discountAmt, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($returnVoucherApplied > 0): ?>
                            <tr style="color:#10b981">
                                <td>Voucher Applied on Return</td>
                                <td class="val">-$<?= number_format($returnVoucherApplied, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($earlyVoucherCredit > 0): ?>
                            <tr style="color:#10b981">
                                <td>Early Return Voucher Credit (for next booking)</td>
                                <td class="val">+$<?= number_format($earlyVoucherCredit, 2) ?></td>
                            </tr>
                        <?php endif; ?>
                    <?php endif; /* !isQuotation */ ?>
                </tbody>
                <tfoot>
                    <?php if ($isQuotation): ?>
                        <tr class="total-row" style="background:#f8fafc;color:#1e293b">
                            <td>Estimated Total</td>
                            <td class="val">$<?= number_format($baseCollectedAtDelivery + $deliveryPrepaid, 2) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr class="total-row">
                            <td>Amount Due at Return</td>
                            <td class="val">$<?= number_format($cashDueAtReturn, 2) ?></td>
                        </tr>
                        <tr class="total-row" style="background:#f8fafc;color:#1e293b">
                            <td>Total Collected for This Rental</td>
                            <td class="val">$<?= number_format($totalCollected, 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </tfoot>
            </table>

        </div><!-- /inv-body -->

        <!-- Footer -->
        <div class="inv-footer">
            <span>Thank you for choosing <strong>Orentincars</strong>!</span>
            <span class="generated">Generated:
                <?= date('d M Y, h:i A') ?>
            </span>
        </div>

    </div><!-- /invoice-wrap -->

    <script>
        function downloadPDF() {
            const originalTitle = document.title;
            document.title = '<?= $isQuotation ? 'Quotation' : 'Invoice' ?>-<?= str_pad($id, 5, '0', STR_PAD_LEFT) ?>';
            window.print();
            document.title = originalTitle;
        }
    </script>
</body>

</html>
