<?php
/**
 * compare_schema_vs_wipe.php
 * Extracts all CREATE TABLE + ADD COLUMN definitions from PHP source files
 * and compares them against wipe_and_reset.sql column by column.
 */

//  Step 1: Parse wipe_and_reset.sql 
$wipeSql = file_get_contents(__DIR__ . "/wipe_and_reset.sql");
preg_match_all('/-- TABLE: (\w+)\s+CREATE TABLE[^(]+\((.+?)\)\s*ENGINE/si', $wipeSql, $wm);
$wipeSchema = [];
for ($i = 0; $i < count($wm[1]); $i++) {
    $tbl  = $wm[1][$i];
    $body = $wm[2][$i];
    preg_match_all('/^\s+`([a-z_][a-z0-9_]*)`\s+(?:int|bigint|smallint|tinyint|varchar|char|text|longtext|decimal|float|double|date|datetime|timestamp|enum|blob|json|time)/im', $body, $cols);
    $wipeSchema[$tbl] = array_flip($cols[1]);
}
echo "Wipe SQL tables parsed: " . count($wipeSchema) . "\n\n";

//  Step 2: Extract from PHP source files ─
$phpFiles = [
    "includes/ledger_helpers.php",
    "includes/settings_helpers.php",
    "includes/gps_helpers.php",
    "includes/notifications.php",
    "includes/voucher_helpers.php",
    "includes/vehicle_helpers.php",
    "includes/reservation_payment_helpers.php",
    "includes/activity_log.php",
    "attendance_migrate.php",
    "auth_migrate.php",
    "investments/index.php",
    "accounts/targets.php",
    "attendance/index.php",
    "leads/pipeline.php",
    "vehicles/requests.php",
    "reservations/deliver.php",
    "reservations/return.php",
    "vehicles/catalog.php",
];

$phpSchema = []; // table => [col => file]

foreach ($phpFiles as $rel) {
    $path = __DIR__ . "/" . $rel;
    if (!file_exists($path)) continue;
    $src = file_get_contents($path);
    
    // Find CREATE TABLE blocks
    preg_match_all('/CREATE TABLE(?:\s+IF NOT EXISTS)?\s+`?(\w+)`?\s*\((.+?)\)\s*(?:ENGINE|;)/si', $src, $ctm);
    for ($i = 0; $i < count($ctm[1]); $i++) {
        $tbl  = $ctm[1][$i];
        $body = $ctm[2][$i];
        preg_match_all('/^\s+`?([a-z_][a-z0-9_]*)`?\s+(?:INT|BIGINT|SMALLINT|TINYINT|VARCHAR|CHAR|TEXT|LONGTEXT|DECIMAL|FLOAT|DOUBLE|DATE|DATETIME|TIMESTAMP|ENUM|BLOB|json|time)/im', $body, $cols);
        foreach ($cols[1] as $col) {
            $phpSchema[$tbl][$col] = $rel;
        }
    }
    
    // Find ADD COLUMN IF NOT EXISTS
    preg_match_all('/ALTER TABLE\s+`?(\w+)`?\s+ADD COLUMN IF NOT EXISTS\s+`?([a-z_][a-z0-9_]*)`?/i', $src, $acm);
    for ($i = 0; $i < count($acm[1]); $i++) {
        $phpSchema[$acm[1][$i]][$acm[2][$i]] = $rel;
    }
    
    // Find INSERT INTO staff_activity_log style table creation hints
    preg_match_all('/INSERT INTO\s+`?(\w+)`?\s*\(([^)]+)\)/i', $src, $ins);
    // skip inserts for now
}

echo "PHP-defined tables: " . count($phpSchema) . "\n\n";

//  Step 3: Compare 
echo "=== COMPARISON: PHP code columns vs wipe_and_reset.sql ===\n\n";
$issues = 0;

foreach ($phpSchema as $tbl => $cols) {
    // Skip internal/debug tables
    if (in_array($tbl, ['example', 'test', 'foo'])) continue;
    
    if (!isset($wipeSchema[$tbl])) {
        echo " TABLE MISSING from wipe SQL: $tbl\n";
        $issues++;
        continue;
    }
    
    $missingCols = [];
    foreach (array_keys($cols) as $col) {
        if (!isset($wipeSchema[$tbl][$col])) {
            $missingCols[] = $col . "  (from: " . $cols[$col] . ")";
        }
    }
    if ($missingCols) {
        echo "  $tbl — missing columns in wipe SQL:\n";
        foreach ($missingCols as $mc) echo "     - $mc\n";
        $issues++;
    }
}

if ($issues === 0) {
    echo " All PHP-defined tables and columns are present in wipe_and_reset.sql!\n";
}

echo "\n--- PHP schema tables: " . count($phpSchema) . " | Wipe SQL tables: " . count($wipeSchema) . " ---\n";
echo "Tables in wipe SQL not covered by PHP schema scan:\n";
foreach (array_keys($wipeSchema) as $t) {
    if (!isset($phpSchema[$t])) echo "  - $t  (auto-managed, no ensure_schema)\n";
}

