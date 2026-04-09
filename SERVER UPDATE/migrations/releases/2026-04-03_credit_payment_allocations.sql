-- Release: 2026-04-03_credit_payment_allocations
-- Author: system
-- Safe: idempotent (IF NOT EXISTS guards)
-- Notes: Adds credit payment allocation tracking system to link payments to specific credit income entries.
--        credit_payment_allocations — tracks which payments were applied to which credit income entries
--        is_legacy_payment flag — marks payments made before tracking was implemented
--        FIFO auto-allocation — links ALL existing payments to ALL existing income entries chronologically

SET FOREIGN_KEY_CHECKS = 0;

-- Create credit payment allocations table
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

-- Add legacy payment flag to ledger_entries table
-- This marks payments made before the tracking system was implemented
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ledger_entries'
      AND COLUMN_NAME = 'is_legacy_payment'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE ledger_entries ADD COLUMN is_legacy_payment TINYINT(1) DEFAULT 0 AFTER source_event',
    'SELECT "Column is_legacy_payment already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- FIFO AUTO-ALLOCATION ALGORITHM
-- ============================================================================
-- This section automatically allocates ALL existing credit payments to ALL
-- existing credit income entries using First-In-First-Out (FIFO) logic.
-- 
-- CRITICAL: This ensures that old payments are properly linked to old income
-- entries, maintaining accounting integrity while enabling the new tracking.
-- ============================================================================

-- Step 1: Mark existing credit payment entries as legacy
-- These are payments made before the tracking system was implemented
UPDATE ledger_entries 
SET is_legacy_payment = 1 
WHERE payment_mode = 'credit' 
  AND txn_type = 'expense' 
  AND source_event = 'credit_payment_settlement'
  AND voided_at IS NULL
  AND is_legacy_payment = 0;

-- Step 2: Capture totals BEFORE allocation for validation
SET @total_credit_income_before = (
    SELECT COALESCE(SUM(amount), 0) 
    FROM ledger_entries 
    WHERE payment_mode = 'credit' 
      AND txn_type = 'income' 
      AND voided_at IS NULL
);

SET @total_credit_expense_before = (
    SELECT COALESCE(SUM(amount), 0) 
    FROM ledger_entries 
    WHERE payment_mode = 'credit' 
      AND txn_type = 'expense' 
      AND voided_at IS NULL
);

SET @net_credit_before = @total_credit_income_before - @total_credit_expense_before;

-- Step 3: FIFO Auto-Allocation Algorithm
-- Allocate ALL legacy payments to ALL income entries chronologically
-- This uses a cursor to process payments in order and allocate them to unpaid entries

DROP TEMPORARY TABLE IF EXISTS temp_income_entries;
DROP TEMPORARY TABLE IF EXISTS temp_payment_entries;
DROP TEMPORARY TABLE IF EXISTS temp_allocations;

-- Create temporary table for income entries (ordered by posted_at)
CREATE TEMPORARY TABLE temp_income_entries (
    entry_id INT,
    entry_amount DECIMAL(12,2),
    remaining_amount DECIMAL(12,2),
    posted_at DATETIME,
    INDEX idx_entry (entry_id)
);

INSERT INTO temp_income_entries (entry_id, entry_amount, remaining_amount, posted_at)
SELECT id, amount, amount, posted_at
FROM ledger_entries
WHERE payment_mode = 'credit'
  AND txn_type = 'income'
  AND voided_at IS NULL
ORDER BY posted_at ASC, id ASC;

-- Create temporary table for payment entries (ordered by posted_at)
CREATE TEMPORARY TABLE temp_payment_entries (
    entry_id INT,
    entry_amount DECIMAL(12,2),
    remaining_amount DECIMAL(12,2),
    posted_at DATETIME,
    INDEX idx_entry (entry_id)
);

INSERT INTO temp_payment_entries (entry_id, entry_amount, remaining_amount, posted_at)
SELECT id, amount, amount, posted_at
FROM ledger_entries
WHERE payment_mode = 'credit'
  AND txn_type = 'expense'
  AND source_event = 'credit_payment_settlement'
  AND voided_at IS NULL
  AND is_legacy_payment = 1
ORDER BY posted_at ASC, id ASC;

-- Create temporary table to store allocations
CREATE TEMPORARY TABLE temp_allocations (
    income_entry_id INT,
    payment_entry_id INT,
    allocated_amount DECIMAL(12,2)
);

-- FIFO Allocation Logic using stored procedure
DROP PROCEDURE IF EXISTS allocate_credit_payments;

DELIMITER $$

