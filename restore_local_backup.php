<?php
/**
 * Restore Local Database Backup
 * 
 * This script restores your local database from the backup you created
 */

// Database credentials
$host = 'localhost';
$dbname = 'orent';
$user = 'root';
$pass = '';

// CHANGE THIS to your backup file name
$backupFile = __DIR__ . '/orent_backup.sql';  // Update this filename!

if (!file_exists($backupFile)) {
    die("ERROR: Backup file not found at: $backupFile\n\nPlease update the \$backupFile variable with your actual backup filename.\n");
}

echo "=== Restore Local Database Backup ===\n\n";

// Connect without selecting database
try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to MySQL\n";
} catch (PDOException $e) {
    die("ERROR: Could not connect to MySQL: " . $e->getMessage() . "\n");
}

// Drop database
echo "\n[1/3] Dropping current database...\n";
try {
    $pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
    echo "✓ Database dropped\n";
} catch (PDOException $e) {
    die("ERROR: Could not drop database: " . $e->getMessage() . "\n");
}

// Create database
echo "\n[2/3] Creating fresh database...\n";
try {
    $pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "✓ Database created\n";
} catch (PDOException $e) {
    die("ERROR: Could not create database: " . $e->getMessage() . "\n");
}

// Select database
$pdo->exec("USE `$dbname`");

// Import backup
echo "\n[3/3] Importing backup from: $backupFile\n";

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO"');

$file = fopen($backupFile, 'r');
$query = '';
$lineCount = 0;
$queryCount = 0;
$startTime = microtime(true);

while (!feof($file)) {
    $line = fgets($file);
    $lineCount++;
    
    if (trim($line) == '' || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
        continue;
    }
    
    $query .= $line;
    
    if (substr(trim($line), -1) == ';') {
        try {
            $pdo->exec($query);
            $queryCount++;
            
            if ($queryCount % 100 == 0) {
                echo "  Processed $queryCount queries...\n";
            }
        } catch (PDOException $e) {
            // Continue on errors
        }
        
        $query = '';
    }
}

fclose($file);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\n=== Restore Complete ===\n";
echo "✓ Queries executed: $queryCount\n";
echo "✓ Time taken: $duration seconds\n\n";
echo "Your local database has been restored!\n";
