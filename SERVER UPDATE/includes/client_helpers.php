<?php

function clients_ensure_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $stmt = $pdo->query("
            SELECT IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'clients'
              AND COLUMN_NAME = 'email'
            LIMIT 1
        ");
        $isNullable = strtoupper((string) $stmt->fetchColumn());

        if ($isNullable !== 'YES') {
            $pdo->exec("ALTER TABLE clients MODIFY COLUMN email VARCHAR(255) NULL");
        }
    } catch (Throwable $e) {
        // Ignore if schema changes are managed manually on the target server.
        app_log('ERROR', 'Client helper: ensure email nullable failed - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

    }
}

function clients_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];
    $key = $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'clients'
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$column]);
        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        app_log('ERROR', "Client helper: column check failed for clients.{$column} - " . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

        $cache[$key] = false;
    }

    return $cache[$key];
}
