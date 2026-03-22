# DETAILED PROMPT: Security Deposit - Deduct, Hold & Real Income Feature

## WHAT YOU NEED TO BUILD

Enhance the security deposit handling at return to support:
1. **Deduct from deposit** - Allow deducting damage/additional costs from the security deposit
2. **Real income conversion** - Deducted amount becomes actual income (not just excluded)
3. **Hold deposit option** - Option to keep/hold deposit after return
4. **Visual tracking** - Highlight reservations where deposit is not fully returned

---

## CURRENT FLOW (DO NOT BREAK)

```
DELIVERY:
- Client pays deposit (e.g., $5000)
- Stored as: reservation.deposit_amount
- Ledger: security_deposit_in (excluded from KPI)

RETURN:
- Full deposit returned to client
- Stored as: reservation.deposit_returned
- Ledger: security_deposit_out (excluded from KPI)
```

---

## NEW FLOW

```
RETURN SCENARIO EXAMPLES:

Scenario 1: Full Return
- Deposit: $5000, No deductions, No damages
- Returned: $5000
- Real Income: $0

Scenario 2: Deduct Damages
- Deposit: $5000, Damages: $2000
- Returned: $3000
- Real Income: $2000 (deducted damages)

Scenario 3: Full Hold
- Deposit: $5000, Hold entire deposit
- Returned: $0
- Real Income: $0
- Status: DEPOSIT HELD

Scenario 4: Partial Hold + Deduct
- Deposit: $5000, Hold: $1000, Damages: $1500
- Returned: $2500
- Real Income: $1000 (held becomes income after X days - optional)

Scenario 5: Deduct + Partial Hold
- Deposit: $5000, Deduct: $2000, Hold: $500
- Returned: $2500
- Real Income: $2000 (deducted amount)
- Status: DEPOSIT PARTIALLY HELD ($500)
```

---

## FILES TO EDIT

| File | Action | Purpose |
|------|--------|---------|
| `reservations/return.php` | Modify | Main return form & processing |
| `reservations/show.php` | Modify | Show deposit status |
| `reservations/bill.php` | Modify | Show on bill/receipt |
| `includes/ledger_helpers.php` | Modify | Add new ledger function |

---

## DATABASE CHANGE

### New SQL File: `migrations/releases/2026-03-23_deposit_tracking.sql`

```sql
-- Release: 2026-03-23_deposit_tracking
-- Author: AI Assistant
-- Safe: idempotent (IF NOT EXISTS)
-- Notes: Adds columns to track deposit deductions, holds, and real income conversion.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE reservations
ADD COLUMN IF NOT EXISTS deposit_deducted DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER deposit_returned,
ADD COLUMN IF NOT EXISTS deposit_held DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER deposit_deducted,
ADD COLUMN IF NOT EXISTS deposit_hold_reason TEXT DEFAULT NULL AFTER deposit_held;

SET FOREIGN_KEY_CHECKS = 1;
```

**Note**: Add this to `PRODUCTION_DB_STEPS.md` under Pending.

---

## IMPLEMENTATION: `reservations/return.php`

### 1. Add new POST variables (around line 157)

Find:
```php
$depositReturned = max(0, (float) ($_POST['deposit_returned'] ?? 0));
```

Add AFTER it:
```php
$depositDeducted = max(0, (float) ($_POST['deposit_deducted'] ?? 0));
$depositHeld = max(0, (float) ($_POST['deposit_held'] ?? 0));
$depositHoldReason = trim($_POST['deposit_hold_reason'] ?? '');
$depositConvertToIncome = isset($_POST['deposit_convert_to_income']) ? 1 : 0;
```

### 2. Add validation (around line 229)

