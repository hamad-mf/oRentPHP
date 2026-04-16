<?php
require_once 'config/db.php';

$pdo = db();

echo "=== staff_attendance table structure ===\n";
$stmt = $pdo->query('DESCRIBE staff_attendance');
while ($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}

echo "\n=== attendance_breaks table structure ===\n";
$stmt = $pdo->query('DESCRIBE attendance_breaks');
while ($row = $stmt->fetch()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
