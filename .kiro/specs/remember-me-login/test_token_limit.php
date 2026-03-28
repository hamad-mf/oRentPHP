<?php
/**
 * Detailed test for token limit enforcement
 */

require_once __DIR__ . '/../../../config/db.php';
require_once __DIR__ . '/../../../includes/remember_token_helpers.php';

$test_user_id = 1;

echo "Testing Token Limit Enforcement\n";
echo "================================\n\n";

// Cleanup
$pdo = db();
$stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
$stmt->execute([$test_user_id]);

echo "Creating 5 tokens with small delays...\n";
$tokens = [];
for ($i = 0; $i < 5; $i++) {
    $tokens[] = generate_remember_token($test_user_id);
    echo "Token " . ($i + 1) . " created: selector = " . $tokens[$i]['selector'] . "\n";
    usleep(100000); // 100ms delay to ensure different timestamps
}

// Check database
$stmt = $pdo->prepare("SELECT selector, created_at FROM remember_tokens WHERE user_id = ? ORDER BY created_at ASC");
$stmt->execute([$test_user_id]);
$db_tokens = $stmt->fetchAll();

echo "\nTokens in database (ordered by created_at):\n";
foreach ($db_tokens as $idx => $token) {
    echo ($idx + 1) . ". Selector: " . $token['selector'] . " | Created: " . $token['created_at'] . "\n";
}

echo "\nCreating 6th token (should delete oldest)...\n";
$oldest_selector = $tokens[0]['selector'];
echo "Oldest selector (should be deleted): $oldest_selector\n";

$token6 = generate_remember_token($test_user_id);
echo "6th token created: selector = " . $token6['selector'] . "\n";

// Check database again
$stmt->execute([$test_user_id]);
$db_tokens_after = $stmt->fetchAll();

echo "\nTokens in database after creating 6th:\n";
foreach ($db_tokens_after as $idx => $token) {
    echo ($idx + 1) . ". Selector: " . $token['selector'] . " | Created: " . $token['created_at'] . "\n";
}

// Check if oldest was deleted
$stmt = $pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE selector = ?");
$stmt->execute([$oldest_selector]);
$oldest_exists = $stmt->fetchColumn() > 0;

echo "\nOldest token still exists: " . ($oldest_exists ? "YES (FAIL)" : "NO (PASS)") . "\n";

// Check total count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?");
$stmt->execute([$test_user_id]);
$total_count = $stmt->fetchColumn();

echo "Total token count: $total_count (should be 5)\n";

// Cleanup
$stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
$stmt->execute([$test_user_id]);

echo "\nTest complete.\n";
