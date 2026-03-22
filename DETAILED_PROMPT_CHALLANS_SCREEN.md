# DETAILED PROMPT: Vehicle Challans Dedicated Screen

## WHAT YOU NEED TO BUILD

A dedicated **Challans** screen accessible from the vehicle sidebar submenu that shows:
1. All challans with tabs/filters (Active/Unpaid, Paid, All)
2. Overdue highlighting
3. Vehicle details (brand, model, license plate)
4. Client details (if associated with a reservation)
5. Payment status and info

---

## FILES TO CREATE

### 1. New File: `vehicles/challans.php` (Main screen)

### 2. Database Migration: `migrations/releases/2026-03-22_vehicle_challans_add_client.sql`

---

## DATABASE CHANGE NEEDED

Add `client_id` column to `vehicle_challans` table to track which client was responsible:

```sql
-- Release: 2026-03-22_vehicle_challans_add_client
-- Author: AI Assistant
-- Safe: idempotent (IF NOT EXISTS)
-- Notes: Adds client_id to vehicle_challans for tracking which client was responsible for the challan.

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE vehicle_challans 
ADD COLUMN IF NOT EXISTS client_id INT DEFAULT NULL AFTER vehicle_id,
ADD COLUMN IF NOT EXISTS paid_by ENUM('company','customer') DEFAULT NULL AFTER status,
ADD COLUMN IF NOT EXISTS paid_date DATE DEFAULT NULL AFTER paid_by,
ADD COLUMN IF NOT EXISTS payment_mode VARCHAR(20) DEFAULT NULL AFTER paid_date;

SET FOREIGN_KEY_CHECKS = 1;
```

**Note**: Also update `PRODUCTION_DB_STEPS.md` - add this migration under Pending.

---

## IMPLEMENTATION: `vehicles/challans.php`

### Structure Overview:
```
- Header with title and Add Challan button
- Filter tabs: All | Active/Unpaid | Paid
- Summary cards: Total Pending, Total Overdue, Total Paid
- Challans table with:
  - Challan title
  - Vehicle (brand, model, plate)
  - Client name
  - Amount
  - Due Date (with overdue badge)
  - Status (Pending/Paid/Customer Paid)
  - Actions (Edit, Delete, Mark Paid)
```

### Complete Code Template:

