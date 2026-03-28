        -- Release: 2026-03-28_client_satisfaction_tracking
        -- Author: system
        -- Safe: idempotent (IF NOT EXISTS guards)
        -- Notes: Adds client satisfaction tracking to reservations table.
        --        client_satisfied ENUM('yes','no') NULL — satisfaction indicator
        --        client_comment VARCHAR(255) NULL — optional feedback text

        SET FOREIGN_KEY_CHECKS = 0;

        -- Add satisfaction indicator column
        ALTER TABLE reservations
            ADD COLUMN IF NOT EXISTS client_satisfied ENUM('yes', 'no') NULL DEFAULT NULL
            AFTER return_paid_amount;

        -- Add comment column
        ALTER TABLE reservations
            ADD COLUMN IF NOT EXISTS client_comment VARCHAR(255) NULL DEFAULT NULL
            AFTER client_satisfied;

        SET FOREIGN_KEY_CHECKS = 1;
