<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/ledger_helpers.php';
require_once 'reports/vehicle_financial.php';

$pdo = db();

echo "=== TESTING VEHICLE FINANCIAL REPORT ===\n\n";

// Test with period 15 Mar - 14 Apr 2026
echo "Period: 2026-03-15 to 2026-04-14\n";
echo "Expected: Should include $5,000 expense (Apr 4) but NOT $10,000 (Apr 7)\n\n";

$expenses = vfr_calculate_vehicle_expenses($pdo, '2026-03-15', '2026-04-14');

echo "Mercedes (ID: 1) expenses: $" . ($expenses[1] ?? 0.00) . "\n\n";

// Get details
$details = vfr_get_vehicle_expense_details($pdo, 1, '2026-03-15', '2026-04-14');
echo "Expense entries found: " . count($details) . "\n";
foreach ($details as $detail) {
    echo "  - \${$detail['amount']}: {$detail['description']} ({$detail['posted_at']})\n";
}

echo "\n--- Testing with current period (15 Mar - 14 Apr 2026) ---\n";
echo "This should show $5,000 (the Apr 4 expense)\n\n";

// Test with period that includes today
echo "\n--- Testing with period 15 Mar - 15 Apr 2026 ---\n";
echo "Expected: Should include BOTH $5,000 (Apr 4) AND $10,000 (Apr 7)\n\n";

$expenses2 = vfr_calculate_vehicle_expenses($pdo, '2026-03-15', '2026-04-15');
echo "Mercedes (ID: 1) expenses: $" . ($expenses2[1] ?? 0.00) . "\n\n";

$details2 = vfr_get_vehicle_expense_details($pdo, 1, '2026-03-15', '2026-04-15');
echo "Expense entries found: " . count($details2) . "\n";
foreach ($details2 as $detail) {
    echo "  - \${$detail['amount']}: {$detail['description']} ({$detail['posted_at']})\n";
}
