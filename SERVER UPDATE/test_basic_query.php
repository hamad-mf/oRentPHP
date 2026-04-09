<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
$pdo = db();

echo "Testing basic query...\n\n";

try {
    // Simple test - just get Mercedes expenses
    $sql = "SELECT 
                le.id, le.amount, le.description, le.posted_at, le.source_type
            FROM ledger_entries le
            WHERE (le.source_type = 'vehicle_expense' AND le.source_id = 1)
            AND le.txn_type = 'expense'
            AND DATE(le.posted_at) BETWEEN '2026-03-15' AND '2026-04-14'
            ORDER BY le.posted_at DESC";
    
    $stmt = $pdo->query($sql);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($expenses) . " expenses for Mercedes in period 15 Mar - 14 Apr:\n\n";
    
    $total = 0;
    foreach ($expenses as $exp) {
        echo "Date: {$exp['posted_at']} | Amount: \${$exp['amount']}\n";
        echo "Desc: {$exp['description']}\n\n";
        $total += $exp['amount'];
    }
    
    echo "Total: \$$total\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
