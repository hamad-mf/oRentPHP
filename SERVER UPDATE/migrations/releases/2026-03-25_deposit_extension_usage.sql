-- Release: 2026-03-25_deposit_extension_usage
-- Author: system
-- Safe: idempotent (IF NOT EXISTS guards)
-- Notes: Adds deposit tracking columns for extension payments.
--        reservations.deposit_used_for_extension  — cumulative deposit used across all extensions
--        reservation_extensions.paid_from_deposit — deposit portion for this extension
--        reservation_extensions.paid_cash         — cash/credit/account portion for this extension
--        reservation_extensions.payment_source_type — how the extension was paid

SET FOREIGN_KEY_CHECKS = 0;

-- 1. reservations: track cumulative deposit used for extensions
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS deposit_used_for_extension DECIMAL(10,2) NOT NULL DEFAULT 0.00
    AFTER deposit_held;

-- 2. reservation_extensions: per-extension deposit amount
ALTER TABLE reservation_extensions
    ADD COLUMN IF NOT EXISTS paid_from_deposit DECIMAL(10,2) NOT NULL DEFAULT 0.00
    AFTER amount;

-- 3. reservation_extensions: per-extension cash/credit/account amount (used in split payments)
ALTER TABLE reservation_extensions
    ADD COLUMN IF NOT EXISTS paid_cash DECIMAL(10,2) NOT NULL DEFAULT 0.00
    AFTER paid_from_deposit;

-- 4. reservation_extensions: payment source type
ALTER TABLE reservation_extensions
    ADD COLUMN IF NOT EXISTS payment_source_type ENUM('cash','credit','account','deposit','split') DEFAULT NULL
    AFTER bank_account_id;

SET FOREIGN_KEY_CHECKS = 1;
