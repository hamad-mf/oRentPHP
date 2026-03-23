<?php

function voucher_ensure_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $hasBalanceCol = (int) $pdo->query("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'clients'
              AND COLUMN_NAME = 'voucher_balance'
        ")->fetchColumn();
        if ($hasBalanceCol === 0) {
            $pdo->exec("ALTER TABLE clients ADD COLUMN voucher_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        }
    } catch (Throwable $e) {
        // Ignore if migration is handled manually.
    }

    $reservationVoucherColumns = [
        'voucher_applied' => "ALTER TABLE reservations ADD COLUMN voucher_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'return_voucher_applied' => "ALTER TABLE reservations ADD COLUMN return_voucher_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'early_return_credit' => "ALTER TABLE reservations ADD COLUMN early_return_credit DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'voucher_credit_issued' => "ALTER TABLE reservations ADD COLUMN voucher_credit_issued DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'additional_charge' => "ALTER TABLE reservations ADD COLUMN additional_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00",
    ];

    foreach ($reservationVoucherColumns as $column => $sql) {
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
           app_log('ERROR', "Voucher helper: reservations.{$column} ensure failed - " . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);
 
            // Ignore if migration is handled manually.
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS client_voucher_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            reservation_id INT DEFAULT NULL,
            type ENUM('credit','debit') NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client_created (client_id, created_at),
            CONSTRAINT fk_voucher_tx_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            CONSTRAINT fk_voucher_tx_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");
}

function voucher_get_balance(PDO $pdo, int $clientId): float
{
    voucher_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT voucher_balance FROM clients WHERE id=? LIMIT 1");
    $stmt->execute([$clientId]);
    $balance = $stmt->fetchColumn();
    return max(0, (float) ($balance ?? 0));
}

function voucher_add_credit(PDO $pdo, int $clientId, float $amount, ?int $reservationId = null, string $note = 'Voucher credit'): float
{
    voucher_ensure_schema($pdo);
    $amount = max(0, round($amount, 2));
    if ($amount <= 0) {
        return voucher_get_balance($pdo, $clientId);
    }

    $pdo->prepare("UPDATE clients SET voucher_balance = voucher_balance + ? WHERE id=?")
        ->execute([$amount, $clientId]);

    $pdo->prepare("INSERT INTO client_voucher_transactions (client_id, reservation_id, type, amount, note) VALUES (?,?,?,?,?)")
        ->execute([$clientId, $reservationId, 'credit', $amount, $note]);

    return voucher_get_balance($pdo, $clientId);
}

function voucher_apply_debit(PDO $pdo, int $clientId, float $requestedAmount, ?int $reservationId = null, string $note = 'Voucher applied'): float
{
    voucher_ensure_schema($pdo);
    $requestedAmount = max(0, round($requestedAmount, 2));
    if ($requestedAmount <= 0) {
        return 0.0;
    }

    $balance = voucher_get_balance($pdo, $clientId);
    $applied = min($requestedAmount, $balance);
    if ($applied <= 0) {
        return 0.0;
    }

    $pdo->prepare("UPDATE clients SET voucher_balance = GREATEST(0, voucher_balance - ?) WHERE id=?")
        ->execute([$applied, $clientId]);

    $pdo->prepare("INSERT INTO client_voucher_transactions (client_id, reservation_id, type, amount, note) VALUES (?,?,?,?,?)")
        ->execute([$clientId, $reservationId, 'debit', $applied, $note]);

    return $applied;
}
