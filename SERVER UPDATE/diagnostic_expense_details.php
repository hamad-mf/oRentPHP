<?php
// Minimal diagnostic - no auth required
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/includes/ledger_helpers.php';
    
    $pdo = db();
    $vehicleId = 1;
    $periodStart = '2026-03-15';
    $periodEnd = '2026-04-14';
    
    echo "<h1>Diagnostic: Mercedes Expense Details</h1>";
    
    $exclusionClause = ledger_kpi_exclusion_clause('le');
    echo "<p>Exclusion clause: <code>$exclusionClause</code></p>";
    
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
    
    echo "<h2>SQL Query:</h2>";
    echo "<pre>" . htmlspecialchars($sql) . "</pre>";
    
    echo "<h2>Parameters:</h2>";
    echo "<ul>";
    echo "<li>vehicle_id: $vehicleId</li>";
    echo "<li>period_start: $periodStart</li>";
    echo "<li>period_end: $periodEnd</li>";
    echo "</ul>";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'vehicle_id' => $vehicleId,
        'period_start' => $periodStart,
        'period_end' => $periodEnd
    ]);
    
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Results: " . count($details) . " entries</h2>";
    
    if (empty($details)) {
        echo "<p style='color: red;'>✗ NO ENTRIES RETURNED</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Amount</th><th>Category</th><th>Description</th><th>Posted At</th></tr>";
        foreach ($details as $d) {
            echo "<tr>";
            echo "<td>{$d['id']}</td>";
            echo "<td>\${$d['amount']}</td>";
            echo "<td>{$d['category']}</td>";
            echo "<td>{$d['description']}</td>";
            echo "<td>{$d['posted_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
