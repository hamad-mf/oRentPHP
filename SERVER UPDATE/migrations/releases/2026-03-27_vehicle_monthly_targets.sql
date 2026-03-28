-- Release: 2026-03-27_vehicle_monthly_targets
-- Author: system
-- Safe: idempotent
-- Notes: Creates vehicle_monthly_targets table for tracking monthly income targets per vehicle.
--        Run manually via phpMyAdmin before deploying vehicle-monthly-targets feature.

SET FOREIGN_KEY_CHECKS = 0;

-- Create vehicle_monthly_targets table (idempotent via IF NOT EXISTS)
CREATE TABLE IF NOT EXISTS vehicle_monthly_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    period_start DATE NOT NULL COMMENT '15th of the month',
    period_end DATE NOT NULL COMMENT '14th of next month',
    target_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='Monthly income targets per vehicle';

-- Add unique constraint (idempotent - check if exists first)
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'vehicle_monthly_targets' 
    AND CONSTRAINT_NAME = 'uq_vehicle_period'
);

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE vehicle_monthly_targets ADD UNIQUE KEY uq_vehicle_period (vehicle_id, period_start)',
    'SELECT "Unique constraint uq_vehicle_period already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add period index (idempotent - check if exists first)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'vehicle_monthly_targets' 
    AND INDEX_NAME = 'idx_period'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE vehicle_monthly_targets ADD INDEX idx_period (period_start, period_end)',
    'SELECT "Index idx_period already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add vehicle index (idempotent - check if exists first)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'vehicle_monthly_targets' 
    AND INDEX_NAME = 'idx_vehicle'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE vehicle_monthly_targets ADD INDEX idx_vehicle (vehicle_id)',
    'SELECT "Index idx_vehicle already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
