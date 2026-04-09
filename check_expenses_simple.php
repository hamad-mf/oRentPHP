<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

$pdo = db();

echo "Checking Mercedes expenses...\n\n";

// Find Mercedes vehicle
$sql = "SELECT id, brand, model FROM vehicles WHERE brand LIKE '%Mercedes%' LIMIT 1";
$vehicle = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    die("No Mercedes found\n");
}

echo "Vehicle: {$vehicle['brand']} {$vehicle['model']} (ID: {$vehicle['id']})\n\n";

// Check all expenses for this vehicle
$sql = "SELECT id, amount, description, posted_at, source_type, source_id, txn_type
        FROM ledger_entries 
        WHERE (source_type = 'vehicle_expense' AND source_id = :vid)
           OR (source_type = 'reservation' AND source_id IN (SELECT id FROM reservations WHERE vehicle_id = :vid))
        AND txn_type = 'expense'
        ORDER BY posted_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['vid' => $vehicle['id']]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total expenses found: " . count($expenses) . "\n\n";

$total = 0;
foreach ($expenses as $exp) {
    echo "Amount: \${$exp['amount']} | Type: {$exp['source_type']} | Date: {$exp['posted_at']}\n";
    echo "Desc: {$exp['description']}\n\n";
    $total += $exp['amount'];
}

echo "Total: \$$total\n";
