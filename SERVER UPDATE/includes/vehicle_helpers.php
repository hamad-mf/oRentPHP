<?php

function vehicle_ensure_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $columnsToEnsure = [
            'maintenance_started_at' => "ALTER TABLE vehicles ADD COLUMN maintenance_started_at DATETIME NULL DEFAULT NULL AFTER status",
            'maintenance_expected_return' => "ALTER TABLE vehicles ADD COLUMN maintenance_expected_return DATE NULL DEFAULT NULL AFTER maintenance_started_at",
            'maintenance_workshop_name' => "ALTER TABLE vehicles ADD COLUMN maintenance_workshop_name VARCHAR(255) NULL DEFAULT NULL AFTER maintenance_expected_return",
        ];

        foreach ($columnsToEnsure as $columnName => $alterSql) {
            $hasColumn = (int) $pdo->query("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'vehicles'
                  AND COLUMN_NAME = " . $pdo->quote($columnName) . "
            ")->fetchColumn();

            if ($hasColumn === 0) {
                $pdo->exec($alterSql);
            }
        }

        $nowSql = app_now_sql();
        $stmt = $pdo->prepare("
            UPDATE vehicles
            SET maintenance_started_at = COALESCE(maintenance_started_at, updated_at, created_at, ?)
            WHERE status = 'maintenance' AND maintenance_started_at IS NULL
        ");
        $stmt->execute([$nowSql]);
    } catch (Throwable $e) {
        app_log('ERROR', 'Vehicle helper: maintenance_started_at backfill failed - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

        // Ignore if migration is handled manually.
    }
}
