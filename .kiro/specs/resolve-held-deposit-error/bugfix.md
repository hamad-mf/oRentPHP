# Bugfix Requirements Document

## Introduction

The "Release Held Deposit" functionality in `reservations/resolve_held_deposit.php` is failing with the error "Could not resolve held deposit. Please try again." when attempting to resolve held deposits on completed reservations. Both "Release to Client" and "Convert to Income" actions are affected. The bug prevents users from completing the security deposit workflow, leaving held deposits in an unresolved state.

## Bug Analysis

### Current Behavior (Defect)

1.1 WHEN a user clicks "Release to Client" on a completed reservation with a held deposit THEN the system shows error "Could not resolve held deposit. Please try again." and the held deposit remains unresolved

1.2 WHEN a user clicks "Convert to Income" on a completed reservation with a held deposit THEN the system shows error "Could not resolve held deposit. Please try again." and the held deposit remains unresolved

1.3 WHEN the resolve operation fails THEN the transaction rolls back but ledger entries may be partially created before the rollback

1.4 WHEN the error occurs THEN no detailed error information is logged to help diagnose the root cause

### Expected Behavior (Correct)

2.1 WHEN a user clicks "Release to Client" on a completed reservation with a held deposit THEN the system SHALL add the held amount to `deposit_returned`, post a ledger entry (EXPENSE "Security Deposit Returned"), update the bank account balance, clear `deposit_held`, set `deposit_held_action='released'`, set `deposit_held_resolved_at`, show a success message, and remove the pending resolution section

2.2 WHEN a user clicks "Convert to Income" on a completed reservation with a held deposit THEN the system SHALL add the held amount to `deposit_deducted`, post two ledger entries (EXPENSE "Security Deposit" and INCOME "Damage Charges"), update the bank account balance, clear `deposit_held`, set `deposit_held_action='converted'`, set `deposit_held_resolved_at`, show a success message, and remove the pending resolution section

2.3 WHEN the resolve operation encounters an error THEN the system SHALL log detailed error information including the exception message and stack trace to aid in diagnosis

2.4 WHEN database schema columns are missing THEN the system SHALL either create them successfully via ALTER TABLE or fail with a clear error message

### Unchanged Behavior (Regression Prevention)

3.1 WHEN a user attempts to resolve a held deposit on a non-completed reservation THEN the system SHALL CONTINUE TO show error "Held deposit can only be resolved on completed reservations"

3.2 WHEN a user attempts to resolve a held deposit that has already been resolved THEN the system SHALL CONTINUE TO show error "This held deposit has already been resolved"

3.3 WHEN a user attempts to resolve a held deposit with zero or negative amount THEN the system SHALL CONTINUE TO show error "No held deposit amount to resolve for this reservation"

3.4 WHEN a user without 'do_return' permission attempts to access the resolve functionality THEN the system SHALL CONTINUE TO deny access with error "You do not have permission to resolve held deposits"

3.5 WHEN ledger entries are successfully posted THEN the system SHALL CONTINUE TO use the correct transaction types, categories, and idempotency keys

3.6 WHEN the deposit bank account is not configured THEN the system SHALL CONTINUE TO log a warning and skip ledger posting without failing the entire operation