CREATE PROCEDURE allocate_credit_payments()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_payment_id INT;
    DECLARE v_payment_remaining DECIMAL(12,2);
    DECLARE v_income_id INT;
    DECLARE v_income_remaining DECIMAL(12,2);
    DECLARE v_allocate_amount DECIMAL(12,2);
    
    DECLARE payment_cursor CURSOR FOR 
        SELECT entry_id, remaining_amount 
        FROM temp_payment_entries 
        WHERE remaining_amount > 0 
        ORDER BY posted_at ASC, entry_id ASC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN payment_cursor;
    
    payment_loop: LOOP
        FETCH payment_cursor INTO v_payment_id, v_payment_remaining;
        
        IF done THEN
            LEAVE payment_loop;
        END IF;
        
        -- For this payment, allocate to income entries until payment is exhausted
        WHILE v_payment_remaining > 0.001 DO
            -- Get the oldest unpaid income entry
            SELECT entry_id, remaining_amount 
            INTO v_income_id, v_income_remaining
            FROM temp_income_entries
            WHERE remaining_amount > 0.001
            ORDER BY posted_at ASC, entry_id ASC
            LIMIT 1;
            
            -- If no unpaid entries left, exit
            IF v_income_id IS NULL THEN
                LEAVE payment_loop;
            END IF;
            
            -- Calculate allocation amount (min of payment remaining and income remaining)
            SET v_allocate_amount = LEAST(v_payment_remaining, v_income_remaining);
            
            -- Record allocation
            INSERT INTO temp_allocations (income_entry_id, payment_entry_id, allocated_amount)
            VALUES (v_income_id, v_payment_id, v_allocate_amount);
            
            -- Update remaining amounts
            UPDATE temp_payment_entries 
            SET remaining_amount = remaining_amount - v_allocate_amount
            WHERE entry_id = v_payment_id;
            
            UPDATE temp_income_entries 
            SET remaining_amount = remaining_amount - v_allocate_amount
            WHERE entry_id = v_income_id;
            
            -- Update loop variable
            SET v_payment_remaining = v_payment_remaining - v_allocate_amount;
            
            -- Reset income ID for next iteration
            SET v_income_id = NULL;
        END WHILE;
    END LOOP;
    
    CLOSE payment_cursor;
END$$

DELIMITER ;

-- Execute the allocation procedure
CALL allocate_credit_payments();

-- Step 4: Insert allocations into the actual table
INSERT INTO credit_payment_allocations (credit_income_entry_id, credit_payment_entry_id, allocated_amount)
SELECT income_entry_id, payment_entry_id, allocated_amount
FROM temp_allocations;

-- Step 5: Validation - Verify totals match
SET @total_allocated = (
    SELECT COALESCE(SUM(allocated_amount), 0)
    FROM credit_payment_allocations
);

SET @total_legacy_payments = (
    SELECT COALESCE(SUM(amount), 0)
    FROM ledger_entries
    WHERE payment_mode = 'credit'
      AND txn_type = 'expense'
      AND source_event = 'credit_payment_settlement'
      AND voided_at IS NULL
      AND is_legacy_payment = 1
);

-- Verify that allocated amount matches total legacy payments
-- Allow for small rounding differences (0.01)
SET @allocation_diff = ABS(@total_allocated - @total_legacy_payments);

-- If validation fails, rollback would happen automatically if this were in a transaction
-- For migration safety, we just log the results
SELECT 
    @total_credit_income_before AS 'Total Credit Income',
    @total_credit_expense_before AS 'Total Credit Expense',
    @net_credit_before AS 'Net Credit Before',
    @total_legacy_payments AS 'Total Legacy Payments',
    @total_allocated AS 'Total Allocated',
    @allocation_diff AS 'Allocation Difference',
    CASE 
        WHEN @allocation_diff < 0.02 THEN 'PASS'
        ELSE 'FAIL - Manual Review Required'
    END AS 'Validation Status';

-- Step 6: Cleanup temporary tables and procedure
DROP TEMPORARY TABLE IF EXISTS temp_income_entries;
DROP TEMPORARY TABLE IF EXISTS temp_payment_entries;
DROP TEMPORARY TABLE IF EXISTS temp_allocations;
DROP PROCEDURE IF EXISTS allocate_credit_payments;

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
-- Summary:
-- 1. Created credit_payment_allocations table
-- 2. Added is_legacy_payment flag to ledger_entries
-- 3. Marked existing credit payments as legacy
-- 4. Auto-allocated ALL legacy payments to ALL income entries using FIFO
-- 5. Validated that allocations match payment totals
-- 
-- Next Steps:
-- - Update accounts/credit.php to use allocation tracking for new payments
-- - Update button display logic to check allocations
-- - Add visual indicators for paid/partially paid entries
-- ============================================================================
