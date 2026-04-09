<?php
require_once __DIR__ . '/config/db.php';
$pdo = db();

echo "=== CHECKING CREDIT PAYMENT ALLOCATIONS ===\n\n";

// Check if tables exist
echo "1. Checking if credit_payment_allocations table exists:\n";
$tableCheck = $pdo->query("SHOW TABLES LIKE 'credit_payment_allocations'")->fetch();
echo $tableCheck ? "✓ Table exists\n\n" : "✗ Table does NOT exist\n\n";

// Check if is_legacy_payment column exists
echo "2. Checking if is_legacy_payment column exists:\n";
$columnCheck = $pdo->query("SHOW COLUMNS FROM ledger_entries LIKE 'is_legacy_payment'")->fetch();
echo $columnCheck ? "✓ Column exists\n\n" : "✗ Column does NOT exist\n\n";

// Check legacy payments
echo "3. Legacy payments marked:\n";
$legacyPayments = $pdo->query("
    SELECT id, amount, posted_at, description, is_legacy_payment
    FROM ledger_entries
    WHERE payment_mode = 'credit'
      AND txn_type = 'expense'
      AND source_event = 'credit_payment_settlement'
      AND voided_at IS NULL
    ORDER BY posted_at DESC
")->fetchAll();

foreach ($legacyPayments as $payment) {
    $legacy = $payment['is_legacy_payment'] ? '✓ LEGACY' : '✗ NOT LEGACY';
    echo "  Payment ID {$payment['id']}: \${$payment['amount']} at {$payment['posted_at']} - {$legacy}\n";
}
echo "\n";

// Check allocations
echo "4. Allocations created:\n";
$allocations = $pdo->query("
    SELECT 
        cpa.id,
        cpa.allocated_amount,
        le_income.id as income_id,
        le_income.amount as income_amount,
        le_income.description as income_desc,
        le_payment.id as payment_id,
        le_payment.amount as payment_amount
    FROM credit_payment_allocations cpa
    JOIN ledger_entries le_income ON le_income.id = cpa.credit_income_entry_id
    JOIN ledger_entries le_payment ON le_payment.id = cpa.credit_payment_entry_id
    ORDER BY cpa.id DESC
    LIMIT 10
")->fetchAll();

if (empty($allocations)) {
    echo "  ✗ NO allocations found!\n\n";
} else {
    echo "  ✓ Found " . count($allocations) . " allocations (showing last 10):\n";
    foreach ($allocations as $alloc) {
        echo "    Allocation #{$alloc['id']}: Payment #{$alloc['payment_id']} (\${$alloc['payment_amount']}) → Income #{$alloc['income_id']} (\${$alloc['income_amount']}) = \${$alloc['allocated_amount']}\n";
    }
    echo "\n";
}

// Check specific entry payment status
echo "5. Checking payment status for credit income entries (first 5):\n";
$entries = $pdo->query("
    SELECT 
        le.id,
        le.amount as entry_amount,
        le.description,
        le.posted_at,
        COALESCE(SUM(cpa.allocated_amount), 0) as allocated,
        le.amount - COALESCE(SUM(cpa.allocated_amount), 0) as remaining
    FROM ledger_entries le
    LEFT JOIN credit_payment_allocations cpa ON cpa.credit_income_entry_id = le.id
    WHERE le.payment_mode = 'credit'
      AND le.txn_type = 'income'
      AND le.voided_at IS NULL
    GROUP BY le.id, le.amount, le.description, le.posted_at
    ORDER BY le.posted_at DESC
    LIMIT 5
")->fetchAll();

foreach ($entries as $entry) {
    $status = $entry['remaining'] <= 0 ? '✓ FULLY PAID' : ($entry['allocated'] > 0 ? "⚠ PARTIAL (\${$entry['allocated']}/\${$entry['entry_amount']})" : '✗ UNPAID');
    echo "  Entry #{$entry['id']}: \${$entry['entry_amount']} - {$status} (Remaining: \${$entry['remaining']})\n";
    echo "    Description: {$entry['description']}\n";
}
echo "\n";

// Check totals
echo "6. Validation - Totals:\n";
$totalIncome = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='income' AND voided_at IS NULL")->fetchColumn();
$totalExpense = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='expense' AND voided_at IS NULL")->fetchColumn();
$totalAllocated = $pdo->query("SELECT COALESCE(SUM(allocated_amount), 0) FROM credit_payment_allocations")->fetchColumn();
$totalLegacyPayments = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='expense' AND source_event='credit_payment_settlement' AND voided_at IS NULL AND is_legacy_payment=1")->fetchColumn();

echo "  Credit Income: \${$totalIncome}\n";
echo "  Credit Expense: \${$totalExpense}\n";
echo "  Net Credit: \$" . ($totalIncome - $totalExpense) . "\n";
echo "  Total Allocated: \${$totalAllocated}\n";
echo "  Total Legacy Payments: \${$totalLegacyPayments}\n";
echo "  Allocation Match: " . (abs($totalAllocated - $totalLegacyPayments) < 0.02 ? '✓ PASS' : '✗ FAIL') . "\n";
