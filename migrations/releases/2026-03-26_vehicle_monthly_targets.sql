-- Release: 2026-03-26_vehicle_monthly_targets
-- Author: System
-- Safe: idempotent (IF NOT EXISTS guards)
-- Notes: Adds vehicle_monthly_targets table for tracking monthly income targets per vehicle.
--        Enables fleet managers to set and monitor vehicle-level performance targets
--        using the same 15th-to-14th monthly period system as other financial screens.

SET FOREIGN_KEY_CHECKS = 0;

-- Create vehicle_monthly_targets table
CREATE TABLE IF NOT EXISTS vehicle_monthly_targets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    target_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uq_vehicle_period (vehicle_id, period_start),
    INDEX idx_period (period_start, period_end),
    INDEX idx_vehicle (vehicle_id),
    
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
