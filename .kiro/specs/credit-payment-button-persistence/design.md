# Credit Payment Button Persistence Bugfix Design

## Overview

The credit payment system currently treats credit as a pooled balance without linking payments to specific credit income entries. This causes the UI to display "Add Payment" buttons for all credit income entries regardless of whether they've been paid. The fix introduces a mechanism to track which credit income entries have been paid (fully or partially) and persists this state across page loads.

The fix must also handle existing credit payments that were made before the fix (like the $1,000 payment at 13:07:45) by providing a migration strategy that either retroactively links them or marks them as "legacy" payments.

## Glossary

- **Bug_Condition (C)**: The condition where a credit income entry displays an "Add Payment" button even after a payment has been recorded for it
- **Property (P)**: The desired behavior where paid credit entries hide/disable the button and unpaid entries show it
- **Preservation**: Existing credit balance calculations, ledger entry creation, and transaction display must remain unchanged
- **Credit Income Entry**: A ledger entry with `payment_mode='credit'` and `txn_type='income'` representing money owed by a client
- **Credit Payment**: A pair of ledger entries that settle credit (one credit expense, one cash/bank income)
- **Payment Linkage**: The mechanism to associate a credit payment with specific credit income entries
- **Legacy Payment**: A credit payment made before the fix was implemented, without linkage to specific entries

## Bug Details

### Bug Condition

The bug manifests when a user adds a credit payment for a credit income entry. The payment is successfully recorded in the ledger, but the UI continues to show the "Add Payment" button for that entry because there's no mechanism to track which entries have been paid.

**Formal Specification:**
```
FUNCTION isBugCondition(creditIncomeEntry, systemState)
  INPUT: creditIncomeEntry of type LedgerEntry (where payment_mode='credit' AND txn_type='income')
         systemState containing all ledger entries and UI state
  OUTPUT: boolean
  
  RETURN creditIncomeEntry.voided_at IS NULL
         AND EXISTS(creditPayment WHERE creditPayment.source_event='credit_payment_settlement' 
                                    AND creditPayment.posted_at >= creditIncomeEntry.posted_at
                                    AND creditPayment.voided_at IS NULL)
         AND UI_displays_add_payment_button(creditIncomeEntry)
         AND NOT EXISTS(linkage WHERE linkage.credit_income_id = creditIncomeEntry.id)
END FUNCTION
```

### Examples

- **Reservation #47 Credit Entry**: A credit income entry exists for reservation #47. At 13:07:45, a $1,000 credit payment was made. The payment was recorded successfully, but the "Add Payment" button still appears for this entry because the system cannot determine if this payment was for this specific entry.

- **Multiple Credit Entries**: Client A has two credit entries: $500 and $300. A $500 payment is made. The system reduces the total credit balance to $300, but both entries still show "Add Payment" buttons because the system doesn't know which entry the payment was for.

- **Partial Payment**: A credit entry for $1,000 exists. A $400 payment is made. The button should update to show $600 remaining, but instead continues to show $1,000 because there's no tracking of partial payments.

- **Edge Case - Full Balance Payment**: Total credit balance is $1,200 across 3 entries. A $1,200 payment is made. All buttons should disappear, and they do (because `$canAddPayment` becomes false), but if a new credit entry is added later, all old entries incorrectly show buttons again.

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Credit balance calculations (total income - total expense) must continue to work exactly as before
- Ledger entry creation for credit payments must remain unchanged (credit expense + cash/bank income)
- Transaction display with amounts, dates, client info, and sorting must remain unchanged
- The "prioritize income" toggle functionality must continue to work
- Voided entry handling must remain unchanged
- Bank account balance updates must continue to work correctly

**Scope:**
All inputs and operations that do NOT involve determining which credit income entries have been paid should be completely unaffected by this fix. This includes:
- Adding new credit income entries (from reservations or manual entries)
- Viewing credit transaction history
- Filtering and pagination
- Voiding entries
- Cash/bank transfer operations

## Comprehensive System Analysis

### Credit Flow Points in the System

After analyzing the entire codebase, credit payments flow through these entry points:

1. **Reservations (Primary Source)**:
   - `reservations/deliver.php` - Can accept credit for delivery payment
   - `reservations/return.php` - Can accept credit for return charges
   - Both use `ledger_post_reservation_event_multi()` which calls `ledger_post()`
   - Creates entries with `source_type='reservation'`, `source_id=reservation_id`, `payment_mode='credit'`

2. **Vehicles**:
   - `vehicles/show.php` - Vehicle expenses can be paid on credit
   - `vehicles/challans.php` - Challan payments can be on credit
   - `vehicles/mark_challan_paid.php` - Processes challan credit payments
   - Creates entries with `source_type='manual'` or vehicle-related source

3. **Manual Credit Entries**:
   - `accounts/credit.php` - Manual credit income/expense entries
   - Uses `ledger_post_manual()` which calls `ledger_post()`

