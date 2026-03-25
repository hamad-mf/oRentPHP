# Implementation Plan: Hope Window — Prediction vs Actual

## Overview

Purely additive change to `accounts/hope_window.php`. One new PHP query block loads actual income per day from `ledger_entries`, the `$days` array gains an `actual` key, and both the list-view and day-view templates gain new columns/cards for actual income and signed variance. No new files, tables, or write operations.

## Tasks

- [x] 1. Add actual income query block to `accounts/hope_window.php`
  - After the existing extension-payments block and before the `$days[]` construction loop, add the `$actualMap` query using `ledger_kpi_exclusion_clause()`
  - Guard with `SHOW TABLES LIKE 'ledger_entries'` check; catch all `Throwable` and fall back to `$actualMap = []`
  - _Requirements: 1.1, 1.2, 1.3, 1.4_

  - [ ]* 1.1 Write property test for correct per-day aggregation (Property 1)
    - **Property 1: Correct per-day aggregation**
    - Seed random income entries on random dates within a period; assert `$actualMap[date]` equals the expected sum per date and no out-of-range dates appear
    - **Validates: Requirements 1.1, 1.3**

  - [ ]* 1.2 Write property test for KPI exclusion filtering (Property 2)
    - **Property 2: KPI exclusion filtering**
    - Seed entries mixing KPI-excluded types (`security_deposit_in/out`, `transfer_in/out`, `source_type=transfer`, `voided_at IS NOT NULL`) with normal income; assert excluded rows never contribute to any date's sum
    - **Validates: Requirements 1.2**

- [x] 2. Extend `$days[]` array construction with `actual` key
  - Add `'actual' => $actualMap[$ds] ?? 0.0` to each day entry inside the existing `while ($cursor <= $endDate)` loop
  - _Requirements: 1.3, 2.1, 2.2, 2.3_

- [x] 3. Update list-view header and row rendering
  - Adjust the header `grid-cols-12` spans from `[3,3,2,2,2]` to `[2,2,2,2,2,2]` and add "Actual" and "Variance" column headers
  - For each day row: when `$row['date'] <= $today`, render the actual income value and signed variance (`actual - expected`) with green/red color class; for future days render `—` placeholders
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4_

  - [ ]* 3.1 Write property test for past/today rows show actual income (Property 3)
    - **Property 3: Past and today days always carry an actual income value**
    - Generate random past dates; assert rendered HTML contains the actual income figure for those rows
    - **Validates: Requirements 2.1, 2.2**

  - [ ]* 3.2 Write property test for future rows hide actual income (Property 4)
    - **Property 4: Future days never show actual income**
    - Generate random future dates; assert rendered HTML does not contain an actual income element or variance value for those rows
    - **Validates: Requirements 2.3**

  - [ ]* 3.3 Write property test for variance value correctness (Property 5)
    - **Property 5: Variance value correctness**
    - Generate random (actual, expected) pairs with `expected > 0`; assert displayed variance equals `actual − expected` (signed)
    - **Validates: Requirements 3.1**

  - [ ]* 3.4 Write property test for variance color matches sign (Property 6)
    - **Property 6: Variance color matches sign**
    - Generate random (actual, expected) pairs; assert green class when `actual >= expected`, red class when `actual < expected`
    - **Validates: Requirements 3.2, 3.3**

- [x] 4. Update day-view stat cards
  - Expand the existing `md:grid-cols-4` stat card grid to `md:grid-cols-6`
  - Add "Actual Income" card: visible only when `$selectedDay['date'] <= $today`
  - Add "Variance" card: visible only when `$selectedDay['date'] <= $today`; show raw `actual - expected` (no percentage); use green/red color class; when `expected = 0` show actual amount without a percentage figure
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 3.1, 3.2, 3.3, 3.4_

  - [ ]* 4.1 Write unit test for zero-expected edge case
    - Render a past day with `expected = 0` and `actual > 0`; assert variance shows the raw actual amount and no `%` or `NaN` appears
    - _Requirements: 3.4_

  - [ ]* 4.2 Write unit test for visual distinction of labels
    - Render a past day; assert the "Actual" label text differs from the "Expected" label text in the output
    - _Requirements: 2.4_

- [x] 5. Checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [x] 6. Verify read-only invariant and graceful degradation
  - Confirm no POST handler or write path touches `ledger_entries`; verify existing migration warning banners for `hope_daily_targets` and `hope_daily_predictions` are untouched
  - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

  - [ ]* 6.1 Write property test for read-only invariant (Property 7)
    - **Property 7: Read-only invariant**
    - Load the page with a seeded database; assert row counts of `ledger_entries`, `hope_daily_predictions`, and `hope_daily_targets` are identical before and after the request
    - **Validates: Requirements 4.6**

  - [ ]* 6.2 Write unit test for graceful failure when ledger query throws
    - Mock a PDO that throws on the actual-income query; assert `$actualMap` is empty and no exception propagates
    - _Requirements: 1.4_

- [x] 7. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- All changes are confined to `accounts/hope_window.php` — no new files or migrations
- Property tests should use a PHP PBT library (e.g., eris or PhpQuickCheck) with ≥ 100 iterations per property
- Each property test must include a comment: `// Feature: hope-window-prediction-vs-actual, Property N: <property_text>`
