# DETAILED PROMPT: Add Payment Flow to Challan Feature

## WHAT YOU NEED TO BUILD

When clicking "Mark Paid" on a challan in the vehicle details page, instead of immediately marking it as paid, show a payment modal asking:
1. **Payment Method**: Cash, Bank Account, or Credit
2. **Bank Account** (only if Bank Account is selected)
3. **Payment Date** (defaults to today)

Then post the payment as an expense to the ledger.

---

## FILES YOU NEED TO EDIT

### 1. `vehicles/show.php` (Main vehicle details page)

**Current Code to Find** (around line where "Mark Paid" button is):
```html
<form method="POST" action="mark_challan_paid.php" class="inline">
    <input type="hidden" name="id" value="<?= (int)$ch['id'] ?>">
    <input type="hidden" name="vehicle_id" value="<?= (int)$id ?>">
    <button type="submit" class="text-xs text-green-400 hover:text-green-300">Mark Paid</button>
</form>
```

**Replace with**: A button that opens a modal instead.

**Add Bank Accounts Data Fetch** (add near other PHP data fetching, around line 170):
```php
// Load bank accounts for payment modal
ledger_ensure_schema($pdo);
$bankAccounts = $pdo->query("SELECT id, name, bank_name FROM bank_accounts WHERE is_active=1 ORDER BY name")->fetchAll();
```

**Add Payment Modal HTML** (add before the closing `</div>` at the end of the page, before footer includes):
```html
<!-- Challan Payment Modal -->
<div id="challanPaymentModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
    onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50">
        <div class="flex items-start justify-between mb-5">
            <div>
                <h3 class="text-white font-semibold text-lg">Pay Challan</h3>
                <p class="text-mb-subtle text-sm mt-0.5">Enter payment details to mark this challan as paid.</p>
            </div>
            <button type="button" onclick="closeChallanPaymentModal()"
                class="text-mb-subtle hover:text-white transition-colors ml-4 flex-shrink-0 p-1 rounded hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form method="POST" action="mark_challan_paid.php" id="challanPaymentForm" class="space-y-4">
            <input type="hidden" name="id" id="payment_challan_id">
            <input type="hidden" name="vehicle_id" value="<?= (int)$id ?>">

            <!-- Challan Info Display -->
            <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-xl p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-white text-sm font-medium" id="payment_challan_title">Challan Title</p>
                        <p class="text-mb-subtle text-xs mt-1">Due: <span id="payment_challan_due">-</span></p>
                    </div>
                    <p class="text-red-400 text-lg font-medium" id="payment_challan_amount">$0.00</p>
                </div>
            </div>

            <!-- Payment Method -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Payment Method</label>
                <div class="grid grid-cols-3 gap-3">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_mode" value="cash" class="peer sr-only" required>
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">
                            Cash
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_mode" value="account" class="peer sr-only">
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">
                            Bank
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="payment_mode" value="credit" class="peer sr-only">
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">
                            Credit
                        </div>
                    </label>
                </div>
            </div>

            <!-- Bank Account Dropdown (shown when Bank is selected) -->
            <div id="bankAccountWrapper" class="hidden">
                <label class="block text-sm text-mb-silver mb-2">Select Bank Account</label>
                <select name="bank_account_id" id="bank_account_select"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <option value="">Select account...</option>
                    <?php foreach ($bankAccounts as $bank): ?>
                        <option value="<?= (int)$bank['id'] ?>">
                            <?= e($bank['name']) ?><?= $bank['bank_name'] ? ' (' . e($bank['bank_name']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Payment Date -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Payment Date</label>
                <input type="date" name="payment_date" id="payment_date_input"
                    value="<?= date('Y-m-d') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="closeChallanPaymentModal()"
                    class="px-5 py-2.5 rounded-full border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-white/30 transition-colors text-sm font-medium">
                    Cancel
                </button>
                <button type="submit"
                    class="px-6 py-2.5 rounded-full bg-green-500 text-white hover:bg-green-600 transition-colors text-sm font-medium">
                    Confirm Payment
                </button>
            </div>
        </form>
    </div>
</div>
```

