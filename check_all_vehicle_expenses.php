<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
$pdo = db();

echo "=== ALL VEHICLE EXPENSES IN SYSTEM ===\n\n";

// Check all vehicle_expense entries
$sql = "SELECT 
            le.id, le.amount, le.description, le.category, le.posted_at, 
            le.source_type, le.source_id,
            v.brand, v.model
        FROM ledger_entries le
        LEFT JOIN vehicles v ON le.source_id = v.id
        WHERE le.source_type = 'vehicle_expense'
        AND le.txn_type = 'expense'
        ORDER BY le.posted_at DESC
        LIMIT 50";

$expenses = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($expenses)) {
    echo "NO vehicle_expense entries found in the system!\n";
    echo "This means no direct vehicle expenses have been added yet.\n\n";
} else {
    echo "Found " . count($expenses) . " vehicle_expense entries:\n\n";
    foreach ($expenses as $exp) {
        echo "Vehicle: {$exp['brand']} {$exp['model']} (ID: {$exp['source_id']})\n";
        echo "Amount: \${$exp['amount']} | Date: {$exp['posted_at']}\n";
        echo "Description: {$exp['description']}\n";
        echo "Category: {$exp['category']}\n\n";
    }
}

// Also check reservation-linked expenses for Mercedes
echo "\n=== RESERVATION EXPENSES FOR MERCEDES (ID: 1) ===\n\n";
$sql = "SELECT 
            le.id, le.amount, le.description, le.category, le.posted_at,
            r.id as reservation_id
        FROM ledger_entries le
        INNER JOIN reservations r ON le.source_type = 'reservation' AND le.source_id = r.id
        WHERE r.vehicle_id = 1
        AND le.txn_type = 'expense'
        ORDER BY le.posted_at DESC
        LIMIT 20";

$resExpenses = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($resExpenses)) {
    echo "No reservation expenses found for Mercedes\n";
} else {
    echo "Found " . count($resExpenses) . " reservation expenses:\n\n";
    $total = 0;
    foreach ($resExpenses as $exp) {
        echo "Amount: \${$exp['amount']} | Date: {$exp['posted_at']}\n";
        echo "Description: {$exp['description']}\n\n";
        $total += $exp['amount'];
    }
    echo "Total: \$$total\n";
}
