<?php
require_once 'config/db.php';
$pdo = db();

echo "Running SIMPLE migration...\n\n";

$sql = file_get_contents('migrations/releases/2026-04-03_credit_payment_allocations_SIMPLE.sql');

try {
    $pdo->exec($sql);
    echo "✓ Migration completed successfully!\n\n";
    
    // Verify table was created
    $check = $pdo->query("SHOW TABLES LIKE 'credit_payment_allocations'")->fetch();
    if ($check) {
        echo "✓ Table 'credit_payment_allocations' created\n";
    } else {
        echo "✗ Table not found\n";
    }
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
}