Find where `$errors['deposit_returned']` validation is and ADD after:
```php
// Deposit validation
$maxDepositCollected = max(0, (float) ($r['deposit_amount'] ?? 0));
$maxReturnable = $maxDepositCollected - $depositDeducted - $depositHeld;
if ($depositReturned > $maxReturnable) {
    $errors['deposit_returned'] = 'Deposit returned cannot exceed $' . number_format($maxReturnable, 2) . ' (Deposit - Deducted - Held).';
}
if ($depositDeducted < 0) $depositDeducted = 0;
if ($depositHeld < 0) $depositHeld = 0;
if ($depositHeld > 0 && $depositHoldReason === '') {
    $errors['deposit_hold_reason'] = 'Please provide a reason for holding the deposit.';
}
```

### 3. Update database save (around line 325)

Find the UPDATE query and ADD the new columns:
```php
$upd = $pdo->prepare("UPDATE reservations SET
    ...existing columns...,
    deposit_returned=?,
    deposit_deducted=?,
    deposit_held=?,
    deposit_hold_reason=?,
    WHERE id=?");
$upd->execute([...existing values..., $depositReturned, $depositDeducted, $depositHeld, $depositHoldReason ?: null, $id]);
```

### 4. Update ledger posting (around line 423)

Find the deposit return section and REPLACE with:
```php
// ── Security Deposit Handling ────────────────────────────────────

// 1. Post deducted amount as REAL INCOME (damage/deductions)
if ($depositDeducted > 0) {
    $depositBankAccountId = ledger_get_security_deposit_account_id($pdo, $id) ?? $configuredSecurityDepositBankId;
    if ($depositBankAccountId !== null) {
        // First, move money out of deposit tracking
        ledger_post(
            $pdo,
            'expense',
            'Security Deposit',
            $depositDeducted,
            'account',
            $depositBankAccountId,
            'reservation',
            $id,
            'security_deposit_deducted',
            "Reservation #$id - Deposit deducted (damage/charges)",
            $ledgerUserId,
            "reservation:security_deposit_deducted:$id"
        );
        // Then, post as real income (counts toward KPI)
        ledger_post(
            $pdo,
            'income',
            'Damage Charges',
            $depositDeducted,
            'account',
            $depositBankAccountId,
            'reservation',
            $id,
            'damage_from_deposit',
            "Reservation #$id - Damage charges from deposit",
            $ledgerUserId,
            "reservation:damage_from_deposit:$id"
        );
    }
}

// 2. Post amount being HELD (stays in liability, not returned)
if ($depositHeld > 0) {
    $depositBankAccountId = ledger_get_security_deposit_account_id($pdo, $id) ?? $configuredSecurityDepositBankId;
    if ($depositBankAccountId !== null) {
        // This is a transfer from deposit liability to held liability
        ledger_post(
            $pdo,
            'expense',
            'Security Deposit',
            $depositHeld,
            'account',
            $depositBankAccountId,
            'reservation',
            $id,
            'security_deposit_held',
            "Reservation #$id - Deposit held: " . ($depositHoldReason ?: 'No reason provided'),
            $ledgerUserId,
            "reservation:security_deposit_held:$id"
        );
    }
}

// 3. Post amount RETURNED to client
if ($depositReturned > 0) {
    $depositBankAccountId = ledger_get_security_deposit_account_id($pdo, $id) ?? $configuredSecurityDepositBankId;
    if ($depositBankAccountId !== null) {
        ledger_post_security_deposit(
            $pdo,
            $id,
            'out',
            $depositReturned,
            $depositBankAccountId,
            $ledgerUserId
        );
    } else {
        $msg .= ' | Security deposit return ledger not posted';
    }
}
```

### 5. Update flash message (around line 383)

Find and UPDATE:
```php
if ($r['deposit_amount'] > 0) {
    $depositSummary = ' | Deposit: $' . number_format((float) $r['deposit_amount'], 2);
    if ($depositDeducted > 0) $depositSummary .= ' (Deducted: -$' . number_format($depositDeducted, 2) . ')';
    if ($depositHeld > 0) $depositSummary .= ' (Held: -$' . number_format($depositHeld, 2) . ')';
    if ($depositReturned > 0) $depositSummary .= ' (Returned: $' . number_format($depositReturned, 2) . ')';
    $msg .= $depositSummary;
}
```

