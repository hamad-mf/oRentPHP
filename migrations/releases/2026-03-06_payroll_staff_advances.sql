-- Release: 2026-03-06_payroll_staff_advances
-- Author: AI assistant
-- Safe: idempotent (guarded ALTERs)
-- Notes: Adds staff advance tracking and payroll payable snapshots.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS payroll_advances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    payroll_id INT DEFAULT NULL,
    month TINYINT NOT NULL,
    year SMALLINT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    remaining_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','partially_recovered','recovered') NOT NULL DEFAULT 'pending',
    note VARCHAR(255) DEFAULT NULL,
    given_at DATETIME NOT NULL,
    recovered_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    bank_account_id INT DEFAULT NULL,
    ledger_entry_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pa_user_status (user_id, status),
    KEY idx_pa_period (month, year),
    KEY idx_pa_payroll (payroll_id),
    KEY idx_pa_bank (bank_account_id),
    CONSTRAINT fk_pa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_payroll FOREIGN KEY (payroll_id) REFERENCES payroll(id) ON DELETE SET NULL,
    CONSTRAINT fk_pa_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_adv_deducted := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payroll'
      AND COLUMN_NAME = 'advance_deducted'
);
SET @sql_adv_deducted := IF(
    @has_adv_deducted = 0,
    'ALTER TABLE payroll ADD COLUMN advance_deducted DECIMAL(10,2) DEFAULT NULL AFTER deductions',
    'SELECT 1'
);
PREPARE stmt_adv_deducted FROM @sql_adv_deducted;
EXECUTE stmt_adv_deducted;
DEALLOCATE PREPARE stmt_adv_deducted;

SET @has_payable_salary := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'payroll'
      AND COLUMN_NAME = 'payable_salary'
);
SET @sql_payable_salary := IF(
    @has_payable_salary = 0,
    'ALTER TABLE payroll ADD COLUMN payable_salary DECIMAL(10,2) DEFAULT NULL AFTER net_salary',
    'SELECT 1'
);
PREPARE stmt_payable_salary FROM @sql_payable_salary;
EXECUTE stmt_payable_salary;
DEALLOCATE PREPARE stmt_payable_salary;

SET FOREIGN_KEY_CHECKS = 1;
