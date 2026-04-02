# Design Document: Bank-Cash Transfers

## Overview

This feature extends the existing transfer modal in the accounts system to support bidirectional transfers between Bank Accounts and the Cash Account. The system currently has two separate transfer mechanisms:
- Bank-to-Bank transfers via the unified transfer modal (using `ledger_transfer()`)
- Cash-to-Bank transfers via a separate modal on the cash page (using `ledger_transfer_cash_to_bank()`)

This design consolidates these mechanisms into a single unified interface that handles all three transfer types:
1. Bank-to-Bank (existing functionality, preserved)
2. Bank-to-Cash (new functionality)
3. Cash-to-Bank (existing functionality, relocated)

The Cash Account is a virtual account calculated from ledger entries where `payment_mode='cash'`, while Bank Accounts are physical entities stored in the `bank_accounts` table with persistent balance columns.

## Architecture

### System Context

The transfer system operates within the existing ledger-based accounting architecture:

```
┌─────────────────────────────────────────────────────────────┐
│                    Accounts UI Layer                         │
│  (accounts/index.php - Transfer Modal)                       │
└────────────────┬────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│              Transfer Handler (POST)                         │
│  - Route detection (Bank/Cash combinations)                  │
│  - Validation                                                │
│  - Delegation to appropriate transfer function               │
└────────────────┬────────────────────────────────────────────┘
                 │
        ┌────────┴────────┐
        ▼                 ▼
┌──────────────┐  ┌──────────────────┐
│ Bank-to-Bank │  │ Bank-Cash        │
│ ledger_      │  │ Transfers        │
│ transfer()   │  │ (New Functions)  │
└──────┬───────┘  └────────┬─────────┘
       │                   │
       └───────┬───────────┘
               ▼
┌─────────────────────────────────────────────────────────────┐
│                  Ledger Layer                                │
│  - ledger_entries table (double-entry bookkeeping)           │
│  - bank_accounts table (balance tracking)                    │
│  - Transaction management                                    │
└─────────────────────────────────────────────────────────────┘
```

### Design Decisions

1. **Unified Modal Approach**: Rather than creating separate modals for each transfer type, we extend the existing transfer modal to handle all cases. This provides a consistent user experience and reduces code duplication.

2. **Special Account ID Convention**: We use `fromId = 0` or `toId = 0` to represent the Cash Account in the transfer handler. This allows us to distinguish Cash Account from Bank Accounts without modifying the database schema.

3. **Function Reuse**: We leverage the existing `ledger_transfer_cash_to_bank()` function for Cash-to-Bank transfers and create a new `ledger_transfer_bank_to_cash()` function that mirrors its structure for Bank-to-Cash transfers.

4. **Balance Calculation Consistency**: Cash balance is calculated using the same SQL pattern used throughout the codebase:
   ```sql
   SUM(amount WHERE payment_mode='cash' AND txn_type='income' AND voided_at IS NULL) -
   SUM(amount WHERE payment_mode='cash' AND txn_type='expense' AND voided_at IS NULL)
   ```

5. **Transaction Atomicity**: All transfer operations use database transactions to ensure atomicity. If any step fails, all changes are rolled back.

## Components and Interfaces

### 1. Frontend Components

#### Transfer Modal UI (accounts/index.php)

**Modifications Required:**
- Add "Cash Account" option to both From and To account dropdowns
- Display cash balance when Cash Account is selected
- Update JavaScript logic to:
  - Calculate and display cash balance
  - Handle Cash Account selection in dropdown filtering
  - Prevent Cash-to-Cash transfers

**JavaScript Functions:**
- `openTransferModal()`: Modified to include Cash Account in dropdown data
- `updateTransferTo()`: Modified to filter out Cash Account when Cash is source
- New helper: Calculate cash balance from ledger data

#### Data Structure for JavaScript:

