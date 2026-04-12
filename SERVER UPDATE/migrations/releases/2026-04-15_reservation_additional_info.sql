-- Release: 2026-04-15_reservation_additional_info
-- Author: system
-- Safe: idempotent (IF NOT EXISTS guards)
-- Notes: Adds supplementary information fields to reservations table.
--        delivery_location VARCHAR(255) NULL — optional delivery location for reference
--        return_location VARCHAR(255) NULL — optional return location for reference
--        additional_note TEXT NULL — optional general note (max 1000 chars in UI)

SET FOREIGN_KEY_CHECKS = 0;

-- Add delivery location column
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS delivery_location VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'Optional delivery location for reference';

-- Add return location column
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS return_location VARCHAR(255) NULL DEFAULT NULL
    COMMENT 'Optional return location for reference';

-- Add additional note column
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS additional_note TEXT NULL DEFAULT NULL
    COMMENT 'Optional general note (max 1000 chars in UI)';

SET FOREIGN_KEY_CHECKS = 1;
0