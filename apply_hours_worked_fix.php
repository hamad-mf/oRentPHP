<?php
require_once __DIR__ . '/config/db.php';
auth_require_admin();

$pdo = db();

echo "<h1>Apply Hours Worked Display Fix</h1>";
echo "<p>This script will:</p>";
echo "<ol>";
echo "<li>Add hours_worked column to payroll table</li>";
echo "<li>Delete existing March 2026 payroll</li>";
echo "<li>Provide instructions to regenerate payroll</li>";
echo "</ol>";

try {
    // Step 1: Add hours_worked column
    echo "<h2>Step 1: Adding hours_worked column...</h2>";
    
    $hasColumn = (bool) $pdo->query("SHOW COLUMNS FROM payroll LIKE 'hours_worked'")->fetchColumn();
    
    if ($hasColumn) {
        echo "<p style='color: orange;'>✓ Column already exists, skipping...</p>";
    } else {
        $pdo->exec("
            ALTER TABLE payroll 
            ADD COLUMN hours_worked DECIMAL(10,4) DEFAULT NULL COMMENT 'Hours worked for hourly staff (NULL for fixed salary staff)' 
            AFTER basic_salary
        ");
        echo "<p style='color: green;'>✓ Column added successfully!</p>";
    }
    
    // Step 2: Delete March 2026 payroll
    echo "<h2>Step 2: Deleting March 2026 payroll...</h2>";
    
    $stmt = $pdo->prepare("DELETE FROM payroll WHERE month = 3 AND year = 2026");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    
    if ($deleted > 0) {
        echo "<p style='color: green;'>✓ Deleted $deleted payroll record(s) for March 2026</p>";
    } else {
        echo "<p style='color: orange;'>✓ No payroll records found for March 2026</p>";
    }
    
    // Step 3: Instructions
    echo "<h2>Step 3: Next Steps</h2>";
    echo "<div style='background: #f0f0f0; padding: 15px; border-left: 4px solid #4CAF50;'>";
    echo "<p><strong>The fix has been applied successfully!</strong></p>";
    echo "<p>Now you need to regenerate the March 2026 payroll:</p>";
    echo "<ol>";
    echo "<li>Go to <a href='payroll/index.php'>Payroll page</a></li>";
    echo "<li>Click 'Generate Payroll' button</li>";
    echo "<li>Select Month: March, Year: 2026</li>";
    echo "<li>Click 'Prepare Batch'</li>";
    echo "<li>Review the batch - you should see hours displayed for staff1</li>";
    echo "<li>Click 'Save Payroll'</li>";
    echo "<li>Now when you view the payroll list, hours will be displayed!</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h2>What Changed?</h2>";
    echo "<ul>";
    echo "<li>✓ Added hours_worked column to payroll table</li>";
    echo "<li>✓ Updated save logic to store hours_worked when creating payroll</li>";
    echo "<li>✓ Updated display logic to show hours in saved payroll list</li>";
    echo "</ul>";
    
    echo "<h2>Expected Display</h2>";
    echo "<p>For hourly staff, you will now see:</p>";
    echo "<div style='background: #f9f9f9; padding: 10px; border: 1px solid #ddd; font-family: monospace;'>";
    echo "$64.50<br>";
    echo "<span style='font-size: 10px; color: #666;'>64.50h × $500.00/h</span>";
    echo "</div>";
    
    echo "<p style='margin-top: 20px;'><a href='payroll/index.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Payroll →</a></p>";
    
} catch (Throwable $e) {
    echo "<p style='color: red;'>ERROR: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
