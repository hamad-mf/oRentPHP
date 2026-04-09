<?php
require 'config/db.php';
$pdo = db();

// Get Mercedes vehicle
$sql = "SELECT id, brand, model FROM vehicles WHERE brand LIKE '%Mercedes%' LIMIT 1";
$vehicle = $pdo->query($sql)->fetch();

echo "Mercedes ID: {$vehicle['id']}\n";
echo "Brand: {$vehicle['brand']} {$vehicle['model']}\n\n";

// Check expenses in period
$sql = "SELECT COUNT(*) as cnt, SUM(amount) as total 
        FROM ledger_entries 
        WHERE source_type='vehicle_expense' 
        AND source_id={$vehicle['id']} 
        AND txn_type='expense'
        AND DATE(posted_at) BETWEEN '2026-03-15' AND '2026-04-14'";
$result = $pdo->query($sql)->fetch();

echo "Expenses in period (15 Mar - 14 Apr 2026):\n";
echo "Count: {$result['cnt']}\n";
echo "Total: \${$result['total']}\n\n";

// Check all expenses
$sql2 = "SELECT id, amount, posted_at, description 
         FROM ledger_entries 
         WHERE source_type='vehicle_expense' 
         AND source_id={$vehicle['id']} 
         AND txn_type='expense'
         ORDER BY posted_at DESC";
$all = $pdo->query($sql2)->fetchAll();

echo "All Mercedes expenses:\n";
foreach ($all as $exp) {
    $inPeriod = (strtotime($exp['posted_at']) >= strtotime('2026-03-15') && 
                 strtotime($exp['posted_at']) <= strtotime('2026-04-14 23:59:59')) ? 'YES' : 'NO';
    echo "  {$exp['posted_at']}: \${$exp['amount']} - In period: $inPeriod\n";
}