```php
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';

$pdo = db();
ledger_ensure_schema($pdo);

$pageTitle = 'Vehicle Challans';
require_once __DIR__ . '/../includes/header.php';

$currentUser = current_user();
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

// Get filter from URL
$filter = $_GET['filter'] ?? 'active';

// Load bank accounts for payment modal
$bankAccounts = $pdo->query("SELECT id, name, bank_name FROM bank_accounts WHERE is_active=1 ORDER BY name")->fetchAll();

// Build query based on filter
$whereClause = '';
$params = [];
if ($filter === 'active') {
    $whereClause = "WHERE vc.status = 'pending'";
} elseif ($filter === 'paid') {
    $whereClause = "WHERE vc.status = 'paid'";
}
// 'all' shows everything

$query = "
    SELECT 
        vc.*,
        v.brand AS vehicle_brand,
        v.model AS vehicle_model,
        v.license_plate,
        c.name AS client_name,
        c.phone AS client_phone
    FROM vehicle_challans vc
    LEFT JOIN vehicles v ON vc.vehicle_id = v.id
    LEFT JOIN clients c ON vc.client_id = c.id
    $whereClause
    ORDER BY 
        CASE WHEN vc.status = 'pending' THEN 0 ELSE 1 END,
        vc.due_date ASC,
        vc.created_at DESC
";

$stmt = $pdo->query($query);
$challans = $stmt->fetchAll();

// Calculate summaries
$totalPending = 0;
$totalOverdue = 0;
$totalPaid = 0;
$overdueCount = 0;

$today = date('Y-m-d');
foreach ($challans as $ch) {
    if ($ch['status'] === 'paid') {
        $totalPaid += $ch['amount'];
    } else {
        $totalPending += $ch['amount'];
        if ($ch['due_date'] && $ch['due_date'] < $today) {
            $totalOverdue += $ch['amount'];
            $overdueCount++;
        }
    }
}

$success = getFlash('success');
$error = getFlash('error');
?>

<div class="space-y-6">
    <!-- Flash Messages -->
    <?php if ($success): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="../vehicles/index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Challans</span>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h2 class="text-white text-2xl font-light">Vehicle Challans</h2>
        <?php if ($isAdmin): ?>
            <a href="../vehicles/create_challan.php" 
                class="bg-mb-accent text-white px-5 py-2.5 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Challan
            </a>
        <?php endif; ?>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-mb-subtle text-xs uppercase tracking-wider">Pending Amount</p>
                    <p class="text-yellow-400 text-2xl font-light mt-1">$<?= number_format($totalPending, 2) ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-yellow-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-mb-subtle text-xs mt-3"><?= count(array_filter($challans, fn($c) => $c['status'] === 'pending')) ?> pending challans</p>
        </div>

        <div class="bg-mb-surface border border-red-500/30 rounded-xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-red-200 text-xs uppercase tracking-wider">Overdue Amount</p>
                    <p class="text-red-400 text-2xl font-light mt-1">$<?= number_format($totalOverdue, 2) ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-red-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>
            <p class="text-red-200 text-xs mt-3"><?= $overdueCount ?> overdue challans</p>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-mb-subtle text-xs uppercase tracking-wider">Paid Amount</p>
                    <p class="text-green-400 text-2xl font-light mt-1">$<?= number_format($totalPaid, 2) ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-green-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-mb-subtle text-xs mt-3"><?= count(array_filter($challans, fn($c) => $c['status'] === 'paid')) ?> paid challans</p>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="border-b border-mb-subtle/10">
            <div class="flex">
                <a href="?filter=all" 
                    class="px-6 py-3 text-sm font-medium transition-colors border-b-2 <?= $filter === 'all' ? 'text-white border-mb-accent' : 'text-mb-subtle border-transparent hover:text-white' ?>">
                    All
                </a>
                <a href="?filter=active" 
                    class="px-6 py-3 text-sm font-medium transition-colors border-b-2 <?= $filter === 'active' ? 'text-white border-mb-accent' : 'text-mb-subtle border-transparent hover:text-white' ?>">
                    Active / Unpaid
                </a>
                <a href="?filter=paid" 
                    class="px-6 py-3 text-sm font-medium transition-colors border-b-2 <?= $filter === 'paid' ? 'text-white border-mb-accent' : 'text-mb-subtle border-transparent hover:text-white' ?>">
                    Paid
                </a>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <?php if (empty($challans)): ?>
                <div class="py-16 text-center">
                    <svg class="w-16 h-16 text-mb-subtle/30 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-mb-subtle text-sm">No challans found.</p>
                    <?php if ($isAdmin): ?>
                        <a href="../vehicles/create_challan.php" class="text-mb-accent text-sm hover:underline mt-2 inline-block">Add your first challan</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table class="w-full text-sm">
                    <thead class="bg-mb-black/50 text-mb-silver text-xs uppercase">
                        <tr>
                            <th class="px-6 py-4 text-left font-medium">Challan</th>
                            <th class="px-6 py-4 text-left font-medium">Vehicle</th>
                            <th class="px-6 py-4 text-left font-medium">Client</th>
                            <th class="px-6 py-4 text-right font-medium">Amount</th>
                            <th class="px-6 py-4 text-left font-medium">Due Date</th>
                            <th class="px-6 py-4 text-left font-medium">Status</th>
                            <th class="px-6 py-4 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($challans as $ch): 
                            $isOverdue = $ch['status'] === 'pending' && $ch['due_date'] && $ch['due_date'] < $today;
                            $rowClass = $isOverdue ? 'bg-red-500/5 hover:bg-red-500/10' : 'hover:bg-mb-black/30';
                        ?>
                            <tr class="<?= $rowClass ?> transition-colors">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="text-white font-medium"><?= e($ch['title']) ?></p>
                                        <?php if ($ch['notes']): ?>
                                            <p class="text-mb-subtle text-xs mt-0.5 truncate max-w-xs"><?= e($ch['notes']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="../vehicles/show.php?id=<?= (int)$ch['vehicle_id'] ?>" class="text-white hover:text-mb-accent transition-colors">
                                        <p><?= e($ch['vehicle_brand']) ?> <?= e($ch['vehicle_model']) ?></p>
                                        <p class="text-mb-accent text-xs"><?= e($ch['license_plate']) ?></p>
                                    </a>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($ch['client_name']): ?>
                                        <p class="text-white"><?= e($ch['client_name']) ?></p>
                                        <?php if ($ch['client_phone']): ?>
                                            <p class="text-mb-subtle text-xs"><?= e($ch['client_phone']) ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-mb-subtle text-xs italic">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="text-red-400 font-medium">$<?= number_format($ch['amount'], 2) ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($ch['due_date']): ?>
                                        <span class="<?= $isOverdue ? 'text-red-400' : 'text-mb-silver' ?>">
                                            <?= date('d M Y', strtotime($ch['due_date'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-mb-subtle text-xs italic">No due date</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($ch['status'] === 'paid'): ?>
                                        <?php if ($ch['paid_by'] === 'customer'): ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/30">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                Customer Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-green-500/10 text-green-400 border border-green-500/30">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                Paid
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-red-500/10 text-red-400 border border-red-500/30 animate-pulse">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Overdue
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-yellow-500/10 text-yellow-400 border border-yellow-500/30">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Pending
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <?php if ($ch['status'] === 'pending'): ?>
                                            <button type="button" 
                                                onclick="openPayModal(<?= (int)$ch['id'] ?>, '<?= e(addslashes($ch['title'])) ?>', <?= (float)$ch['amount'] ?>, '<?= $ch['due_date'] ? date('d M Y', strtotime($ch['due_date'])) : '' ?>')"
                                                class="text-green-400 hover:text-green-300 text-xs">
                                                Pay
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?>
                                            <a href="../vehicles/edit_challan.php?id=<?= (int)$ch['id'] ?>" 
                                                class="text-mb-accent hover:text-white text-xs">
                                                Edit
                                            </a>
                                            <form method="POST" action="../vehicles/delete_challan.php" class="inline" 
                                                onsubmit="return confirm('Delete this challan?');">
                                                <input type="hidden" name="id" value="<?= (int)$ch['id'] ?>">
                                                <input type="hidden" name="redirect_to" value="challans">
                                                <button type="submit" class="text-red-400 hover:text-red-300 text-xs">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div id="payModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
    onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50">
        <div class="flex items-start justify-between mb-5">
            <div>
                <h3 class="text-white font-semibold text-lg">Pay Challan</h3>
                <p class="text-mb-subtle text-sm mt-0.5">Enter payment details</p>
            </div>
            <button type="button" onclick="closePayModal()"
                class="text-mb-subtle hover:text-white transition-colors ml-4 flex-shrink-0 p-1 rounded hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <form method="POST" action="../vehicles/mark_challan_paid.php" class="space-y-4">
            <input type="hidden" name="id" id="pay_challan_id">
            <input type="hidden" name="redirect_to" value="challans">

            <!-- Challan Info -->
            <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-xl p-4">
                <p class="text-white text-sm font-medium" id="pay_title">Challan Title</p>
                <div class="flex justify-between items-center mt-2">
                    <p class="text-mb-subtle text-xs">Due: <span id="pay_due">-</span></p>
                    <p class="text-red-400 text-lg font-medium" id="pay_amount">$0.00</p>
                </div>
            </div>

            <!-- Paid By -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Paid By</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="paid_by" value="company" class="peer sr-only" checked required>
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">
                            Company
                        </div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="paid_by" value="customer" class="peer sr-only">
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">
                            Customer
                        </div>
                    </label>
                </div>
            </div>

            <!-- Company Payment Details (shown when Company is selected) -->
            <div id="companyPaymentSection">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Payment Method</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="relative cursor-pointer">
                            <input type="radio" name="payment_mode" value="cash" class="peer sr-only" checked>
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

                <div id="bankAccountWrapper" class="hidden mt-3">
                    <label class="block text-sm text-mb-silver mb-2">Select Bank Account</label>
                    <select name="bank_account_id"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <option value="">Select account...</option>
                        <?php foreach ($bankAccounts as $bank): ?>
                            <option value="<?= (int)$bank['id'] ?>">
                                <?= e($bank['name']) ?><?= $bank['bank_name'] ? ' (' . e($bank['bank_name']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mt-3">
                    <label class="block text-sm text-mb-silver mb-2">Payment Date</label>
                    <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>

            <!-- Customer Payment Info (shown when Customer is selected) -->
            <div id="customerPaymentSection" class="hidden space-y-3">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Customer Paid Date</label>
                    <input type="date" name="customer_paid_date" value="<?= date('Y-m-d') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Notes (optional)</label>
                    <input type="text" name="customer_notes" placeholder="e.g., Collected via UPI"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="closePayModal()"
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

<script>
// Payment Modal Functions
function openPayModal(challanId, title, amount, dueDate) {
    document.getElementById('pay_challan_id').value = challanId;
    document.getElementById('pay_title').textContent = title;
    document.getElementById('pay_amount').textContent = '$' + parseFloat(amount).toFixed(2);
    document.getElementById('pay_due').textContent = dueDate || 'No due date';
    document.getElementById('payModal').classList.remove('hidden');
    
    // Reset form
    document.getElementById('payModal').querySelector('form').reset();
    document.getElementById('pay_challan_id').value = challanId;
    
    // Show company payment section by default
    document.getElementById('companyPaymentSection').classList.remove('hidden');
    document.getElementById('customerPaymentSection').classList.add('hidden');
    document.getElementById('bankAccountWrapper').classList.add('hidden');
}

function closePayModal() {
    document.getElementById('payModal').classList.add('hidden');
}

// Handle paid_by change
document.addEventListener('DOMContentLoaded', function() {
    const paidByRadios = document.querySelectorAll('input[name="paid_by"]');
    const companySection = document.getElementById('companyPaymentSection');
    const customerSection = document.getElementById('customerPaymentSection');
    const bankWrapper = document.getElementById('bankAccountWrapper');
    const paymentModeRadios = document.querySelectorAll('input[name="payment_mode"]');
    
    paidByRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'company') {
                companySection.classList.remove('hidden');
                customerSection.classList.add('hidden');
            } else {
                companySection.classList.add('hidden');
                customerSection.classList.remove('hidden');
            }
        });
    });
    
    paymentModeRadios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'account') {
                bankWrapper.classList.remove('hidden');
            } else {
                bankWrapper.classList.add('hidden');
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

---

## 2. New File: `vehicles/create_challan.php`

```php
<?php
require_once __DIR__ . '/../config/db.php';