```javascript
var transferAccountsData = [
    {id: 0, name: 'Cash Account', balance: <calculated>},
    {id: 1, name: 'Bank Account 1', balance: 1000.00},
    {id: 2, name: 'Bank Account 2', balance: 2500.00},
    ...
];
```

### 2. Backend Components

#### Transfer Handler (accounts/index.php POST handler)

**Current Implementation:**
```php
if ($action === 'transfer_funds' && $isAdmin) {
    $fromId = (int) ($_POST['from_account_id'] ?? 0);
    $toId = (int) ($_POST['to_account_id'] ?? 0);
    // ... calls ledger_transfer()
}
```

**Modified Implementation:**
```php
if ($action === 'transfer_funds' && $isAdmin) {
    $fromId = (int) ($_POST['from_account_id'] ?? 0);
    $toId = (int) ($_POST['to_account_id'] ?? 0);
    
    // Route to appropriate transfer function based on account types
    if ($fromId === 0 && $toId === 0) {
        // Cash-to-Cash: Invalid
    } elseif ($fromId === 0) {
        // Cash-to-Bank: Use ledger_transfer_cash_to_bank()
    } elseif ($toId === 0) {
        // Bank-to-Cash: Use new ledger_transfer_bank_to_cash()
    } else {
        // Bank-to-Bank: Use existing ledger_transfer()
    }
}
```

#### New Function: ledger_transfer_bank_to_cash()

**Location:** `includes/ledger_helpers.php`

**Signature:**
```php
function ledger_transfer_bank_to_cash(
    PDO $pdo,
    int $fromId,
    float $amount,
    ?string $description,
    int $userId,
    ?string $postedAt = null,
    string &$error = ''
): bool
```

**Responsibilities:**
- Validate source bank account exists and is active
- Validate sufficient bank balance
- Create expense ledger entry for bank (Transfer Out)
- Create income ledger entry for cash (Transfer In)
- Decrement bank account balance
- Execute within transaction
- Log action

**Implementation Pattern:**
Mirrors `ledger_transfer_cash_to_bank()` but in reverse:
- Bank expense entry: `txn_type='expense'`, `category='Transfer Out'`, `payment_mode='account'`, `bank_account_id=fromId`
- Cash income entry: `txn_type='income'`, `category='Transfer In'`, `payment_mode='cash'`, `bank_account_id=NULL`

### 3. Data Layer

#### Ledger Entries Structure

Each transfer creates two ledger entries (double-entry bookkeeping):

**Bank-to-Cash Transfer:**
```
Entry 1 (Bank Expense):
  txn_type: 'expense'
  category: 'Transfer Out'
  payment_mode: 'account'
  bank_account_id: <source_bank_id>
  source_type: 'transfer'
  source_event: 'transfer_out'
  
Entry 2 (Cash Income):
  txn_type: 'income'
  category: 'Transfer In'
  payment_mode: 'cash'
  bank_account_id: NULL
  source_type: 'transfer'
  source_event: 'transfer_in'
```

**Cash-to-Bank Transfer:**
```
Entry 1 (Cash Expense):
  txn_type: 'expense'
  category: 'Transfer Out'
  payment_mode: 'cash'
  bank_account_id: NULL
  source_type: 'transfer'
  source_event: 'transfer_out'
  
Entry 2 (Bank Income):
  txn_type: 'income'
  category: 'Transfer In'
  payment_mode: 'account'
  bank_account_id: <destination_bank_id>
  source_type: 'transfer'
  source_event: 'transfer_in'
```

## Data Models

### Cash Account (Virtual)

The Cash Account is not a database entity but a calculated value:

```php
$cashBalance = 
    SUM(ledger_entries.amount WHERE payment_mode='cash' AND txn_type='income' AND voided_at IS NULL) -
    SUM(ledger_entries.amount WHERE payment_mode='cash' AND txn_type='expense' AND voided_at IS NULL)
```

**Properties:**
- `id`: 0 (special identifier for routing)
- `name`: "Cash Account"
- `balance`: Calculated dynamically
- `is_active`: Always true (implicit)

