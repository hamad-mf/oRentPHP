-- ============================================================
--  oRent — MIGRATION: Security Deposit Tracking Columns
--  Run this in phpMyAdmin → SQL tab
-- ============================================================

ALTER TABLE `reservations` 
  ADD COLUMN IF NOT EXISTS `deposit_deducted` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `deposit_returned`,
  ADD COLUMN IF NOT EXISTS `deposit_held`     decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `deposit_deducted`,
  ADD COLUMN IF NOT EXISTS `deposit_hold_reason` text DEFAULT NULL               AFTER `deposit_held`;

-- ============================================================
-- DONE!
-- ============================================================