### 6. Update the Return Form (around line 819)

REPLACE the entire deposit section with:

```html
<?php if ((float) ($r['deposit_amount'] ?? 0) > 0):
    $depositAmount = (float) ($r['deposit_amount'] ?? 0);
    $alreadyReturned = (float) ($r['deposit_returned'] ?? 0);
    $alreadyDeducted = (float) ($r['deposit_deducted'] ?? 0);
    $alreadyHeld = (float) ($r['deposit_held'] ?? 0);
    $remainingDeposit = $depositAmount - $alreadyReturned - $alreadyDeducted - $alreadyHeld;
    $totalCharges = $overdueAmt + $kmOverageChg + $damageChg + $additionalChg + $chellanAmt + $lateChg;
    $maxDeductible = min($totalCharges, $remainingDeposit);
?>
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 space-y-4">
        <div class="flex items-center justify-between">
            <h4 class="text-white font-medium">Security Deposit</h4>
            <span class="text-mb-accent text-sm">Collected: $<?= number_format($depositAmount, 2) ?></span>
        </div>
        
        <?php if ($alreadyReturned > 0 || $alreadyDeducted > 0 || $alreadyHeld > 0): ?>
            <div class="bg-mb-black/40 rounded-lg p-3 text-xs space-y-1">
                <p class="text-mb-silver">Already processed:</p>
                <?php if ($alreadyReturned > 0): ?>
                    <p class="text-green-400">Returned: $<?= number_format($alreadyReturned, 2) ?></p>
                <?php endif; ?>
                <?php if ($alreadyDeducted > 0): ?>
                    <p class="text-red-400">Deducted: $<?= number_format($alreadyDeducted, 2) ?></p>
                <?php endif; ?>
                <?php if ($alreadyHeld > 0): ?>
                    <p class="text-yellow-400">Held: $<?= number_format($alreadyHeld, 2) ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-mb-black/30 rounded-lg p-4">
            <p class="text-mb-subtle text-xs mb-3">Remaining deposit: <span class="text-white font-medium">$<?= number_format($remainingDeposit, 2) ?></span></p>
            
            <!-- Amount to Return -->
            <div class="mb-4">
                <label class="block text-sm text-mb-silver mb-2">Amount to Return to Client</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle">$</span>
                    <input type="number" name="deposit_returned" step="0.01" min="0"
                        max="<?= $remainingDeposit ?>"
                        value="<?= e($_POST['deposit_returned'] ?? $remainingDeposit) ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <?php if (isset($errors['deposit_returned'])): ?>
                    <p class="text-red-400 text-xs mt-1"><?= e($errors['deposit_returned']) ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Amount to Deduct -->
            <div class="mb-4">
                <label class="block text-sm text-mb-silver mb-2">
                    Amount to Deduct from Deposit
                    <span class="text-mb-subtle text-xs ml-2">(Becomes real income)</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle">$</span>
                    <input type="number" name="deposit_deducted" id="depositDeducted" step="0.01" min="0"
                        max="<?= $maxDeductible ?>"
                        value="<?= e($_POST['deposit_deducted'] ?? '0') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <p class="text-mb-subtle text-xs mt-1">Total charges: $<?= number_format($totalCharges, 2) ?>. Max deductible: $<?= number_format($maxDeductible, 2) ?></p>
            </div>
            
            <!-- Amount to Hold -->
            <div class="mb-4">
                <label class="block text-sm text-mb-silver mb-2">
                    Amount to Hold
                    <span class="text-mb-subtle text-xs ml-2">(Not returned yet)</span>
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle">$</span>
                    <input type="number" name="deposit_held" id="depositHeld" step="0.01" min="0"
                        value="<?= e($_POST['deposit_held'] ?? '0') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <?php if (isset($errors['deposit_hold_reason'])): ?>
                    <p class="text-red-400 text-xs mt-1"><?= e($errors['deposit_hold_reason']) ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Hold Reason (shown when holding) -->
            <div id="holdReasonSection" class="hidden mb-4">
                <label class="block text-sm text-mb-silver mb-2">Reason for Holding Deposit</label>
                <input type="text" name="deposit_hold_reason"
                    placeholder="e.g., Pending damage assessment, Investigation ongoing"
                    value="<?= e($_POST['deposit_hold_reason'] ?? '') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
            </div>
            
            <!-- Summary -->
            <div class="bg-mb-accent/10 border border-mb-accent/30 rounded-lg p-3 mt-4">
                <div class="flex justify-between text-sm">
                    <span class="text-mb-silver">Total Deposit:</span>
                    <span class="text-white font-medium">$<?= number_format($depositAmount, 2) ?></span>
                </div>
                <div class="flex justify-between text-sm mt-1">
                    <span class="text-mb-silver">Returning:</span>
                    <span class="text-green-400">-$<span id="summaryReturn"><?= number_format($remainingDeposit, 2) ?></span></span>
                </div>
                <div class="flex justify-between text-sm mt-1">
                    <span class="text-mb-silver">Converting to Income:</span>
                    <span class="text-red-400">-$<span id="summaryDeduct">0.00</span></span>
                </div>
                <div class="flex justify-between text-sm mt-1">
                    <span class="text-mb-silver">Holding:</span>
                    <span class="text-yellow-400">-$<span id="summaryHold">0.00</span></span>
                </div>
                <div class="border-t border-mb-subtle/30 mt-2 pt-2 flex justify-between">
                    <span class="text-mb-silver">Fully Accounted:</span>
                    <span class="text-mb-accent font-medium" id="summaryTotal">$<?= number_format($depositAmount, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
```