**Add JavaScript Functions** (add in the `<script>` section at the bottom of show.php):
```javascript
// Challan Payment Modal Functions
function openChallanPaymentModal(challanId, title, amount, dueDate) {
    document.getElementById('payment_challan_id').value = challanId;
    document.getElementById('payment_challan_title').textContent = title;
    document.getElementById('payment_challan_amount').textContent = '$' + parseFloat(amount).toFixed(2);
    document.getElementById('payment_challan_due').textContent = dueDate ? dueDate : 'No due date';
    document.getElementById('challanPaymentModal').classList.remove('hidden');
    
    // Reset form
    document.getElementById('challanPaymentForm').reset();
    document.getElementById('payment_challan_id').value = challanId;
    document.getElementById('payment_date_input').value = new Date().toISOString().split('T')[0];
    document.getElementById('bankAccountWrapper').classList.add('hidden');
}

function closeChallanPaymentModal() {
    document.getElementById('challanPaymentModal').classList.add('hidden');
}

// Handle payment mode change
document.addEventListener('DOMContentLoaded', function() {
    const paymentModeRadios = document.querySelectorAll('input[name="payment_mode"]');
    const bankWrapper = document.getElementById('bankAccountWrapper');
    const bankSelect = document.getElementById('bank_account_select');
    
    paymentModeRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'account') {
                bankWrapper.classList.remove('hidden');
                bankSelect.required = true;
            } else {
                bankWrapper.classList.add('hidden');
                bankSelect.required = false;
                bankSelect.value = '';
            }
        });
    });
});
```

**Replace the "Mark Paid" button** in the challans table with:
```html
<button type="button" onclick="openChallanPaymentModal(<?= (int)$ch['id'] ?>, '<?= e(addslashes($ch['title'])) ?>', <?= (float)$ch['amount'] ?>, '<?= $ch['due_date'] ? date('d M Y', strtotime($ch['due_date'])) : '' ?>')"
    class="text-xs text-green-400 hover:text-green-300">Mark Paid</button>
```

---

### 2. `vehicles/mark_challan_paid.php` (Update this file)

**Replace the entire file with:**

```php
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$pdo = db();
ledger_ensure_schema($pdo);

$challanId     = (int)   ($_POST['id'] ?? 0);
$vehicleId     = (int)   ($_POST['vehicle_id'] ?? 0);
$paymentMode   = trim($_POST['payment_mode'] ?? '');
$bankAccountId = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
$paymentDate   = trim($_POST['payment_date'] ?? '');
$currentUser   = current_user();

if ($challanId <= 0 || $vehicleId <= 0) {
    flash('error', 'Invalid challan request.');
    redirect('index.php');
}

if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to pay challans.');
    redirect("show.php?id=$vehicleId");
}

// Validate payment mode
$validModes = ['cash', 'credit', 'account'];
if (!in_array($paymentMode, $validModes, true)) {
    flash('error', 'Invalid payment mode.');
    redirect("show.php?id=$vehicleId");
}

// Resolve bank account for 'account' mode
$resolvedBankId = null;
if ($paymentMode === 'account') {
    if ($bankAccountId && $bankAccountId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$bankAccountId]);
        if ($stmt->fetch()) {
            $resolvedBankId = $bankAccountId;
        }
    }
    if (!$resolvedBankId) {
        $resolvedBankId = ledger_resolve_bank_account_id($pdo, 'account', null);
    }
    if (!$resolvedBankId) {
        flash('error', 'No active bank account found. Please add a bank account first.');
        redirect("show.php?id=$vehicleId");
    }
}

// Parse payment date
$postedAt = null;
if ($paymentDate !== '') {
    $dateCheck = DateTime::createFromFormat('Y-m-d', $paymentDate);
    if ($dateCheck && $dateCheck->format('Y-m-d') === $paymentDate) {
        $postedAt = $paymentDate . ' ' . (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('H:i:s');
    } else {
        $postedAt = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    }
} else {
    $postedAt = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
}

try {
    // Fetch challan details
    $stmt = $pdo->prepare('SELECT * FROM vehicle_challans WHERE id = ? AND vehicle_id = ?');
    $stmt->execute([$challanId, $vehicleId]);
    $challan = $stmt->fetch();
    
    if (!$challan) {
        flash('error', 'Challan not found.');
        redirect("show.php?id=$vehicleId");
    }

    // Update challan status to paid
    $updateStmt = $pdo->prepare('UPDATE vehicle_challans SET status = "paid" WHERE id = ? AND vehicle_id = ?');
    $updateStmt->execute([$challanId, $vehicleId]);

    // Get vehicle info for description
    $vStmt = $pdo->prepare('SELECT brand, model FROM vehicles WHERE id = ?');
    $vStmt->execute([$vehicleId]);
    $vehicle = $vStmt->fetch();
    $vehicleName = $vehicle ? ($vehicle['brand'] . ' ' . $vehicle['model']) : 'Vehicle #' . $vehicleId;

    // Build ledger description
    $ledgerDesc = 'Challan Paid - ' . $vehicleName . ' - ' . $challan['title'];
    if ($challan['due_date']) {
        $ledgerDesc .= ' (Due: ' . date('d M Y', strtotime($challan['due_date'])) . ')';
    }

    // Post to ledger as expense
    ledger_post(
        $pdo,
        'expense',
        'Traffic Challan',
        (float) $challan['amount'],
        $paymentMode,
        $resolvedBankId,
        'challan_payment',
        $challanId,
        'paid',
        $ledgerDesc,
        (int) ($currentUser['id'] ?? 0),
        'challan_' . $challanId . '_paid_' . time(),
        $postedAt
    );

    app_log('ACTION', "Challan paid (ID: $challanId, Amount: {$challan['amount']}, Mode: $paymentMode) for vehicle ID: $vehicleId");
    flash('success', 'Challan marked as paid. $' . number_format($challan['amount'], 2) . ' posted to ledger as Traffic Challan expense.');

} catch (Throwable $e) {
    app_log('ERROR', 'Failed to pay challan - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'challan_id' => $challanId,
        'vehicle_id' => $vehicleId,
    ]);
    flash('error', 'Failed to process payment. Please try again.');
}

redirect("show.php?id=$vehicleId");
```

