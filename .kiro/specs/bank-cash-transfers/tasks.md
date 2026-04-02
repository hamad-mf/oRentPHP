# Implementation Plan: Bank-Cash Transfers

## Overview

This implementation extends the existing transfer modal to support bidirectional transfers between Bank Accounts and the Cash Account. The work involves modifying the frontend transfer modal UI, updating the backend transfer handler to route different transfer types, and creating a new function for Bank-to-Cash transfers that mirrors the existing Cash-to-Bank function.

## Tasks

- [x] 1. Create new Bank-to-Cash transfer function
  - [x] 1.1 Implement ledger_transfer_bank_to_cash() function in includes/ledger_helpers.php
    - Add function signature matching ledger_transfer_cash_to_bank() pattern
    - Validate source bank account exists and is active
    - Validate sufficient bank balance
    - Create expense ledger entry for bank (Transfer Out)
    - Create income ledger entry for cash (Transfer In)
    - Decrement bank account balance
    - Execute within database transaction with rollback on error
    - Log action with app_log()
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_

  - [ ]* 1.2 Write property test for Bank-to-Cash transfer balance conservation
    - **Property 1: Transfer Balance Conservation**
    - **Validates: Requirements 2.4**

  - [ ]* 1.3 Write property test for Bank-to-Cash paired ledger entries
    - **Property 2: Transfer Creates Paired Ledger Entries**
    - **Validates: Requirements 2.2, 2.3, 5.3, 5.4**

  - [ ]* 1.4 Write property test for Bank-to-Cash atomicity
    - **Property 9: Transfer Atomicity**
    - **Validates: Requirements 2.6**

- [x] 2. Update transfer handler routing logic
  - [x] 2.1 Modify POST handler in accounts/index.php for transfer_funds action
    - Add routing logic to detect Cash Account (ID = 0)
    - Route Cash-to-Cash transfers to validation error
    - Route Cash-to-Bank transfers to ledger_transfer_cash_to_bank()
    - Route Bank-to-Cash transfers to ledger_transfer_bank_to_cash()
    - Route Bank-to-Bank transfers to existing ledger_transfer()
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 4.1, 4.2_

  - [ ]* 2.2 Write property test for same account transfer rejection
    - **Property 4: Same Account Transfer Rejection**
    - **Validates: Requirements 4.1, 4.2**

  - [ ]* 2.3 Write property test for non-positive amount rejection
    - **Property 5: Non-Positive Amount Rejection**
    - **Validates: Requirements 4.3**

  - [ ]* 2.4 Write property test for insufficient balance rejection
    - **Property 6: Insufficient Balance Rejection**
    - **Validates: Requirements 2.1, 3.2, 4.4, 4.5**

  - [ ]* 2.5 Write property test for invalid account rejection
    - **Property 7: Invalid Account Rejection**
    - **Validates: Requirements 4.6**

- [x] 3. Checkpoint - Ensure backend transfer logic works
  - Ensure all tests pass, ask the user if questions arise.

- [x] 4. Update transfer modal UI to include Cash Account
  - [x] 4.1 Modify openTransferModal() function in accounts/index.php
    - Calculate cash balance from ledger entries using SUM pattern
    - Add Cash Account (ID = 0) to transferAccountsData array
    - Format cash balance display consistently with bank accounts
    - _Requirements: 1.1, 1.2, 6.1, 6.2, 6.3_

  - [x] 4.2 Modify updateTransferTo() function in accounts/index.php
    - Filter out source account from destination dropdown
    - Filter out Cash Account when Cash is selected as source
    - _Requirements: 1.3, 1.4_

  - [ ]* 4.3 Write property test for source account exclusion
    - **Property 3: Source Account Exclusion**
    - **Validates: Requirements 1.3, 1.4**

  - [ ]* 4.4 Write property test for cash balance calculation accuracy
    - **Property 11: Cash Balance Calculation Accuracy**
    - **Validates: Requirements 6.1**

- [x] 5. Add validation and error handling
  - [x] 5.1 Implement validation checks in transfer handler
    - Validate amount > 0
    - Validate source != destination
    - Validate sufficient balance for source account
    - Validate bank accounts exist and are active
    - Return appropriate error messages per requirements
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [ ]* 5.2 Write property test for audit trail completeness
    - **Property 8: Audit Trail Completeness**
    - **Validates: Requirements 5.1, 5.2, 5.5**

  - [ ]* 5.3 Write property test for successful transfer logging
    - **Property 10: Successful Transfer Logging**
    - **Validates: Requirements 2.7, 3.8, 5.6**

- [x] 6. Verify backward compatibility
  - [x] 6.1 Test existing Bank-to-Bank transfers
    - Verify ledger_transfer() is still called for Bank-to-Bank
    - Verify existing validation rules are preserved
    - Verify existing error messages are unchanged
    - _Requirements: 7.1, 7.2, 7.3_

  - [ ]* 6.2 Write property test for Bank-to-Bank transfer preservation
    - **Property 12: Bank-to-Bank Transfer Preservation**
    - **Validates: Requirements 7.2**

- [x] 7. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- The implementation reuses existing patterns (ledger_transfer_cash_to_bank) for consistency
- All transfer operations use database transactions for atomicity
- Cash Account uses special ID = 0 for routing logic
- Property tests validate universal correctness properties across all transfer types
