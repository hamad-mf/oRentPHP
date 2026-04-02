# Requirements Document

## Introduction

This feature extends the existing transfer modal in the accounts system to support bidirectional transfers between Bank Accounts and the Cash Account. Currently, the system supports Bank-to-Bank transfers via the transfer modal and has a separate Cash-to-Bank transfer modal on the cash page. This feature consolidates and extends transfer functionality to handle all transfer types (Bank-to-Bank, Bank-to-Cash, Cash-to-Bank) through a unified interface.

## Glossary

- **Transfer_Modal**: The existing modal dialog in accounts/index.php opened via openTransferModal() that currently handles Bank-to-Bank transfers
- **Cash_Account**: A virtual account representing cash balance calculated from ledger_entries where payment_mode='cash'
- **Bank_Account**: A physical bank account stored in the bank_accounts table with a balance column
- **Ledger_Entry**: A record in the ledger_entries table representing a financial transaction
- **Transfer_Handler**: The POST action handler that processes transfer_funds requests
- **Ledger_Transfer_Function**: The ledger_transfer() function in includes/ledger_helpers.php that handles Bank-to-Bank transfers
- **Cash_Transfer_Function**: The ledger_transfer_cash_to_bank() function that handles Cash-to-Bank transfers

## Requirements

### Requirement 1: Unified Transfer Modal Interface

**User Story:** As an admin user, I want to use a single transfer modal for all transfer types, so that I have a consistent interface for moving funds between accounts.

#### Acceptance Criteria

1. WHEN the Transfer_Modal is opened, THE Transfer_Modal SHALL display "Cash Account" as an option in both the "From Account" and "To Account" dropdowns
2. WHEN "Cash Account" is selected in the "From Account" dropdown, THE Transfer_Modal SHALL display the current cash balance next to the selection
3. WHEN a Bank_Account is selected in the "From Account" dropdown, THE Transfer_Modal SHALL exclude that account from the "To Account" dropdown options
4. WHEN "Cash Account" is selected in the "From Account" dropdown, THE Transfer_Modal SHALL exclude "Cash Account" from the "To Account" dropdown options
5. THE Transfer_Modal SHALL maintain its existing UI styling and layout when displaying Cash_Account options

### Requirement 2: Bank-to-Cash Transfer Processing

**User Story:** As an admin user, I want to withdraw cash from a bank account, so that I can record cash withdrawals in the system.

#### Acceptance Criteria

1. WHEN a user submits a transfer with a Bank_Account as source and Cash_Account as destination, THE Transfer_Handler SHALL validate that the Bank_Account has sufficient balance
2. WHEN a Bank-to-Cash transfer is valid, THE Transfer_Handler SHALL create a ledger_entry with txn_type='expense', category='Transfer Out', payment_mode='account', and source_type='transfer' for the Bank_Account
3. WHEN a Bank-to-Cash transfer is valid, THE Transfer_Handler SHALL create a ledger_entry with txn_type='income', category='Transfer In', payment_mode='cash', bank_account_id=NULL, and source_type='transfer' for the Cash_Account
4. WHEN a Bank-to-Cash transfer is valid, THE Transfer_Handler SHALL decrement the Bank_Account balance by the transfer amount
5. WHEN a Bank-to-Cash transfer is valid, THE Transfer_Handler SHALL execute all operations within a database transaction
6. IF a Bank-to-Cash transfer fails at any step, THEN THE Transfer_Handler SHALL rollback all changes and display an error message
7. WHEN a Bank-to-Cash transfer completes successfully, THE Transfer_Handler SHALL log the action with app_log() and redirect to accounts/index.php with a success message

### Requirement 3: Cash-to-Bank Transfer Processing

**User Story:** As an admin user, I want to deposit cash into a bank account, so that I can record cash deposits in the system.

#### Acceptance Criteria

