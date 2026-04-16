# Complete Guide: Reverting $15,000 Staff Advance

## Summary
You accidentally gave a $15,000 staff advance on production (thinking you were on local). This guide explains EXACTLY what was affected and how to cleanly revert it.

---

## What Happened When You Created the Advance

The system performed these 4 database operations (in a transaction):

### 1. Created Advance Record
```sql
INSERT INTO payroll_advances 
(user_id, payroll_id, month, year, amount, remaining_amount, status, note, given_at, created_by)
VALUES (?, NULL, 4, 2026, 15000.00, 15000.00, 'pending', ?, '2026-04-16 ...', ?)
```

### 2. Created Ledger Entry
```sql
INSERT INTO ledger_entries 
(txn_type, category, description, amount, payment_mode, bank_account_id, source_type, source_id, source_event, posted_at, created_by)
VALUES ('expense', 'Staff Advance', 'Staff Advance - [Name] (pre-payroll)', 15000.00, 'account', ?, 'payroll_advance', ?, 'advance_payment', '2026-04-16 ...', ?)
```

### 3. Updated Bank Balance
```sql
UPDATE bank_accounts 
SET balance = balance - 15000.00 
WHERE id = ?
```

### 4. Linked Records Together
```sql
UPDATE payroll_advances 
SET bank_account_id = ?, ledger_entry_id = ? 
WHERE id = ?
```

---

## Where This $15,000 Appears in Your System

### ✅ DIRECTLY AFFECTED (Database Tables)
1. **`payroll_advances` table** - The advance record with status='pending', remaining_amount=15000
2. **`ledger_entries` table** - An expense entry with category='Staff Advance', amount=15000
3. **`bank_accounts` table** - Your selected bank account balance is $15,000 LESS

### ✅ INDIRECTLY AFFECTED (Calculated from above tables)
These pages/reports query the above tables dynamically (NO cached data):

1. **Staff Profile Page** (`staff/show.php`)
   - Shows "Due: $15,000.00" badge
   - Lists the advance in "Recent Advances" section

2. **Staff Dashboard** (`index.php` for that staff member)
   - Shows advance in "My Payroll Advance" widget

3. **Accounts Page** (`accounts/index.php`)
   - Shows $15,000 in EXPENSES for today (16 Apr 2026)
   - Shows in NET calculation (reduces net by $15,000)
   - Shows in ledger table as "Staff Advance" expense

4. **Monthly Reports** (`reports/index.php`)
   - Includes $15,000 in total expenses for period 16 Mar - 15 Apr
   - Affects net income calculation

5. **Hope Window** (`accounts/hope_window.php`)
   - Includes $15,000 in expense calculations

6. **Vehicle Targets** (`accounts/vehicle_targets.php`, `accounts/targets.php`)
   - Includes $15,000 in expense totals

7. **Expense Category Breakdown**
   - Shows $15,000 under "Staff Advance" category

### ❌ NOT AFFECTED
- No cached/aggregate tables exist
- No separate transaction logs
- No audit trail tables (beyond the records themselves)
- No external systems

---

## How to Revert (Step-by-Step)

### Step 1: Verify the Advance
Run the verification query first (already in `revert_15000_advance.sql`):

```sql
SELECT 
    pa.id AS advance_id,
    pa.amount,
    pa.given_at,
    pa.status,
    pa.remaining_amount,
    u.name AS staff_name,
    ba.name AS bank_account,
    ba.balance AS current_bank_balance,
    le.id AS ledger_entry_id,
    le.description
FROM payroll_advances pa
LEFT JOIN users u ON pa.user_id = u.id
LEFT JOIN bank_accounts ba ON pa.bank_account_id = ba.id
LEFT JOIN ledger_entries le ON pa.ledger_entry_id = le.id
WHERE pa.amount = 15000.00 
AND DATE(pa.given_at) = '2026-04-16'
ORDER BY pa.given_at DESC 
LIMIT 1;
```

**Check:**
- Confirms it's the right advance
- Shows which staff member
- Shows which bank account
- Shows current bank balance

### Step 2: Execute the Revert
Uncomment the revert section in `revert_15000_advance.sql` and run it.

**What it does:**
1. Finds the $15,000 advance from today
2. Gets the linked ledger entry and bank account
3. **Restores bank balance** (+$15,000)
4. **Deletes ledger entry** (removes from all reports)
5. **Deletes advance record** (removes from staff profile)

### Step 3: Verify Clean Removal
The script includes verification queries that should return 0 rows.

---

## After Revert - What Changes

### ✅ Immediately Fixed
1. **Staff profile** - "Due" amount disappears, advance not in history
2. **Accounts page** - $15,000 expense disappears from today
3. **Bank account** - Balance restored to original amount
4. **All reports** - $15,000 no longer in expense calculations
5. **Ledger** - No trace of the transaction

### ✅ No Trace Left
- The records are DELETED (not soft-deleted)
- No "voided" or "cancelled" status
- It's as if it never happened

---

## Safety Features

1. **Specific targeting** - Only targets $15,000 advance from 16 Apr 2026
2. **Won't affect other advances** - Even if there are other advances today
3. **Transaction safety** - Original creation was in a transaction (atomic)
4. **Verification first** - Script requires you to verify before executing

---

## Execute on Production

```bash
# Option 1: MySQL command line
mysql -u your_user -p your_database < revert_15000_advance.sql

# Option 2: Via PHP
php -r "require 'config/db.php'; \$pdo = db(); \$sql = file_get_contents('revert_15000_advance.sql'); \$pdo->exec(\$sql);"

# Option 3: Copy-paste into phpMyAdmin or your DB tool
```

---

## Questions Answered

**Q: Does this affect any cached data?**  
A: No. Your system has NO cached/aggregate tables. Everything is calculated on-the-fly from the 3 main tables.

**Q: Will this leave any audit trail?**  
A: No. The records are completely deleted. No soft-delete, no "cancelled" status.

**Q: What if someone already saw the advance?**  
A: After deletion, it won't appear anywhere. If they refresh, it's gone.

**Q: Is this safe?**  
A: Yes. The script specifically targets only the $15,000 advance from today. It won't touch other data.

**Q: Can I undo the revert?**  
A: No. Once deleted, it's gone. But you can always create a new advance if needed.

---

## Summary

**3 tables affected:**
- `payroll_advances` - DELETE the record
- `ledger_entries` - DELETE the expense entry  
- `bank_accounts` - RESTORE the balance

**Everything else updates automatically** because they query these tables dynamically.

**No trace left** - Complete clean removal.
