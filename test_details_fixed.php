<?php
require 'config/db.php';
require 'includes/ledger_helpers.php';

$pdo = db();
$vehicleId = 1;
$periodStart = '2026-03-15';
$periodEnd = '2026-04-14';

echo "=== TESTING FIXED EXPENSE DETAILS ===\n\n";

try {
    $exclusionClause = ledger_kpi_exclusion_clause('le');
    
    // Remove the category filter - it's not needed for details
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
    
    echo "Result count: " . count($details) . "\n\n";
    
    if (empty($details)) {
        echo "✗ NO entries\n";
    } else {
        echo "✓ Entries found:\n";
        foreach ($details as $d) {
            echo "  {$d['id']}: \${$d['amount']} - {$d['category']} - {$d['description']}\n";
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== END ===\n";
