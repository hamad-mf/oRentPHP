# Design Document: Hope Window — Prediction vs Actual

## Overview

This feature adds a read-only "Actual vs Predicted" comparison layer to `accounts/hope_window.php`. For past days and today, the page will display the actual income collected (sourced from `ledger_entries`) alongside the existing expected income figure, plus a signed variance. Future days are unchanged. No new tables, migrations, or write operations are introduced.

The change is purely additive: one new PHP query block loads actual income per day, the `$days` array gains an `actual` key, and the list-view and day-view templates each gain a new column/card for actual income and variance.

---

## Architecture

The feature lives entirely within `accounts/hope_window.php`. No new files are created.

```
accounts/hope_window.php
  ├── [existing] period/date setup
  ├── [existing] target/prediction/expected-income loading
  ├── [NEW] actual income query  ← single SELECT … GROUP BY DATE(posted_at)
  ├── [existing] $days[] array construction  ← gains 'actual' key
  └── HTML/JS rendering
        ├── list view  ← gains Actual + Variance columns for past/today rows
        └── day view   ← gains Actual + Variance cards for past/today
```

The query reuses `ledger_kpi_exclusion_clause()` from `includes/ledger_helpers.php`, which is already `require_once`'d at the top of the file.

### Data flow

```
ledger_entries
    │
    ▼  SELECT DATE(posted_at), SUM(amount)
    │  WHERE txn_type='income'
    │    AND [ledger_kpi_exclusion_clause()]
    │    AND DATE(posted_at) BETWEEN $rangeStart AND $rangeEnd
    │  GROUP BY DATE(posted_at)
    ▼
$actualMap['YYYY-MM-DD'] => float
    │
    ▼  merged into $days[]['actual']
    │
    ▼  rendered only when $row['date'] <= $today
```

---

## Components and Interfaces

### 1. Actual Income Query (new PHP block)

Placed after the existing extension-payments block and before the `$days` array construction.

```php
// Load actual income per day from ledger
$actualMap = [];
$hasLedgerTable = false;
try {
    $hasLedgerTable = (bool) $pdo->query("SHOW TABLES LIKE 'ledger_entries'")->fetchColumn();
} catch (Throwable $e) { /* silent */ }

if ($hasLedgerTable) {
    try {
        $kpiClause = ledger_kpi_exclusion_clause();
        $actualStmt = $pdo->prepare(
            "SELECT DATE(posted_at) AS day, COALESCE(SUM(amount), 0) AS total
             FROM ledger_entries
             WHERE txn_type = 'income'
               AND $kpiClause
               AND DATE(posted_at) BETWEEN ? AND ?
             GROUP BY DATE(posted_at)"
        );
        $actualStmt->execute([$rangeStart, $rangeEnd]);
        foreach ($actualStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $actualMap[$row['day']] = (float) $row['total'];
        }
    } catch (Throwable $e) {
        // Treat as zero for all days; page continues rendering
        $actualMap = [];
    }
}
```

### 2. $days Array Extension

The `actual` key is added to each day entry:

```php
$days[] = [
    // ... existing keys ...
    'actual' => $actualMap[$ds] ?? 0.0,   // NEW
];
```

### 3. List View — New Columns

The header row gains two new columns (Actual, Variance). Each day row conditionally renders them:

- Past day or today (`$row['date'] <= $today`): show actual income and signed variance.
- Future day: show `—` placeholder (no value).

Column layout shifts from `grid-cols-12` with spans `[3,3,2,2,2]` to `[2,2,2,2,2,2]` to accommodate the two new columns.

### 4. Day View — New Cards

Two new stat cards are added to the `md:grid-cols-4` grid (expanding to `md:grid-cols-6`):

- **Actual Income** card: shown for past/today, hidden for future.
- **Variance** card: shown for past/today when expected > 0; shows "—" when expected = 0.

### 5. Variance Computation Helper (inline)

```php
$variance = $row['actual'] - $row['expected'];
$varianceClass = $variance >= 0 ? 'text-green-400' : 'text-red-400';
$varianceSign  = $variance >= 0 ? '+' : '-';
```

No division is performed (no percentage), avoiding division-by-zero entirely.

---

## Data Models

No new tables or schema changes. The feature reads from the existing `ledger_entries` table.

### Relevant columns used

| Column | Usage |
|---|---|
| `txn_type` | Filter to `'income'` only |
| `posted_at` | Group by `DATE(posted_at)` and filter to period range |
| `amount` | Summed per day |
| `voided_at` | Excluded via `ledger_kpi_exclusion_clause()` (`voided_at IS NULL`) |
| `source_event` | Excluded via KPI clause (security deposit, transfer events) |
| `source_type` | Excluded via KPI clause (`transfer`) |

