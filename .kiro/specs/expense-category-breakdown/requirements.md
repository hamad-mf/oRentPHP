# Requirements Document

## Introduction

This feature adds an expense category breakdown modal to the Vehicle Financial Report screen. Users can click a new summary card to view a full-screen modal displaying all expense categories with their amounts for a selected monthly period (15th-14th). The modal dynamically loads both custom-configured and system-generated expense categories, ensuring new categories automatically appear when added.

## Glossary

- **Vehicle_Financial_Report**: The existing report screen showing vehicle income, expenses, and balance with monthly period filtering
- **Summary_Card**: A clickable card in the top summary cards section that displays a metric or opens a detailed view
- **Expense_Category_Modal**: A full-screen modal popup that displays expense breakdown by category
- **Monthly_Period**: A date range from the 15th of one month to the 14th of the next month
- **Custom_Category**: An expense category configured by users in Settings → Expense Categories
- **System_Category**: A hardcoded expense category built into the system
- **Ledger_Entry**: A financial transaction record in the ledger_entries table
- **Category_Amount**: The total sum of expense amounts for a specific category within a period

## Requirements

### Requirement 1: Display Expense Categories Summary Card

**User Story:** As a user, I want to see a new summary card for expense categories, so that I can access detailed category breakdown information.

#### Acceptance Criteria

1. THE Vehicle_Financial_Report SHALL display an Expense Categories summary card in the top summary cards section
2. THE Expense_Categories_Card SHALL be positioned as the 4th card after Total Income, Total Expenses, and Net Balance cards
3. THE Expense_Categories_Card SHALL display the title "Expense Categories" or similar descriptive text
4. THE Expense_Categories_Card SHALL use the same visual styling as existing summary cards (bg-mb-surface, border, rounded-xl, padding)
5. WHEN a user clicks the Expense_Categories_Card, THE Vehicle_Financial_Report SHALL open the Expense_Category_Modal

### Requirement 2: Render Full-Screen Expense Category Modal

**User Story:** As a user, I want to view a full-screen modal with expense categories, so that I can see detailed breakdown without leaving the report screen.

#### Acceptance Criteria

1. WHEN the Expense_Categories_Card is clicked, THE Vehicle_Financial_Report SHALL display the Expense_Category_Modal as a full-screen overlay
2. THE Expense_Category_Modal SHALL include a backdrop that dims the background content
3. THE Expense_Category_Modal SHALL include a close button that dismisses the modal
4. WHEN the close button is clicked, THE Expense_Category_Modal SHALL close and return to the Vehicle_Financial_Report
5. WHEN the Escape key is pressed, THE Expense_Category_Modal SHALL close
6. THE Expense_Category_Modal SHALL prevent body scrolling while open
7. THE Expense_Category_Modal SHALL use consistent styling with existing modals (bg-[#141414], border-mb-subtle/20)

### Requirement 3: Load Expense Categories Dynamically

**User Story:** As a user, I want all expense categories to appear automatically, so that I don't need to manually update the modal when categories change.

#### Acceptance Criteria

1. THE Expense_Category_Modal SHALL fetch expense categories from both custom-configured and system-generated sources
2. THE Expense_Category_Modal SHALL retrieve custom categories from the system_settings table with key 'expense_categories'
3. THE Expense_Category_Modal SHALL retrieve system categories from the hardcoded system category list
4. WHEN a new custom category is added in Settings, THE Expense_Category_Modal SHALL display the new category on next load
5. THE Expense_Category_Modal SHALL display all unique categories without duplicates

### Requirement 4: Calculate Category Amounts for Selected Period

**User Story:** As a user, I want to see how much was spent in each category for a specific month, so that I can analyze expense patterns.

#### Acceptance Criteria

1. THE Expense_Category_Modal SHALL query the ledger_entries table to calculate category amounts
2. THE Expense_Category_Modal SHALL filter ledger entries where txn_type = 'expense'
3. THE Expense_Category_Modal SHALL apply the ledger_kpi_exclusion_clause to exclude non-KPI entries
4. THE Expense_Category_Modal SHALL group ledger entries by category field
5. THE Expense_Category_Modal SHALL sum the amount field for each category
6. THE Expense_Category_Modal SHALL display the calculated amount next to each category name
7. THE Expense_Category_Modal SHALL format amounts as currency with 2 decimal places

### Requirement 5: Filter by Monthly Period (15th-14th)

**User Story:** As a user, I want to filter expense categories by the same monthly period as the main report, so that the data is consistent.

#### Acceptance Criteria

1. THE Expense_Category_Modal SHALL use the same monthly period logic as the Vehicle_Financial_Report (15th to 14th)
2. THE Expense_Category_Modal SHALL default to the current period as determined by period_for_today()
3. THE Expense_Category_Modal SHALL include a month selector to change the displayed period
4. THE Expense_Category_Modal SHALL include a year selector to change the displayed period
5. WHEN the month or year is changed, THE Expense_Category_Modal SHALL recalculate category amounts for the new period
6. THE Expense_Category_Modal SHALL filter ledger entries where posted_at date is between period start and end dates (inclusive)

### Requirement 6: Display Category List with Amounts

**User Story:** As a user, I want to see all categories with their amounts in an organized list, so that I can quickly identify spending patterns.

#### Acceptance Criteria

1. THE Expense_Category_Modal SHALL display all expense categories in a list format
2. THE Expense_Category_Modal SHALL show the category name and amount for each category
3. THE Expense_Category_Modal SHALL sort categories by amount in descending order (highest to lowest)
4. THE Expense_Category_Modal SHALL display categories with zero amounts
5. THE Expense_Category_Modal SHALL use text-red-400 color for expense amounts to maintain consistency with expense styling
6. THE Expense_Category_Modal SHALL include a total row showing the sum of all category amounts
7. THE Expense_Category_Modal SHALL display a count of total categories shown

### Requirement 7: Parse and Pretty-Print Category Data

**User Story:** As a developer, I want category data to be properly parsed and formatted, so that the modal displays accurate information.

#### Acceptance Criteria

1. THE Vehicle_Financial_Report SHALL parse expense categories using expense_categories_get_list() function
2. THE Vehicle_Financial_Report SHALL encode category data as JSON for JavaScript consumption
3. THE Expense_Category_Modal SHALL decode the JSON category data correctly
4. FOR ALL valid category configurations, parsing the categories then encoding then parsing SHALL produce equivalent category lists (round-trip property)
5. WHEN category data is malformed or missing, THE Vehicle_Financial_Report SHALL fall back to default expense categories

