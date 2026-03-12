-- Release: 2026-03-12_delivery_prepaid_charge
-- Author: Hamad
-- Safe: idempotent (ALTER TABLE with IF NOT EXISTS)
-- Notes: Adds prepaid delivery charge fields to reservations table.
--        delivery_charge_prepaid         - delivery charge collected at reservation creation
--        delivery_prepaid_payment_method - how the prepaid delivery charge was paid (cash/account/credit)
--        delivery_prepaid_bank_account_id- bank account used when method = account

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE reservations
  ADD COLUMN IF NOT EXISTS delivery_charge_prepaid          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS delivery_prepaid_payment_method  ENUM('cash','account','credit') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS delivery_prepaid_bank_account_id INT DEFAULT NULL;

SET FOREIGN_KEY_CHECKS = 1;
