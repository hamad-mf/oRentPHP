-- Migration: Add custom points support to job cards
-- Date: 2026-04-10
-- Description: Adds a table to store custom inspection points that can be added after the standard 37 items

CREATE TABLE IF NOT EXISTS vehicle_job_card_custom_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_card_id INT NOT NULL,
    point_number INT NOT NULL,
    note TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_card_id) REFERENCES vehicle_job_cards(id) ON DELETE CASCADE,
    INDEX idx_job_card_id (job_card_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
