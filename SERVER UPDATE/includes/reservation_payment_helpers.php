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
        'delivery_manual_amount' => "ALTER TABLE reservations ADD COLUMN delivery_manual_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'return_payment_method' => "ALTER TABLE reservations ADD COLUMN return_payment_method ENUM('cash','account','credit') DEFAULT NULL",
        'return_paid_amount' => "ALTER TABLE reservations ADD COLUMN return_paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'delivery_discount_type' => "ALTER TABLE reservations ADD COLUMN delivery_discount_type ENUM('percent','amount') DEFAULT NULL",
        'delivery_discount_value' => "ALTER TABLE reservations ADD COLUMN delivery_discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'advance_paid' => "ALTER TABLE reservations ADD COLUMN advance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'advance_payment_method' => "ALTER TABLE reservations ADD COLUMN advance_payment_method ENUM('cash','account','credit') DEFAULT NULL",
        'advance_bank_account_id' => "ALTER TABLE reservations ADD COLUMN advance_bank_account_id INT DEFAULT NULL",
    ];

    foreach ($columns as $column => $sql) {
        try {
            $exists = (int)$pdo->query("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'reservations'
                  AND COLUMN_NAME = '{$column}'
            ")->fetchColumn();
            if ($exists === 0) {
                $pdo->exec($sql);
            }
        }
        catch (Throwable $e) {
            app_log('ERROR', "Reservation payment helper: schema ensure failed for reservations.{$column} - " . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),            ]);

        // Ignore if migration is handled manually.
        }
    }
}

function reservation_payment_method_normalize(?string $value): ?string
{
    $value = strtolower(trim((string)$value));
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

/**
 * Check if a held deposit is overdue based on system settings
 * 
 * @param PDO $pdo Database connection
 * @param string|null $depositHeldAt Timestamp when deposit was held
 * @param float $depositHeld Amount held
 * @return array ['is_overdue' => bool, 'days_held' => int, 'threshold_days' => int]
 */
function reservation_held_deposit_status(PDO $pdo, ?string $depositHeldAt, float $depositHeld): array
{
    if ($depositHeld <= 0 || !$depositHeldAt) {
        return ['is_overdue' => false, 'days_held' => 0, 'threshold_days' => 0];
    }

    try {
        // Get threshold from settings
        $thresholdDays = (int) $pdo->query("SELECT `value` FROM system_settings WHERE `key`='held_deposit_alert_days'")->fetchColumn();
        if ($thresholdDays <= 0) {
            $thresholdDays = 7; // Default
        }

        // Check if test mode is enabled (hours treated as days)
        $testMode = (int) $pdo->query("SELECT `value` FROM system_settings WHERE `key`='held_deposit_test_mode'")->fetchColumn();
    } catch (Throwable $e) {
        // Settings don't exist yet - use defaults
        $thresholdDays = 7;
        $testMode = 0;
    }

    $heldAt = new DateTime($depositHeldAt);
    $now = new DateTime();
    
    if ($testMode === 1) {
        // Test mode: treat hours as days
        $hoursHeld = (int) round(($now->getTimestamp() - $heldAt->getTimestamp()) / 3600);
        $isOverdue = $hoursHeld >= $thresholdDays;
        return ['is_overdue' => $isOverdue, 'days_held' => $hoursHeld, 'threshold_days' => $thresholdDays, 'test_mode' => true];
    } else {
        // Normal mode: actual days
        $daysHeld = (int) $heldAt->diff($now)->days;
        $isOverdue = $daysHeld >= $thresholdDays;
        return ['is_overdue' => $isOverdue, 'days_held' => $daysHeld, 'threshold_days' => $thresholdDays, 'test_mode' => false];
    }
}

/**
 * Get all reservations with overdue held deposits
 * 
 * @param PDO $pdo Database connection
 * @return array Array of reservation records with overdue held deposits
 */
function reservation_get_overdue_held_deposits(PDO $pdo): array
{
    // Check if deposit_held_at column exists
    try {
        $columnExists = (int) $pdo->query("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'reservations'
              AND COLUMN_NAME = 'deposit_held_at'
        ")->fetchColumn();
        
        if ($columnExists === 0) {
            // Column doesn't exist yet - return empty array
            return [];
        }
    } catch (Throwable $e) {
        return [];
    }

    $stmt = $pdo->query("
        SELECT r.*, 
               c.name AS client_name, 
               v.brand, v.model, v.license_plate
        FROM reservations r
        JOIN clients c ON r.client_id = c.id
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE r.deposit_held > 0 
          AND r.deposit_held_at IS NOT NULL
          AND r.deposit_held_action IS NULL
        ORDER BY r.deposit_held_at ASC
    ");
    
    $overdue = [];
    while ($row = $stmt->fetch()) {
        $status = reservation_held_deposit_status($pdo, $row['deposit_held_at'], (float) $row['deposit_held']);
        if ($status['is_overdue']) {
            $row['held_status'] = $status;
            $overdue[] = $row;
        }
    }
    
    return $overdue;
}

/**
 * Check if a column exists in a table (for graceful degradation).
 */
function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

/**
 * Calculate available security deposit for a reservation.
 * Gracefully handles missing deposit_used_for_extension column (pre-migration).
 *
 * Formula: deposit_amount - deposit_returned - deposit_deducted - deposit_held - deposit_used_for_extension
 */
function calculate_available_deposit(PDO $pdo, int $reservationId): float
{
    $hasExtCol = column_exists($pdo, 'reservations', 'deposit_used_for_extension');

    if ($hasExtCol) {
        $sql = "SELECT GREATEST(0,
            COALESCE(deposit_amount, 0) -
            COALESCE(deposit_returned, 0) -
            COALESCE(deposit_deducted, 0) -
            COALESCE(deposit_held, 0) -
            COALESCE(deposit_used_for_extension, 0)
        ) AS available FROM reservations WHERE id = ?";
    } else {
        $sql = "SELECT GREATEST(0,
            COALESCE(deposit_amount, 0) -
            COALESCE(deposit_returned, 0) -
            COALESCE(deposit_deducted, 0) -
            COALESCE(deposit_held, 0)
        ) AS available FROM reservations WHERE id = ?";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reservationId]);
    return (float) $stmt->fetchColumn();
}

/**
 * Get the bank account ID used for the security deposit on a reservation.
 * First tries ledger history, then falls back to system settings default.
 */
function get_deposit_bank_account(PDO $pdo, int $reservationId): ?int
{
    // Try to get from ledger history (the account that received the deposit)
    $bankId = ledger_get_security_deposit_account_id($pdo, $reservationId);

    // Fallback to configured default from settings
    if ($bankId === null) {
        try {
            $configuredId = (int) $pdo->query(
                "SELECT `value` FROM system_settings WHERE `key`='security_deposit_bank_account_id'"
            )->fetchColumn();
            $bankId = ledger_get_active_bank_account_id($pdo, $configuredId);
        } catch (Throwable $e) {
            // Settings table may not exist yet
        }
    }

    return $bankId;
}
