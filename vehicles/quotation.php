<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();
settings_ensure_table($pdo);

$stmt = $pdo->prepare('SELECT brand, model, license_plate, year, color, daily_rate, monthly_rate, rate_1day, rate_7day, rate_15day, rate_30day FROM vehicles WHERE id = ?');
$stmt->execute([$id]);
$v = $stmt->fetch();
if (!$v) {
    die('<p style="font-family:sans-serif;padding:2rem;color:red;">Vehicle not found.</p>');
}

$deliveryChargeDefault = (float) settings_get($pdo, 'delivery_charge_default', '0');
$returnPickupChargeDefault = (float) settings_get($pdo, 'return_pickup_charge_default', '0');
$lateReturnRatePerHour = (float) settings_get($pdo, 'late_return_rate_per_hour', '0');

function fmt_rate(?float $val): string
{
    $v = (float) ($val ?? 0);
    return $v > 0 ? '$' . number_format($v, 2) : '-';
}

$quoteRef = 'Q-' . date('Ymd') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
$dateStr = strtoupper(date('d M Y'));
$validUntil = strtoupper(date('d M Y', strtotime('+30 days')));

$rateRows = [
    ['label' => 'Daily', 'value' => (float) $v['daily_rate']],
    ['label' => '1 Day Package', 'value' => (float) $v['rate_1day']],
    ['label' => '7 Day Package', 'value' => (float) $v['rate_7day']],
    ['label' => '15 Day Package', 'value' => (float) $v['rate_15day']],
    ['label' => '30 Day Package', 'value' => (float) $v['rate_30day']],
    ['label' => 'Monthly', 'value' => (float) $v['monthly_rate']],
];
$rateRows = array_values(array_filter($rateRows, static fn($row) => $row['value'] > 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Quotation - <?= e($v['brand'] . ' ' . $v['model']) ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Times New Roman',Times,serif;background:#f5f5f5;color:#1a1a1a}
        .action-bar{background:#111;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:100}
        .action-bar a,.action-bar button{color:#fff;background:#2b2b2b;border:1px solid #444;padding:8px 14px;border-radius:6px;text-decoration:none;font-size:13px}
        .action-bar a:hover,.action-bar button:hover{background:#3a3a3a}
        .sheet{max-width:820px;margin:24px auto;background:#fff;border:1px solid #ccc;padding:32px 36px}
        table{width:100%;border-collapse:collapse;font-size:11px}
        th,td{padding:6px 10px;border-bottom:1px solid #eee;text-align:left}
        th{font-size:10px;color:#555;text-transform:uppercase;letter-spacing:.5px}
        @media print{
            .action-bar{display:none}
            body{background:#fff}
            .sheet{border:none;margin:0;width:100%}
        }
    </style>
</head>
<body>
    <div class="action-bar">
        <a href="show.php?id=<?= $id ?>">← Back to Vehicle</a>
        <button onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="sheet">
        <!-- HEADER -->
        <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:2px solid #111;padding-bottom:14px;margin-bottom:14px;">
            <tr>
                <td style="width:50%;vertical-align:middle;">
                    <div style="font-size:26px;font-weight:bold;letter-spacing:-0.5px;line-height:1.1;">
                        OrentinCars<br>
                        <span style="font-size:13px;font-weight:normal;color:#444;">Orentin Cars Pvt. Ltd.</span>
                    </div>
                    <div style="margin-top:6px;font-size:11px;color:#444;line-height:1.7;">
                        Kerala, India<br>
                        Phone: 7591955531&nbsp;|&nbsp;7591955532<br>
                        Orentincarspvtltd@gmail.com&nbsp;|&nbsp;orentincars.com
                    </div>
                </td>
                <td style="width:50%;text-align:right;vertical-align:top;">
                    <div style="font-size:22px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#111;">
                        QUOTATION
                    </div>
                    <div style="margin-top:8px;font-size:11px;color:#444;line-height:1.8;">
                        <table cellpadding="0" cellspacing="0" style="margin-left:auto;">
                            <tr>
                                <td style="padding-right:10px;color:#666;">Quote No.</td>
                                <td style="font-weight:bold;"><?= e($quoteRef) ?></td>
                            </tr>
                            <tr>
                                <td style="padding-right:10px;color:#666;">Date</td>
                                <td><?= e($dateStr) ?></td>
                            </tr>
                            <tr>
                                <td style="padding-right:10px;color:#666;">Valid Until</td>
                                <td><?= e($validUntil) ?></td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <!-- INFO BAR -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
            <tr>
                <td style="width:50%;vertical-align:top;">
                    <div style="font-size:10px;text-transform:uppercase;color:#666;letter-spacing:1px;margin-bottom:4px;">Prepared For</div>
                    <div style="font-size:13px;font-weight:bold;">&nbsp;</div>
                </td>
                <td style="width:50%;vertical-align:top;">
                    <table width="100%" cellpadding="3" cellspacing="0" style="border:1px solid #ccc;font-size:11px;">
                        <thead>
                            <tr style="background:#f0f0f0;">
                                <th style="text-align:left;padding:5px 8px;border-bottom:1px solid #ccc;">Date Issued</th>
                                <th style="text-align:left;padding:5px 8px;border-bottom:1px solid #ccc;">Quote No.</th>
                                <th style="text-align:left;padding:5px 8px;border-bottom:1px solid #ccc;">Page</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding:5px 8px;"><?= e($dateStr) ?></td>
                                <td style="padding:5px 8px;font-weight:bold;"><?= e($quoteRef) ?></td>
                                <td style="padding:5px 8px;">1 OF 1</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>

        <!-- NOTICE -->
        <div style="background:#f7f7f7;border:1px solid #ddd;padding:7px 12px;font-size:10px;color:#555;text-align:center;margin-bottom:18px;line-height:1.6;">
            THIS IS A QUOTATION ONLY - NOT AN INVOICE. PRICES ARE SUBJECT TO CHANGE WITHOUT NOTICE.<br>
            QUOTATION IS VALID FOR 30 DAYS FROM DATE OF ISSUE UNLESS STATED OTHERWISE.
        </div>

        <!-- VEHICLE TABLE -->
        <div style="margin-bottom:18px;">
            <div style="background:#111;color:#fff;padding:5px 10px;font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;margin-bottom:0;">
                <?= e($v['brand'] . ' ' . $v['model']) ?><?= $v['year'] ? ' (' . e((string) $v['year']) . ')' : '' ?>
            </div>
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ccc;border-top:none;font-size:11px;">
                <thead>
                    <tr style="background:#f0f0f0;">
                        <th style="text-align:left;padding:6px 10px;border-bottom:1px solid #ccc;width:60%;">RENTAL TYPE / DESCRIPTION</th>
                        <th style="text-align:right;padding:6px 10px;border-bottom:1px solid #ccc;width:20%;">UNIT PRICE</th>
                        <th style="text-align:right;padding:6px 10px;border-bottom:1px solid #ccc;width:20%;">AMOUNT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($rateRows)): ?>
                        <?php foreach ($rateRows as $i => $row): ?>
                            <?php $bg = $i % 2 === 0 ? '#fff' : '#fafafa'; ?>
                            <tr style="background:<?= $bg ?>;">
                                <td style="padding:6px 10px;border-bottom:1px solid #eee;"><?= e($row['label']) ?></td>
                                <td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;"><?= fmt_rate($row['value']) ?></td>
                                <td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;font-weight:bold;"><?= fmt_rate($row['value']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" style="padding:10px;color:#999;text-align:center;font-style:italic;">No rental rates specified.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- FOOTER / CHARGES -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;">
            <tr>
                <td style="width:50%;vertical-align:top;padding-right:20px;">
                    <div style="font-size:10px;color:#555;line-height:1.7;border:1px solid #ddd;padding:10px 12px;background:#fafafa;">
                        <strong>Terms &amp; Conditions</strong><br>
                        All rates are subject to availability and may change.<br>
                        Delivery and return charges apply per booking.<br>
                        Additional charges may apply as agreed.<br><br>
                        <span style="font-style:italic;">We appreciate your business. Have a great day!</span>
                    </div>
                </td>
                <td style="width:50%;vertical-align:top;">
                    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #ccc;font-size:11px;">
                        <tr>
                            <td style="padding:6px 10px;border-bottom:1px solid #eee;background:#f7f7f7;">Delivery Charge</td>
                            <td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;background:#f7f7f7;"><?= fmt_rate($deliveryChargeDefault) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:6px 10px;border-bottom:1px solid #eee;">Return Charge</td>
                            <td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;"><?= fmt_rate($returnPickupChargeDefault) ?></td>
                        </tr>
                        <?php if ($lateReturnRatePerHour > 0): ?>
                            <tr>
                                <td style="padding:6px 10px;border-bottom:1px solid #eee;background:#f7f7f7;">Late Return (Per Hour)</td>
                                <td style="padding:6px 10px;border-bottom:1px solid #eee;text-align:right;background:#f7f7f7;"><?= fmt_rate($lateReturnRatePerHour) ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
