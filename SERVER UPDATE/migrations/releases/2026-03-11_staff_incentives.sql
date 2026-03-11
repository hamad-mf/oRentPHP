-- Release: 2026-03-11_staff_incentives
-- Author: opencode
-- Safe: idempotent
-- Notes: Adds staff_incentives table for tracking monthly incentives per staff member

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS staff_incentives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
    year INT NOT NULL CHECK (year BETWEEN 2000 AND 2100),
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    INDEX idx_user_month_year (user_id, month, year),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
