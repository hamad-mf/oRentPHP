# Implementation Tasks: Extension Payment from Deposit

## Overview
This task list implements the extension payment from deposit feature following the requirements and design documents. The implementation follows graceful degradation patterns and includes comprehensive testing.

---

## Phase 1: Database Schema Migration

### Task 1: Create Migration SQL File
**File**: `migrations/releases/2026-03-25_deposit_extension_usage.sql`

Create idempotent SQL migration with:
- Add `deposit_used_for_extension` DECIMAL(10,2) to `reservations` table
- Add `paid_from_deposit` DECIMAL(10,2) to `reservation_extensions` table
- Add `paid_cash` DECIMAL(10,2) to `reservation_extensions` table
- Add `payment_source_type` ENUM('cash','credit','account','deposit','split') to `reservation_extensions` table
- Use `IF NOT EXISTS` guards for idempotency
- Include release header with ID, author, and description

**Validates**: Requirement 2 (Database Schema), Requirement 8 (Migration File)

---

## Phase 2: Helper Functions

### Task 2: Add Deposit Calculation Helper
**File**: `includes/reservation_payment_helpers.php`

Add function `calculate_available_deposit(PDO $pdo, int $reservationId): float`
- Check if `deposit_used_for_extension` column exists using `information_schema`
- Calculate: deposit_amount - deposit_returned - deposit_deducted - deposit_held - deposit_used_for_extension
- Return 0 if result is negative
- Gracefully handle missing column (treat as 0)

**Validates**: Requirement 1.5 (Available Deposit Calculation), Property 1 (Remaining Deposit Calculation), Property 10 (Graceful Degradation)

### Task 3: Add Deposit Bank Account Helper
**File**: `includes/reservation_payment_helpers.php`

Add function `get_deposit_bank_account(PDO $pdo, int $reservationId): ?int`
- First try to get from ledger history using `ledger_get_security_deposit_account_id()`
- Fallback to configured default from settings
- Return null if no account found

**Validates**: Requirement 4.3 (Bank Account Consistency), Property 8 (Deposit Bank Account Consistency)

### Task 4: Add Ledger Helper for Deposit Expense
**File**: `includes/ledger_helpers.php`

Add function `ledger_post_extension_from_deposit(PDO $pdo, int $reservationId, int $extensionId, float $depositAmount, int $bankAccountId, int $userId): ?int`
- Post expense entry with category "Security Deposit"
- Use source_event "extension_from_deposit"
- Use idempotency key format: `reservation:extension_from_deposit:{extension_id}`
- Return ledger entry ID or null

**Validates**: Requirement 4.1 (Deposit Expense Posting), Property 6 (Deposit Expense Ledger Entry)

---

## Phase 3: Extension Form UI Updates

### Task 5: Add Payment Source Selection UI
**File**: `reservations/extend.php`

Add payment source radio buttons after rental type selection:
- Deposit (show available amount)
- Cash
- Credit
- Account (with bank account dropdown)
- Split (deposit + cash/credit/account)

Display available deposit amount when deposit is selected.

**Validates**: Requirement 1.1 (Payment Source Selection), Requirement 1.2 (Display Available Deposit)

### Task 6: Add Split Payment UI
**File**: `reservations/extend.php`

Add split payment section (hidden by default):
- Input for deposit portion (max = available deposit)
- Input for cash portion
- Dropdown for cash method (cash/credit/account)
- Bank account selector (shown when account selected)
- Live validation: deposit + cash = extension amount

Show split payment UI when deposit insufficient or user selects split.

**Validates**: Requirement 1.3 (Split Payment Option), Requirement 1.4 (Split Payment Validation)

### Task 7: Add JavaScript for Payment Source Toggle
**File**: `reservations/extend.php`

Add JavaScript functions:
- `togglePaymentSource()`: Show/hide relevant fields based on selection
- `validateSplitPayment()`: Real-time validation of split amounts
- `updateAvailableDeposit()`: Fetch and display current available deposit

**Validates**: Requirement 1.1 (Payment Source Selection), Property 2 (Split Payment Sum Validation)

---

## Phase 4: Extension Payment Processing

### Task 8: Add Deposit Payment Processing Logic
**File**: `reservations/extend.php`

In POST handler, add deposit payment processing:
- Validate payment source type
- For deposit-only: verify sufficient deposit, update `deposit_used_for_extension`
- For split: validate amounts sum to total, verify deposit portion available
- Store `paid_from_deposit`, `paid_cash`, `payment_source_type` in extension record
- Handle graceful degradation (check column existence)

**Validates**: Requirement 3 (Extension Payment Processing), Property 3 (Deposit Sufficiency), Property 4 (Deposit Usage Increment), Property 5 (Extension Payment Data Persistence)

### Task 9: Add Ledger Posting for Deposit Extensions
**File**: `reservations/extend.php`