### $days entry shape (after change)

```php
[
    'date'             => 'YYYY-MM-DD',
    'label'            => 'Mon, 15 Jun',
    'target'           => float,
    'override'         => bool,
    'expected'         => float,
    'prediction_count' => int,
    'prediction_sum'   => float,
    'predictions'      => array,
    'is_today'         => bool,
    'actual'           => float,   // NEW — 0.0 for future days
]
```

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Correct per-day aggregation

*For any* period range and any set of non-voided income ledger entries (after KPI exclusion), the `$actualMap` produced by the query must contain exactly the sum of `amount` values for each date, and must not contain dates outside the period range.

**Validates: Requirements 1.1, 1.3**

---

### Property 2: KPI exclusion filtering

*For any* set of ledger entries that includes rows with `source_event IN ('security_deposit_in', 'security_deposit_out', 'transfer_in', 'transfer_out')` or `source_type = 'transfer'` or `voided_at IS NOT NULL`, adding those rows to the database must not change the value in `$actualMap` for any date.

**Validates: Requirements 1.2**

---

### Property 3: Past and today days always carry an actual income value

*For any* day whose date is less than or equal to today (Asia/Kolkata), the rendered HTML for that day's row/card must contain the actual income figure (even if it is zero).

**Validates: Requirements 2.1, 2.2**

---

### Property 4: Future days never show actual income

*For any* day whose date is strictly greater than today (Asia/Kolkata), the rendered HTML for that day's row must not contain an actual income figure or variance value.

**Validates: Requirements 2.3**

---

### Property 5: Variance value correctness

*For any* past or today day where `expected > 0`, the variance displayed must equal `actual - expected` (signed, not absolute).

**Validates: Requirements 3.1**

---

### Property 6: Variance color matches sign

*For any* past or today day, if `actual >= expected` the variance element must carry a green color class; if `actual < expected` it must carry a red color class.

**Validates: Requirements 3.2, 3.3**

---

### Property 7: Read-only invariant

*For any* page load of Hope Window, the row counts of `ledger_entries`, `hope_daily_predictions`, and `hope_daily_targets` must be identical before and after the request.

**Validates: Requirements 4.6**

---

## Error Handling

| Scenario | Behavior |
|---|---|
| `ledger_entries` table does not exist | `$actualMap = []`; all days render with `actual = 0.0`; no error banner shown |
| Query throws `Throwable` | Caught; `$actualMap = []`; page continues rendering normally |
| `SHOW TABLES` check fails | `$hasLedgerTable = false`; query skipped entirely |
| Future day accessed | `actual` key is present (0.0) but the template simply does not render the actual/variance UI for that row |
| `expected = 0` on a past day | Variance is shown as `actual - 0 = actual`; no percentage computed; no division-by-zero risk |

The existing migration warning banners for `hope_daily_targets` and `hope_daily_predictions` are untouched.

---

## Testing Strategy

### Unit tests

Focus on specific examples and edge cases:

- **Example: graceful failure** — Mock a PDO that throws on the actual-income query; assert `$actualMap` is empty and no exception propagates (covers Requirement 1.4).
- **Example: zero expected income** — Render a past day with `expected = 0` and `actual > 0`; assert variance is shown as the raw actual amount and no `%` or `NaN` appears (covers Requirement 3.4).
- **Example: visual distinction** — Render a past day; assert the "Actual" label text differs from the "Expected" label text in the output (covers Requirement 2.4).

### Property-based tests

Use a PHP property-based testing library (e.g., **eris** or **PhpQuickCheck**) with a minimum of **100 iterations per property**.

Each test must be tagged with a comment in the format:
`// Feature: hope-window-prediction-vs-actual, Property N: <property_text>`

| Property | Test description |
|---|---|
| P1: Correct aggregation | Generate random sets of income entries on random dates within a period; assert `$actualMap[date]` equals the expected sum for each date |
| P2: KPI exclusion | Generate random entries mixing KPI-excluded and non-excluded types; assert excluded entries never contribute to any date's sum |
| P3: Past/today show actual | Generate random past dates; assert rendered HTML contains the actual income value |
| P4: Future days hide actual | Generate random future dates; assert rendered HTML does not contain an actual income element |
| P5: Variance correctness | Generate random (actual, expected) pairs with expected > 0; assert displayed variance equals actual − expected |
| P6: Variance color | Generate random (actual, expected) pairs; assert green class when actual ≥ expected, red class when actual < expected |
| P7: Read-only invariant | Load the page with a seeded database; assert table row counts are unchanged after the request |

**Unit tests and property tests are complementary** — unit tests catch concrete bugs in specific scenarios; property tests verify general correctness across the input space. Both are required for comprehensive coverage.
