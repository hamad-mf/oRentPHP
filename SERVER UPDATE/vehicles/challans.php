<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';

$canView = ($_SESSION['user']['role'] ?? '') === 'admin' ||
           auth_has_perm('add_vehicles') ||
           auth_has_perm('view_all_vehicles') ||
           auth_has_perm('view_vehicle_availability');
if (!$canView) {
    flash('error', 'You do not have permission to view challans.');
    redirect('index.php');
}

$pdo = db();
ledger_ensure_schema($pdo);

$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

// Load bank accounts for payment modal
$bankAccounts = $pdo->query("SELECT id, name, bank_name FROM bank_accounts WHERE is_active=1 ORDER BY name")->fetchAll();

// Get filter
$filter = $_GET['filter'] ?? 'active';

$whereClause = '';
if ($filter === 'active') {
    $whereClause = "WHERE vc.status = 'pending'";
} elseif ($filter === 'paid') {
    $whereClause = "WHERE vc.status = 'paid'";
}

$query = "
    SELECT
        vc.*,
        v.brand  AS vehicle_brand,
        v.model  AS vehicle_model,
        v.license_plate,
        c.name   AS client_name,
        c.phone  AS client_phone
    FROM vehicle_challans vc
    LEFT JOIN vehicles v ON vc.vehicle_id = v.id
    LEFT JOIN clients  c ON vc.client_id  = c.id
    $whereClause
    ORDER BY
        CASE WHEN vc.status = 'pending' THEN 0 ELSE 1 END,
        vc.due_date ASC,
        vc.created_at DESC
";

$challans = $pdo->query($query)->fetchAll();

