<?php
require_once __DIR__ . '/config/db.php';

$migrationFile = __DIR__ . '/migrations/releases/2026-04-03_credit_payment_allocations_SIMPLE.sql';

if (!file_exists($migrationFile)) {
    die("ERROR: Migration file not found\n");
}

echo "Running migration: 2026-04-03_credit_payment_allocations_SIMPLE.sql\n\n";

$pdo = db();
$sql = file_get_contents($migrationFile);

try {
    $pdo->exec($sql);
    echo "✓ Migration completed successfully!\n";
    echo "✓ Table 'credit_payment_allocations' created\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
