<?php
/**
 * Database Cleanup and Restore Script (Using PDO Multi-Query)
 * 
 * This script will:
 * 1. Drop all tables in the current database
 * 2. Restore from local_db_backup.sql using PDO
 */

require_once __DIR__ . '/config/db.php';

echo "=== Database Cleanup and Restore ===\n\n";

try {
    $pdo = db();
    
    // Step 1: Disable foreign key checks
    echo "Step 1: Disabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "✓ Foreign key checks disabled\n\n";
    
    // Step 2: Get all tables
    echo "Step 2: Getting list of all tables...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Found " . count($tables) . " tables\n\n";
    
    // Step 3: Drop all tables
    if (count($tables) > 0) {
        echo "Step 3: Dropping all tables...\n";
        foreach ($tables as $table) {
            echo "  - Dropping table: $table\n";
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        echo "✓ All tables dropped\n\n";
    } else {
        echo "Step 3: No tables to drop\n\n";
    }
    
    // Step 4: Read backup file
    echo "Step 4: Reading backup file...\n";
    $backupFile = __DIR__ . '/local_db_backup.sql';
    
    if (!file_exists($backupFile)) {
        throw new Exception("Backup file not found: $backupFile");
    }
    
    $sql = file_get_contents($backupFile);
    if ($sql === false) {
        throw new Exception("Failed to read backup file");
    }
    
    echo "✓ Backup file loaded (" . number_format(strlen($sql)) . " bytes)\n\n";
    
    // Step 5: Execute backup SQL using multi-query approach
    echo "Step 5: Restoring database from backup...\n";
    echo "  (This may take a moment...)\n";
    
    // Remove comments and split by semicolons more carefully
    $lines = explode("\n", $sql);
    $currentStatement = '';
    $statements = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines
        if (empty($line)) {
            continue;
        }
        
        // Skip comment lines
        if (substr($line, 0, 2) === '--' || substr($line, 0, 2) === '/*' || substr($line, 0, 1) === '#') {
            continue;
        }
        
        // Add line to current statement
        $currentStatement .= $line . ' ';
        
        // If line ends with semicolon, we have a complete statement
        if (substr(rtrim($line), -1) === ';') {
            $stmt = trim($currentStatement);
            if (!empty($stmt) && $stmt !== ';') {
                $statements[] = $stmt;
            }
            $currentStatement = '';
        }
    }
    
    // Add any remaining statement
    if (!empty(trim($currentStatement))) {
        $statements[] = trim($currentStatement);
    }
    
    echo "  Found " . count($statements) . " SQL statements to execute\n";
    
    // Execute statements
    $executed = 0;
    $errors = 0;
    
    foreach ($statements as $i => $statement) {
        try {
            // Skip SET and other MySQL-specific commands that might cause issues
            if (preg_match('/^(SET|START TRANSACTION|COMMIT|\/\*!)/i', $statement)) {
                continue;
            }
            
            $pdo->exec($statement);
            $executed++;
            
            // Show progress every 100 statements
            if ($executed % 100 === 0) {
                echo "  Executed $executed statements...\n";
            }
        } catch (PDOException $e) {
            $errors++;
            // Only show first few errors to avoid spam
            if ($errors <= 5) {
                echo "  Warning on statement " . ($i + 1) . ": " . $e->getMessage() . "\n";
                echo "  Statement preview: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    echo "✓ Executed $executed SQL statements";
    if ($errors > 0) {
        echo " ($errors warnings)";
    }
    echo "\n\n";
    
    // Step 6: Re-enable foreign key checks
    echo "Step 6: Re-enabling foreign key checks...\n";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "✓ Foreign key checks re-enabled\n\n";
    
    // Step 7: Verify restoration
    echo "Step 7: Verifying restoration...\n";
    $stmt = $pdo->query("SHOW TABLES");
    $newTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Database now has " . count($newTables) . " tables\n\n";
    
    if (count($newTables) === 0) {
        throw new Exception("No tables were restored! Check the backup file format.");
    }
    
    echo "=== SUCCESS ===\n";
    echo "Database has been successfully cleaned and restored from backup!\n\n";
    echo "Restored tables:\n";
    foreach ($newTables as $table) {
        // Get row count
        try {
            $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $countStmt->fetchColumn();
            echo "  - $table ($count rows)\n";
        } catch (PDOException $e) {
            echo "  - $table (error counting rows)\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n=== ERROR ===\n";
    echo "Failed to restore database: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
