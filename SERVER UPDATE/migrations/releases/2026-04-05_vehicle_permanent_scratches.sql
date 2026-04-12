-- Release: 2026-04-05_vehicle_permanent_scratches
-- Author: AI Assistant
-- Safe: idempotent (CREATE TABLE IF NOT EXISTS)
-- Notes: Creates vehicle_permanent_scratches table for storing permanent scratch/damage photos and descriptions per vehicle.
--        These scratches persist across all reservations and auto-populate in delivery/return inspection interfaces.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `vehicle_permanent_scratches` (
    `id`          INT              NOT NULL AUTO_INCREMENT,
    `vehicle_id`  INT              NOT NULL,
    `description` VARCHAR(255)     NOT NULL,
    `file_path`   VARCHAR(255)     NOT NULL,
    `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_by`  INT              NULL,
    PRIMARY KEY (`id`),
    KEY `idx_vps_vehicle` (`vehicle_id`),
    CONSTRAINT `fk_vps_vehicle`
        FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
