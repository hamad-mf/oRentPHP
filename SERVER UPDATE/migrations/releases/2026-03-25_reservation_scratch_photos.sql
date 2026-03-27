-- Release: 2026-03-25_reservation_scratch_photos
-- Safe: idempotent (CREATE TABLE IF NOT EXISTS)
-- Notes: Creates reservation_scratch_photos table for storing delivery and return scratch/damage photos per reservation.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `reservation_scratch_photos` (
    `id`             INT              NOT NULL AUTO_INCREMENT,
    `reservation_id` INT              NOT NULL,
    `event_type`     ENUM('delivery','return') NOT NULL,
    `slot_index`     TINYINT          NOT NULL,
    `file_path`      VARCHAR(255)     NOT NULL,
    `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_rsp_reservation` (`reservation_id`),
    CONSTRAINT `fk_rsp_reservation`
        FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
