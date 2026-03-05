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
    }
}