### 7. Add JavaScript for real-time calculation (add before `</script>`)

```javascript
// Deposit calculation
function updateDepositSummary() {
    const depositTotal = <?= $depositAmount ?>;
    const returned = parseFloat(document.getElementById('depositReturned')?.value) || 0;
    const deducted = parseFloat(document.getElementById('depositDeducted')?.value) || 0;
    const held = parseFloat(document.getElementById('depositHeld')?.value) || 0;
    const remaining = depositTotal - returned - deducted - held;
    
    document.getElementById('summaryReturn').textContent = returned.toFixed(2);
    document.getElementById('summaryDeduct').textContent = deducted.toFixed(2);
    document.getElementById('summaryHold').textContent = held.toFixed(2);
    document.getElementById('summaryTotal').textContent = '$' + depositTotal.toFixed(2);
    
    // Show/hide hold reason
    const holdReasonSection = document.getElementById('holdReasonSection');
    if (held > 0) {
        holdReasonSection.classList.remove('hidden');
    } else {
        holdReasonSection.classList.add('hidden');
    }
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    ['deposit_returned', 'deposit_deducted', 'deposit_held'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updateDepositSummary);
    });
    updateDepositSummary();
});
```

---

## IMPLEMENTATION: `reservations/show.php`

### Add deposit status indicator (around line 285)

