-- ============================================================================
-- REVERT $15,000 STAFF ADVANCE (Given by accident on production)
-- Date: 16 Apr 2026
-- ============================================================================
-- This script cleanly removes the advance without leaving any trace
--
-- WHAT THIS AFFECTS:
-- 1. payroll_advances table - The advance record itself
-- 2. ledger_entries table - The expense transaction
-- 3. bank_accounts table - The bank balance (will be restored)
-- 4. Dashboard/Reports - All calculated dynamically from above tables
--    - Staff profile "Due" amount
--    - Monthly reports expense totals
--    - Hope window calculations
--    - Vehicle targets
--    - Accounts page
--
-- NO CACHED DATA - Everything is calculated on-the-fly from these 3 tables
-- ============================================================================

-- STEP 1: Verify the advance exists (SAFETY CHECK)
SELECT 
    pa.id AS advance_id,
    pa.amount,
    pa.given_at,
    pa.status,
    pa.remaining_amount,
    u.name AS staff_name,
    ba.name AS bank_account,
    ba.balance AS current_bank_balance,
    le.id AS ledger_entry_id,
    le.description
FROM payroll_advances pa
LEFT JOIN users u ON pa.user_id = u.id
LEFT JOIN bank_accounts ba ON pa.bank_account_id = ba.id
LEFT JOIN ledger_entries le ON pa.ledger_entry_id = le.id
WHERE pa.amount = 15000.00 
AND DATE(pa.given_at) = '2026-04-16'
ORDER BY pa.given_at DESC 
LIMIT 1;

-- ============================================================================
-- UNCOMMENT BELOW TO EXECUTE THE REVERT (after verifying above)
-- ============================================================================

/*
-- Step 2: Find the advance record (most recent $15,000 advance from today)
SET @advance_id = (
    SELECT id 
    FROM payroll_advances 
    WHERE amount = 15000.00 
    AND DATE(given_at) = '2026-04-16'
    ORDER BY given_at DESC 
    LIMIT 1
);

-- Step 3: Get the associated ledger entry ID and bank account
SET @ledger_id = (SELECT ledger_entry_id FROM payroll_advances WHERE id = @advance_id);
SET @bank_id = (SELECT bank_account_id FROM payroll_advances WHERE id = @advance_id);
SET @amount = (SELECT amount FROM payroll_advances WHERE id = @advance_id);

-- Step 4: Restore the bank account balance (add back the $15,000)
UPDATE bank_accounts 
SET balance = balance + @amount
WHERE id = @bank_id;

-- Step 5: Delete the ledger entry
DELETE FROM ledger_entries 
WHERE id = @ledger_id;

-- Step 6: Delete the payroll advance record
DELETE FROM payroll_advances 
WHERE id = @advance_id;

-- Step 7: Verify clean removal
SELECT 'VERIFICATION: Should return 0 rows' AS check_name;
SELECT * FROM payroll_advances WHERE amount = 15000.00 AND DATE(given_at) = '2026-04-16';
SELECT * FROM ledger_entries WHERE description LIKE '%Staff Advance%' AND amount = 15000.00 AND DATE(posted_at) = '2026-04-16';
*/
