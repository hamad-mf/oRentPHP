<?php
require_once 'config/db.php';
$pdo = db();
require_once 'includes/ledger_helpers.php';

$pageTitle = 'Diagnostic - Vehicle Expense Report';
require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6 p-6">
    <h1 class="text-2xl text-white">Vehicle Expense Report - Diagnostic</h1>
    
    <?php
    // Get Mercedes vehicle
    $vehicle = $pdo->query("SELECT * FROM vehicles WHERE id = 1")->fetch();
    
    if (!$vehicle) {
        echo "<p class='text-red-400'>Mercedes vehicle not found!</p>";
        require_once 'includes/footer.php';
        exit;
    }
    
    echo "<div class='bg-mb-surface p-4 rounded-lg border border-mb-subtle/20'>";
    echo "<h2 class='text-white font-medium mb-2'>Vehicle: {$vehicle['brand']} {$vehicle['model']}</h2>";
    
    // Check direct expenses
    $sql = "SELECT id, amount, description, posted_at 
            FROM ledger_entries 
            WHERE source_type = 'vehicle_expense' 
            AND source_id = 1 
            AND txn_type = 'expense'
            ORDER BY posted_at DESC";
    $expenses = $pdo->query($sql)->fetchAll();
    
    echo "<h3 class='text-mb-silver mt-4 mb-2'>Direct Vehicle Expenses:</h3>";
    echo "<div class='space-y-2'>";
    $total = 0;
    foreach ($expenses as $exp) {
        echo "<div class='text-sm'>";
        echo "<span class='text-green-400'>\${$exp['amount']}</span> - ";
        echo "<span class='text-white'>{$exp['description']}</span> ";
        echo "<span class='text-mb-subtle'>({$exp['posted_at']})</span>";
        echo "</div>";
        $total += $exp['amount'];
    }
    echo "<div class='mt-2 pt-2 border-t border-mb-subtle/20 text-white font-medium'>Total: \$$total</div>";
    echo "</div>";
    
    // Test the report function
    echo "<h3 class='text-mb-silver mt-6 mb-2'>Report Function Test (Period: 15 Mar - 14 Apr 2026):</h3>";
    
    require_once 'reports/vehicle_financial.php';
    
    try {
        $reportExpenses = vfr_calculate_vehicle_expenses($pdo, '2026-03-15', '2026-04-14');
        $mercedesTotal = $reportExpenses[1] ?? 0;
        
        echo "<div class='p-3 rounded " . ($mercedesTotal > 0 ? "bg-green-500/10 border border-green-500/30" : "bg-red-500/10 border border-red-500/30") . "'>";
        echo "<p class='text-white'>Report shows: <span class='font-bold'>\$$mercedesTotal</span></p>";
        
        if ($mercedesTotal == 15000) {
            echo "<p class='text-green-400 mt-2'>✓ FIX IS WORKING! Both expenses are included.</p>";
        } elseif ($mercedesTotal == 5000) {
            echo "<p class='text-yellow-400 mt-2'>⚠ Only showing Apr 4 expense. Apr 7 expense missing.</p>";
        } elseif ($mercedesTotal == 0) {
            echo "<p class='text-red-400 mt-2'>✗ NO EXPENSES SHOWING! Fix not working.</p>";
        } else {
            echo "<p class='text-yellow-400 mt-2'>? Unexpected amount</p>";
        }
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='p-3 rounded bg-red-500/10 border border-red-500/30'>";
        echo "<p class='text-red-400'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "</div>";
    }
    
    echo "</div>";
    ?>
    
    <div class="bg-mb-surface p-4 rounded-lg border border-mb-subtle/20">
        <h3 class="text-white font-medium mb-2">Next Steps:</h3>
        <ol class="list-decimal list-inside space-y-2 text-sm text-mb-subtle">
            <li>If you see "$15,000" above, the fix is working correctly</li>
            <li>Go to <a href="reports/vehicle_financial.php" class="text-mb-accent hover:underline">Vehicle Financial Report</a></li>
            <li>Select period "15 Mar - 14 Apr 2026"</li>
            <li>Look for Mercedes-Benz in the table</li>
            <li>Click on the expense amount to see the drill-down</li>
            <li>If still showing $0, try hard refresh (Ctrl+F5)</li>
        </ol>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
