<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/client_helpers.php';

auth_check();

echo "<h1>Client Photo Diagnostic</h1>";
echo "<pre>";

$pdo = db();

// Test 1: Direct column check
echo "=== Test 1: Direct INFORMATION_SCHEMA Query ===\n";
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
          AND COLUMN_NAME = 'photo'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Result: " . print_r($result, true) . "\n";
    echo "Column exists: " . ($result['count'] > 0 ? 'YES' : 'NO') . "\n\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}

// Test 2: Using helper function
echo "=== Test 2: clients_has_column() Function ===\n";
try {
    $supportsClientPhoto = clients_has_column($pdo, 'photo');
    echo "clients_has_column(\$pdo, 'photo') = " . ($supportsClientPhoto ? 'TRUE' : 'FALSE') . "\n\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}

// Test 3: Check all clients columns
echo "=== Test 3: All Clients Table Columns ===\n";
try {
    $stmt = $pdo->query("
        SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'clients'
        ORDER BY ORDINAL_POSITION
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['COLUMN_NAME']} ({$col['DATA_TYPE']}) " . 
             ($col['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
    echo "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
}

// Test 4: Check database name
echo "=== Test 4: Current Database ===\n";
try {
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $dbName = $stmt->fetchColumn();
    echo "Database: $dbName\n\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
}

// Test 5: Check if photo column exists via DESCRIBE
echo "=== Test 5: DESCRIBE clients Table ===\n";
try {
    $stmt = $pdo->query("DESCRIBE clients");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $photoFound = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'photo') {
            $photoFound = true;
            echo "PHOTO COLUMN FOUND:\n";
            echo print_r($col, true) . "\n";
        }
    }
    if (!$photoFound) {
        echo "Photo column NOT found in DESCRIBE output\n";
    }
    echo "\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
}

echo "</pre>";
?>
