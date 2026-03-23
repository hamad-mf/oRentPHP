<?php
/**
 * precise_audit.php — extracts EXACT INSERT INTO column lists from PHP files
 * and compares with wipe_and_reset.sql
 */

$root = __DIR__;
$wipeSql = file_get_contents($root . '/wipe_and_reset.sql');

// Parse CREATE TABLE columns from wipe SQL
$wipeColumns = [];
preg_match_all('/CREATE\s+TABLE\s+[`"]?(\w+)[`"]?\s*\((.+?)\)\s*ENGINE/si', $wipeSql, $tables, PREG_SET_ORDER);
foreach ($tables as $t) {
    $tbl = strtolower($t[1]);
    $body = $t[2];
    $cols = [];
    foreach (explode("\n", $body) as $line) {
        $line = trim($line);
        if (preg_match('/^[`"]?(\w+)[`"]?\s+(INT|TINYINT|SMALLINT|BIGINT|VARCHAR|TEXT|ENUM|DECIMAL|DATE|DATETIME|TIMESTAMP|TIME|FLOAT|DOUBLE|CHAR|BLOB|JSON)/i', $line, $m)) {
            $cols[] = strtolower($m[1]);
        }
    }
    $wipeColumns[$tbl] = $cols;
}

// Scan PHP files — extract INSERT INTO (col, col, ...) exactly
$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$usedColumns = []; // table => [col => file:line]
$skipDirs = ['vendor', 'node_modules', '.git', 'SERVER UPDATE', 'logs', 'uploads', '.gemini'];

foreach ($phpFiles as $file) {
    if ($file->getExtension() !== 'php')
        continue;
    $path = $file->getRealPath();
    $skip = false;
    foreach ($skipDirs as $d) {
        if (
            strpos($path, DIRECTORY_SEPARATOR . $d . DIRECTORY_SEPARATOR) !== false ||
            strpos($path, DIRECTORY_SEPARATOR . $d) === strlen($path) - strlen(DIRECTORY_SEPARATOR . $d)
        ) {
            $skip = true;
            break;
        }
    }
    if ($skip)
        continue;

    $src = file_get_contents($path);
    $lines = explode("\n", $src);

    // ── 1. Multi-line INSERT INTO table (cols) (join lines for multi-line queries) ──
    // Flatten consecutive SQL fragments  
    $flat = preg_replace('/\s+/', ' ', $src);

    preg_match_all(
        '/INSERT\s+(?:IGNORE\s+)?INTO\s+[`"]?(\w+)[`"]?\s*\(\s*([^)]+)\)/si',
        $flat,
        $inserts,
        PREG_SET_ORDER
    );
    foreach ($inserts as $ins) {
        $tbl = strtolower(trim($ins[1], '`"'));
        $raw = $ins[2];
        $cols = array_map(fn($c) => strtolower(trim($c, " `\"\t\r\n")), explode(',', $raw));
        foreach ($cols as $c) {
            $c = preg_replace('/[^a-z0-9_]/', '', $c);
            if ($c && strlen($c) > 1) {
                $usedColumns[$tbl][$c] = $path;
            }
        }
    }

    // ── 2. UPDATE table SET col=? ──
    preg_match_all(
        '/UPDATE\s+[`"]?(\w+)[`"]?\s+SET\s+((?:[`"]?\w+[`"]?\s*=\s*[^,\n]++,?\s*)+)/si',
        $flat,
        $updates,
        PREG_SET_ORDER
    );
    foreach ($updates as $upd) {
        $tbl = strtolower(trim($upd[1], '`"'));
        preg_match_all('/[`"]?(\w+)[`"]?\s*=/', $upd[2], $cm);
        foreach ($cm[1] as $c) {
            $c = strtolower($c);
            if ($c && strlen($c) > 1 && !in_array($c, ['set', 'where', 'and', 'or'])) {
                $usedColumns[$tbl][$c] = $path;
            }
        }
    }
}

// Report missing
$missing = [];
foreach ($usedColumns as $tbl => $cols) {
    if (in_array($tbl, ['information_schema', 'mysql']))
        continue;
    $wipeCols = $wipeColumns[$tbl] ?? null;
    if ($wipeCols === null) {
        $missing[$tbl]['table_missing'] = true;
        $missing[$tbl]['cols'] = $cols;
        continue;
    }
    foreach ($cols as $c => $file) {
        if (!in_array($c, $wipeCols)) {
            $missing[$tbl]['cols'][$c] = basename($file);
        }
    }
}

if (empty(array_filter($missing))) {
    echo "✅ All good — wipe_and_reset.sql matches code!\n";
} else {
    foreach ($missing as $tbl => $info) {
        if (!empty($info['table_missing'])) {
            echo "TABLE MISSING: $tbl\n  cols: " . implode(', ', array_keys($info['cols'])) . "\n";
        } elseif (!empty($info['cols'])) {
            echo "COLUMN MISSING in '$tbl': " . implode(', ', array_map(
                fn($col, $file) => "$col (in $file)",
                array_keys($info['cols']),
                $info['cols']
            )) . "\n";
        }
    }
}