if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to add challans.');
    redirect('index.php');
}

$pdo = db();
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $dueDate = trim($_POST['due_date'] ?? '');
    $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if ($vehicleId <= 0) $errors['vehicle_id'] = 'Vehicle is required.';
    if (!$title) $errors['title'] = 'Title is required.';
    if ($amount <= 0) $errors['amount'] = 'Amount must be greater than 0.';
    
    if (empty($errors)) {
        $dueDateValue = $dueDate !== '' ? $dueDate : null;
        
        $stmt = $pdo->prepare('INSERT INTO vehicle_challans (vehicle_id, client_id, title, amount, due_date, notes) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$vehicleId, $clientId, $title, $amount, $dueDateValue, $notes ?: null]);
        
        $challanId = $pdo->lastInsertId();
        app_log('ACTION', "Created challan ID: $challanId for vehicle ID: $vehicleId");
        flash('success', 'Challan added successfully.');
        redirect('challans.php');
    }
}

// Get vehicles for dropdown
$vehicles = $pdo->query("SELECT id, brand, model, license_plate FROM vehicles ORDER BY brand, model")->fetchAll();

// Get clients for dropdown
$clients = $pdo->query("SELECT id, name, phone FROM clients WHERE is_blacklisted = 0 ORDER BY name")->fetchAll();

