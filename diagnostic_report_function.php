<?php
// Test the actual report function
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/ledger_helpers.php';

$pdo = db();

// Copy the exact function from the report
function vfr_get_vehicle_expense_details_test(PDO $pdo, int $vehicleId, string $periodStart, string $periodEnd): array {
    try {
        $exclusionClause = ledger_kpi_exclusion_clause('le');
        
        $sql = "
            SELECT 
                le.id, le.amount, le.description, le.category,
                le.source_event, le.payment_mode, le.posted_at
            FROM ledger_entries le
            LEFT JOIN reservations r 
                ON le.source_type = 'reservation' 
                AND le.source_id = r.id
            WHERE le.txn_type = 'expense'
                AND $exclusionClause
                AND DATE(le.posted_at) BETWEEN :period_start AND :period_end
                AND (
                    (le.source_type = 'reservation' AND r.vehicle_id = :vehicle_id)
                    OR (le.source_type = 'vehicle_expense' AND le.source_id = :vehicle_id)
                )
            ORDER BY le.posted_at DESC
        ";
        
        echo "<h3>SQL Query:</h3>";
        echo "<pre>" . htmlspecialchars($sql) . "</pre>";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'vehicle_id' => $vehicleId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $result;
    } catch (PDOException $e) {
        echo "<p style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
        return [];
    }
}

$vehicleId = 1;
$periodStart = '2026-03-15';
$periodEnd = '2026-04-14';

echo "<h1>Testing vfr_get_vehicle_expense_details() Function</h1>";
echo "<p>Vehicle ID: $vehicleId</p>";
echo "<p>Period: $periodStart to $periodEnd</p>";

$result = vfr_get_vehicle_expense_details_test($pdo, $vehicleId, $periodStart, $periodEnd);

echo "<h2>Results: " . count($result) . " entries</h2>";

if (empty($result)) {
    echo "<p style='color:red; font-weight:bold'>NO ENTRIES RETURNED!</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Amount</th><th>Category</th><th>Description</th><th>Posted At</th></tr>";
    foreach ($result as $r) {
        echo "<tr>";
        echo "<td>{$r['id']}</td>";
        echo "<td>\${$r['amount']}</td>";
        echo "<td>{$r['category']}</td>";
        echo "<td>{$r['description']}</td>";
        echo "<td>{$r['posted_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
