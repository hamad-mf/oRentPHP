-- Release: 2026-03-08_client_reviews_add_created_by
-- Author: Antigravity
-- Safe: idempotent
-- Notes: Adds created_by (user ID) to client_reviews for tracking which staff member made the review.

SET FOREIGN_KEY_CHECKS = 0;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'client_reviews'
      AND COLUMN_NAME = 'created_by'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE client_reviews ADD COLUMN created_by INT NULL AFTER review',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