Find the deposit section and UPDATE:
```php
<?php if ((float) ($r['deposit_amount'] ?? 0) > 0): ?>
    <div class="flex items-center gap-4 p-3 bg-mb-black/30 rounded-lg">
        <div class="flex-1">
            <p class="text-mb-subtle text-xs uppercase">Security Deposit</p>
            <p class="text-white text-sm">$<?= number_format((float) $r['deposit_amount'], 2) ?></p>
        </div>
        
        <?php 
        $depositReturned = (float) ($r['deposit_returned'] ?? 0);
        $depositDeducted = (float) ($r['deposit_deducted'] ?? 0);
        $depositHeld = (float) ($r['deposit_held'] ?? 0);
        $remaining = (float) $r['deposit_amount'] - $depositReturned - $depositDeducted - $depositHeld;
        ?>
        
        <?php if ($r['status'] === 'completed'): ?>
            <?php if ($depositHeld > 0): ?>
                <div class="px-3 py-1.5 rounded-full bg-yellow-500/10 border border-yellow-500/30">
                    <span class="text-yellow-400 text-xs font-medium">HELD: $<?= number_format($depositHeld, 2) ?></span>
                </div>
                <?php if ($depositDeducted > 0): ?>
                    <div class="px-3 py-1.5 rounded-full bg-red-500/10 border border-red-500/30">
                        <span class="text-red-400 text-xs font-medium">DEDUCTED: $<?= number_format($depositDeducted, 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($depositReturned > 0): ?>
                    <div class="px-3 py-1.5 rounded-full bg-green-500/10 border border-green-500/30">
                        <span class="text-green-400 text-xs font-medium">RETURNED: $<?= number_format($depositReturned, 2) ?></span>
                    </div>
                <?php endif; ?>
            <?php elseif ($depositDeducted > 0): ?>
                <div class="px-3 py-1.5 rounded-full bg-red-500/10 border border-red-500/30">
                    <span class="text-red-400 text-xs font-medium">DEDUCTED: $<?= number_format($depositDeducted, 2) ?></span>
                </div>
                <?php if ($depositReturned > 0): ?>
                    <div class="px-3 py-1.5 rounded-full bg-green-500/10 border border-green-500/30">
                        <span class="text-green-400 text-xs font-medium">RETURNED: $<?= number_format($depositReturned, 2) ?></span>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="px-3 py-1.5 rounded-full bg-green-500/10 border border-green-500/30">
                    <span class="text-green-400 text-xs font-medium">RETURNED: $<?= number_format($depositReturned, 2) ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($depositHeld > 0 && !empty($r['deposit_hold_reason'])): ?>
                <div class="mt-2 text-xs text-mb-subtle">
                    <span class="text-yellow-400">Hold Reason:</span> <?= e($r['deposit_hold_reason']) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="px-3 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/30">
                <span class="text-blue-400 text-xs font-medium">PENDING</span>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
```

---

## IMPLEMENTATION: `reservations/bill.php`

### Add deposit breakdown to bill (find deposit section and update)

```php
<?php if ((float) ($r['deposit_amount'] ?? 0) > 0): ?>
    <div class="border-t border-gray-200 pt-4 mt-4">
        <h4 class="text-sm font-medium text-gray-600 mb-2">Security Deposit</h4>
        <div class="text-sm space-y-1">
            <div class="flex justify-between">
                <span>Deposit Collected:</span>
                <span>$<?= number_format((float) $r['deposit_amount'], 2) ?></span>
            </div>
            <?php if ((float) ($r['deposit_deducted'] ?? 0) > 0): ?>
                <div class="flex justify-between text-red-600">
                    <span>Less: Deducted (Damage/Charges):</span>
                    <span>-$<?= number_format((float) $r['deposit_deducted'], 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if ((float) ($r['deposit_held'] ?? 0) > 0): ?>
                <div class="flex justify-between text-yellow-600">
                    <span>Less: Held:</span>
                    <span>-$<?= number_format((float) $r['deposit_held'], 2) ?></span>
                </div>
                <?php if (!empty($r['deposit_hold_reason'])): ?>
                    <div class="text-xs text-gray-500 pl-4">Reason: <?= e($r['deposit_hold_reason']) ?></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ((float) ($r['deposit_returned'] ?? 0) > 0): ?>
                <div class="flex justify-between text-green-600">
                    <span>Less: Returned to Client:</span>
                    <span>-$<?= number_format((float) $r['deposit_returned'], 2) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
```

---

## DESIGN SYSTEM

