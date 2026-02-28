<?php

function reservation_payment_ensure_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [
        'delivery_payment_method' => "ALTER TABLE reservations ADD COLUMN delivery_payment_method ENUM('cash','account','credit') DEFAULT NULL",
        'delivery_paid_amount' => "ALTER TABLE reservations ADD COLUMN delivery_paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'return_payment_method' => "ALTER TABLE reservations ADD COLUMN return_payment_method ENUM('cash','account','credit') DEFAULT NULL",
        'return_paid_amount' => "ALTER TABLE reservations ADD COLUMN return_paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'delivery_discount_type' => "ALTER TABLE reservations ADD COLUMN delivery_discount_type ENUM('percent','amount') DEFAULT NULL",
        'delivery_discount_value' => "ALTER TABLE reservations ADD COLUMN delivery_discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    ];

    foreach ($columns as $column => $sql) {
        try {
            $exists = (int) $pdo->query("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'reservations'
                  AND COLUMN_NAME = '{$column}'
            ")->fetchColumn();
            if ($exists === 0) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) {
            // Ignore if migration is handled manually.
        }
    }
}

function reservation_payment_method_normalize(?string $value): ?string
{
    $value = strtolower(trim((string) $value));
    if (in_array($value, ['cash', 'account', 'credit'], true)) {
        return $value;
    }
    return null;
}

function reservation_payment_method_label(?string $value): string
{
    $normalized = reservation_payment_method_normalize($value);
    if ($normalized === 'account') {
        return 'Account';
    }
    if ($normalized === 'credit') {
        return 'Credit';
    }
    return 'Cash';
}
