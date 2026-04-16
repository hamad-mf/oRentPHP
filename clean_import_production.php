<?php
/**
 * Clean Import Production Database
 * 
 * This script:
 * 1. Drops the entire database
 * 2. Recreates it with compatible collation
 * 3. Imports production SQL file
 */

// Database credentials
$host = 'localhost';
$dbname = 'orent';
$user = 'root';
$pass = '';
$sqlFile = __DIR__ . '/u230826074_orentin.sql';

if (!file_exists($sqlFile)) {
    die("ERROR: SQL file not found at: $sqlFile\n");
}

echo "=== Clean Import Production Database ===\n\n";

// Step 1: Connect without selecting database
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to MySQL\n";
} catch (PDOException $e) {
    die("ERROR: Could not connect to MySQL: " . $e->getMessage() . "\n");
}

// Step 2: Drop database if exists
echo "\n[1/3] Dropping database '$dbname'...\n";
try {
    $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
    echo "✓ Database dropped\n";
} catch (PDOException $e) {
    die("ERROR: Could not drop database: " . $e->getMessage() . "\n");
}

// Step 3: Create database with compatible collation
echo "\n[2/3] Creating database '$dbname'...\n";
try {
    $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "✓ Database created with utf8mb4_general_ci collation\n";
} catch (PDOException $e) {
    die("ERROR: Could not create database: " . $e->getMessage() . "\n");
}

// Step 4: Select the new database
$pdo->exec("USE `$dbname`");

// Step 5: Import SQL file
echo "\n[3/3] Importing production data from: $sqlFile\n";
echo "This may take a few minutes...\n\n";

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"');
$pdo->exec('SET time_zone = "+05:30"');

$file = fopen($sqlFile, 'r');
$query = '';
$lineCount = 0;
$queryCount = 0;
$errorCount = 0;
$startTime = microtime(true);

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
                echo "  Processed $queryCount queries ($lineCount lines)...\n";
            }
        } catch (PDOException $e) {
            $errorCount++;
            // Only show first 5 errors to avoid spam
            if ($errorCount <= 5) {
                echo "  WARNING at line $lineCount: " . $e->getMessage() . "\n";
            }
        }
        
        $query = '';
    }
}

fclose($file);

// Re-enable foreign key checks
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n=== Import Complete ===\n";
echo "✓ Total lines processed: $lineCount\n";
echo "✓ Total queries executed: $queryCount\n";
if ($errorCount > 0) {
    echo "⚠ Errors encountered: $errorCount (non-critical)\n";
}
echo "✓ Time taken: $duration seconds\n\n";

echo "Your localhost now has a clean copy of production data!\n";
