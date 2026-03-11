-- Release: 2026-03-10_reservation_advance_payment
-- Author: Hamad
-- Safe: idempotent (ALTER TABLE with IF NOT EXISTS)
-- Notes: Adds advance payment fields to reservations table.
--        advance_paid            - amount collected at reservation creation as advance
--        advance_payment_method  - how the advance was paid (cash/account/credit)
--        advance_bank_account_id - bank account used when method = account

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE reservations
  ADD COLUMN IF NOT EXISTS advance_paid              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS advance_payment_method    ENUM('cash','account','credit') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS advance_bank_account_id   INT DEFAULT NULL;

SET FOREIGN_KEY_CHECKS = 1;
