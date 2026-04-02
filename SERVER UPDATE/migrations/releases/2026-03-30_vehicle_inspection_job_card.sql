-- Release: 2026-03-30_vehicle_inspection_job_card
-- Author: system
-- Safe: idempotent (IF NOT EXISTS guards)
-- Notes: Adds standalone vehicle inspection job card system.
--        vehicle_job_cards — header table for inspection records
--        vehicle_job_card_items — detail table for 37 inspection items

SET FOREIGN_KEY_CHECKS = 0;

-- Create job card header table
CREATE TABLE IF NOT EXISTS vehicle_job_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    inspection_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create job card items table
CREATE TABLE IF NOT EXISTS vehicle_job_card_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    item_number INT NOT NULL COMMENT 'Serial number 1-37',
    item_name VARCHAR(100) NOT NULL,
    check_value VARCHAR(100) NULL DEFAULT NULL COMMENT 'Value entered in Check Table column',
    note VARCHAR(255) NULL DEFAULT NULL,
    FOREIGN KEY (job_card_id) REFERENCES vehicle_job_cards(id) ON DELETE CASCADE,
    INDEX idx_job_card (job_card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
