<?php

function settings_ensure_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
        `key` VARCHAR(100) NOT NULL PRIMARY KEY,
        `value` TEXT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
}

function settings_get(PDO $pdo, string $key, string $default = ''): string
{
    settings_ensure_table($pdo);
    $stmt = $pdo->prepare("SELECT `value` FROM system_settings WHERE `key` = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    if ($value === false || $value === null) {
        return $default;
    }
    return (string) $value;
}

function settings_set(PDO $pdo, string $key, string $value): void
{
    settings_ensure_table($pdo);
    $stmt = $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
    $stmt->execute([$key, $value]);
}

function mobile_bottom_nav_catalog(): array
{
    return [
        'dashboard' => 'Dashboard',
        'vehicles' => 'Vehicles',
        'pipeline' => 'Pipeline',
        'reservations' => 'Bookings',
        'accounts' => 'Accounts',
        'clients' => 'Clients',
        'gps' => 'GPS',
        'settings' => 'Settings',
    ];
}

function mobile_bottom_nav_default_keys(): array
{
    return ['dashboard', 'vehicles', 'pipeline', 'reservations', 'accounts'];
}

function mobile_bottom_nav_encode_keys(array $keys): string
{
    $catalog = mobile_bottom_nav_catalog();
    $clean = [];
    $seen = [];

    foreach ($keys as $key) {
        $normalized = strtolower(trim((string) $key));
        if ($normalized === '' || !isset($catalog[$normalized]) || isset($seen[$normalized])) {
            continue;
        }
        $seen[$normalized] = true;
        $clean[] = $normalized;
    }

    if (empty($clean)) {
        $clean = mobile_bottom_nav_default_keys();
    }

    return json_encode(array_values($clean), JSON_UNESCAPED_UNICODE) ?: '[]';
}

function mobile_bottom_nav_decode_keys(string $encoded): array
{
    $decoded = json_decode($encoded, true);
    $rawItems = is_array($decoded) ? $decoded : preg_split('/\r\n|\r|\n|,/', $encoded);
    if (!is_array($rawItems)) {
        return mobile_bottom_nav_default_keys();
    }

    $catalog = mobile_bottom_nav_catalog();
    $keys = [];
    $seen = [];

    foreach ($rawItems as $item) {
        $key = strtolower(trim((string) $item));
        if ($key === '' || !isset($catalog[$key]) || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $keys[] = $key;
    }

    return empty($keys) ? mobile_bottom_nav_default_keys() : $keys;
}

function mobile_bottom_nav_get_keys(PDO $pdo, int $requiredCount = 5): array
{
    $requiredCount = max(1, min(8, $requiredCount));

    $defaults = mobile_bottom_nav_default_keys();
    $defaultEncoded = mobile_bottom_nav_encode_keys($defaults);

    settings_ensure_table($pdo);
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('mobile_bottom_nav_keys', ?)");
    $stmt->execute([$defaultEncoded]);

    $stored = settings_get($pdo, 'mobile_bottom_nav_keys', $defaultEncoded);
    $keys = mobile_bottom_nav_decode_keys($stored);

    $catalog = mobile_bottom_nav_catalog();
    foreach ($defaults as $key) {
        if (count($keys) >= $requiredCount) {
            break;
        }
        if (isset($catalog[$key]) && !in_array($key, $keys, true)) {
            $keys[] = $key;
        }
    }
    foreach (array_keys($catalog) as $key) {
        if (count($keys) >= $requiredCount) {
            break;
        }
        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
        }
    }

    return array_slice($keys, 0, $requiredCount);
}

function lead_sources_default_map(): array
{
    return [
        'walk_in' => 'Walk-in',
        'phone' => 'Phone Call',
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram',
        'referral' => 'Referral',
        'website' => 'Website',
        'other' => 'Other',
    ];
}

function lead_source_slug(string $label): string
{
    $label = strtolower(trim($label));
    $label = preg_replace('/[^a-z0-9]+/', '_', $label) ?? '';
    return trim($label, '_');
}

function lead_source_guess_label(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    return ucwords(str_replace(['_', '-'], ' ', $value));
}

