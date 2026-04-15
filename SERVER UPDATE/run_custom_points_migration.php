<?php
// Run the custom points migration on production database
require_once __DIR__ . '/config/db.php';

try {
    $pdo = db();
    
    // Read the migration file
    $sql = file_get_contents(__DIR__ . '/migrations/releases/2026-04-10_job_card_custom_points.sql');
    
    // Execute the migration
    $pdo->exec($sql);
    
    echo "✓ Migration executed successfully!\n";
    echo "✓ Table 'vehicle_job_card_custom_points' created.\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
