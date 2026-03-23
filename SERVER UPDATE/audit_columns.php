<?php
/**
 * audit_columns.php
 * Scans all PHP files for SQL column references per table,
 * then compares against INFORMATION_SCHEMA to report missing columns.
 *
 * Run: php audit_columns.php
 */

// ── DB connection ──────────────────────────────────────────────
$host = '127.0.0.1';
$db = 'orent';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// ── 1. Fetch all existing columns from INFORMATION_SCHEMA ──────
$liveSchema = [];
$rows = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = '$db'
    ORDER BY TABLE_NAME, ORDINAL_POSITION
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $liveSchema[$row['TABLE_NAME']][$row['COLUMN_NAME']] = true;
}

echo "\n✅ Loaded " . array_sum(array_map('count', $liveSchema)) . " columns across " . count($liveSchema) . " tables from DB.\n\n";

// ── 2. Scan PHP files ──────────────────────────────────────────
$phpRoot = __DIR__;
$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($phpRoot));
$files = [];
foreach ($phpFiles as $f) {
    if ($f->isFile() && $f->getExtension() === 'php') {
        $path = $f->getRealPath();
        // skip vendor/node_modules
        if (str_contains($path, 'vendor') || str_contains($path, 'node_modules'))
            continue;
        $files[] = $path;
    }
}
echo "🔍 Scanning " . count($files) . " PHP files...\n\n";

// ── 3. Table → columns mapping from PHP code ──────────────────
// We look for:
//   INSERT INTO table_name (col1, col2, ...)
//   UPDATE table_name SET col1=?, col2=?
//   SELECT ... FROM table_name (alias)
//   r.col_name , v.col_name , s.col_name  etc.  (alias dot notation)
//   explicit column names in WHERE/ON clauses

// Map aliases → table names (common ones in this project)
$aliasMap = [
    'r' => 'reservations',
    'v' => 'vehicles',
    'c' => 'clients',
    's' => 'staff',
    'u' => 'users',
    'le' => 'ledger_entries',
    'ba' => 'bank_accounts',
    'l' => 'leads',
    'e' => 'expenses',
    'i' => 'investments',
    'a' => 'staff_attendance',
    'p' => 'payroll',
];

$codeColumns = []; // table -> set of columns

foreach ($files as $file) {
    $src = file_get_contents($file);

    // a) INSERT INTO table (col1, col2...)
    preg_match_all('/INSERT\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?\s*\(([^)]+)\)/i', $src, $m1);
    for ($k = 0; $k < count($m1[0]); $k++) {
        $tbl = $m1[1][$k];
        $cols = preg_split('/\s*,\s*/', trim($m1[2][$k]));
        foreach ($cols as $col) {
            $col = trim($col, '` ');
            if ($col)
                $codeColumns[$tbl][$col] = $file;
        }
    }

    // b) UPDATE table SET col1=..., col2=...
    preg_match_all('/UPDATE\s+`?(\w+)`?\s+SET\s+(.+?)(?:\s+WHERE|\s*$)/si', $src, $m2);
    for ($k = 0; $k < count($m2[0]); $k++) {
        $tbl = $m2[1][$k];
        $setPart = $m2[2][$k];
        preg_match_all('/`?(\w+)`?\s*=/', $setPart, $setCols);
        foreach ($setCols[1] as $col) {
            if ($col && !in_array(strtoupper($col), ['AND', 'OR', 'WHERE', 'NULL', 'NOT', 'IF'])) {
                $codeColumns[$tbl][$col] = $file;
            }
        }
    }

    // c) alias.column  e.g. r.km_limit, v.status
    preg_match_all('/\b([a-z]{1,3})\.([a-z][a-z0-9_]+)\b/', $src, $m3);
    for ($k = 0; $k < count($m3[0]); $k++) {
        $alias = $m3[1][$k];
        $col = $m3[2][$k];
        if (isset($aliasMap[$alias]) && strlen($col) > 2) {
            $codeColumns[$aliasMap[$alias]][$col] = $file;
        }
    }
}

// ── 4. Compare and report ──────────────────────────────────────
$missing = [];

foreach ($codeColumns as $table => $cols) {
    if (!isset($liveSchema[$table])) {
        // table itself doesn't exist
        foreach ($cols as $col => $file) {
            $missing[$table][] = ['col' => $col, 'file' => basename($file)];
        }
        continue;
    }
    foreach ($cols as $col => $file) {
        if (!isset($liveSchema[$table][$col])) {
            $missing[$table][] = ['col' => $col, 'file' => basename($file)];
        }
    }
}

if (empty($missing)) {
    echo "🎉 No missing columns found! Everything looks good.\n";
} else {
    echo "❌ Missing columns found:\n";
    echo str_repeat('─', 70) . "\n";
    foreach ($missing as $table => $cols) {
        echo "\nTable: $table\n";
        $seen = [];
        foreach ($cols as $entry) {
            if (in_array($entry['col'], $seen))
                continue;
            $seen[] = $entry['col'];
            echo "  ⚠  {$entry['col']}   (seen in: {$entry['file']})\n";
        }
    }
    echo "\n" . str_repeat('─', 70) . "\n";
    echo "\n💡 Suggested ALTER TABLE statements:\n\n";
    foreach ($missing as $table => $cols) {
        $seen = [];
        $alters = [];
        foreach ($cols as $entry) {
            if (in_array($entry['col'], $seen))
                continue;
            $seen[] = $entry['col'];
            $alters[] = "  ADD COLUMN IF NOT EXISTS `{$entry['col']}` VARCHAR(255) DEFAULT NULL  -- CHECK TYPE";
        }
        if ($alters) {
            echo "ALTER TABLE `$table`\n";
            echo implode(",\n", $alters) . ";\n\n";
        }
    }
}