$pageTitle = 'Add Challan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="../vehicles/index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <a href="challans.php" class="hover:text-white transition-colors">Challans</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Add Challan</span>
    </div>

    <h2 class="text-white text-2xl font-light">Add New Challan</h2>

    <form method="POST" class="space-y-6">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <!-- Vehicle -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Vehicle <span class="text-red-400">*</span></label>
                <select name="vehicle_id" required
                    class="w-full bg-mb-black border <?= isset($errors['vehicle_id']) ? 'border-red-500' : 'border-mb-subtle/20' ?> rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <option value="">Select vehicle...</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= ($old['vehicle_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                            <?= e($v['brand']) ?> <?= e($v['model']) ?> - <?= e($v['license_plate']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($errors['vehicle_id'] ?? ''): ?>
                    <p class="text-red-400 text-xs mt-1"><?= e($errors['vehicle_id']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Client (Optional) -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Client (Optional)</label>
                <select name="client_id"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <option value="">Select client (optional)...</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($old['client_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?> <?= $c['phone'] ? ' - ' . e($c['phone']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-mb-subtle text-xs mt-1">Assign a client if the challan is linked to a rental</p>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Challan Title <span class="text-red-400">*</span></label>
                <input type="text" name="title" required value="<?= e($old['title'] ?? '') ?>"
                    placeholder="e.g., Speed violation - Highway 44"
                    class="w-full bg-mb-black border <?= isset($errors['title']) ? 'border-red-500' : 'border-mb-subtle/20' ?> rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                <?php if ($errors['title'] ?? ''): ?>
                    <p class="text-red-400 text-xs mt-1"><?= e($errors['title']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Amount and Due Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Amount <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle">$</span>
                        <input type="number" name="amount" required step="0.01" min="0" value="<?= e($old['amount'] ?? '') ?>"
                            placeholder="0.00"
                            class="w-full bg-mb-black border <?= isset($errors['amount']) ? 'border-red-500' : 'border-mb-subtle/20' ?> rounded-lg pl-8 pr-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    </div>
                    <?php if ($errors['amount'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['amount']) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm text-mb-silver mb-2">Due Date</label>
                    <input type="date" name="due_date" value="<?= e($old['due_date'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Notes</label>
                <textarea name="notes" rows="3"
                    placeholder="Additional details about this challan..."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm resize-y"><?= e($old['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="challans.php"
                class="px-6 py-3 rounded-full border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-white/30 transition-colors text-sm font-medium">
                Cancel
            </a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium shadow-lg shadow-mb-accent/20">
                Add Challan
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

---

## 3. New File: `vehicles/edit_challan.php`

Similar to create_challan.php but:
- Loads existing challan data
- Uses UPDATE query instead of INSERT
- Pre-populates all fields
- Button says "Update Challan" instead of "Add Challan"

```php
<?php
require_once __DIR__ . '/../config/db.php';

if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to edit challans.');
    redirect('index.php');
}

$pdo = db();
$errors = [];

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Invalid challan ID.');
    redirect('challans.php');
}

// Load existing challan
$stmt = $pdo->prepare('SELECT * FROM vehicle_challans WHERE id = ?');
$stmt->execute([$id]);
$challan = $stmt->fetch();
if (!$challan) {
    flash('error', 'Challan not found.');
    redirect('challans.php');
}

$old = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $challan;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $dueDate = trim($_POST['due_date'] ?? '');
    $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    if ($vehicleId <= 0) $errors['vehicle_id'] = 'Vehicle is required.';
    if (!$title) $errors['title'] = 'Title is required.';
    if ($amount <= 0) $errors['amount'] = 'Amount must be greater than 0.';
    
    if (empty($errors)) {
        $dueDateValue = $dueDate !== '' ? $dueDate : null;
        
        $stmt = $pdo->prepare('UPDATE vehicle_challans SET vehicle_id=?, client_id=?, title=?, amount=?, due_date=?, notes=? WHERE id=?');
        $stmt->execute([$vehicleId, $clientId, $title, $amount, $dueDateValue, $notes ?: null, $id]);
        
        app_log('ACTION', "Updated challan ID: $id");
        flash('success', 'Challan updated successfully.');
        redirect('challans.php');
    }
}

// Get vehicles and clients for dropdowns
$vehicles = $pdo->query("SELECT id, brand, model, license_plate FROM vehicles ORDER BY brand, model")->fetchAll();
$clients = $pdo->query("SELECT id, name, phone FROM clients WHERE is_blacklisted = 0 ORDER BY name")->fetchAll();

$pageTitle = 'Edit Challan';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Use same form structure as create_challan.php but: -->
<!-- - Set form action to edit_challan.php -->
<!-- - Pre-populate all fields with $old values -->
<!-- - Add hidden id field -->
<!-- - Button text: "Update Challan" -->

<!-- (Full code same as create_challan.php with modifications above) -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
```

---

## 4. Update `vehicles/delete_challan.php`

Add redirect_to support:

```php
// After successful delete, check for redirect_to
$redirectTo = $_POST['redirect_to'] ?? '';
if ($redirectTo === 'challans') {
    redirect('challans.php');
}
redirect('show.php?id=' . $vehicleId);
```

---

## 5. Update `vehicles/mark_challan_paid.php`

Add support for customer paid and redirect_to:

```php
$paidBy = trim($_POST['paid_by'] ?? 'company');
$customerNotes = trim($_POST['customer_notes'] ?? '');
$customerPaidDate = trim($_POST['customer_paid_date'] ?? '');

// After validation, before updating status:
if ($paidBy === 'customer') {
    // Mark as paid by customer - no ledger entry
    $paidDateValue = $customerPaidDate !== '' ? $customerPaidDate : date('Y-m-d');
    $notesUpdate = $challan['notes'] ? $challan['notes'] . "\n" : '';
    $notesUpdate .= "Customer paid on " . date('d M Y', strtotime($paidDateValue));
    if ($customerNotes) $notesUpdate .= ": " . $customerNotes;
    
    $updateStmt = $pdo->prepare('UPDATE vehicle_challans SET status="paid", paid_by="customer", paid_date=?, notes=? WHERE id=?');
    $updateStmt->execute([$paidDateValue, $notesUpdate, $challanId]);
    
    app_log('ACTION', "Challan marked as customer paid (ID: $challanId) for vehicle ID: $vehicleId");
    flash('success', 'Challan marked as customer paid.');
} else {
    // Company paid - proceed with existing ledger logic
    // ... existing code ...
}

// Check for redirect_to
$redirectTo = $_POST['redirect_to'] ?? '';
if ($redirectTo === 'challans') {
    redirect('challans.php');
}
redirect("show.php?id=$vehicleId");
```

---

## DESIGN SYSTEM

Use the same CSS classes as rest of project:
- Cards: `bg-mb-surface border border-mb-subtle/20 rounded-xl`
- Tables: `w-full text-sm`
- Status badges: Use color-coded spans shown in code
- Overdue: `bg-red-500/5` row highlight + `animate-pulse` on badge

---

## CRITICAL RULES

1. **DO NOT change any existing files except** `delete_challan.php` and `mark_challan_paid.php`
2. **DO NOT modify other design/styling**
3. **Use `db()` for database, `e()` for escaping, `auth_has_perm()` for permissions**
4. **Log all actions with `app_log()`**

---

## QUICK REFERENCE

| File | Action |
|------|--------|
| `vehicles/challans.php` | CREATE - Main listing screen |
| `vehicles/create_challan.php` | CREATE - Add new challan form |
| `vehicles/edit_challan.php` | CREATE - Edit existing challan form |
| `vehicles/delete_challan.php` | MODIFY - Add redirect_to support |
| `vehicles/mark_challan_paid.php` | MODIFY - Add customer paid logic |
| `migrations/releases/2026-03-22_vehicle_challans_add_client.sql` | CREATE - Add client_id, paid_by, paid_date, payment_mode columns |

---

## TESTING CHECKLIST

- [ ] Challans page loads with all challans
- [ ] Filter tabs work (All/Active/Paid)
- [ ] Summary cards show correct totals
- [ ] Overdue challans highlighted in red
- [ ] Can add new challan with vehicle + optional client
- [ ] Can edit existing challan
- [ ] Can delete challan
- [ ] Company paid → ledger entry created
- [ ] Customer paid → no ledger entry, status updated
- [ ] Redirect works correctly after actions
