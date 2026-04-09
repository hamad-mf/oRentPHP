# Implementation Plan

- [ ] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Payment Button Persists After Payment
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the bug exists
  - **Scoped PBT Approach**: For deterministic bugs, scope the property to the concrete failing case(s) to ensure reproducibility
  - Test implementation details from Bug Condition in design:
    - Create a credit income entry (e.g., $1,000 for reservation #47)
    - Record a credit payment for that entry
    - Verify that the "Add Payment" button still appears (BUG - should be hidden/disabled)
    - Test that no linkage exists between payment and credit income entry
  - The test assertions should match the Expected Behavior Properties from design:
    - Button should be hidden for fully paid entries
    - Button should show remaining amount for partially paid entries
    - State should persist across page reloads
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS (this is correct - it proves the bug exists)
  - Document counterexamples found to understand root cause:
    - Example: "After $1,000 payment for reservation #47 credit entry, button still shows 'Add Payment' with full amount"
    - Example: "No record in database linking payment to specific credit income entry"
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2, 1.3_

- [ ] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Credit Balance and Ledger Integrity
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-buggy inputs:
    - Calculate total credit balance (income - expense) and record the result
    - Create a credit payment and observe the ledger entries created (credit expense + cash/bank income)
    - Display credit transactions and observe sorting, filtering, pagination behavior
    - Test "prioritize income" toggle and observe the sorting change
    - Test voided entry handling and observe balance calculations
  - Write property-based tests capturing observed behavior patterns from Preservation Requirements:
    - Property: Credit balance calculation remains unchanged (total income - total expense)
    - Property: Ledger entry creation for payments remains unchanged (two entries: credit expense + cash/bank income)
    - Property: Transaction display with amounts, dates, client info remains unchanged
    - Property: "Prioritize income" toggle continues to work correctly
    - Property: Voided entry handling remains unchanged
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [-] 3. Fix for credit payment button persistence

  - [x] 3.1 Create database schema for payment allocations
    - Create migration file: `migrations/releases/2026-04-03_credit_payment_allocations.sql`
    - Add `credit_payment_allocations` table with columns:
      - `id` (INT AUTO_INCREMENT PRIMARY KEY)
      - `credit_income_entry_id` (INT NOT NULL, FK to ledger_entries.id)
      - `credit_payment_entry_id` (INT NOT NULL, FK to ledger_entries.id)
      - `allocated_amount` (DECIMAL(12,2) NOT NULL)
      - `created_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
    - Add indexes on `credit_income_entry_id` and `credit_payment_entry_id`
    - Add optional `is_legacy_payment` flag to `ledger_entries` table (TINYINT(1) DEFAULT 0)
    - _Bug_Condition: isBugCondition(creditIncomeEntry, systemState) where no linkage exists between payment and entry_
    - _Expected_Behavior: Payment allocations are tracked in database, enabling button state calculation_
    - _Preservation: Schema changes do not affect existing ledger entry creation or balance calculations_
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.2 Update credit payment recording logic in accounts/credit.php
    - Modify the "Add Payment" button to pass `credit_income_entry_id` via hidden form field
    - Update `openCreditPaymentModal()` JavaScript function to accept and store entry ID
    - In POST handler for `action=add_payment`:
      - Capture `credit_income_entry_id` from form submission
      - After creating credit payment ledger entries, insert allocation record
      - Link payment to specific credit income entry in `credit_payment_allocations` table
      - Handle edge case: if payment amount exceeds single entry, allocate to multiple entries using FIFO
    - _Bug_Condition: Currently no linkage is created when payment is recorded_
    - _Expected_Behavior: Payment is linked to specific credit income entry via allocation table_
    - _Preservation: Ledger entry creation logic remains unchanged (still creates credit expense + cash/bank income)_
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.3 Update button display logic in accounts/credit.php
    - For each credit income entry row in the table:
      - Query `credit_payment_allocations` to calculate total allocated amount for this entry
      - Calculate remaining unpaid amount: `entry_amount - total_allocated`
      - Update `$canRowSettle` condition to check if `remaining_amount > 0`
      - Update button text to show remaining amount if partially paid
      - Hide button if fully paid (`remaining_amount <= 0`)
    - Add visual indicator for paid/partially paid entries:
      - Show badge: "Paid: $X / $Y" for partial payments
      - Show badge: "Fully Paid" for complete payments
    - Update `$prefillAmount` calculation to use remaining unpaid amount instead of full entry amount
    - _Bug_Condition: Currently button shows for all entries regardless of payment state_
    - _Expected_Behavior: Button reflects actual payment state and persists across page loads_
    - _Preservation: Display of other transaction details (amounts, dates, client info) remains unchanged_
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.4 Handle legacy payments (payments made before fix)
    - Create migration script to mark existing credit payment entries as legacy:
      - Query for entries with `source_event='credit_payment_settlement'` and `posted_at < fix_deployment_date`
      - Update `is_legacy_payment=1` for these entries
    - Update button display logic to exclude legacy payments from allocation calculations
    - Add UI notice: "Note: $X in legacy payments (made before tracking) are included in the balance"
    - Optional: Create admin page `/accounts/credit_legacy_allocations.php` for manual allocation of legacy payments
    - _Bug_Condition: Existing $1,000 payment at 13:07:45 has no linkage to credit income entry_
    - _Expected_Behavior: Legacy payments reduce pool balance but don't affect individual button states_
    - _Preservation: Total credit balance calculations include legacy payments (unchanged behavior)_
    - _Requirements: 2.1, 2.3_

  - [ ] 3.5 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Payment Button Reflects Payment State
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied:
      - Button is hidden/disabled for fully paid entries
      - Button shows remaining amount for partially paid entries
      - State persists across page reloads
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed)
    - _Requirements: Expected Behavior Properties from design (2.1, 2.2, 2.3)_

  - [ ] 3.6 Verify preservation tests still pass
    - **Property 2: Preservation** - Credit Balance and Ledger Integrity
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all tests still pass after fix:
      - Credit balance calculation unchanged
      - Ledger entry creation unchanged
      - Transaction display unchanged
      - "Prioritize income" toggle works correctly
      - Voided entry handling unchanged

- [ ] 4. Interactive Testing - Verify fix with user screenshots
  - **IMPORTANT**: This is an interactive testing phase where I guide the user step-by-step
  - **Approach**: User will show me their dashboard, accounts screen, and credit transactions screen after implementation
  - **Test Cases** (4-5 maximum, covering ALL edge cases):
    
    1. **Full Payment Test**:
       - Step 1: Navigate to Accounts → Credit Transactions → Screenshot → Verify credit entries are visible
       - Step 2: Click "Add Payment" on a credit entry (e.g., $500) → Fill amount $500 → Submit → Screenshot → Verify success message
       - Step 3: Return to Credit Transactions page → Screenshot → Verify "Add Payment" button is HIDDEN for that entry
       - Step 4: Reload page (F5) → Screenshot → Verify button remains hidden (persistence check)
    
    2. **Partial Payment Test**:
       - Step 1: Navigate to credit entry with $1,000 balance → Screenshot → Verify "Add Payment" button shows
       - Step 2: Click "Add Payment" → Enter $400 → Submit → Screenshot → Verify success message
       - Step 3: Return to Credit Transactions → Screenshot → Verify button now shows remaining amount ($600)
       - Step 4: Reload page → Screenshot → Verify button still shows $600 (persistence check)
    
    3. **Multiple Entries Test**:
       - Step 1: Create two credit entries (e.g., $500 and $300) → Screenshot → Verify both show "Add Payment" buttons
       - Step 2: Make $500 payment → Screenshot → Verify payment recorded
       - Step 3: Return to Credit Transactions → Screenshot → Verify only the $300 entry shows "Add Payment" button
       - Step 4: Verify the $500 entry shows "Fully Paid" badge
    
    4. **Legacy Payment Test**:
       - Step 1: Navigate to Credit Transactions → Screenshot → Verify reservation #47 entry (with $1,000 legacy payment at 13:07:45)
       - Step 2: Verify UI shows legacy payment notice: "Note: $X in legacy payments are included in the balance"
       - Step 3: Verify button state for entries that existed before fix → Screenshot
       - Step 4: Make a new payment after fix → Screenshot → Verify new payment is properly tracked
    
    5. **Page Reload Persistence Test**:
       - Step 1: Make any payment (full or partial) → Screenshot → Verify button state changes
       - Step 2: Reload page (F5) → Screenshot → Verify button state persists
       - Step 3: Navigate away and return to Credit Transactions → Screenshot → Verify state still persists
       - Step 4: Log out and log back in → Navigate to Credit Transactions → Screenshot → Verify state persists across sessions
  
  - **Expected Results**:
    - All "Add Payment" buttons reflect actual payment state
    - Fully paid entries hide/disable the button
    - Partially paid entries show remaining amount
    - State persists across page reloads and sessions
    - Legacy payments are handled correctly without breaking button logic
    - Total credit balance calculations remain correct
  
  - **Interactive Approach**:
    - User will provide screenshots for each step
    - I will verify each screenshot before moving to next step
    - If any test fails, I will diagnose the issue and provide fix guidance
    - User can ask questions at any point during testing
  
  - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 3.4, 3.5_

- [ ] 5. Checkpoint - Ensure all tests pass
  - Ensure all tests pass (bug condition test, preservation tests, interactive tests)
  - Verify no regressions in credit balance calculations, ledger entry creation, or transaction display
  - Ask the user if questions arise or if any edge cases need additional testing