4. **Credit Payment Settlement**:
   - `accounts/credit.php` - "Add Payment" button creates settlement entries
   - Creates TWO entries: credit expense + cash/bank income
   - Credit expense has `source_event='credit_payment_settlement'`
   - NO linkage to which credit income entry was being paid

### All Flows Converge at `ledger_post()`

**Critical Discovery**: Every credit entry (income or expense) goes through the `ledger_post()` function in `includes/ledger_helpers.php`. This function already captures:
- `source_type` - 'reservation', 'manual', 'transfer', etc.
- `source_id` - The ID of the source entity (reservation ID, etc.)
- `source_event` - The specific event ('delivery', 'return', 'credit_payment_settlement', etc.)

This existing structure is PERFECT for our fix - we just need to add allocation tracking on top of it.

## Hypothesized Root Cause

Based on comprehensive code analysis, the root causes are:

1. **No Payment Linkage Mechanism**: The `ledger_entries` table has `source_id` for the original credit income (e.g., reservation ID), but credit payment entries (source_event='credit_payment_settlement') have NO way to link back to which specific credit income entries they're paying off. The system treats credit as a pool.

2. **Button Display Logic**: The `$canRowSettle` variable in `accounts/credit.php` only checks:
   - If there's any credit balance remaining (`$canAddPayment`)
   - If the row is an income entry
   - If the row is not voided
   
   It does NOT check if this specific entry has already been paid.

3. **No Persistent State**: There's no database column or mechanism to track which credit income entries have received payments and how much has been paid against each entry.

4. **Legacy Data Problem**: Existing credit payments (like the $1,000 payment at 13:07:45) have no linkage to specific entries, making it impossible to retroactively determine which entries they were meant to pay.

5. **Multiple Entry Points**: Credit can come from reservations, vehicle expenses, challans, or manual entries - but all converge at `ledger_post()`, making it the perfect interception point for our fix.

## Correctness Properties

Property 1: Bug Condition - Payment Button Reflects Payment State

_For any_ credit income entry that has received a payment (full or partial), the fixed system SHALL either hide the "Add Payment" button (if fully paid) or update it to show the remaining unpaid amount (if partially paid), and this state SHALL persist across page loads.

**Validates: Requirements 2.1, 2.2, 2.3**

Property 2: Preservation - Credit Balance and Ledger Integrity

_For any_ operation that does NOT involve determining payment state for specific credit entries (such as calculating total credit balance, creating ledger entries, displaying transaction history, or filtering), the fixed code SHALL produce exactly the same behavior as the original code, preserving all existing functionality.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

## Fix Implementation

### Changes Required

The fix requires both schema changes and code modifications to introduce payment linkage while handling legacy data.

**Database Schema Changes:**

1. **Create Credit Payment Allocations Table**:
   ```sql
   CREATE TABLE credit_payment_allocations (
       id INT AUTO_INCREMENT PRIMARY KEY,
       credit_income_entry_id INT NOT NULL,
       credit_payment_entry_id INT NOT NULL,
       allocated_amount DECIMAL(12,2) NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       FOREIGN KEY (credit_income_entry_id) REFERENCES ledger_entries(id) ON DELETE CASCADE,
       FOREIGN KEY (credit_payment_entry_id) REFERENCES ledger_entries(id) ON DELETE CASCADE,
       INDEX idx_income_entry (credit_income_entry_id),
       INDEX idx_payment_entry (credit_payment_entry_id)
   ) ENGINE=InnoDB;
   ```

2. **Add Legacy Payment Flag** (optional, for tracking):
   ```sql
   ALTER TABLE ledger_entries 
   ADD COLUMN is_legacy_payment TINYINT(1) DEFAULT 0 
   AFTER source_event;
   ```

**File**: `accounts/credit.php`

**Specific Changes**:

1. **Modify Payment Recording Logic**:
   - When a payment is added via the "Add Payment" button, capture which credit income entry triggered it
   - Add a hidden field to the form to pass the `credit_income_entry_id`
   - After creating the credit payment ledger entries, insert allocation records linking the payment to the specific entry
   - Handle cases where payment amount exceeds the single entry (allocate to multiple entries using FIFO or user selection)

2. **Update Button Display Logic**:
   - For each credit income entry row, calculate the total allocated amount from `credit_payment_allocations`
   - Calculate remaining unpaid amount: `entry_amount - total_allocated`
   - Update `$canRowSettle` to check if remaining amount > 0
   - Update button text to show remaining amount if partially paid
   - Hide button if fully paid (remaining amount <= 0)

3. **Add Paid Amount Display**:
   - Show a visual indicator (badge or text) on entries that have been partially or fully paid
   - Display: "Paid: $X / $Y" or "Fully Paid" status

