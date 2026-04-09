<?php
require 'config/db.php';
require 'includes/ledger_helpers.php';

$pdo = db();
$periodStart = '2026-03-15';
$periodEnd = '2026-04-14';

$exclusionClause = ledger_kpi_exclusion_clause('le');

$sql = "
    SELECT 
        CASE 
            WHEN le.source_type = 'reservation' THEN r.vehicle_id
            WHEN le.source_type = 'vehicle_expense' THEN le.source_id
        END AS vehicle_id,
        le.source_type,
        le.amount,
        le.posted_at
    FROM ledger_entries le
    LEFT JOIN reservations r 
        ON le.source_type = 'reservation' 
        AND le.source_id = r.id
    WHERE le.txn_type = 'expense'
        AND $exclusionClause
        AND le.category NOT LIKE '%Security Deposit%'
        AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
        AND (
            (le.source_type = 'reservation' AND r.vehicle_id IS NOT NULL)
            OR le.source_type = 'vehicle_expense'
        )
    ORDER BY vehicle_id, posted_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'period_start' => $periodStart,
    'period_end' => $periodEnd
]);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Query returned " . count($results) . " rows\n\n";

// Group by vehicle
$byVehicle = [];
foreach ($results as $row) {
    $vid = $row['vehicle_id'];
    if (!isset($byVehicle[$vid])) {
        $byVehicle[$vid] = [];
    }
    $byVehicle[$vid][] = $row;
}

echo "Vehicles with expenses:\n";
foreach ($byVehicle as $vid => $expenses) {
    $total = array_sum(array_column($expenses, 'amount'));
    echo "Vehicle ID $vid: " . count($expenses) . " expenses, Total: \$$total\n";
    foreach ($expenses as $exp) {
        echo "  - {$exp['posted_at']}: \${$exp['amount']} ({$exp['source_type']})\n";
    }
}

// Check specifically for Mercedes (ID 1)
echo "\n\nMercedes (ID 1) in results: ";
if (isset($byVehicle[1])) {
    echo "YES - \$" . array_sum(array_column($byVehicle[1], 'amount')) . "\n";
} else {
    echo "NO\n";
}
