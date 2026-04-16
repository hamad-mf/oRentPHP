<?php
require_once __DIR__ . '/config/db.php';

$pdo = db();

echo "Checking staff1 configuration...\n\n";

// Check current configuration
$stmt = $pdo->prepare("
    SELECT u.id, u.name, s.id AS staff_id, s.salary_type, s.hourly_rate
    FROM users u
    JOIN staff s ON s.id = u.staff_id
    WHERE u.id = 2
");
$stmt->execute();
$staff = $stmt->fetch();

if (!$staff) {
    echo "ERROR: Staff with user_id=2 not found!\n";
    exit(1);
}

echo "Current Configuration:\n";
echo "  User ID: {$staff['id']}\n";
echo "  Name: {$staff['name']}\n";
echo "  Staff ID: {$staff['staff_id']}\n";
echo "  Salary Type: " . ($staff['salary_type'] ?? 'NULL') . "\n";
echo "  Hourly Rate: " . ($staff['hourly_rate'] ?? 'NULL') . "\n\n";

// Check if already hourly
if ($staff['salary_type'] === 'hourly' && $staff['hourly_rate'] == 500.00) {
    echo "✓ Staff is already configured as hourly with \$500/hour rate\n";
    echo "The hours should be displaying in the payroll batch screen.\n";
    exit(0);
}

// Apply fix
echo "Applying fix: Setting to hourly with \$500/hour rate...\n";

$updateStmt = $pdo->prepare("
    UPDATE staff 
    SET salary_type = 'hourly',
        hourly_rate = 500.00
    WHERE id = ?
");

if ($updateStmt->execute([$staff['staff_id']])) {
    echo "✓ Successfully updated!\n\n";
    
    // Verify
    $verifyStmt = $pdo->prepare("
        SELECT salary_type, hourly_rate 
        FROM staff 
        WHERE id = ?
    ");
    $verifyStmt->execute([$staff['staff_id']]);
    $updated = $verifyStmt->fetch();
    
    echo "New Configuration:\n";
    echo "  Salary Type: {$updated['salary_type']}\n";
    echo "  Hourly Rate: {$updated['hourly_rate']}\n\n";
    echo "✓ Fix applied successfully!\n";
    echo "Now generate the March 2026 payroll batch to see the hours display.\n";
} else {
    echo "✗ Failed to update staff record\n";
    exit(1);
}
