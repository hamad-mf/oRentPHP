# Implementation Plan

- [ ] 1. Write bug condition exploration test
  - **Property 1: Bug Condition** - Nested Transaction Exception on Held Deposit Resolution
  - **CRITICAL**: This test MUST FAIL on unfixed code - failure confirms the bug exists
  - **DO NOT attempt to fix the test or the code when it fails**
  - **NOTE**: This test encodes the expected behavior - it will validate the fix when it passes after implementation
  - **GOAL**: Surface counterexamples that demonstrate the nested transaction exception
  - **Scoped PBT Approach**: Scope the property to concrete failing cases: resolve held deposit (release or convert) with configured bank account
  - Test that resolving a held deposit (action='release' or action='convert') on a completed reservation with deposit_held > 0, deposit_held_action=NULL, and a configured bank account succeeds without throwing nested transaction exception
  - The test assertions should verify: success=true, deposit_held=0, deposit_held_action set, deposit_held_resolved_at set, ledger entries created, bank account balance updated
  - Run test on UNFIXED code
  - **EXPECTED OUTCOME**: Test FAILS with exception "There is already an active transaction" or similar PDO nested transaction error (this is correct - it proves the bug exists)
  - Document counterexamples found: specific reservation IDs, held amounts, and actions that trigger the nested transaction exception
  - Mark task complete when test is written, run, and failure is documented
  - _Requirements: 1.1, 1.2, 1.3_

- [ ] 2. Write preservation property tests (BEFORE implementing fix)
  - **Property 2: Preservation** - Validation and Edge Case Behavior
  - **IMPORTANT**: Follow observation-first methodology
  - Observe behavior on UNFIXED code for non-buggy inputs (validation failures, edge cases)
  - Test Case 1: Non-completed reservation → observe error "Held deposit can only be resolved on completed reservations"
  - Test Case 2: Already resolved deposit → observe error "This held deposit has already been resolved"
  - Test Case 3: Zero held amount → observe error "No held deposit amount to resolve for this reservation"
  - Test Case 4: Missing permission → observe error "You do not have permission to resolve held deposits"
  - Test Case 5: No bank account configured → observe warning logged, operation succeeds without ledger posting
  - Write property-based tests capturing these observed validation behaviors
  - Property-based testing generates many test cases for stronger guarantees
  - Run tests on UNFIXED code
  - **EXPECTED OUTCOME**: Tests PASS (this confirms baseline behavior to preserve)
  - Mark task complete when tests are written, run, and passing on unfixed code
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [x] 3. Fix nested transaction issue in resolve_held_deposit.php

  - [x] 3.1 Remove outer transaction wrapper
    - Remove `$pdo->beginTransaction()` call at line 77
    - Remove `$pdo->commit()` call at line 137
    - Remove `if ($pdo->inTransaction()) { $pdo->rollBack(); }` check in catch block (lines 143-145)
    - Keep reservation UPDATE statements unchanged (simple single-row updates)
    - Keep all ledger function calls unchanged (they handle their own transactions)
    - Keep try-catch block and error logging unchanged
    - Keep activity logging call unchanged
    - _Bug_Condition: isBugCondition(input) where input.action IN ['release', 'convert'] AND input.reservation.status == 'completed' AND input.reservation.deposit_held > 0 AND input.reservation.deposit_held_action IS NULL AND depositBankAccountId IS NOT NULL AND outerTransactionActive == true AND ledgerFunctionCallsBeginTransaction == true_
    - _Expected_Behavior: For all inputs where isBugCondition(input), result.success == true AND result.reservation.deposit_held == 0 AND result.reservation.deposit_held_action IN ['released', 'converted'] AND result.reservation.deposit_held_resolved_at IS NOT NULL AND result.ledger_entries_created > 0 AND result.bank_account_balance_updated == true_
    - _Preservation: Validation logic (3.1-3.4), ledger posting behavior (3.5), edge case handling (3.6) from design_
    - _Requirements: 2.1, 2.2, 2.3, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

  - [x] 3.2 Verify bug condition exploration test now passes
    - **Property 1: Expected Behavior** - Held Deposit Resolution Success
    - **IMPORTANT**: Re-run the SAME test from task 1 - do NOT write a new test
    - The test from task 1 encodes the expected behavior
    - When this test passes, it confirms the expected behavior is satisfied
    - Run bug condition exploration test from step 1
    - **EXPECTED OUTCOME**: Test PASSES (confirms bug is fixed - no nested transaction exception)
    - Verify: success=true, deposit_held=0, deposit_held_action set, deposit_held_resolved_at set, ledger entries created, bank account balance updated
    - _Requirements: 2.1, 2.2, 2.3_

  - [x] 3.3 Verify preservation tests still pass
    - **Property 2: Preservation** - Validation and Edge Case Behavior
    - **IMPORTANT**: Re-run the SAME tests from task 2 - do NOT write new tests
    - Run preservation property tests from step 2
    - **EXPECTED OUTCOME**: Tests PASS (confirms no regressions)
    - Confirm all validation error messages unchanged
    - Confirm edge case handling (no bank account) unchanged
    - Confirm ledger posting behavior unchanged
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_

- [ ] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.
