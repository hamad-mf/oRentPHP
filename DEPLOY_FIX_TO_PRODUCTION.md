# Deploy Credit Payment Button Fix to Production

**Issue**: "Add Payment" button still shows after making payment in production

**Root Cause**: The fix code is in the local files but not deployed to production server

---

## Files That Need to Be Deployed

### 1. Migration File (Run Once on Production Database)
```
migrations/releases/2026-04-03_credit_payment_allocations.sql
```

**What it does**:
- Creates `credit_payment_allocations` table
- Adds `is_legacy_payment` column to `ledger_entries`
- Sets up the tracking system

### 2. Updated PHP File (Copy to Production)
```
accounts/credit.php
```

**What changed**:
- Payment recording now creates allocation records
- Button display checks allocations
- Shows "Fully Paid" badge when paid
- JavaScript passes entry ID to modal

---

## Deployment Steps

### Step 1: Backup Production Database
```bash
# On production server
mysqldump -u username -p database_name > backup_before_credit_fix.sql
```

### Step 2: Run Migration on Production
```bash
# On production server
mysql -u username -p database_name < migrations/releases/2026-04-03_credit_payment_allocations.sql
```

**Verify it worked**:
```sql
-- Check table exists
SHOW TABLES LIKE 'credit_payment_allocations';

-- Check column exists
SHOW COLUMNS FROM ledger_entries LIKE 'is_legacy_payment';
```

### Step 3: Deploy Updated PHP File
```bash
# Copy from local to production
scp accounts/credit.php user@production-server:/path/to/oRentPHP/accounts/
```

Or use your existing deployment method (FTP, Git, etc.)

### Step 4: Test on Production
1. Go to Accounts → Credit Transactions
2. Find any credit entry with "Add Payment" button
3. Click "Add Payment"
4. Enter amount and submit
5. **Verify**: Button should disappear or show "Pay Remaining"
6. Reload page - button state should persist

---

## Quick Verification Script

Run this on production to verify deployment:

```php
<?php
require_once 'config/db.php';
$pdo = db();

// Check table
$table = $pdo->query("SHOW TABLES LIKE 'credit_payment_allocations'")->fetch();
echo $table ? "✓ Table exists\n" : "✗ Table missing\n";

// Check column
$column = $pdo->query("SHOW COLUMNS FROM ledger_entries LIKE 'is_legacy_payment'")->fetch();
echo $column ? "✓ Column exists\n" : "✗ Column missing\n";

// Check code
$code = file_get_contents('accounts/credit.php');
echo strpos($code, 'credit_payment_allocations') !== false ? "✓ Code updated\n" : "✗ Code not updated\n";
?>
```

---

## What Will Happen After Deployment

### For NEW Payments (After Deployment):
- ✅ Button disappears when fully paid
- ✅ Shows "Pay Remaining" for partial payments
- ✅ State persists across page reloads
- ✅ Works for all credit sources

### For OLD Entries (Before Deployment):
- They will still show "Add Payment" buttons
- But NEW payments on them WILL work correctly
- The tracking starts from deployment forward

---

## Rollback Plan (If Something Goes Wrong)

### Rollback Database:
```bash
mysql -u username -p database_name < backup_before_credit_fix.sql
```

### Rollback Code:
```bash
# Restore old credit.php from backup
cp accounts/credit.php.backup accounts/credit.php
```

---

## Summary

**Deploy these 2 things to production**:
1. Run migration SQL file on production database
2. Copy updated `accounts/credit.php` to production server

That's it! The "Add Payment" button will work correctly after deployment.
