-- Release: 2026-03-29_remember_me_tokens
-- Author: system
-- Safe: idempotent (IF NOT EXISTS)
-- Notes: Creates remember_tokens table for persistent authentication across browser sessions.
--        Implements selector/validator pattern for secure "Remember Me" functionality.
--        Supports multi-device login with 30-day token expiry.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector VARCHAR(32) NOT NULL,
    validator_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_selector (selector),
    INDEX idx_expires_at (expires_at),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