### Bank Account (Physical)

Stored in `bank_accounts` table:

```sql
CREATE TABLE bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)
```

### Ledger Entry

Stored in `ledger_entries` table:

```sql
CREATE TABLE ledger_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    txn_type ENUM('income','expense','adjustment') NOT NULL,
    category VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_mode VARCHAR(20) DEFAULT NULL,
    bank_account_id INT DEFAULT NULL,
    source_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    source_id INT DEFAULT NULL,
    source_event VARCHAR(50) DEFAULT NULL,
    posted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    voided_at DATETIME DEFAULT NULL,
    void_reason TEXT DEFAULT NULL,
    voided_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ...
)
```

### Transfer Request Model

Data submitted from the transfer modal:

```php
[
    'action' => 'transfer_funds',
    'from_account_id' => int,  // 0 for Cash Account, >0 for Bank Account
    'to_account_id' => int,    // 0 for Cash Account, >0 for Bank Account
    'amount' => float,
    'description' => string|null,
    'posted_at' => string (Y-m-d format)
]
```


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Transfer Balance Conservation

*For any* valid transfer (Bank-to-Bank, Bank-to-Cash, or Cash-to-Bank), the total system balance (sum of all bank account balances plus cash balance) before the transfer SHALL equal the total system balance after the transfer.

**Validates: Requirements 2.4, 3.5**

### Property 2: Transfer Creates Paired Ledger Entries

*For any* valid transfer, exactly two ledger entries SHALL be created: one expense entry for the source account with `source_event='transfer_out'` and one income entry for the destination account with `source_event='transfer_in'`, both with matching amounts and descriptions.

**Validates: Requirements 2.2, 2.3, 3.3, 3.4, 5.3, 5.4**

### Property 3: Source Account Exclusion

*For any* account selected as the source in the transfer modal, that account SHALL NOT appear in the destination account dropdown options.

**Validates: Requirements 1.3, 1.4**

### Property 4: Same Account Transfer Rejection

*For any* account (Bank or Cash), attempting to transfer from that account to itself SHALL be rejected with the error message "Cannot transfer to the same account."

**Validates: Requirements 4.1, 4.2**

### Property 5: Non-Positive Amount Rejection

*For any* transfer type, attempting to transfer an amount less than or equal to zero SHALL be rejected with the error message "Transfer amount must be greater than zero."

**Validates: Requirements 4.3**

### Property 6: Insufficient Balance Rejection

*For any* transfer where the source account balance is less than the transfer amount, the transfer SHALL be rejected with an error message indicating insufficient balance and displaying the current balance.

**Validates: Requirements 2.1, 3.2, 4.4, 4.5**

### Property 7: Invalid Account Rejection

*For any* transfer involving a non-existent or inactive bank account (as source or destination), the transfer SHALL be rejected with an error message indicating the account is "not found or inactive."

**Validates: Requirements 4.6**

### Property 8: Audit Trail Completeness

*For any* successful transfer, both ledger entries SHALL record the user-provided `posted_at` timestamp, the current user's ID in `created_by`, and either the user-provided description or a generated description in the format "Transfer: [Source] → [Destination]".

**Validates: Requirements 5.1, 5.2, 5.5**

### Property 9: Transfer Atomicity

*For any* transfer that encounters an error during execution, no changes SHALL be persisted to the database (no ledger entries created, no balance changes).

**Validates: Requirements 2.6, 3.7**

### Property 10: Successful Transfer Logging

*For any* successful transfer, an action log entry SHALL be created via `app_log()` with level 'ACTION' containing the transfer amount, source account identifier, destination account identifier, and user ID.

**Validates: Requirements 2.7, 3.8, 5.6**

### Property 11: Cash Balance Calculation Accuracy

*For any* ledger state, the calculated cash balance SHALL equal the sum of all non-voided cash income entries minus the sum of all non-voided cash expense entries.

**Validates: Requirements 6.1**

