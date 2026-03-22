-- Release: 2026-03-22_vehicle_challans
-- Author: AI Assistant
-- Safe: idempotent (IF NOT EXISTS)
-- Notes: Creates new vehicle_challans table for tracking challans (traffic fines) per vehicle with title, amount, due_date, and status fields.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS vehicle_challans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    due_date DATE DEFAULT NULL,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
