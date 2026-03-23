-- Release: 2026-03-13_reservation_extension_grace
-- Author: Hamad
-- Safe: idempotent
-- Notes: Add reservation extension tracking and extension_paid_amount on reservations.

SET FOREIGN_KEY_CHECKS = 0;

SET @reservations_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
);

SET @extension_paid_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservations'
      AND COLUMN_NAME = 'extension_paid_amount'
);

SET @extension_paid_sql := IF(
    @reservations_table_exists > 0 AND @extension_paid_exists = 0,
    'ALTER TABLE reservations ADD COLUMN extension_paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
);
PREPARE stmt FROM @extension_paid_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS reservation_extensions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT NOT NULL,
  old_end_date DATETIME NOT NULL,
  base_start_date DATETIME NOT NULL,
  new_end_date DATETIME NOT NULL,
  rental_type ENUM('daily','1day','7day','15day','30day','monthly') NOT NULL DEFAULT 'daily',
  days INT NOT NULL,
  rate_per_day DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  payment_method ENUM('cash','account','credit') DEFAULT NULL,
  bank_account_id INT DEFAULT NULL,
  ledger_entry_id INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reservation (reservation_id),
  INDEX idx_created_at (created_at),
  CONSTRAINT fk_res_extension_reservation FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_extension_bank FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
