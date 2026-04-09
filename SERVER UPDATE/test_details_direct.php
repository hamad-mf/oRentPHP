<?php
require 'config/db.php';
$pdo = db();

$vehicleId = 1;
$periodStart = '2026-03-15';
$periodEnd = '2026-04-14';

echo "=== DIRECT TEST ===\n\n";

// Test without exclusion clause first
$sql = "
    SELECT 
        le.id, le.amount, le.description, le.category,
        le.source_event, le.payment_mode, le.posted_at,
        le.source_type, le.source_id
    FROM ledger_entries le
    LEFT JOIN reservations r 
        ON le.source_type = 'reservation' 
        AND le.source_id = r.id
    WHERE le.txn_type = 'expense'
        AND le.voided_at IS NULL
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
    echo "✗ NO entries\n";
} else {
    echo "✓ Entries:\n";
    foreach ($details as $d) {
        echo "  ID {$d['id']}: \${$d['amount']} - {$d['category']}\n";
        echo "    Source: {$d['source_type']} (ID: {$d['source_id']})\n";
        echo "    Posted: {$d['posted_at']}\n";
    }
}

echo "\n=== END ===\n";