// Summaries — always computed over ALL challans, not just the filtered set
$allStmt = $pdo->query("
    SELECT vc.status, vc.amount, vc.due_date
    FROM vehicle_challans vc
");
$allRows = $allStmt->fetchAll();
$today = date('Y-m-d');
$totalPending = 0; $totalOverdue = 0; $totalPaid = 0;
$pendingCount = 0; $overdueCount = 0; $paidCount = 0;
foreach ($allRows as $row) {
    if ($row['status'] === 'paid') {
        $totalPaid += $row['amount'];
        $paidCount++;
    } else {
        $totalPending += $row['amount'];
        $pendingCount++;
        if ($row['due_date'] && $row['due_date'] < $today) {
            $totalOverdue += $row['amount'];
            $overdueCount++;
        }
    }
}

$success = getFlash('success');
$error   = getFlash('error');

$pageTitle = 'Vehicle Challans';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">

    <?php if ($success): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        <span class="text-white">Challans</span>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h2 class="text-white text-2xl font-light">Vehicle Challans</h2>
        <?php if ($isAdmin): ?>
            <a href="create_challan.php"
                class="bg-mb-accent text-white px-5 py-2.5 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium inline-flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
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
                    <svg class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
            <p class="text-mb-subtle text-xs mt-3"><?= $pendingCount ?> pending challan<?= $pendingCount !== 1 ? 's' : '' ?></p>
        </div>

        <div class="bg-mb-surface border <?= $overdueCount > 0 ? 'border-red-500/30' : 'border-mb-subtle/20' ?> rounded-xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="<?= $overdueCount > 0 ? 'text-red-200' : 'text-mb-subtle' ?> text-xs uppercase tracking-wider">Overdue Amount</p>
                    <p class="text-red-400 text-2xl font-light mt-1">$<?= number_format($totalOverdue, 2) ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-red-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
            </div>
            <p class="<?= $overdueCount > 0 ? 'text-red-200' : 'text-mb-subtle' ?> text-xs mt-3"><?= $overdueCount ?> overdue challan<?= $overdueCount !== 1 ? 's' : '' ?></p>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-mb-subtle text-xs uppercase tracking-wider">Paid Amount</p>
                    <p class="text-green-400 text-2xl font-light mt-1">$<?= number_format($totalPaid, 2) ?></p>
                </div>
                <div class="w-12 h-12 rounded-full bg-green-500/10 flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
            <p class="text-mb-subtle text-xs mt-3"><?= $paidCount ?> paid challan<?= $paidCount !== 1 ? 's' : '' ?></p>
        </div>
    </div>

    <!-- Tabs + Table -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <!-- Filter Tabs -->
        <div class="border-b border-mb-subtle/10">
            <div class="flex">
                <?php
                $tabs = ['active' => 'Active / Unpaid', 'paid' => 'Paid', 'all' => 'All'];
                foreach ($tabs as $key => $label):
                    $active = $filter === $key;
                ?>
                    <a href="?filter=<?= $key ?>"
                        class="px-6 py-3.5 text-sm font-medium transition-colors border-b-2 whitespace-nowrap
                               <?= $active ? 'text-white border-mb-accent' : 'text-mb-subtle border-transparent hover:text-white' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
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
                        <a href="create_challan.php" class="text-mb-accent text-sm hover:underline mt-2 inline-block">Add your first challan</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table class="w-full text-sm">
                    <thead class="bg-mb-black/50">
                        <tr class="text-mb-subtle text-xs uppercase">
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
                            $rowClass  = $isOverdue ? 'bg-red-500/5 hover:bg-red-500/10' : 'hover:bg-mb-black/30';
                            $dueFmt    = $ch['due_date'] ? date('d M Y', strtotime($ch['due_date'])) : '';
                        ?>
                            <tr class="<?= $rowClass ?> transition-colors">
                                <!-- Challan title + notes -->
                                <td class="px-6 py-4">
                                    <p class="text-white font-medium"><?= e($ch['title']) ?></p>
                                    <?php if (!empty($ch['notes'])): ?>
                                        <p class="text-mb-subtle text-xs mt-0.5 truncate max-w-xs"><?= e($ch['notes']) ?></p>
                                    <?php endif; ?>
                                </td>

                                <!-- Vehicle -->
                                <td class="px-6 py-4">
                                    <a href="show.php?id=<?= (int)$ch['vehicle_id'] ?>" class="group">
                                        <p class="text-white group-hover:text-mb-accent transition-colors"><?= e($ch['vehicle_brand']) ?> <?= e($ch['vehicle_model']) ?></p>
                                        <p class="text-mb-accent text-xs"><?= e($ch['license_plate']) ?></p>
                                    </a>
                                </td>

                                <!-- Client -->
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

                                <!-- Amount -->
                                <td class="px-6 py-4 text-right">
                                    <span class="text-red-400 font-medium">$<?= number_format($ch['amount'], 2) ?></span>
                                </td>

                                <!-- Due Date -->
                                <td class="px-6 py-4">
                                    <?php if ($ch['due_date']): ?>
                                        <span class="<?= $isOverdue ? 'text-red-400 font-medium' : 'text-mb-silver' ?>"><?= e($dueFmt) ?></span>
                                    <?php else: ?>
                                        <span class="text-mb-subtle text-xs italic">No due date</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td class="px-6 py-4">
                                    <?php if ($ch['status'] === 'paid'): ?>
                                        <?php if (($ch['paid_by'] ?? '') === 'customer'): ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/30">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                                Customer Paid
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-green-500/10 text-green-400 border border-green-500/30">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                Paid
                                            </span>
                                        <?php endif; ?>
                                    <?php elseif ($isOverdue): ?>
                                        <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-red-500/10 text-red-400 border border-red-500/30 animate-pulse">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Overdue
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full bg-yellow-500/10 text-yellow-400 border border-yellow-500/30">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <?php if ($ch['status'] === 'pending'): ?>
                                            <button type="button"
                                                onclick="openPayModal(<?= (int)$ch['id'] ?>, '<?= e(addslashes($ch['title'])) ?>', <?= (float)$ch['amount'] ?>, '<?= e($dueFmt) ?>')"
                                                class="text-green-400 hover:text-green-300 text-xs">Pay</button>
                                        <?php endif; ?>
                                        <?php if ($isAdmin): ?>
                                            <a href="edit_challan.php?id=<?= (int)$ch['id'] ?>" class="text-mb-accent hover:text-white text-xs">Edit</a>
                                            <form method="POST" action="delete_challan.php" class="inline" onsubmit="return confirm('Delete this challan?');">
                                                <input type="hidden" name="id" value="<?= (int)$ch['id'] ?>">
                                                <input type="hidden" name="vehicle_id" value="<?= (int)$ch['vehicle_id'] ?>">
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
    onclick="if(event.target===this)closePayModal()">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
    <div class="relative bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-md shadow-2xl shadow-black/50">
        <div class="flex items-start justify-between mb-5">
            <div>
                <h3 class="text-white font-semibold text-lg">Pay Challan</h3>
                <p class="text-mb-subtle text-sm mt-0.5">Select how this challan was settled.</p>
            </div>
            <button type="button" onclick="closePayModal()" class="text-mb-subtle hover:text-white transition-colors ml-4 flex-shrink-0 p-1 rounded hover:bg-white/5">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <form method="POST" action="mark_challan_paid.php" id="payForm" class="space-y-4">
            <input type="hidden" name="id" id="pay_challan_id">
            <input type="hidden" name="redirect_to" value="challans">

            <!-- Info card -->
            <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-xl p-4">
                <p class="text-white text-sm font-medium" id="pay_title">—</p>
                <div class="flex justify-between items-center mt-2">
                    <p class="text-mb-subtle text-xs">Due: <span id="pay_due">—</span></p>
                    <p class="text-red-400 text-lg font-medium" id="pay_amount">$0.00</p>
                </div>
            </div>

            <!-- Paid By -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Paid By</label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="relative cursor-pointer">
                        <input type="radio" name="paid_by" value="company" class="peer sr-only" required>
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">Company</div>
                    </label>
                    <label class="relative cursor-pointer">
                        <input type="radio" name="paid_by" value="customer" class="peer sr-only">
                        <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">Customer</div>
                    </label>
                </div>
            </div>

            <!-- Company Section -->
            <div id="companyPaymentSection" class="space-y-3">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Payment Method</label>
                    <div class="grid grid-cols-3 gap-3">
                        <label class="relative cursor-pointer">
                            <input type="radio" name="payment_mode" value="cash" class="peer sr-only">
                            <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">Cash</div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="payment_mode" value="account" class="peer sr-only">
                            <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">Bank</div>
                        </label>
                        <label class="relative cursor-pointer">
                            <input type="radio" name="payment_mode" value="credit" class="peer sr-only">
                            <div class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-center text-sm text-mb-silver peer-checked:border-mb-accent peer-checked:text-white peer-checked:bg-mb-accent/10 transition-all">Credit</div>
                        </label>
                    </div>
                </div>
                <div id="bankAccountWrapper" class="hidden">
                    <label class="block text-sm text-mb-silver mb-2">Bank Account</label>
                    <select name="bank_account_id" id="bank_account_select"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <option value="">Select account...</option>
                        <?php foreach ($bankAccounts as $bank): ?>
                            <option value="<?= (int)$bank['id'] ?>"><?= e($bank['name']) ?><?= !empty($bank['bank_name']) ? ' (' . e($bank['bank_name']) . ')' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Payment Date</label>
                    <input type="date" name="payment_date" id="pay_date_input" value="<?= date('Y-m-d') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>

            <!-- Customer Section -->
            <div id="customerPaymentSection" class="hidden space-y-3">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Date Paid by Customer</label>
                    <input type="date" name="customer_paid_date" value="<?= date('Y-m-d') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Notes <span class="text-mb-subtle font-normal">(optional)</span></label>
                    <input type="text" name="customer_notes" placeholder="e.g., Collected via UPI"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button" onclick="closePayModal()"
                    class="px-5 py-2.5 rounded-full border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-white/30 transition-colors text-sm font-medium">Cancel</button>
                <button type="submit"
                    class="px-6 py-2.5 rounded-full bg-green-500 text-white hover:bg-green-600 transition-colors text-sm font-medium">Confirm Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayModal(challanId, title, amount, dueDate) {
    const form = document.getElementById('payForm');
    form.reset();
    document.getElementById('pay_challan_id').value = challanId;
    document.getElementById('pay_date_input').value = new Date().toISOString().split('T')[0];
    document.getElementById('pay_title').textContent = title;
    document.getElementById('pay_amount').textContent = '$' + parseFloat(amount).toFixed(2);
    document.getElementById('pay_due').textContent = dueDate || 'No due date';
    document.getElementById('companyPaymentSection').classList.remove('hidden');
    document.getElementById('customerPaymentSection').classList.add('hidden');
    document.getElementById('bankAccountWrapper').classList.add('hidden');
    const bankSel = document.getElementById('bank_account_select');
    if (bankSel) { bankSel.required = false; bankSel.value = ''; }
    document.getElementById('payModal').classList.remove('hidden');
}
function closePayModal() {
    document.getElementById('payModal').classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', function () {
    // Paid By toggle
    document.querySelectorAll('#payForm input[name="paid_by"]').forEach(function (r) {
        r.addEventListener('change', function () {
            const isCompany = this.value === 'company';
            document.getElementById('companyPaymentSection').classList.toggle('hidden', !isCompany);
            document.getElementById('customerPaymentSection').classList.toggle('hidden', isCompany);
        });
    });
    // Bank dropdown toggle
    document.querySelectorAll('#payForm input[name="payment_mode"]').forEach(function (r) {
        r.addEventListener('change', function () {
            const isAccount = this.value === 'account';
            const wrapper = document.getElementById('bankAccountWrapper');
            const sel = document.getElementById('bank_account_select');
            wrapper.classList.toggle('hidden', !isAccount);
            if (sel) sel.required = isAccount;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
