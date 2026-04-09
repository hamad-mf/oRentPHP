<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/ledger_helpers.php';

$pdo = db();

echo "=== TESTING SQL DIRECTLY ===\n\n";

// Test the UNION query directly
$periodStart = '2026-03-15';
$periodEnd = '2026-04-14';

$exclusionClause = ledger_kpi_exclusion_clause('le');

$sql = "
    SELECT 
        vehicle_id,
        SUM(total_expense) AS total_expense
    FROM (
        -- Reservation-linked expenses
        SELECT 
            r.vehicle_id,
            le.amount AS total_expense
        FROM ledger_entries le
        INNER JOIN reservations r 
            ON le.source_type = 'reservation' 
            AND le.source_id = r.id
        WHERE le.txn_type = 'expense'
            AND $exclusionClause
            AND le.category NOT LIKE '%Security Deposit%'
            AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
            AND r.vehicle_id IS NOT NULL
        
        UNION ALL
        
        -- Direct vehicle expenses
        SELECT 
            le.source_id AS vehicle_id,
            le.amount AS total_expense
        FROM ledger_entries le
        WHERE le.source_type = 'vehicle_expense'
            AND le.txn_type = 'expense'
            AND $exclusionClause
            AND le.category NOT LIKE '%Security Deposit%'
            AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
    ) AS combined_expenses
    GROUP BY vehicle_id
";

echo "Period: $periodStart to $periodEnd\n\n";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'period_start' => $periodStart,
    'period_end' => $periodEnd
]);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Results:\n";
foreach ($results as $row) {
    echo "Vehicle ID {$row['vehicle_id']}: \${$row['total_expense']}\n";
}

echo "\nMercedes (ID 1): $" . (array_column($results, 'total_expense', 'vehicle_id')[1] ?? '0.00') . "\n";

echo "\n--- Expected: $5,000 (Apr 4 expense is in range, Apr 7 is NOT) ---\n";
