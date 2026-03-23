# Resolve Held Deposit Error Bugfix Design

## Overview

The "Release Held Deposit" and "Convert to Income" functionality fails with a generic error message due to nested transaction deadlock. The `resolve_held_deposit.php` script starts a transaction, then calls ledger helper functions that attempt to start their own transactions, causing PDO to throw an exception. The fix involves removing the outer transaction wrapper and relying on the ledger helper functions' internal transaction management, which already provides atomicity and idempotency guarantees.

## Glossary

- **Bug_Condition (C)**: The condition that triggers the bug - when resolve_held_deposit.php attempts to resolve a held deposit by calling ledger functions within an outer transaction
- **Property (P)**: The desired behavior - held deposits should be resolved successfully with proper ledger entries and database updates
- **Preservation**: Existing validation logic, ledger posting behavior, and error handling that must remain unchanged
- **Nested Transaction**: An attempt to call `beginTransaction()` when a transaction is already active, which PDO does not support
- **ledger_post()**: The function in `includes/ledger_helpers.php` that posts ledger entries with its own transaction management
- **ledger_post_security_deposit()**: A wrapper function that calls ledger_post() for security deposit transactions
- **Idempotency Key**: A unique identifier used by ledger_post() to prevent duplicate entries

## Bug Details

### Bug Condition

The bug manifests when a user attempts to resolve a held deposit (either release or convert action) on a completed reservation. The `resolve_held_deposit.php` script starts a transaction at line 77, then calls ledger helper functions (`ledger_post_security_deposit()` or `ledger_post()`) which attempt to start their own transactions at line 139 of `ledger_helpers.php`. PDO does not support nested transactions, causing an exception to be thrown.

**Formal Specification:**
```
FUNCTION isBugCondition(input)
  INPUT: input of type ResolveHeldDepositRequest
  OUTPUT: boolean
  
  RETURN input.action IN ['release', 'convert']
         AND input.reservation.status == 'completed'
         AND input.reservation.deposit_held > 0
         AND input.reservation.deposit_held_action IS NULL
         AND depositBankAccountId IS NOT NULL
         AND outerTransactionActive == true
         AND ledgerFunctionCallsBeginTransaction == true
END FUNCTION
```

### Examples

- **Release Action**: User clicks "Release to Client" on reservation #123 with $500 held deposit → System throws exception "There is already an active transaction" → Generic error shown
- **Convert Action**: User clicks "Convert to Income" on reservation #456 with $300 held deposit → System throws exception during first ledger_post() call → Generic error shown
- **No Bank Account**: User attempts to resolve held deposit but no bank account configured → System logs warning and skips ledger posting → Success (no nested transaction attempted)
- **Already Resolved**: User attempts to resolve an already-resolved held deposit → Validation catches this before transaction starts → Proper error message shown

## Expected Behavior

### Preservation Requirements

**Unchanged Behaviors:**
- Validation logic must continue to check for completed status, non-zero held amount, and unresolved state
- Permission checking must continue to require 'do_return' permission
- Ledger posting must continue to use correct transaction types, categories, and idempotency keys
- Bank account balance updates must continue to work correctly
- Activity logging must continue to record resolution actions
- Warning logs must continue when bank account is not configured

**Scope:**
All inputs that do NOT involve resolving a held deposit with a configured bank account should be completely unaffected by this fix. This includes:
- Validation failures (non-completed reservations, zero held amount, already resolved)
- Permission denials
- Cases where no bank account is configured (warning logged, no ledger posting)
- All other reservation operations

## Hypothesized Root Cause

Based on the bug description and code analysis, the root cause is:

1. **Nested Transaction Deadlock**: The primary issue is that `resolve_held_deposit.php` wraps ledger function calls in an outer transaction (line 77), but the ledger functions (`ledger_post()`, `ledger_post_security_deposit()`) have their own internal transaction management (line 139 in ledger_helpers.php). PDO does not support nested transactions, causing an exception.

2. **Unnecessary Outer Transaction**: The outer transaction in `resolve_held_deposit.php` is redundant because:
   - Each `ledger_post()` call already manages its own transaction atomically
   - The idempotency keys prevent duplicate ledger entries even if called multiple times
   - The reservation UPDATE statements are simple single-row updates that don't need multi-statement atomicity with ledger posts

3. **Generic Error Handling**: The catch block logs the actual error but only shows a generic message to the user, making diagnosis difficult during development.

4. **Transaction Rollback Behavior**: When the nested transaction exception occurs, the outer transaction's rollback has no effect on the ledger functions' internal transactions (which never started), but it does roll back the reservation UPDATE statement, leaving the system in a consistent but unresolved state.

## Correctness Properties

Property 1: Bug Condition - Held Deposit Resolution Success

_For any_ request to resolve a held deposit where the reservation is completed, has a non-zero held amount, is not already resolved, has a configured bank account, and the user has permission, the fixed code SHALL successfully update the reservation record, post the appropriate ledger entries, update bank account balances, log the action, and display a success message without throwing a nested transaction exception.

**Validates: Requirements 2.1, 2.2, 2.3**

Property 2: Preservation - Validation and Edge Case Behavior

_For any_ request that fails validation checks (non-completed reservation, zero held amount, already resolved, missing permission) or has no configured bank account, the fixed code SHALL produce exactly the same behavior as the original code, preserving all validation error messages, warning logs, and early exit paths.

**Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5, 3.6**

## Fix Implementation

### Changes Required

**File**: `reservations/resolve_held_deposit.php`

**Function**: Main script execution (lines 77-145)

**Specific Changes**:

1. **Remove Outer Transaction Wrapper**: Remove the `$pdo->beginTransaction()` call at line 77 and the `$pdo->commit()` call at line 137. The ledger helper functions already provide transaction management.

