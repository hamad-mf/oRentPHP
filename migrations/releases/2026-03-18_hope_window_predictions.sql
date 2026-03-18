-- Release: 2026-03-18_hope_window_predictions
-- Author: Hamad
-- Safe: idempotent
-- Notes: Adds hope_daily_predictions table for manual prediction entries in Hope Window.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS hope_daily_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    target_date DATE NOT NULL,
    label VARCHAR(255) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_target_date (target_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