function lead_sources_decode_map(string $encoded): array
{
    $decoded = json_decode($encoded, true);
    if (!is_array($decoded)) {
        return lead_sources_default_map();
    }

    $map = [];
    foreach ($decoded as $item) {
        $value = '';
        $label = '';

        if (is_array($item)) {
            $value = lead_source_slug((string) ($item['value'] ?? ''));
            $label = trim((string) ($item['label'] ?? ''));
        } elseif (is_string($item)) {
            $label = trim($item);
            $value = lead_source_slug($label);
        }

        if ($value === '' && $label !== '') {
            $value = lead_source_slug($label);
        }
        if ($label === '' && $value !== '') {
            $label = lead_source_guess_label($value);
        }
        if ($value === '' || $label === '' || isset($map[$value])) {
            continue;
        }

        $map[$value] = $label;
    }

    if (empty($map)) {
        return lead_sources_default_map();
    }

    return $map;
}

function expense_categories_default_list(): array
{
    return [
        'Manual Expense',
        'Fuel',
        'Rent',
        'Salary',
        'Maintenance',
        'Utilities',
        'Office Expense',
        'Marketing',
        'Miscellaneous',
    ];
}

function expense_category_normalize_label(string $label): string
{
    $label = preg_replace('/\s+/', ' ', trim($label)) ?? '';
    return trim($label);
}

function expense_categories_decode_list(string $encoded): array
{
    $rawItems = [];
    $decoded = json_decode($encoded, true);
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (is_string($item)) {
                $rawItems[] = $item;
            } elseif (is_array($item) && isset($item['label'])) {
                $rawItems[] = (string) $item['label'];
            }
        }
    } else {
        $rawItems = preg_split('/\r\n|\r|\n/', $encoded) ?: [];
    }

    $list = [];
    $seen = [];
    foreach ($rawItems as $item) {
        $label = expense_category_normalize_label((string) $item);
        if ($label === '') {
            continue;
        }
        $key = strtolower($label);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $list[] = $label;
    }

    return empty($list) ? expense_categories_default_list() : $list;
}

function expense_categories_encode_list(array $list): string
{
    $clean = [];
    $seen = [];

    foreach ($list as $item) {
        $label = expense_category_normalize_label((string) $item);
        if ($label === '') {
            continue;
        }
        $key = strtolower($label);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $clean[] = $label;
    }

    if (empty($clean)) {
        $clean = expense_categories_default_list();
    }

    return json_encode(array_values($clean), JSON_UNESCAPED_UNICODE) ?: '[]';
}

function expense_categories_get_list(PDO $pdo): array
{
    $defaults = expense_categories_default_list();
    $defaultEncoded = expense_categories_encode_list($defaults);

    settings_ensure_table($pdo);
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('expense_categories', ?)");
    $stmt->execute([$defaultEncoded]);

    $stored = settings_get($pdo, 'expense_categories', $defaultEncoded);
    return expense_categories_decode_list($stored);
}

function expense_categories_parse_textarea(string $input): array
{
    $lines = preg_split('/\r\n|\r|\n/', $input) ?: [];
    $list = [];
    $seen = [];

    foreach ($lines as $line) {
        $label = expense_category_normalize_label($line);
        if ($label === '') {
            continue;
        }
        $key = strtolower($label);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $list[] = $label;
    }

    return $list;
}

function lead_sources_encode_map(array $map): string
{
    $rows = [];
    foreach ($map as $value => $label) {
        $value = lead_source_slug((string) $value);
        $label = trim((string) $label);
        if ($value === '' || $label === '') {
            continue;
        }
        $rows[] = ['value' => $value, 'label' => $label];
    }
    return json_encode($rows, JSON_UNESCAPED_UNICODE) ?: '[]';
}

function lead_sources_ensure_storage_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $stmt = $pdo->query("SELECT DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'leads'
              AND COLUMN_NAME = 'source'
            LIMIT 1");
        $dataType = strtolower((string) $stmt->fetchColumn());
        if ($dataType === 'enum') {
            $pdo->exec("ALTER TABLE leads MODIFY COLUMN source VARCHAR(100) NOT NULL DEFAULT 'phone'");
        }
    } catch (Throwable $e) {
        app_log('ERROR', 'Settings helper: lead source column migration check failed - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

        // Ignore if leads table is not present yet.
    }
}

