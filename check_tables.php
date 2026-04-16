<?php
require_once __DIR__ . '/config/db.php';

$pdo = db();

// Get all tables
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Total tables in database: " . count($tables) . "\n\n";

// Check for specific table
if (in_array('credit_payment_allocations', $tables)) {
    echo "✓ credit_payment_allocations table EXISTS\n";
} else {
    echo "✗ credit_payment_allocations table MISSING\n";
}

echo "\nAll tables:\n";
foreach ($tables as $table) {
    echo "  - $table\n";
}
