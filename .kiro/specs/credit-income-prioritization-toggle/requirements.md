# Requirements Document

## Introduction

This feature adds a toggle switch to the credit transactions page that allows users to prioritize income entries over expense entries in the transaction list. When enabled, all income transactions appear first, followed by expense transactions. When disabled, transactions display in the current mixed chronological order.

## Glossary

- **Credit_Transactions_Page**: The accounts/credit.php page that displays all credit-based ledger entries
- **Toggle_Switch**: A UI control that allows users to switch between two states (ON/OFF)
- **Income_Entry**: A ledger entry with txn_type='income'
- **Expense_Entry**: A ledger entry with txn_type='expense'
- **User_Preference**: A persistent setting stored per user that survives page reloads and sessions
- **Transaction_List**: The table displaying credit ledger entries with their details

## Requirements

### Requirement 1: Toggle Switch UI

**User Story:** As a user, I want a toggle switch on the credit transactions page, so that I can control how transactions are sorted.

#### Acceptance Criteria

1. THE Credit_Transactions_Page SHALL display a toggle switch labeled "Prioritize income" in the header section near the "Show voided" checkbox
2. THE Toggle_Switch SHALL have two states: ON (enabled) and OFF (disabled)
3. THE Toggle_Switch SHALL default to ON state for all users
4. WHEN the Toggle_Switch state changes, THE Credit_Transactions_Page SHALL reload to apply the new sorting order
5. THE Toggle_Switch SHALL visually indicate its current state using standard UI patterns (checked/unchecked)

### Requirement 2: Income Prioritization Sorting

**User Story:** As a user, I want income entries to appear first when the toggle is ON, so that I can quickly see outstanding credit amounts.

#### Acceptance Criteria

1. WHEN the Toggle_Switch is ON, THE Transaction_List SHALL display all Income_Entry records before any Expense_Entry records
2. WHEN the Toggle_Switch is ON, THE Transaction_List SHALL sort Income_Entry records by posted_at in descending order (newest first)
3. WHEN the Toggle_Switch is ON, THE Transaction_List SHALL sort Expense_Entry records by posted_at in descending order (newest first)
4. WHEN the Toggle_Switch is OFF, THE Transaction_List SHALL display all entries sorted by posted_at in descending order regardless of txn_type
5. THE Transaction_List SHALL maintain the voided filter state independently of the prioritization toggle

### Requirement 3: Preference Persistence

**User Story:** As a user, I want my toggle preference to be remembered, so that I don't have to reset it every time I visit the page.

#### Acceptance Criteria

1. WHEN a user changes the Toggle_Switch state, THE Credit_Transactions_Page SHALL save the preference to the user's settings
2. WHEN a user loads the Credit_Transactions_Page, THE Toggle_Switch SHALL reflect the user's saved preference
3. WHERE no saved preference exists, THE Toggle_Switch SHALL default to ON state
4. THE Credit_Transactions_Page SHALL persist the toggle state across browser sessions
5. THE Credit_Transactions_Page SHALL persist the toggle state across page reloads and pagination

### Requirement 4: SQL Query Modification

**User Story:** As a developer, I want the SQL query to conditionally sort by transaction type, so that the feature performs efficiently.

#### Acceptance Criteria

1. WHEN the Toggle_Switch is ON, THE Credit_Transactions_Page SHALL modify the ORDER BY clause to sort by txn_type before posted_at
2. WHEN the Toggle_Switch is ON, THE Credit_Transactions_Page SHALL use a CASE expression to assign income entries a lower sort priority value than expense entries
3. WHEN the Toggle_Switch is OFF, THE Credit_Transactions_Page SHALL use the original ORDER BY clause (le.id DESC, le.posted_at DESC)
4. THE Credit_Transactions_Page SHALL maintain query performance with the modified ORDER BY clause
5. THE Credit_Transactions_Page SHALL apply the sorting logic to both the main query and the pagination count query

### Requirement 5: URL Parameter Handling

**User Story:** As a user, I want the toggle state to be reflected in the URL, so that I can bookmark or share specific views.

#### Acceptance Criteria

1. WHEN the Toggle_Switch is ON, THE Credit_Transactions_Page SHALL include a URL parameter prioritize_income=1
2. WHEN the Toggle_Switch is OFF, THE Credit_Transactions_Page SHALL omit the prioritize_income parameter or set it to 0
3. WHEN a user navigates to the page with prioritize_income=1 in the URL, THE Toggle_Switch SHALL be ON
4. WHEN a user navigates to the page without the prioritize_income parameter, THE Toggle_Switch SHALL use the saved preference or default to ON
5. THE Credit_Transactions_Page SHALL preserve the prioritize_income parameter across pagination links