---

## DESIGN SYSTEM (IMPORTANT - DO NOT CHANGE)

### Color Classes to Use:
- Modal background: `bg-mb-surface`
- Text: `text-white`, `text-mb-silver`, `text-mb-subtle`
- Accent: `text-mb-accent`
- Success/Payment: `text-green-400`, `bg-green-500`
- Danger/Amount: `text-red-400`
- Borders: `border-mb-subtle/20`
- Radio button styling: Use the peer-checked pattern shown above

### Card/Modal Pattern:
```html
<div class="bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50">
    <!-- Content -->
</div>
```

### Form Input Pattern:
```html
<input type="text" name="field_name"
    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
```

---

## CRITICAL RULES

1. **DO NOT change any existing styling or design**
2. **DO NOT modify other files** - only edit `vehicles/show.php` and `vehicles/mark_challan_paid.php`
3. **Use `ledger_post()` function** for posting to ledger (see pattern above)
4. **Use proper parameter binding** with `$pdo->prepare()`
5. **Use `e()` function** for escaping HTML output
6. **Always use `auth_has_perm('add_vehicles')`** for permission check
7. **Use `db()` function** for database connection
8. **Log actions with `app_log()`** for debugging

---

## HELPER FUNCTIONS REFERENCE

### `ledger_post()` signature:
```php
ledger_post(
    PDO $pdo,
    string $txnType,        // 'expense' for challan payments
    string $category,       // 'Traffic Challan' for this feature
    float $amount,
    ?string $paymentMode,   // 'cash', 'account', or 'credit'
    ?int $bankAccountId,    // null for cash/credit, bank account ID for 'account'
    string $sourceType,     // 'challan_payment' for this feature
    ?int $sourceId,         // $challanId
    ?string $sourceEvent,   // 'paid'
    ?string $description,
    ?int $userId,
    ?string $idempotencyKey = null,
    ?string $postedAt = null
): ?int
```

### `ledger_ensure_schema()` - Call this once at the top of mark_challan_paid.php

---

## TESTING CHECKLIST

After implementing:
- [ ] Clicking "Mark Paid" opens the payment modal
- [ ] Modal shows correct challan info (title, amount, due date)
- [ ] Can select Cash, Bank, or Credit payment method
- [ ] Bank dropdown only appears when "Bank" is selected
- [ ] Payment date defaults to today
- [ ] Submitting creates ledger entry with category "Traffic Challan"
- [ ] Challan status updates to "paid"
- [ ] Success message shows correctly
- [ ] Error handling works (no bank account, invalid mode, etc.)
- [ ] Light mode displays correctly

---

## QUICK REFERENCE

| File | What to Change |
|------|----------------|
| `vehicles/show.php` | Add bank accounts query, payment modal HTML, JS functions, replace Mark Paid button |
| `vehicles/mark_challan_paid.php` | Replace entirely with payment processing code |

---

## NOTES FOR AI

- The category for ledger is `Traffic Challan` (separate from existing `Vehicle Expense` category)
- Payment modes follow the same pattern as `vehicles/add_expense.php`
- The ledger source_type is `challan_payment` to distinguish from other vehicle expenses
- Bank accounts are loaded from `bank_accounts` table via `ledger_ensure_schema()`
