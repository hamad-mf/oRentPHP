-- Release: 2026-03-16_gps_daily_checks
-- Author: Hamad
-- Safe: idempotent
-- Notes: Adds gps_daily_checks table to track 3 daily GPS checks per active reservation.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS gps_daily_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    check_date DATE NOT NULL,
    check_slot TINYINT NOT NULL,
    tracking_active TINYINT(1) NOT NULL DEFAULT 1,
    last_location VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gps_daily_slot (reservation_id, check_date, check_slot),
    INDEX idx_gps_daily_vehicle (vehicle_id),
    INDEX idx_gps_daily_date (check_date),
    INDEX idx_gps_daily_reservation (reservation_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
