-- Release: 2026-03-08_client_rating_review
-- Author: Codex (GPT-5)
-- Safe: idempotent
-- Notes: Adds optional rating_review field to clients.

SET FOREIGN_KEY_CHECKS = 0;

SET @clients_table_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clients'
);
SET @clients_review_col_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'rating_review'
);
SET @clients_sql := IF(
    @clients_table_exists > 0 AND @clients_review_col_exists = 0,
    'ALTER TABLE clients ADD COLUMN rating_review TEXT NULL AFTER rating',
    'SELECT 1'
);
PREPARE stmt FROM @clients_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
