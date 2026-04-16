<?php
require_once __DIR__ . '/config/db.php';

echo "=== Bank Accounts in Database ===\n\n";

$stmt = db()->query('SELECT id, name, balance FROM bank_accounts ORDER BY id');
$accounts = $stmt->fetchAll();

echo "Total bank accounts: " . count($accounts) . "\n\n";

foreach ($accounts as $row) {
    echo sprintf("ID: %d | Name: %s | Balance: %.2f\n", 
        $row['id'], 
        $row['name'], 
        $row['balance']
    );
}
