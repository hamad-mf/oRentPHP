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
