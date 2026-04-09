-- Release: 2026-04-03_credit_payment_allocations_SIMPLE
-- Author: system
-- Safe: idempotent (IF NOT EXISTS guards)
-- Notes: Adds credit payment allocation tracking system to link payments to specific credit income entries.
--        NO LEGACY PAYMENT HANDLING - Just creates the tracking system for future payments.

SET FOREIGN_KEY_CHECKS = 0;

-- Create credit payment allocations table
-- This tracks which payments were applied to which credit income entries
CREATE TABLE IF NOT EXISTS credit_payment_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    credit_income_entry_id INT NOT NULL COMMENT 'FK to ledger_entries.id (income entry being paid)',
    credit_payment_entry_id INT NOT NULL COMMENT 'FK to ledger_entries.id (payment entry)',
    allocated_amount DECIMAL(12,2) NOT NULL COMMENT 'Amount allocated from payment to income entry',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (credit_income_entry_id) REFERENCES ledger_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (credit_payment_entry_id) REFERENCES ledger_entries(id) ON DELETE CASCADE,
    INDEX idx_income_entry (credit_income_entry_id),
    INDEX idx_payment_entry (credit_payment_entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- That's it! Simple and clean.
-- From now on, when users click "Add Payment", the system will:
-- 1. Create the payment ledger entries (as before)
-- 2. Create an allocation record linking payment to the specific credit entry
-- 3. Button will check allocations to show/hide correctly

SELECT 'Migration complete! Credit payment tracking is now active.' AS status;
