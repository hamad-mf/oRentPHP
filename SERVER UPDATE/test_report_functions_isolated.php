<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
$pdo = db();

// Copy the functions here to test them in isolation
function ledger_kpi_exclusion_clause_test(string $alias = ''): string {
    $prefix = trim($alias);
    if ($prefix !== '') $prefix .= '.';
    return "({$prefix}category NOT LIKE '%Transfer%')";
}

function vfr_calculate_vehicle_expenses_test(PDO $pdo, string $periodStart, string $periodEnd): array {
    try {
        $exclusionClause = ledger_kpi_exclusion_clause_test('le');
        
        $sql = "
            SELECT 
                vehicle_id,
                SUM(total_expense) AS total_expense
            FROM (
                -- Reservation-linked expenses
                SELECT 
                    r.vehicle_id,
                    le.amount AS total_expense
                FROM ledger_entries le
                INNER JOIN reservations r 
                    ON le.source_type = 'reservation' 
                    AND le.source_id = r.id
                WHERE le.txn_type = 'expense'
                    AND $exclusionClause
                    AND le.category NOT LIKE '%Security Deposit%'
                    AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
                    AND r.vehicle_id IS NOT NULL
                
                UNION ALL
                
                -- Direct vehicle expenses
                SELECT 
                    le.source_id AS vehicle_id,
                    le.amount AS total_expense
                FROM ledger_entries le
                WHERE le.source_type = 'vehicle_expense'
                    AND le.txn_type = 'expense'
                    AND $exclusionClause
                    AND le.category NOT LIKE '%Security Deposit%'
                    AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
            ) AS combined_expenses
            GROUP BY vehicle_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
        
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[(int)$row['vehicle_id']] = (float)$row['total_expense'];
        }
        
        return $result;
    } catch (PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        return [];
    }
}

echo "=== TESTING REPORT FUNCTION ===\n\n";

$expenses = vfr_calculate_vehicle_expenses_test($pdo, '2026-03-15', '2026-04-14');

echo "Mercedes (ID 1) total expenses: $" . ($expenses[1] ?? 0.00) . "\n";
echo "Expected: $15,000.00 (both Apr 4 and Apr 7 expenses)\n\n";

if (isset($expenses[1]) && $expenses[1] == 15000.00) {
    echo "✓ FIX IS WORKING CORRECTLY!\n";
} else {
    echo "✗ Something is wrong\n";
}
