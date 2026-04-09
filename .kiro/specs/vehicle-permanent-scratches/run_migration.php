<?php
require_once __DIR__ . '/../../../config/db.php';

$pdo = db();
$sql = file_get_contents(__DIR__ . '/../../../migrations/releases/2026-04-05_vehicle_permanent_scratches.sql');

try {
    $pdo->exec($sql);
    echo "Migration executed successfully\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
