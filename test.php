<?php
// Temporary debug file - DELETE after fixing!
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background:#111;color:#0f0;padding:20px;font-family:monospace'>";
echo "PHP Version: " . PHP_VERSION . "\n\n";

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=u230826074_orentin;charset=utf8mb4',
        'u230826074_orentin',
        'Jazir@123gold',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ DB Connected successfully\n\n";

    // List all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found (" . count($tables) . "):\n";
    foreach ($tables as $t) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "  - $t ($count rows)\n";
    }

    // Test the queries from index.php
    echo "\n--- Index.php Query Tests ---\n";
    echo "vehicles: " . $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn() . "\n";
    echo "available: " . $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='available'")->fetchColumn() . "\n";
    echo "rented: " . $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='rented'")->fetchColumn() . "\n";

} catch (PDOException $e) {
    echo "❌ DB Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>