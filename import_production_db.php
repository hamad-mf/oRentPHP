<?php
/**
 * Import Production Database to Localhost
 * 
 * This script imports a large SQL file by reading it line by line
 * to avoid memory issues with huge files.
 * 
 * Usage: php import_production_db.php
 */

require_once __DIR__ . '/config/db.php';

// Configuration
$sqlFile = __DIR__ . '/u230826074_orentin.sql';  // Your production SQL file

if (!file_exists($sqlFile)) {
    die("ERROR: SQL file not found at: $sqlFile\n");
}

echo "Starting import of: $sqlFile\n";
echo "Database: " . DB_NAME . "\n";
echo "Host: " . DB_HOST . "\n\n";

$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Disable foreign key checks during import
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"');
$pdo->exec('SET time_zone = "+05:30"');

$file = fopen($sqlFile, 'r');
$query = '';
$lineCount = 0;
$queryCount = 0;
$startTime = microtime(true);

echo "Importing...\n";

while (!feof($file)) {
    $line = fgets($file);
    $lineCount++;
    
    // Skip comments and empty lines
    if (trim($line) == '' || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
        continue;
    }
    
    $query .= $line;
    
    // Check if query is complete (ends with semicolon)
    if (substr(trim($line), -1) == ';') {
        try {
            $pdo->exec($query);
            $queryCount++;
            
            // Progress indicator every 100 queries
            if ($queryCount % 100 == 0) {
                echo "Processed $queryCount queries ($lineCount lines)...\n";
            }
        } catch (PDOException $e) {
            echo "ERROR at line $lineCount: " . $e->getMessage() . "\n";
            echo "Query: " . substr($query, 0, 200) . "...\n\n";
            // Continue with next query instead of stopping
        }
        
        $query = '';
    }
}

fclose($file);

// Re-enable foreign key checks
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n✓ Import completed!\n";
echo "Total lines processed: $lineCount\n";
echo "Total queries executed: $queryCount\n";
echo "Time taken: $duration seconds\n";