After successful extension creation:
- If deposit used: call `ledger_post_extension_from_deposit()` for expense entry
- Post extension income entry (existing logic, may need split handling)
- For split payments: post separate entries for deposit and cash portions
- Use correct bank accounts for each portion

**Validates**: Requirement 4 (Ledger Posting), Property 6-9 (Ledger Entry Properties)

---

## Phase 5: Return Flow Updates

### Task 10: Update Remaining Deposit Calculation
**File**: `reservations/return.php`

Update deposit calculation logic:
- Check if `deposit_used_for_extension` column exists
- Include `deposit_used_for_extension` in remaining deposit calculation
- Update all deposit-related calculations to use new formula
- Ensure graceful degradation

**Validates**: Requirement 5.1 (Return Deposit Calculation), Property 1 (Remaining Deposit Calculation), Property 10 (Graceful Degradation)

### Task 11: Add Deposit Usage Breakdown Display
**File**: `reservations/return.php`

Add deposit breakdown section showing:
- Deposit collected
- Used for extensions (if > 0)
- Deducted for damages (if > 0)
- Held (if > 0)
- Remaining deposit

**Validates**: Requirement 5.2 (Deposit Usage Breakdown), Requirement 10 (Deposit Usage Breakdown Display)

### Task 12: Add Insufficient Deposit Handling
**File**: `reservations/return.php`

When damage charges exceed remaining deposit:
- Calculate additional payment needed: damage_charge - remaining_deposit
- Display prominent alert showing additional payment required
- Auto-calculate and show breakdown
- Ensure deposit deducted uses all remaining deposit

**Validates**: Requirement 6 (Insufficient Deposit Handling), Property 12 (Additional Payment Calculation)

---

## Phase 6: Reservation Details Display

### Task 13: Add Extension Payment Source Display
**File**: `reservations/show.php`

Query and display extension payment details:
- Fetch extensions with `paid_from_deposit`, `paid_cash`, `payment_source_type`
- Display payment source for each extension
- For split payments: show both deposit and cash amounts
- Show total deposit used for extensions
- Handle graceful degradation (fallback to payment_method)

**Validates**: Requirement 7 (Extension Payment Display), Property 14 (Extension Payment Source Display), Property 15 (Total Deposit Usage Display)

---

## Phase 7: Documentation

### Task 14: Update PRODUCTION_DB_STEPS.md
**File**: `PRODUCTION_DB_STEPS.md`

Add entry under "Pending" section:
- Date: 2026-03-25
- Release ID: deposit_extension_usage
- SQL file path
- Notes describing columns and purpose

**Validates**: Requirement 9 (Production Database Steps Documentation)

### Task 15: Update SESSION_RULES.md
**File**: `SESSION_RULES/SESSION_2026_03_07_RULES.md`

Add entry to release log table:
- Date, release ID, deployment status
- Note about deposit extension usage feature
- Mark SQL as pending application

**Validates**: Requirement 9 (Documentation)

---

## Phase 8: Testing

### Task 16: Manual Testing Checklist
Create and execute manual test scenarios:

1. Extension with full deposit payment
2. Extension with insufficient deposit (split payment)
3. Extension with zero deposit available
4. Multiple extensions depleting deposit gradually
5. Return with deposit used for extensions
6. Return with damages exceeding remaining deposit
7. Graceful degradation (before migration)
8. Ledger entry verification

**Validates**: All requirements and properties

### Task 17: Property-Based Test Setup (Optional)
**File**: `tests/extension_deposit_properties.test.js` (or similar)

Set up property-based testing framework:
- Install fast-check or hypothesis
- Create test generators for reservation/extension data
- Implement tests for Properties 1-15 from design document
- Configure minimum 100 iterations per property

**Validates**: All correctness properties

---

## Implementation Notes

### Graceful Degradation Pattern
All code must check for column existence before using new columns:
```php
$hasColumn = column_exists($pdo, 'reservations', 'deposit_used_for_extension');
if ($hasColumn) {
    // Use new column
} else {
    // Fallback behavior
}
```

### Idempotency
- All ledger posts use unique idempotency keys
- Migration SQL uses IF NOT EXISTS guards
- Safe to re-run migrations and ledger posts

### Error Handling
- Validate all inputs before processing
- Use database transactions for atomic updates
- Provide clear error messages to users
- Log all errors for debugging

### Testing Priority
1. Manual testing of core flows (Tasks 1-15)
2. Edge cases (insufficient deposit, multiple extensions)
3. Graceful degradation (before migration)
4. Property-based testing (optional but recommended)

---

## Completion Criteria

All tasks marked complete when:
- [ ] Migration SQL file created and tested
- [ ] Helper functions implemented with graceful degradation
- [ ] Extension form UI updated with payment sources
- [ ] Extension payment processing handles all scenarios
- [ ] Ledger posting works for deposit and split payments
- [ ] Return flow calculates deposit correctly
- [ ] Reservation details display extension payment info
- [ ] Documentation updated
- [ ] Manual testing completed successfully
- [ ] No syntax errors or diagnostics issues
