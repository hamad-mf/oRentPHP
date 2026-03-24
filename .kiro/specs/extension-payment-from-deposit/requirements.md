# Requirements Document

## Introduction

This feature enables using security deposit funds to pay for reservation extensions. Currently, when extending an active reservation, the extension amount must be paid via cash, credit, or account transfer. This feature adds the option to deduct the extension payment from the security deposit that was collected at delivery, with support for split payments when the deposit is insufficient. The system will track deposit usage for extensions separately and ensure proper handling at return time when damages may exceed the remaining deposit.

## Glossary

- **Extension_Payment_System**: The subsystem responsible for processing reservation extension payments
- **Deposit_Tracker**: The component that tracks security deposit usage across extensions and damages
- **Return_Payment_Calculator**: The component that calculates additional payments needed at return when deposit is insufficient
- **Ledger_Service**: The financial ledger system that records all monetary transactions
- **Extension_Record**: A database record storing details of a single reservation extension
- **Security_Deposit**: The refundable amount collected from the client at delivery
- **Remaining_Deposit**: The security deposit amount minus all deductions (extensions + damages + held amounts)
- **Split_Payment**: A payment composed of multiple sources (deposit + cash/credit/account)
- **Graceful_Degradation**: Code pattern where new features work correctly before and after database migration

## Requirements

### Requirement 1: Extension Payment Source Selection

**User Story:** As a staff member, I want to choose whether to pay for an extension using deposit or cash/credit/account, so that I can use the client's deposit when appropriate.

#### Acceptance Criteria

1. WHEN the extension form is displayed, THE Extension_Payment_System SHALL display available payment sources including deposit, cash, credit, and account
2. WHEN deposit is selected as payment source, THE Extension_Payment_System SHALL display the current available deposit amount
3. WHEN the extension amount exceeds available deposit, THE Extension_Payment_System SHALL offer a split payment option combining deposit with cash/credit/account
4. WHEN split payment is selected, THE Extension_Payment_System SHALL validate that deposit portion plus cash portion equals the total extension amount
5. THE Extension_Payment_System SHALL calculate remaining deposit as: deposit_amount - deposit_returned - deposit_deducted - deposit_held - deposit_used_for_extension

### Requirement 2: Database Schema for Deposit Extension Tracking

**User Story:** As a system administrator, I want the database to track deposit usage for extensions, so that the system maintains accurate financial records.

#### Acceptance Criteria

1. THE Deposit_Tracker SHALL store deposit_used_for_extension as a decimal column on the reservations table
2. THE Deposit_Tracker SHALL store paid_from_deposit as a decimal column on the reservation_extensions table
3. THE Deposit_Tracker SHALL store paid_cash as a decimal column on the reservation_extensions table
4. THE Deposit_Tracker SHALL store payment_source_type as an enum column on the reservation_extensions table with values: cash, credit, account, deposit, split
5. WHEN the deposit_used_for_extension column does not exist, THE Deposit_Tracker SHALL treat the value as zero (graceful degradation)
6. WHEN the paid_from_deposit column does not exist, THE Deposit_Tracker SHALL treat the value as zero (graceful degradation)

### Requirement 3: Extension Payment Processing with Deposit

**User Story:** As a staff member, I want to process extension payments using deposit funds, so that clients can use their deposit for extensions.

#### Acceptance Criteria

1. WHEN an extension is paid from deposit, THE Extension_Payment_System SHALL verify that available deposit is greater than or equal to the amount being deducted
2. WHEN an extension is paid from deposit, THE Extension_Payment_System SHALL increment reservations.deposit_used_for_extension by the deposit amount used
3. WHEN an extension is paid from deposit, THE Extension_Payment_System SHALL store the deposit amount in reservation_extensions.paid_from_deposit
4. WHEN an extension is paid via split payment, THE Extension_Payment_System SHALL store both paid_from_deposit and paid_cash amounts in the extension record
5. WHEN an extension is paid via split payment, THE Extension_Payment_System SHALL verify that paid_from_deposit plus paid_cash equals the total extension amount within 0.01 tolerance
6. IF available deposit is less than the requested deposit deduction, THEN THE Extension_Payment_System SHALL return an error message stating insufficient deposit

### Requirement 4: Ledger Posting for Deposit-Paid Extensions

**User Story:** As a financial administrator, I want deposit-paid extensions to be recorded in the ledger, so that all financial transactions are tracked.

#### Acceptance Criteria

1. WHEN an extension is paid from deposit, THE Ledger_Service SHALL post an expense entry with category Security_Deposit and source_event extension_from_deposit
2. WHEN an extension is paid from deposit, THE Ledger_Service SHALL post an income entry with category Reservation_Extension and source_event extension
3. WHEN an extension is paid from deposit, THE Ledger_Service SHALL use the security deposit bank account for both expense and income entries
4. WHEN an extension is paid via split payment, THE Ledger_Service SHALL post separate entries for the deposit portion and the cash portion
5. WHEN an extension is paid via split payment with account method, THE Ledger_Service SHALL use the selected bank account for the cash portion
6. THE Ledger_Service SHALL use idempotency keys in the format reservation:extension_from_deposit:{extension_id} for deposit expense entries
7. THE Ledger_Service SHALL use idempotency keys in the format reservation:extension:{extension_id} for extension income entries

