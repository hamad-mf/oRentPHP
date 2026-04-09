<?php
// Direct query test - bypassing all auth
$host = 'localhost';
$db = 'orent';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $vehicleId = 1;
    $periodStart = '2026-03-15';
    $periodEnd = '2026-04-14';
    
    echo "Testing expense details query for Mercedes (ID $vehicleId)\n\n";
    
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
            AND (le.source_event IS NULL OR le.source_event NOT IN ('security_deposit_in','security_deposit_out','transfer_out','transfer_in'))
            AND (le.source_type IS NULL OR le.source_type <> 'transfer')
            AND DATE(le.posted_at) BETWEEN ? AND ?
            AND (
                (le.source_type = 'reservation' AND r.vehicle_id = ?)
                OR (le.source_type = 'vehicle_expense' AND le.source_id = ?)
            )
        ORDER BY le.posted_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$periodStart, $periodEnd, $vehicleId, $vehicleId]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Results: " . count($results) . " entries\n\n";
    
    if (empty($results)) {
        echo "NO ENTRIES FOUND!\n";
    } else {
        foreach ($results as $r) {
            echo "ID: {$r['id']}\n";
            echo "  Amount: \${$r['amount']}\n";
            echo "  Category: {$r['category']}\n";
            echo "  Description: {$r['description']}\n";
            echo "  Source: {$r['source_type']} (ID: {$r['source_id']})\n";
            echo "  Posted: {$r['posted_at']}\n\n";
        }
    }
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
