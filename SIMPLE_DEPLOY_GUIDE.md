# Simple Deployment Guide - Fix "Add Payment" Button

**Problem**: "Add Payment" button still shows after making payment

**Solution**: Deploy 2 files to production

---

## What You're Deploying

### 1. Simple Migration (No Legacy Stuff)
```
migrations/releases/2026-04-03_credit_payment_allocations_SIMPLE.sql
```
- Creates `credit_payment_allocations` table
- That's it! No legacy payment logic, no FIFO allocation, no yellow notice

### 2. Updated PHP File
```
accounts/credit.php
```
- Payment recording creates allocation records
- Button checks allocations to show/hide
- Shows "Fully Paid" badge when paid
- NO legacy payment notice

---

## Deployment Steps

### Option 1: Using Your Sync Script (Recommended)

```powershell
# From oRentPHP directory
cd "D:\WORK\oRentPHP"

# Sync files to SERVER UPDATE folder
powershell -ExecutionPolicy Bypass -File ".\SERVER UPDATE\sync_to_server_update.ps1"

# Deploy to production (pushes to GitHub, Hostinger auto-deploys)
powershell -ExecutionPolicy Bypass -File ".\SERVER UPDATE\sync_to_server_update.ps1" -Deploy -Message "fix credit payment button"
```

### Option 2: Manual Deployment

1. **Upload migration file to production**:
   - Copy `migrations/releases/2026-04-03_credit_payment_allocations_SIMPLE.sql` to production server

2. **Run migration on production database**:
   ```bash
   mysql -u username -p database_name < migrations/releases/2026-04-03_credit_payment_allocations_SIMPLE.sql
   ```

3. **Upload updated PHP file**:
   - Copy `accounts/credit.php` to production server

---

## After Deployment - Test It

1. Go to **Accounts → Credit Transactions**
2. Find any credit entry with "Add Payment" button
3. Click "Add Payment"
4. Enter amount and submit
5. **Result**: Button should disappear or show "Pay Remaining"
6. Reload page - button state should persist ✓

---

## What Will Happen

### For ALL Payments (After Deployment):
- ✅ Button disappears when fully paid
- ✅ Shows "Pay Remaining ($X)" for partial payments
- ✅ Shows "Fully Paid" green badge
- ✅ State persists across page reloads
- ✅ NO yellow legacy payment notice

### For Old Entries (Before Deployment):
- They still show "Add Payment" buttons (normal)
- When you pay them NOW, the button will work correctly
- No legacy payment tracking - we're starting fresh from deployment

---

## Summary

**Simple approach**:
- No legacy payment logic
- No FIFO allocation
- No yellow notice
- Just makes the button work correctly from now on

**Deploy**:
1. Run SIMPLE migration SQL
2. Upload updated credit.php
3. Done!

The "Add Payment" button will work perfectly after deployment.