function lead_sources_get_map(PDO $pdo): array
{
    $defaults = lead_sources_default_map();
    $defaultEncoded = lead_sources_encode_map($defaults);

    lead_sources_ensure_storage_column($pdo);
    settings_ensure_table($pdo);
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('lead_sources', ?)");
    $stmt->execute([$defaultEncoded]);

    $stored = settings_get($pdo, 'lead_sources', $defaultEncoded);
    $map = lead_sources_decode_map($stored);
    if (empty($map)) {
        $map = $defaults;
    }
    return $map;
}

function lead_sources_parse_textarea(string $input): array
{
    $lines = preg_split('/\r\n|\r|\n/', $input) ?: [];
    $map = [];

    foreach ($lines as $line) {
        $label = trim($line);
        if ($label === '') {
            continue;
        }

        $value = lead_source_slug($label);
        if ($value === '' || isset($map[$value])) {
            continue;
        }
        $map[$value] = $label;
    }

    return $map;
}

// ── Pagination Helper ──────────────────────────────────────────────────────

/**
 * Get the configured items per page (with a safe fallback).
 */
function get_per_page(PDO $pdo, int $default = 25): int {
    $v = (int) settings_get($pdo, 'per_page', (string) $default);
    return max(5, min(200, $v ?: $default));
}

/**
 * Run a paginated query.
 * Returns ['rows'=>[], 'total'=>int, 'page'=>int, 'per_page'=>int, 'total_pages'=>int].
 *
 * $countSql must be a SELECT COUNT(*) ... with the same WHERE as $sql.
 * $sql must NOT already have a LIMIT clause.
 */
function paginate_query(PDO $pdo, string $sql, string $countSql, array $params, int $page, int $perPage): array {
    $page    = max(1, $page);
    $total   = (int) $pdo->prepare($countSql)->execute($params) ? 0 : 0;
    $cStmt   = $pdo->prepare($countSql);
    $cStmt->execute($params);
    $total   = (int) $cStmt->fetchColumn();
    $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
    $page    = min($page, max(1, $totalPages));
    $offset  = ($page - 1) * $perPage;
    $stmt    = $pdo->prepare($sql . " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
    $stmt->execute($params);
    return [
        'rows'        => $stmt->fetchAll(),
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
    ];
}

/**
 * Render pagination navigation HTML.
 * $queryParams = current $_GET array (page will be overwritten).
 */
function render_pagination(array $pg, array $queryParams = []): string {
    if (($pg['total_pages'] ?? 1) <= 1) {
        return '';
    }

    $cur = (int)($pg['page'] ?? 1);
    $total = (int)($pg['total_pages'] ?? 1);
    $from = max(1, $cur - 2);
    $to = min($total, $cur + 2);
    $start = ($cur - 1) * (int)($pg['per_page'] ?? 0) + 1;
    $end = min($cur * (int)($pg['per_page'] ?? 0), (int)($pg['total'] ?? 0));

    $html = '<div class="pt-2 pb-6">';
    $html .= '<p class="text-center text-xs text-mb-subtle mb-3">Showing ' . $start . '-' . $end . ' of ' . (int)($pg['total'] ?? 0) . '</p>';
    $html .= '<div class="flex items-center justify-center gap-2">';

    if ($cur > 1) {
        $qp = array_merge($queryParams, ['page' => $cur - 1]);
        $html .= '<a href="?' . http_build_query($qp) . '" class="px-3.5 py-2 rounded-lg bg-mb-surface border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-white/30 transition-colors text-sm font-medium">Prev</a>';
    }

    for ($i = $from; $i <= $to; $i++) {
        $qp = array_merge($queryParams, ['page' => $i]);
        if ($i === $cur) {
            $html .= '<span class="min-w-[40px] text-center px-3.5 py-2 rounded-lg bg-mb-accent text-white text-sm font-semibold border border-mb-accent/80">' . $i . '</span>';
        } else {
            $html .= '<a href="?' . http_build_query($qp) . '" class="min-w-[40px] text-center px-3.5 py-2 rounded-lg bg-mb-surface border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-white/30 transition-colors text-sm font-medium">' . $i . '</a>';
        }
    }

    if ($cur < $total) {
        $qp = array_merge($queryParams, ['page' => $cur + 1]);
        $html .= '<a href="?' . http_build_query($qp) . '" class="px-3.5 py-2 rounded-lg bg-mb-surface border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-white/30 transition-colors text-sm font-medium">Next</a>';
    }

    $html .= '</div></div>';
    return $html;
}