### Property 12: Bank-to-Bank Transfer Preservation

*For any* Bank-to-Bank transfer (where both source and destination are bank accounts with ID > 0), the transfer SHALL execute using the existing `ledger_transfer()` function and maintain all existing validation rules and behavior.

**Validates: Requirements 7.2**

## Error Handling

### Validation Errors

All validation errors are handled before any database operations begin:

1. **Same Account Transfer**: Detected by comparing `fromId` and `toId`
   - Error: "Cannot transfer to the same account."
   - HTTP: Redirect with flash error message

2. **Non-Positive Amount**: Detected by checking `amount <= 0`
   - Error: "Transfer amount must be greater than zero."
   - HTTP: Redirect with flash error message

3. **Insufficient Balance**: Detected by comparing amount with source balance
   - Bank-to-Cash: "Insufficient balance in source account (Balance: $X.XX)."
   - Cash-to-Bank: "Insufficient cash balance (Balance: $X.XX)."
   - HTTP: Redirect with flash error message

4. **Invalid Account**: Detected by querying bank_accounts table
   - Error: "Source account not found or inactive." or "Destination account not found or inactive."
   - HTTP: Redirect with flash error message

### Database Errors

All database operations are wrapped in transactions:

```php
try {
    $pdo->beginTransaction();
    // ... operations ...
    $pdo->commit();
    return true;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_log('ERROR', "Transfer failed: " . $e->getMessage());
    $error = 'Database error. Please try again.';
    return false;
}
```