2. **Remove Transaction Rollback**: Remove the `if ($pdo->inTransaction()) { $pdo->rollBack(); }` check in the catch block (lines 143-145) since there will be no outer transaction to roll back.

3. **Preserve Reservation Updates**: Keep the reservation UPDATE statements as-is. These are simple single-row updates that don't require explicit transaction wrapping for atomicity.

4. **Preserve Ledger Function Calls**: Keep all calls to `ledger_post_security_deposit()` and `ledger_post()` unchanged. These functions handle their own transactions and idempotency.

5. **Preserve Error Handling**: Keep the try-catch block and error logging. The catch will now handle any genuine errors from ledger posting or database operations.

6. **Preserve Activity Logging**: Keep the `log_activity()` call after the main operations. This can be called outside a transaction since it's a separate logging operation.

### Why This Fix Works

- **Eliminates Nested Transactions**: By removing the outer transaction, ledger functions can manage their own transactions without conflict
- **Maintains Atomicity**: Each ledger_post() call is atomic within its own transaction
- **Idempotency Protection**: The idempotency keys in ledger_post() prevent duplicate entries if the script is retried
- **Simpler Error Handling**: Errors from ledger posting will be genuine issues (database errors, constraint violations) rather than transaction nesting problems
- **Consistent State**: If a ledger post fails, its transaction rolls back internally, and the reservation UPDATE can be retried

## Testing Strategy

### Validation Approach

The testing strategy follows a two-phase approach: first, surface counterexamples that demonstrate the bug on unfixed code, then verify the fix works correctly and preserves existing behavior.

### Exploratory Bug Condition Checking

**Goal**: Surface counterexamples that demonstrate the bug BEFORE implementing the fix. Confirm the nested transaction hypothesis.

**Test Plan**: Write tests that simulate resolving held deposits with configured bank accounts. Run these tests on the UNFIXED code to observe the nested transaction exception.

**Test Cases**:
1. **Release with Bank Account**: Attempt to release a $500 held deposit on a completed reservation with a configured bank account (will fail with nested transaction error on unfixed code)
2. **Convert with Bank Account**: Attempt to convert a $300 held deposit to income on a completed reservation with a configured bank account (will fail with nested transaction error on unfixed code)
3. **Multiple Ledger Posts**: Attempt to convert held deposit, which calls ledger_post() twice (will fail on first call on unfixed code)
4. **No Bank Account**: Attempt to resolve held deposit with no configured bank account (should succeed even on unfixed code, as no ledger posting occurs)

**Expected Counterexamples**:
- Exception message: "There is already an active transaction" or similar PDO nested transaction error
- Possible causes: Outer transaction wrapping ledger function calls that have internal transactions

### Fix Checking

**Goal**: Verify that for all inputs where the bug condition holds, the fixed function produces the expected behavior.

**Pseudocode:**
```
FOR ALL input WHERE isBugCondition(input) DO
  result := resolve_held_deposit_fixed(input)
  ASSERT result.success == true
  ASSERT result.reservation.deposit_held == 0
  ASSERT result.reservation.deposit_held_action IN ['released', 'converted']
  ASSERT result.reservation.deposit_held_resolved_at IS NOT NULL
  ASSERT result.ledger_entries_created > 0
  ASSERT result.bank_account_balance_updated == true
END FOR
```

### Preservation Checking

**Goal**: Verify that for all inputs where the bug condition does NOT hold, the fixed function produces the same result as the original function.

**Pseudocode:**
```
FOR ALL input WHERE NOT isBugCondition(input) DO
  ASSERT resolve_held_deposit_original(input) = resolve_held_deposit_fixed(input)
END FOR
```

**Testing Approach**: Property-based testing is recommended for preservation checking because:
- It generates many test cases automatically across the input domain
- It catches edge cases that manual unit tests might miss
- It provides strong guarantees that behavior is unchanged for all non-buggy inputs

**Test Plan**: Observe behavior on UNFIXED code first for validation failures and edge cases, then write property-based tests capturing that behavior.

**Test Cases**:
1. **Non-Completed Reservation**: Verify that attempting to resolve held deposit on non-completed reservation shows error "Held deposit can only be resolved on completed reservations"
2. **Already Resolved**: Verify that attempting to resolve an already-resolved held deposit shows error "This held deposit has already been resolved"
3. **Zero Held Amount**: Verify that attempting to resolve with zero held amount shows error "No held deposit amount to resolve for this reservation"
4. **Permission Denied**: Verify that users without 'do_return' permission are denied access

### Unit Tests

- Test release action with valid held deposit and configured bank account
- Test convert action with valid held deposit and configured bank account
- Test that reservation fields are updated correctly (deposit_held=0, deposit_held_action set, deposit_held_resolved_at set)
- Test that ledger entries are created with correct transaction types and categories
- Test that bank account balances are updated correctly
- Test edge case: no bank account configured (warning logged, no ledger posting)
- Test validation: non-completed reservation
- Test validation: already resolved held deposit
- Test validation: zero held amount
- Test validation: missing permission

### Property-Based Tests

- Generate random held deposit amounts and verify both release and convert actions work correctly
- Generate random reservation states and verify validation logic catches invalid states
- Generate random bank account configurations and verify ledger posting works or skips appropriately
- Test that idempotency keys prevent duplicate ledger entries if script is called multiple times

### Integration Tests

- Test full flow: create reservation → complete it → hold deposit → release held deposit
- Test full flow: create reservation → complete it → hold deposit → convert held deposit to income
- Test that activity log records resolution actions correctly
- Test that UI shows success message and removes pending resolution section
- Test concurrent resolution attempts (idempotency protection)