1. WHEN a user submits a transfer with Cash_Account as source and a Bank_Account as destination, THE Transfer_Handler SHALL calculate the current cash balance from ledger_entries
2. WHEN a Cash-to-Bank transfer is submitted, THE Transfer_Handler SHALL validate that the cash balance is greater than or equal to the transfer amount
3. WHEN a Cash-to-Bank transfer is valid, THE Transfer_Handler SHALL create a ledger_entry with txn_type='expense', category='Transfer Out', payment_mode='cash', bank_account_id=NULL, and source_type='transfer' for the Cash_Account
4. WHEN a Cash-to-Bank transfer is valid, THE Transfer_Handler SHALL create a ledger_entry with txn_type='income', category='Transfer In', payment_mode='account', and source_type='transfer' for the Bank_Account
5. WHEN a Cash-to-Bank transfer is valid, THE Transfer_Handler SHALL increment the Bank_Account balance by the transfer amount
6. WHEN a Cash-to-Bank transfer is valid, THE Transfer_Handler SHALL execute all operations within a database transaction
7. IF a Cash-to-Bank transfer fails at any step, THEN THE Transfer_Handler SHALL rollback all changes and display an error message
8. WHEN a Cash-to-Bank transfer completes successfully, THE Transfer_Handler SHALL log the action with app_log() and redirect to accounts/index.php with a success message

### Requirement 4: Transfer Validation and Error Handling

**User Story:** As an admin user, I want clear error messages when transfers fail, so that I understand what went wrong and can correct it.

#### Acceptance Criteria

1. WHEN a user attempts to transfer from Cash_Account to Cash_Account, THE Transfer_Handler SHALL reject the transfer with error message "Cannot transfer to the same account."
2. WHEN a user attempts to transfer from a Bank_Account to the same Bank_Account, THE Transfer_Handler SHALL reject the transfer with error message "Cannot transfer to the same account."
3. WHEN a user attempts a transfer with amount less than or equal to zero, THE Transfer_Handler SHALL reject the transfer with error message "Transfer amount must be greater than zero."
4. WHEN a Bank-to-Cash transfer has insufficient bank balance, THE Transfer_Handler SHALL reject the transfer with error message "Insufficient balance in source account (Balance: $X.XX)."
5. WHEN a Cash-to-Bank transfer has insufficient cash balance, THE Transfer_Handler SHALL reject the transfer with error message "Insufficient cash balance (Balance: $X.XX)."
6. WHEN a transfer references an inactive or non-existent Bank_Account, THE Transfer_Handler SHALL reject the transfer with error message "Source account not found or inactive." or "Destination account not found or inactive."

### Requirement 5: Audit Trail and Transaction Recording

**User Story:** As a system administrator, I want all transfers to be properly recorded with audit information, so that I can track financial movements and maintain compliance.

#### Acceptance Criteria

1. FOR ALL transfer types, THE Transfer_Handler SHALL record the posted_at timestamp from the user-provided date field
2. FOR ALL transfer types, THE Transfer_Handler SHALL record the created_by field with the current user's ID
3. FOR ALL transfer types, THE Transfer_Handler SHALL set source_event='transfer_out' for the source account entry
4. FOR ALL transfer types, THE Transfer_Handler SHALL set source_event='transfer_in' for the destination account entry
5. FOR ALL transfer types, THE Transfer_Handler SHALL use the user-provided description or generate a default description in the format "Transfer: [Source] → [Destination]"
6. WHEN a transfer completes, THE Transfer_Handler SHALL call app_log() with action type 'ACTION' and a message describing the transfer

### Requirement 6: Cash Account Balance Calculation

**User Story:** As an admin user, I want to see the current cash balance when selecting Cash Account, so that I know how much cash is available for transfer.

#### Acceptance Criteria

1. WHEN the Transfer_Modal loads, THE Transfer_Modal SHALL calculate cash balance as SUM(amount WHERE payment_mode='cash' AND txn_type='income' AND voided_at IS NULL) - SUM(amount WHERE payment_mode='cash' AND txn_type='expense' AND voided_at IS NULL)
2. WHEN "Cash Account" is selected in the "From Account" dropdown, THE Transfer_Modal SHALL display the calculated cash balance in the format "Available: $X.XX"
3. THE Transfer_Modal SHALL format the cash balance display consistently with Bank_Account balance displays

### Requirement 7: Backward Compatibility

**User Story:** As a system user, I want existing Bank-to-Bank transfers to continue working unchanged, so that the new feature doesn't break existing functionality.

#### Acceptance Criteria

1. WHEN a user submits a Bank-to-Bank transfer, THE Transfer_Handler SHALL continue to use the existing ledger_transfer() function
2. WHEN a user submits a Bank-to-Bank transfer, THE Transfer_Handler SHALL maintain all existing validation rules and error messages
3. THE Transfer_Modal SHALL continue to support all existing Bank-to-Bank transfer features without modification to their behavior
