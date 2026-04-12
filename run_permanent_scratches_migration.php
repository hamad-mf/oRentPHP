<?php
// Run the permanent scratches migration on production database
require_once __DIR__ . '/config/db.php';

try {
    $pdo = db();
    
    // Read the migration file
    $sql = file_get_contents(__DIR__ . '/migrations/releases/2026-04-05_vehicle_permanent_scratches.sql');
    
    // Execute the migration
    $pdo->exec($sql);
    
    echo "✓ Migration executed successfully!\n";
    echo "✓ Table 'vehicle_permanent_scratches' created.\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
