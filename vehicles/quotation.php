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
    return $v > 0 ? '$' . number_format($v, 2) : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Quotation — <?= e($v['brand'] . ' ' . $v['model']) ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;color:#1a1a1a}
        .action-bar{background:#111;padding:12px 24px;display:flex;align-items:center;justify-content:space-between;gap:12px;position:sticky;top:0;z-index:100}
        .action-bar a,.action-bar button{color:#fff;background:#2b2b2b;border:1px solid #444;padding:8px 14px;border-radius:6px;text-decoration:none;font-size:13px}
        .action-bar a:hover,.action-bar button:hover{background:#3a3a3a}
        .sheet{max-width:900px;margin:24px auto;background:#fff;border:1px solid #e5e5e5;border-radius:10px;overflow:hidden}
        .header{padding:24px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;gap:20px}
        .title{font-size:20px;font-weight:600}
        .muted{color:#666;font-size:12px}
        .section{padding:20px 24px;border-bottom:1px solid #f0f0f0}
        .grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
        .label{font-size:12px;color:#777;text-transform:uppercase;letter-spacing:.03em}
        .value{font-size:14px;color:#111;margin-top:4px}
        table{width:100%;border-collapse:collapse;font-size:13px}
        th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
        th{font-size:12px;color:#555;text-transform:uppercase;letter-spacing:.03em}
        .footer{padding:16px 24px;font-size:12px;color:#777}
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
        <div class="header">
            <div>
                <div class="title">Vehicle Quotation</div>
                <div class="muted">Generated on <?= date('d M Y, h:i A') ?></div>
            </div>
            <div class="muted" style="text-align:right;">
                <?= e($v['brand'] . ' ' . $v['model']) ?><br>
                <?= e($v['license_plate']) ?>
            </div>
        </div>

        <div class="section">
            <div class="grid">
                <div>
                    <div class="label">Brand / Model</div>
                    <div class="value"><?= e($v['brand'] . ' ' . $v['model']) ?></div>
                </div>
                <div>
                    <div class="label">License Plate</div>
                    <div class="value"><?= e($v['license_plate']) ?></div>
                </div>
                <div>
                    <div class="label">Year</div>
                    <div class="value"><?= e((string) $v['year']) ?></div>
                </div>
                <div>
                    <div class="label">Color</div>
                    <div class="value"><?= e((string) ($v['color'] ?? '—')) ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="title" style="font-size:16px;margin-bottom:10px;">Rental Types & Rates</div>
            <table>
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Daily</td><td><?= fmt_rate($v['daily_rate']) ?></td></tr>
                    <tr><td>1 Day Package</td><td><?= fmt_rate($v['rate_1day']) ?></td></tr>
                    <tr><td>7 Day Package</td><td><?= fmt_rate($v['rate_7day']) ?></td></tr>
                    <tr><td>15 Day Package</td><td><?= fmt_rate($v['rate_15day']) ?></td></tr>
                    <tr><td>30 Day Package</td><td><?= fmt_rate($v['rate_30day']) ?></td></tr>
                    <tr><td>Monthly</td><td><?= fmt_rate($v['monthly_rate']) ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="title" style="font-size:16px;margin-bottom:10px;">Delivery & Return Charges</div>
            <table>
                <thead>
                    <tr>
                        <th>Charge</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Delivery Charge (Estimated)</td><td><?= fmt_rate($deliveryChargeDefault) ?></td></tr>
                    <tr><td>Return Pickup Charge (Estimated)</td><td><?= fmt_rate($returnPickupChargeDefault) ?></td></tr>
                    <tr><td>Late Return Rate (Per Hour, Estimated)</td><td><?= fmt_rate($lateReturnRatePerHour) ?></td></tr>
                </tbody>
            </table>
        </div>

        <div class="footer">
            This quotation is a simple rate guide. Final charges may vary based on rental duration, km overage, damages, and discounts.
        </div>
    </div>
</body>
</html>