**Error Recovery:**
- All changes are rolled back on any failure
- Error is logged via `app_log()`
- Generic error message returned to user (security: don't expose internal details)
- User can retry the operation

### Edge Cases

1. **Zero Cash Balance**: Cash-to-Bank transfers are prevented when cash balance is zero or negative
2. **Concurrent Transfers**: Database transactions with row-level locking prevent race conditions
3. **Voided Entries**: Voided entries are excluded from balance calculations via `voided_at IS NULL` filter
4. **Floating Point Precision**: All amounts use DECIMAL(12,2) for exact precision

## Testing Strategy

### Unit Testing

Unit tests will focus on specific examples and edge cases:

**Transfer Handler Routing:**
- Test that Cash-to-Cash transfers are rejected
- Test that Bank-to-Bank transfers call `ledger_transfer()`
- Test that Cash-to-Bank transfers call `ledger_transfer_cash_to_bank()`
- Test that Bank-to-Cash transfers call `ledger_transfer_bank_to_cash()`

**Error Message Validation:**
- Test specific error messages for each validation failure type
- Test error message format includes balance amounts where specified

**UI Integration:**
- Test that Cash Account appears in dropdown data
- Test that cash balance is calculated and displayed correctly
- Test that source account is excluded from destination dropdown

### Property-Based Testing

Property tests will verify universal properties across all inputs using a PHP property-based testing library (e.g., Eris or php-quickcheck). Each test will run a minimum of 100 iterations with randomized inputs.

**Test Configuration:**
- Library: Eris (PHP port of QuickCheck)
- Iterations: 100 minimum per property
- Generators: Random amounts, account IDs, descriptions, dates, ledger states

**Property Test Cases:**

1. **Property 1: Transfer Balance Conservation**
   - Generate: Random initial ledger state, random valid transfer
   - Execute: Perform transfer
   - Assert: Total system balance unchanged
   - Tag: `Feature: bank-cash-transfers, Property 1: For any valid transfer, total system balance remains constant`

2. **Property 2: Transfer Creates Paired Ledger Entries**
   - Generate: Random valid transfer parameters
   - Execute: Perform transfer
   - Assert: Exactly 2 entries created with matching amounts, correct types, correct source_events
   - Tag: `Feature: bank-cash-transfers, Property 2: For any valid transfer, exactly two paired ledger entries are created`

3. **Property 3: Source Account Exclusion**
   - Generate: Random account as source
   - Execute: Build destination dropdown
   - Assert: Source account not in destination options
   - Tag: `Feature: bank-cash-transfers, Property 3: For any source account, it does not appear in destination options`

4. **Property 4: Same Account Transfer Rejection**
   - Generate: Random account ID
   - Execute: Attempt transfer from account to itself
   - Assert: Transfer rejected with correct error message
   - Tag: `Feature: bank-cash-transfers, Property 4: For any account, transferring to itself is rejected`

5. **Property 5: Non-Positive Amount Rejection**
   - Generate: Random non-positive amount (≤ 0)
   - Execute: Attempt transfer
   - Assert: Transfer rejected with correct error message
   - Tag: `Feature: bank-cash-transfers, Property 5: For any non-positive amount, transfer is rejected`

6. **Property 6: Insufficient Balance Rejection**
   - Generate: Random transfer where amount > source balance
   - Execute: Attempt transfer
   - Assert: Transfer rejected with error message containing balance
   - Tag: `Feature: bank-cash-transfers, Property 6: For any transfer exceeding source balance, transfer is rejected`

7. **Property 7: Invalid Account Rejection**
   - Generate: Random transfer with non-existent or inactive account
   - Execute: Attempt transfer
   - Assert: Transfer rejected with correct error message
   - Tag: `Feature: bank-cash-transfers, Property 7: For any transfer with invalid account, transfer is rejected`

8. **Property 8: Audit Trail Completeness**
   - Generate: Random valid transfer with user-provided date and description
   - Execute: Perform transfer
   - Assert: Both entries have correct posted_at, created_by, and description
   - Tag: `Feature: bank-cash-transfers, Property 8: For any successful transfer, audit fields are correctly populated`

9. **Property 9: Transfer Atomicity**
   - Generate: Random transfer, simulate failure at random step
   - Execute: Attempt transfer with injected failure
   - Assert: No ledger entries exist, no balance changes
   - Tag: `Feature: bank-cash-transfers, Property 9: For any transfer that fails, no changes are persisted`

10. **Property 10: Successful Transfer Logging**
    - Generate: Random valid transfer
    - Execute: Perform transfer
    - Assert: Log entry exists with level 'ACTION' and contains transfer details
    - Tag: `Feature: bank-cash-transfers, Property 10: For any successful transfer, action is logged`

11. **Property 11: Cash Balance Calculation Accuracy**
    - Generate: Random set of cash ledger entries (income and expense)
    - Execute: Calculate cash balance
    - Assert: Result equals sum(income) - sum(expense) excluding voided entries
    - Tag: `Feature: bank-cash-transfers, Property 11: For any ledger state, cash balance calculation is accurate`

12. **Property 12: Bank-to-Bank Transfer Preservation**
    - Generate: Random Bank-to-Bank transfer
    - Execute: Perform transfer
    - Assert: Behavior matches existing ledger_transfer() function (same validations, same ledger entries, same balance updates)
    - Tag: `Feature: bank-cash-transfers, Property 12: For any Bank-to-Bank transfer, existing behavior is preserved`

### Integration Testing

Integration tests will verify the complete flow:

1. **End-to-End Transfer Flow**: Test complete user journey from opening modal to successful transfer
2. **Modal State Management**: Test modal opening, closing, and state updates
3. **Flash Message Display**: Test success and error messages appear correctly
4. **Redirect Behavior**: Test proper redirects after transfer completion

### Manual Testing Checklist

- [ ] Open transfer modal and verify Cash Account appears in both dropdowns
- [ ] Select Cash Account and verify balance displays correctly
- [ ] Perform Bank-to-Cash transfer and verify cash balance increases
- [ ] Perform Cash-to-Bank transfer and verify bank balance increases
- [ ] Attempt invalid transfers and verify error messages
- [ ] Verify existing Bank-to-Bank transfers still work
- [ ] Check ledger entries are created correctly for all transfer types
- [ ] Verify transaction atomicity by simulating database errors