### Color Coding:
- **Returned**: Green (`text-green-400`)
- **Deducted**: Red (`text-red-400`) - becomes real income
- **Held**: Yellow (`text-yellow-400`) - pending return
- **Pending**: Blue (`text-blue-400`)

### Status Badges:
```html
<!-- Fully Returned -->
<span class="px-3 py-1.5 rounded-full bg-green-500/10 border border-green-500/30 text-green-400 text-xs">RETURNED</span>

<!-- Partially Processed -->
<span class="px-3 py-1.5 rounded-full bg-red-500/10 border border-red-500/30 text-red-400 text-xs">DEDUCTED: $X</span>
<span class="px-3 py-1.5 rounded-full bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 text-xs">HELD: $X</span>

<!-- Pending (active reservation) -->
<span class="px-3 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/30 text-blue-400 text-xs">PENDING</span>
```

---

## CRITICAL RULES

1. **DO NOT break existing deposit collection at delivery**
2. **Deducted amount MUST post to ledger as real income** (counts toward KPI)
3. **Held amount stays excluded from KPI** (just transfers liability)
4. **Always validate**: Return + Deduct + Hold <= Remaining Deposit
5. **Log all actions** with `app_log()`
6. **Use `db()` for database, `e()` for escaping HTML**
7. **Never allow negative amounts**

---

## QUICK REFERENCE

| Action | What Happens |
|--------|--------------|
| Return $3000 | `security_deposit_out` → excluded from KPI |
| Deduct $1500 | `expense` + `income` → counts toward KPI |
| Hold $500 | `security_deposit_held` → excluded from KPI |

---

## TESTING CHECKLIST

- [ ] Can return full deposit
- [ ] Can deduct damage charges from deposit
- [ ] Deducted amount appears as real income in ledger
- [ ] Can hold deposit with reason
- [ ] Can do partial return + partial deduct + partial hold
- [ ] Validation prevents over-processing (can't return more than remaining)
- [ ] Summary calculation updates in real-time
- [ ] Deposit status shows correctly on reservation details
- [ ] Bill shows deposit breakdown
- [ ] Hold reason displays on details page

---

## HELPER FUNCTIONS TO ADD (ledger_helpers.php)

```php
/**
 * Post deposit deduction as real income (counts toward KPI)
 */
function ledger_post_deposit_deduction(PDO $pdo, int $reservationId, float $amount, int $bankAccountId, int $userId, string $description = ''): ?int {
    if ($amount <= 0) return null;
    
    $desc = $description ?: "Reservation #$reservationId - Deposit deducted (damage/charges)";
    
    // Move out of deposit tracking
    ledger_post($pdo, 'expense', 'Security Deposit', $amount, 'account', $bankAccountId, 
        'reservation', $reservationId, 'security_deposit_deducted', $desc, $userId,
        "reservation:security_deposit_deducted:$reservationId");
    
    // Post as real income
    return ledger_post($pdo, 'income', 'Damage Charges', $amount, 'account', $bankAccountId,
        'reservation', $reservationId, 'damage_from_deposit', $desc, $userId,
        "reservation:damage_from_deposit:$reservationId");
}

/**
 * Post deposit hold (stays excluded from KPI)
 */
function ledger_post_deposit_held(PDO $pdo, int $reservationId, float $amount, int $bankAccountId, int $userId, string $reason = ''): ?int {
    if ($amount <= 0) return null;
    
    $desc = "Reservation #$reservationId - Deposit held" . ($reason ? ": $reason" : "");
    
    return ledger_post($pdo, 'expense', 'Security Deposit', $amount, 'account', $bankAccountId,
        'reservation', $reservationId, 'security_deposit_held', $desc, $userId,
        "reservation:security_deposit_held:$reservationId");
}
```

---

## NOTES FOR AI

- The deducted amount is the KEY change - it becomes REAL income that affects KPIs
- Held amount is just a liability transfer - excluded from KPIs
- Always show clear breakdown so staff understands what happens to each portion
- Validation is critical - prevent processing more than what's available
