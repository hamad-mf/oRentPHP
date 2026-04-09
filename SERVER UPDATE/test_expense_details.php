<?php
require 'config/db.php';
require 'includes/ledger_helpers.php';

$pdo = db();
$vehicleId = 1; // Mercedes
$periodStart = '2026-03-15';
$periodEnd = '2026-04-14';

echo "=== TESTING EXPENSE DETAILS FUNCTION ===\n\n";

// Test current function
$exclusionClause = ledger_kpi_exclusion_clause('le');

$sql = "
    SELECT 
        le.id, le.amount, le.description, le.category,
        le.source_event, le.payment_mode, le.posted_at
    FROM ledger_entries le
    LEFT JOIN reservations r 
        ON le.source_type = 'reservation' 
        AND le.source_id = r.id
    WHERE le.txn_type = 'expense'
        AND $exclusionClause
        AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
        AND (
            (le.source_type = 'reservation' AND r.vehicle_id = :vehicle_id)
            OR (le.source_type = 'vehicle_expense' AND le.source_id = :vehicle_id)
        )
    ORDER BY le.posted_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    'vehicle_id' => $vehicleId,
    'period_start' => $periodStart,
    'period_end' => $periodEnd
]);

$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Mercedes expense details: " . count($details) . " entries\n\n";

if (empty($details)) {
    echo "✗ NO entries returned (this is the bug)\n";
} else {
    echo "✓ Found entries:\n";
    foreach ($details as $d) {
        echo "  - ID {$d['id']}: \${$d['amount']} on {$d['posted_at']}\n";
        echo "    Category: {$d['category']}\n";
        echo "    Description: {$d['description']}\n";
    }
}

echo "\n=== END TEST ===\n";
