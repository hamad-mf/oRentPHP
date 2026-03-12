-- Release: 2026-03-12_ledger_void_entries
-- Author: Hamad
-- Safe: idempotent (ALTER TABLE with IF NOT EXISTS)
-- Notes: Adds void metadata to ledger entries (soft-void with reason).

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE ledger_entries
  ADD COLUMN IF NOT EXISTS voided_at DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS voided_by INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS void_reason VARCHAR(255) DEFAULT NULL;

SET FOREIGN_KEY_CHECKS = 1;
