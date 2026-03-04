<?php

function investment_ensure_schema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS emi_investments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            lender VARCHAR(255) DEFAULT NULL,
            total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            down_payment DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            emi_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tenure_months INT(11) NOT NULL DEFAULT 1,
            start_date DATE NOT NULL,
            notes TEXT DEFAULT NULL,
            down_payment_account_id INT(11) DEFAULT NULL,
            down_payment_ledger_id INT(11) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        app_log('ERROR', 'Investment schema ensure failed: could not create emi_investments - ' . $e->getMessage());
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS emi_schedules (
            id INT(11) NOT NULL AUTO_INCREMENT,
            investment_id INT(11) NOT NULL,
            installment_no INT(11) NOT NULL,
            due_date DATE NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
            paid_date DATE DEFAULT NULL,
            bank_account_id INT(11) DEFAULT NULL,
            ledger_entry_id INT(11) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_investment_id (investment_id),
            CONSTRAINT emi_schedules_ibfk_1 FOREIGN KEY (investment_id) REFERENCES emi_investments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        app_log('ERROR', 'Investment schema ensure failed: could not create emi_schedules - ' . $e->getMessage());
    }

    $investmentColumns = [
        'title' => "ALTER TABLE emi_investments ADD COLUMN title VARCHAR(255) NOT NULL DEFAULT ''",
        'lender' => "ALTER TABLE emi_investments ADD COLUMN lender VARCHAR(255) DEFAULT NULL",
        'total_cost' => "ALTER TABLE emi_investments ADD COLUMN total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        'down_payment' => "ALTER TABLE emi_investments ADD COLUMN down_payment DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        'loan_amount' => "ALTER TABLE emi_investments ADD COLUMN loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        'emi_amount' => "ALTER TABLE emi_investments ADD COLUMN emi_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        'tenure_months' => "ALTER TABLE emi_investments ADD COLUMN tenure_months INT(11) NOT NULL DEFAULT 1",
        'start_date' => "ALTER TABLE emi_investments ADD COLUMN start_date DATE NOT NULL DEFAULT '1970-01-01'",
        'notes' => "ALTER TABLE emi_investments ADD COLUMN notes TEXT DEFAULT NULL",
        'down_payment_account_id' => "ALTER TABLE emi_investments ADD COLUMN down_payment_account_id INT(11) DEFAULT NULL",
        'down_payment_ledger_id' => "ALTER TABLE emi_investments ADD COLUMN down_payment_ledger_id INT(11) DEFAULT NULL",
    ];

    foreach ($investmentColumns as $column => $sql) {
        try {
            $exists = (int) $pdo->query("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'emi_investments'
                  AND COLUMN_NAME = '{$column}'
            ")->fetchColumn();
            if ($exists === 0) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) {
            app_log('ERROR', "Investment schema ensure failed: emi_investments.{$column} - " . $e->getMessage());
        }
    }

    $scheduleColumns = [
        'installment_no' => "ALTER TABLE emi_schedules ADD COLUMN installment_no INT(11) NOT NULL DEFAULT 1",
        'bank_account_id' => "ALTER TABLE emi_schedules ADD COLUMN bank_account_id INT(11) DEFAULT NULL",
        'ledger_entry_id' => "ALTER TABLE emi_schedules ADD COLUMN ledger_entry_id INT(11) DEFAULT NULL",
    ];

    foreach ($scheduleColumns as $column => $sql) {
        try {
            $exists = (int) $pdo->query("
                SELECT COUNT(*)
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'emi_schedules'
                  AND COLUMN_NAME = '{$column}'
            ")->fetchColumn();
            if ($exists === 0) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) {
            app_log('ERROR', "Investment schema ensure failed: emi_schedules.{$column} - " . $e->getMessage());
        }
    }
}