4. **Handle Legacy Payments**:
   - Add a one-time migration script or admin tool to handle existing unlinked payments
   - Options:
     - **Option A (Recommended)**: Mark existing credit payment entries as `is_legacy_payment=1` and exclude them from allocation logic. They reduce the pool balance but don't link to specific entries.
     - **Option B**: Provide an admin interface to manually allocate legacy payments to entries
     - **Option C**: Auto-allocate legacy payments using FIFO (oldest unpaid entries first) based on payment timestamps

5. **Update Modal Behavior**:
   - Modify `openCreditPaymentModal()` to accept and store the credit income entry ID
   - Add hidden input field in the form: `<input type="hidden" name="credit_income_entry_id" value="...">`
   - Prefill amount should be min(remaining_unpaid_for_entry, total_credit_balance)

### Migration Strategy for Existing Data

**For the $1,000 payment at 13:07:45:**

1. **Identify the payment**: Query for ledger entries with `source_event='credit_payment_settlement'` and `posted_at='2026-04-03 13:07:45'`

2. **Mark as legacy**: Update `is_legacy_payment=1` for this entry

3. **UI Handling**: 
   - Legacy payments still reduce the total credit balance (existing behavior)
   - But they don't affect individual entry button states
   - Show a notice: "Note: $X in legacy payments (made before tracking) are included in the balance"

4. **Future Payments**: All new payments after the fix will be properly allocated

**Alternative Approach (if retroactive linking is desired):**

1. Provide an admin page: `/accounts/credit_legacy_allocations.php`
2. List all legacy payments with their amounts and timestamps
3. List all credit income entries that existed before or at the payment time
4. Allow admin to manually allocate each legacy payment to one or more entries
5. Store allocations in `credit_payment_allocations` table

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm that the button persistence issue exists and understand the root cause.

**Test Plan**: Create test scenarios with credit entries and payments, then verify that buttons incorrectly persist after payments. Run these tests on the UNFIXED code to observe failures.

**Test Cases**:
1. **Single Entry Full Payment Test**: Create a credit entry for $500, make a $500 payment, verify button still appears (will fail on unfixed code - button should disappear but doesn't)
2. **Single Entry Partial Payment Test**: Create a credit entry for $1000, make a $400 payment, verify button shows $1000 instead of $600 (will fail on unfixed code)
3. **Multiple Entries Payment Test**: Create two credit entries ($500, $300), make a $500 payment, verify both entries show buttons (will fail on unfixed code - should only show button for unpaid entry)
4. **Page Reload Test**: Make a payment, reload the page, verify button state persists (will fail on unfixed code - state is not persisted)

**Expected Counterexamples**:
- Buttons appear for fully paid entries
- Button amounts don't reflect partial payments
- No visual indication of payment status
- Possible causes: no payment linkage mechanism, no persistent state tracking, button logic only checks pool balance

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds (credit entries with payments), the fixed function produces the expected behavior (correct button state).

**Pseudocode:**
```
FOR ALL creditIncomeEntry WHERE has_received_payment(creditIncomeEntry) DO
  remaining := calculate_remaining_unpaid(creditIncomeEntry)
  button_state := get_button_state(creditIncomeEntry)
  
  IF remaining <= 0 THEN
    ASSERT button_state = 'hidden' OR button_state = 'disabled'
  ELSE
    ASSERT button_state = 'visible'
    ASSERT button_amount = remaining
  END IF
  
  // Verify persistence
  reload_page()
  button_state_after := get_button_state(creditIncomeEntry)
  ASSERT button_state = button_state_after
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold (operations not related to button state), the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL operation WHERE NOT affects_button_state(operation) DO
  result_original := execute_on_original_code(operation)
  result_fixed := execute_on_fixed_code(operation)
  ASSERT result_original = result_fixed
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code first for credit balance calculations, ledger entry creation, and transaction display, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Credit Balance Calculation Preservation**: Generate random sets of credit income/expense entries, verify total balance calculation is identical
2. **Ledger Entry Creation Preservation**: Make payments with various modes (cash/bank), verify ledger entries are created identically
3. **Transaction Display Preservation**: Verify sorting, filtering, pagination produce identical results
4. **Voiding Preservation**: Void entries and verify balance calculations remain correct

### Unit Tests

- Test `credit_payment_allocations` table insertion when payment is made
- Test calculation of remaining unpaid amount for an entry
- Test button display logic with various payment states (unpaid, partially paid, fully paid)
- Test legacy payment handling (marked entries don't affect button state)
- Test edge cases (payment amount exceeds entry amount, multiple entries, zero balance)

### Property-Based Tests

- Generate random credit entries and payments, verify button states are always correct
- Generate random sequences of payments and page reloads, verify state persistence
- Generate random legacy payment scenarios, verify they don't break button logic
- Test that all preservation requirements hold across many random scenarios

### Integration Tests

- Test full flow: create credit entry → make payment → verify button state → reload page → verify persistence
- Test multiple entries: create several entries → make payments → verify correct entries show/hide buttons
- Test legacy migration: run migration script → verify legacy payments are handled correctly
- Test admin allocation tool (if implemented): allocate legacy payment → verify button state updates
