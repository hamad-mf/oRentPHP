# Requirements Document

## Introduction

The Hope Window page currently shows per-day predictions (manually entered via `hope_daily_predictions`) and expected income projected from the reservation schedule. This feature adds an "Actual vs Predicted" comparison layer: for past days, the page will display the actual income collected (sourced from `ledger_entries`) alongside the predicted/expected figures, so the user can evaluate forecast accuracy over time. Future days continue to show only predictions and expected income as before. No existing functionality is modified.

## Glossary

- **Hope_Window**: The page at `accounts/hope_window.php` that shows daily income targets, predictions, and expected income per period.
- **Ledger**: The `ledger_entries` table that records all actual income and expense transactions posted to the system.
- **Actual_Income**: The sum of non-voided `ledger_entries` rows with `txn_type = 'income'` whose `posted_at` date falls on a given day, excluding security deposit and transfer events (consistent with existing KPI exclusion logic via `ledger_kpi_exclusion_clause()`).
- **Expected_Income**: The projected income for a day, calculated from the reservation schedule (booking advance, delivery due, return due, extension payments) plus the sum of manual `hope_daily_predictions` entries for that day.
- **Past_Day**: Any calendar date strictly before today's date in the Asia/Kolkata timezone.
- **Today**: The current calendar date in the Asia/Kolkata timezone.
- **Future_Day**: Any calendar date strictly after today's date in the Asia/Kolkata timezone.
- **Prediction_Accuracy**: The ratio of Actual_Income to Expected_Income for a Past_Day, expressed as a percentage.
- **Period**: The 15th-to-15th billing window currently selected on the Hope_Window page.

---

## Requirements

### Requirement 1: Load Actual Income Per Day for the Selected Period

**User Story:** As a business owner, I want to see how much income was actually collected on each past day, so that I can compare it against what was predicted.

#### Acceptance Criteria

1. WHEN the Hope_Window page loads for a selected Period, THE Hope_Window SHALL query the Ledger for the sum of non-voided income entries grouped by `DATE(posted_at)` for all dates within the Period range.
2. THE Hope_Window SHALL apply the same KPI exclusion logic used elsewhere in the system (excluding `security_deposit_in`, `security_deposit_out`, `transfer_in`, `transfer_out` source events and `transfer` source type) when computing Actual_Income per day.
3. THE Hope_Window SHALL store the per-day Actual_Income in a map keyed by date string (`YYYY-MM-DD`) for use in rendering.
4. IF the `ledger_entries` table does not exist or the query fails, THEN THE Hope_Window SHALL treat Actual_Income as zero for all days and continue rendering without error.

---

### Requirement 2: Display Actual Income on Past Days

**User Story:** As a business owner, I want past days to show the actual income collected next to the predicted amount, so that I can see how accurate my forecasts were.

#### Acceptance Criteria

1. WHEN rendering a day row or day detail view for a Past_Day, THE Hope_Window SHALL display the Actual_Income for that day alongside the Expected_Income.
2. WHEN rendering a day row or day detail view for Today, THE Hope_Window SHALL display the Actual_Income collected so far for that day alongside the Expected_Income.
3. WHEN rendering a day row or day detail view for a Future_Day, THE Hope_Window SHALL NOT display an Actual_Income column or value for that day.
4. THE Hope_Window SHALL visually distinguish the Actual_Income figure from the Expected_Income figure (e.g., different label and/or color).

---

### Requirement 3: Show Prediction Accuracy for Past Days

**User Story:** As a business owner, I want to see a variance or accuracy indicator for past days, so that I can quickly identify days where income was significantly above or below expectations.

#### Acceptance Criteria

1. WHEN rendering a Past_Day or Today where Expected_Income is greater than zero, THE Hope_Window SHALL display the variance between Actual_Income and Expected_Income (i.e., `Actual_Income - Expected_Income`).
2. WHEN the variance is positive (Actual_Income exceeds Expected_Income), THE Hope_Window SHALL render the variance in a success color (green).
3. WHEN the variance is negative (Actual_Income is below Expected_Income), THE Hope_Window SHALL render the variance in a warning color (red).
4. WHEN Expected_Income is zero for a Past_Day, THE Hope_Window SHALL display the Actual_Income without a percentage accuracy figure to avoid division by zero.

---

### Requirement 4: Preserve All Existing Hope Window Functionality

**User Story:** As a developer, I want the new actual-vs-predicted display to be purely additive, so that no existing targets, predictions, expected income calculations, or admin edit forms are broken.

#### Acceptance Criteria

1. THE Hope_Window SHALL continue to display and allow editing of per-day targets (via `hope_daily_targets`) exactly as before.
2. THE Hope_Window SHALL continue to display and allow editing of per-day predictions (via `hope_daily_predictions`) exactly as before.
3. THE Hope_Window SHALL continue to compute and display Expected_Income from the reservation schedule and predictions exactly as before.
4. THE Hope_Window SHALL continue to display the Gap column (Expected_Income minus Target) exactly as before.
5. WHEN the `hope_daily_predictions` table or `hope_daily_targets` table is missing, THE Hope_Window SHALL continue to show the existing migration warning banners and degrade gracefully, as before.
6. THE Hope_Window SHALL NOT modify any data in `ledger_entries`, `hope_daily_predictions`, or `hope_daily_targets` as part of this feature.