### Requirement 5: Return Flow Deposit Calculation

**User Story:** As a staff member, I want the return screen to show accurate remaining deposit after extensions, so that I can correctly process deposit returns and deductions.

#### Acceptance Criteria

1. WHEN the return form is displayed, THE Return_Payment_Calculator SHALL calculate remaining deposit as: deposit_amount - deposit_returned - deposit_deducted - deposit_held - deposit_used_for_extension
2. WHEN the return form is displayed, THE Return_Payment_Calculator SHALL display a deposit usage breakdown showing amounts used for extensions and damages
3. WHEN deposit_used_for_extension column does not exist, THE Return_Payment_Calculator SHALL calculate remaining deposit without the extension component (graceful degradation)
4. THE Return_Payment_Calculator SHALL validate that deposit_returned plus deposit_deducted plus deposit_held does not exceed remaining deposit
5. WHEN damages exceed remaining deposit, THE Return_Payment_Calculator SHALL calculate additional payment as: damage_charge minus remaining_deposit

### Requirement 6: Insufficient Deposit at Return Handling

**User Story:** As a staff member, I want the system to automatically calculate additional payment needed when damages exceed remaining deposit, so that I can collect the correct amount from the client.

#### Acceptance Criteria

1. WHEN damage charges exceed remaining deposit at return, THE Return_Payment_Calculator SHALL calculate additional_payment_needed as: damage_charge minus remaining_deposit
2. WHEN additional payment is needed, THE Return_Payment_Calculator SHALL display the additional payment amount prominently on the return form
3. WHEN additional payment is needed, THE Return_Payment_Calculator SHALL add the additional payment to the total amount due at return
4. WHEN additional payment is collected, THE Return_Payment_Calculator SHALL record the payment method (cash, credit, or account)
5. WHEN additional payment is collected via account method, THE Return_Payment_Calculator SHALL record the bank account that received the payment

### Requirement 7: Extension Payment Display on Reservation Details

**User Story:** As a staff member, I want to see how each extension was paid on the reservation details page, so that I can review the payment history.

#### Acceptance Criteria

1. WHEN the reservation details page is displayed, THE Extension_Payment_System SHALL show payment source for each extension (deposit, cash, credit, account, or split)
2. WHEN an extension was paid via split payment, THE Extension_Payment_System SHALL display both the deposit amount and cash amount
3. WHEN an extension was paid from deposit, THE Extension_Payment_System SHALL display the deposit amount used
4. THE Extension_Payment_System SHALL display total deposit used for extensions across all extensions
5. WHEN the paid_from_deposit column does not exist, THE Extension_Payment_System SHALL display payment source as the payment_method value (graceful degradation)

### Requirement 8: Migration File Creation

**User Story:** As a system administrator, I want a SQL migration file for this feature, so that I can safely apply database changes to production.

#### Acceptance Criteria

1. THE Deposit_Tracker SHALL provide a migration file at migrations/releases/2026-03-25_deposit_extension_usage.sql
2. THE migration file SHALL use IF NOT EXISTS guards for all schema changes to ensure idempotency
3. THE migration file SHALL add deposit_used_for_extension column to reservations table with default value 0.00
4. THE migration file SHALL add paid_from_deposit column to reservation_extensions table with default value 0.00
5. THE migration file SHALL add paid_cash column to reservation_extensions table with default value 0.00
6. THE migration file SHALL add payment_source_type column to reservation_extensions table with default value matching payment_method
7. THE migration file SHALL include a comment header with release ID, author, and description

### Requirement 9: Production Database Steps Documentation

**User Story:** As a system administrator, I want the migration to be documented in PRODUCTION_DB_STEPS.md, so that I have a clear deployment checklist.

#### Acceptance Criteria

1. THE Deposit_Tracker SHALL add an entry to PRODUCTION_DB_STEPS.md under the Pending section
2. THE entry SHALL include the date 2026-03-25
3. THE entry SHALL include the release ID deposit_extension_usage
4. THE entry SHALL include the SQL file path migrations/releases/2026-03-25_deposit_extension_usage.sql
5. THE entry SHALL include notes describing the columns being added and their purpose

### Requirement 10: Deposit Usage Breakdown Display

**User Story:** As a staff member, I want to see a breakdown of how the deposit was used, so that I can explain charges to the client.

#### Acceptance Criteria

1. WHEN the reservation details page is displayed, THE Extension_Payment_System SHALL display total deposit collected
2. WHEN the reservation details page is displayed, THE Extension_Payment_System SHALL display deposit used for extensions
3. WHEN the reservation details page is displayed, THE Extension_Payment_System SHALL display deposit deducted for damages
4. WHEN the reservation details page is displayed, THE Extension_Payment_System SHALL display deposit held with reason
5. WHEN the reservation details page is displayed, THE Extension_Payment_System SHALL display deposit returned to client
6. WHEN the reservation details page is displayed, THE Extension_Payment_System SHALL display remaining deposit as: deposit_amount minus all deductions
7. WHEN the bill page is displayed, THE Extension_Payment_System SHALL include the deposit usage breakdown in the printed bill
