<?php
/**
 * Diagnostic script to check why Security Deposit section is/isn't showing
 * for a specific reservation
 */

require_once __DIR__ . '/config/db.php';

$reservationId = 153; // The reservation the client is looking at

$pdo = db();

echo "=== DIAGNOSTIC: Security Deposit Section Visibility ===\n\n";

// Check if reservation exists
$stmt = $pdo->prepare('SELECT * FROM reservations WHERE id = ?');
$stmt->execute([$reservationId]);
$r = $stmt->fetch();

if (!$r) {
    echo "ERROR: Reservation #$reservationId not found!\n";
    exit(1);
}

echo "Reservation #$reservationId found\n";
echo "Status: {$r['status']}\n";
echo "Client ID: {$r['client_id']}\n\n";

// Check the key field that controls visibility
$depositAmount = (float) ($r['deposit_amount'] ?? 0);
echo "--- VISIBILITY CONDITION ---\n";
echo "deposit_amount: " . number_format($depositAmount, 2) . "\n";
echo "Condition: if ((float) (\$r['deposit_amount'] ?? 0) > 0)\n";
echo "Result: " . ($depositAmount > 0 ? "TRUE - Section SHOULD be visible" : "FALSE - Section will be HIDDEN") . "\n\n";

// Check all deposit-related columns
echo "--- ALL DEPOSIT COLUMNS ---\n";
$depositColumns = [
    'deposit_amount',
    'deposit_returned',
    'deposit_deducted', 
    'deposit_held',
    'deposit_hold_reason',
    'deposit_held_at',
    'deposit_used_for_extension'
];

foreach ($depositColumns as $col) {
    if (array_key_exists($col, $r)) {
        $val = $r[$col];
        if (is_numeric($val)) {
            echo "$col: " . number_format((float) $val, 2) . "\n";
        } else {
            echo "$col: " . ($val ?? 'NULL') . "\n";
        }
    } else {
        echo "$col: [COLUMN DOES NOT EXIST IN DATABASE]\n";
    }
}

// Check if columns exist in schema
echo "\n--- SCHEMA CHECK ---\n";
$schemaStmt = $pdo->query("SHOW COLUMNS FROM reservations LIKE 'deposit_%'");
$existingCols = $schemaStmt->fetchAll(PDO::FETCH_COLUMN);
echo "Deposit columns in schema: " . implode(', ', $existingCols) . "\n";

// Calculate remaining deposit
if ($depositAmount > 0) {
    echo "\n--- DEPOSIT CALCULATION ---\n";
    $returned = (float) ($r['deposit_returned'] ?? 0);
    $deducted = (float) ($r['deposit_deducted'] ?? 0);
    $held = (float) ($r['deposit_held'] ?? 0);
    $usedForExt = 0.0;
    if (in_array('deposit_used_for_extension', $existingCols)) {
        $usedForExt = (float) ($r['deposit_used_for_extension'] ?? 0);
    }
    
    $remaining = $depositAmount - $returned - $deducted - $held - $usedForExt;
    
    echo "Original deposit: " . number_format($depositAmount, 2) . "\n";
    echo "Already returned: " . number_format($returned, 2) . "\n";
    echo "Already deducted: " . number_format($deducted, 2) . "\n";
    echo "Already held: " . number_format($held, 2) . "\n";
    echo "Used for extension: " . number_format($usedForExt, 2) . "\n";
    echo "Remaining: " . number_format($remaining, 2) . "\n";
}

echo "\n=== END DIAGNOSTIC ===\n";
