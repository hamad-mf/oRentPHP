<?php
/**
 * Generates a complete wipe_and_reset.sql from the live database.
 * Run: php generate_wipe_sql.php
 */
require "config/db.php";
$pdo = db();
$outFile = "wipe_and_reset.sql";

ob_start();

echo "-- ============================================================\n";
echo "-- oRentPHP -- Full Database Wipe + Fresh Start\n";
echo "--   THIS DELETES ALL DATA. Run an Excel export first!\n";
echo "-- Steps:\n";
echo "--   1. (LOCAL)      phpMyAdmin  select DB  SQL tab  paste  Go\n";
echo "--   2. (PRODUCTION) Hostinger phpMyAdmin  select DB  SQL tab  paste  Go\n";
echo "-- After running: log in as  admin / admin123\n";
echo "-- Then immediately change the admin password in Settings.\n";
echo "-- ============================================================\n\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
sort($tables);

echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";
echo "--  Drop all tables (" . count($tables) . " total) \n";
foreach (array_reverse($tables) as $t) {
    echo "DROP TABLE IF EXISTS `$t`;\n";
}
echo "\nSET FOREIGN_KEY_CHECKS = 1;\n\n";

echo "--  Create all tables \n\n";
foreach ($tables as $t) {
    $row = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
    $ddl = $row[1];
    // Strip current AUTO_INCREMENT value so fresh installs start from 1
    $ddl = preg_replace('/\s*AUTO_INCREMENT=\d+/i', '', $ddl);
    echo "-- TABLE: $t\n";
    echo $ddl . ";\n\n";
}

echo "--  Seed: default settings \n";
$defaultSettings = [
    ['late_return_rate_per_hour',  '0'],
    ['deposit_percentage',         '0'],
    ['delivery_charge_default',    '0'],
    ['lead_incentive_per_lead',    '0'],
    ['delivery_incentive_per_delivery', '0'],
    ['per_page',                   '25'],
    ['lead_sources',               '[{"value":"walk_in","label":"Walk-in"},{"value":"phone","label":"Phone Call"},{"value":"whatsapp","label":"WhatsApp"},{"value":"instagram","label":"Instagram"},{"value":"referral","label":"Referral"},{"value":"website","label":"Website"},{"value":"other","label":"Other"}]'],
];
echo "INSERT INTO `system_settings` (`key`, `value`) VALUES\n";
$parts = [];
foreach ($defaultSettings as [$k, $v]) {
    $parts[] = "  ('" . $k . "', '" . addslashes($v) . "')";
}
echo implode(",\n", $parts) . "\n";
echo "ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);\n\n";

echo "--  Seed: default bank account \n";
echo "INSERT INTO `bank_accounts` (`name`, `balance`, `is_active`, `created_at`) VALUES ('Main Cash', 0.00, 1, NOW());\n\n";

echo "--  Seed: admin account (password: admin123) \n";
echo "-- Change password immediately after first login via Settings.\n";
$hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXFAxoyI';
echo "INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`, `is_active`, `created_at`)\n";
echo "VALUES ('Admin', 'admin', 'admin@orent.local', '$hash', 'admin', 1, NOW());\n";

$sql = ob_get_clean();
file_put_contents($outFile, $sql);
$lines = count(file($outFile));
echo "Done. Written to $outFile ($lines lines, " . strlen($sql) . " bytes)\n";

// Quick verify
$creates = substr_count($sql, 'CREATE TABLE');
$drops = substr_count($sql, 'DROP TABLE');
echo "CREATE TABLE: $creates | DROP TABLE: $drops\n";
