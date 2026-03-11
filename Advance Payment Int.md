# Advance Payment Implementation Guide

This document outlines the full implementation of the **Advance Payment** feature for reservations. It includes database requirements, architectural logic, and specific code updates for all relevant files.

## 1. Database Schema
The `reservations` table requires three new columns. Run the following SQL in your database management tool (e.g., phpMyAdmin):

```sql
ALTER TABLE reservations
  ADD COLUMN IF NOT EXISTS advance_paid              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS advance_payment_method    ENUM('cash','account','credit') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS advance_bank_account_id   INT DEFAULT NULL;
```

---

## 2. Core Logic: Historical Correction
The system uses a **Historical Correction** approach for editing advance payments to ensure daily targets and bank balances remain accurate.

- **On Creation:** The full advance is posted to the ledger for the current date.
- **On Edit (Increase/Decrease):** The original ledger entry (from the date the reservation was created) is **updated**.
    - This corrects the **Daily Target** for that original date.
    - It ensures today's target is not penalized or falsely boosted.
    - Bank balances are automatically adjusted by calculating the difference between the old and new amounts.
- **On Removal:** If the advance is set to 0, the original ledger entry is deleted, and the bank balance is reversed.

---

## 3. Required Helper Includes
Ensure these helpers are imported at the top of your reservation PHP files:
```php
require_once __DIR__ . '/../includes/reservation_payment_helpers.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
```

---

## 4. Implementation Details by File

### A. `reservations/create.php`
Allows specification of advance amount and method during booking.

**Backend Changes:**
1.  Initialize `$activeBankAccounts` using `ledger_get_accounts($pdo)`.
2.  In the `POST` handler, read `advance_paid`, `advance_payment_method`, and `advance_bank_account_id`.
3.  Add validation: Advance cannot exceed `total_price - voucher_applied`.
4.  Update the `INSERT` statement to include the 3 new columns.
5.  Call `ledger_post_reservation_event()` after the database commit.

---

### B. `reservations/edit.php` (Complex Logic)
Handles historical correction of ledger entries.

**Logic Block for `POST` handler:**
```php
$advKey = "reservation:advance:{$id}";
$newRounded = round($newAdvancePaid, 2);

// Check for original entry
$st = $pdo->prepare("SELECT id, bank_account_id, amount FROM ledger_entries WHERE idempotency_key = ? LIMIT 1");
$st->execute([$advKey]);
$existingLedger = $st->fetch();

if ($existingLedger) {
    if ($newRounded <= 0) {
        // DELETE: Advance removed, reverse bank balance
        if ($existingLedger['bank_account_id']) {
            $pdo->prepare("UPDATE bank_accounts SET balance = balance - ? WHERE id = ?")
                ->execute([$existingLedger['amount'], $existingLedger['bank_account_id']]);
        }
        $pdo->prepare("DELETE FROM ledger_entries WHERE id = ?")->execute([(int)$existingLedger['id']]);
    } else {
        // UPDATE: Adjust original entry
        $newBankId = ledger_resolve_bank_account_id($pdo, $newAdvanceMethod, $newAdvanceBankId);
        
        // Handle bank balance swap or simple amount adjustment
        if ($existingLedger['bank_account_id'] == $newBankId) {
            $diff = $newRounded - $existingLedger['amount'];
            if ($newBankId) {
                $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id = ?")->execute([$diff, $newBankId]);
            }
        } else {
            if ($existingLedger['bank_account_id']) {
                $pdo->prepare("UPDATE bank_accounts SET balance = balance - ? WHERE id = ?")->execute([$existingLedger['amount'], $existingLedger['bank_account_id']]);
            }
            if ($newBankId) {
                $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id = ?")->execute([$newRounded, $newBankId]);
            }
        }
        $pdo->prepare("UPDATE ledger_entries SET amount = ?, payment_mode = ?, bank_account_id = ? WHERE id = ?")
            ->execute([$newRounded, $newAdvanceMethod, $newBankId, (int)$existingLedger['id']]);
    }
} elseif ($newRounded > 0) {
    // INSERT: New advance added to legacy reservation
    ledger_post_reservation_event($pdo, $id, 'advance', $newAdvancePaid, $newAdvanceMethod, $userId, $newAdvanceBankId);
}
```

---

### C. `reservations/show.php`
Shows the collected amount to the user.

**UI Modification:** Add a row for "Advance Collected" in Pricing Summary.

---

### D. `reservations/deliver.php`
Deducts the advance from the final balance.

**Calculation Logic:**
```php
$totalCollectedSoFar = (float)$r['voucher_applied'] + (float)$r['advance_paid'];
$balanceRemaining = max(0, (float)$r['total_price'] - $totalCollectedSoFar);
```
Ensure the UI displays the advance clearly in the "Charge Breakdown" section.
