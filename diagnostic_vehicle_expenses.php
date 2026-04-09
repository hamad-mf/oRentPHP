<?php
/**
 * Diagnostic script to check vehicle expense report issue
 * Run this to see what's happening with the Mercedes-Benz expenses
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'includes/ledger_helpers.php';
require_once 'reports/vehicle_financial.php';

echo "=== VEHICLE EXPENSE DIAGNOSTIC ===\n\n";

// Find Mercedes-Benz vehicle
$sql = "SELECT id, brand, model FROM vehicles WHERE brand LIKE '%Mercedes%' LIMIT 1";
$stmt = $pdo->query($sql);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    echo "ERROR: No Mercedes-Benz vehicle found\n";
    exit(1);
}

echo "Vehicle: {$vehicle['brand']} {$vehicle['model']} (ID: {$vehicle['id']})\n\n";

// Check direct vehicle expenses
echo "--- Direct Vehicle Expenses (source_type='vehicle_expense') ---\n";
$sql = "SELECT id, amount, description, category, posted_at, source_type, source_id 
        FROM ledger_entries 
        WHERE source_type = 'vehicle_expense' 
        AND source_id = :vehicle_id 
        AND txn_type = 'expense'
        ORDER BY posted_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['vehicle_id' => $vehicle['id']]);
$directExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($directExpenses)) {
    echo "No direct expenses found\n";
} else {
    foreach ($directExpenses as $exp) {
        echo "  ID: {$exp['id']}, Amount: \${$exp['amount']}, Date: {$exp['posted_at']}\n";
        echo "  Description: {$exp['description']}\n";
        echo "  Category: {$exp['category']}\n\n";
    }
}

// Check reservation-linked expenses
echo "--- Reservation-Linked Expenses (source_type='reservation') ---\n";
$sql = "SELECT le.id, le.amount, le.description, le.category, le.posted_at, r.vehicle_id
        FROM ledger_entries le
        INNER JOIN reservations r ON le.source_type = 'reservation' AND le.source_id = r.id
        WHERE r.vehicle_id = :vehicle_id 
        AND le.txn_type = 'expense'
        ORDER BY le.posted_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['vehicle_id' => $vehicle['id']]);
$reservationExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($reservationExpenses)) {
    echo "No reservation expenses found\n";
} else {
    foreach ($reservationExpenses as $exp) {
        echo "  ID: {$exp['id']}, Amount: \${$exp['amount']}, Date: {$exp['posted_at']}\n";
        echo "  Description: {$exp['description']}\n\n";
    }
}

// Test the report function with current period
echo "\n--- Testing Report Function ---\n";
$period = period_for_today();
echo "Period: {$period['start']} to {$period['end']}\n\n";

$expenses = vfr_calculate_vehicle_expenses($pdo, $period['start'], $period['end']);
echo "Report shows for vehicle {$vehicle['id']}: $" . ($expenses[$vehicle['id']] ?? 0.00) . "\n\n";

// Test with specific period (15 Mar - 14 Apr 2026)
echo "--- Testing with Period: 2026-03-15 to 2026-04-14 ---\n";
$expenses = vfr_calculate_vehicle_expenses($pdo, '2026-03-15', '2026-04-14');
echo "Report shows for vehicle {$vehicle['id']}: $" . ($expenses[$vehicle['id']] ?? 0.00) . "\n\n";

// Get detailed expenses
$details = vfr_get_vehicle_expense_details($pdo, $vehicle['id'], '2026-03-15', '2026-04-14');
echo "Detailed expense entries: " . count($details) . "\n";
if (!empty($details)) {
    foreach ($details as $detail) {
        echo "  - \${$detail['amount']}: {$detail['description']} ({$detail['posted_at']})\n";
    }
}

echo "\n=== END DIAGNOSTIC ===\n";
