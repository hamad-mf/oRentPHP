<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
$pdo = db();

$me = current_user();
$id = (int) ($me['staff_id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Your staff profile is not linked yet.');
    redirect('../index.php');
}

// Load staff + user
$stmt = $pdo->prepare(
    "SELECT s.*, u.id as user_id, u.username, u.role as user_role, u.is_active
     FROM staff s LEFT JOIN users u ON u.staff_id = s.id
     WHERE s.id = ? LIMIT 1"
);
$stmt->execute([$id]);
$staff = $stmt->fetch();
if (!$staff)
    redirect('index.php');

$userId = (int) ($staff['user_id'] ?? 0);

// --- Staff Advance Feature ---
$advanceBalance = 0.0;
$advanceHistory = [];
$hasAdvanceTable = false;
try {
    $hasAdvanceTable = (bool) $pdo->query("SHOW TABLES LIKE 'payroll_advances'")->fetchColumn();
    if ($hasAdvanceTable && $userId) {
        $advStmt = $pdo->prepare("SELECT id, amount, remaining_amount, status, note, given_at FROM payroll_advances WHERE user_id = ? ORDER BY given_at DESC LIMIT 10");
        $advStmt->execute([$userId]);
        $advanceHistory = $advStmt->fetchAll();
        $balStmt = $pdo->prepare("SELECT COALESCE(SUM(remaining_amount),0) FROM payroll_advances WHERE user_id = ? AND remaining_amount > 0 AND status IN ('pending','partially_recovered')");
        $balStmt->execute([$userId]);
        $advanceBalance = (float) $balStmt->fetchColumn();
    }
} catch (Exception $advEx) {}

// --- Staff Incentive Feature ---
$hasIncentiveTable = false;
$incentiveHistory = [];
$totalIncentives = 0.0;
try {
    $hasIncentiveTable = (bool) $pdo->query("SHOW TABLES LIKE 'staff_incentives'")->fetchColumn();
    if ($hasIncentiveTable && $userId) {
        $incStmt = $pdo->prepare("SELECT id, month, year, amount, note, created_at FROM staff_incentives WHERE user_id = ? ORDER BY year DESC, month DESC, id DESC");
        $incStmt->execute([$userId]);
        $incentiveHistory = $incStmt->fetchAll();
        $incSumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM staff_incentives WHERE user_id = ?");
        $incSumStmt->execute([$userId]);
        $totalIncentives = (float) $incSumStmt->fetchColumn();
    }
} catch (Exception $incEx) {}

// Permissions
$perms = [];
if ($userId) {
    $pStmt = $pdo->prepare("SELECT permission FROM staff_permissions WHERE user_id = ?");
    $pStmt->execute([$userId]);
    $perms = $pStmt->fetchAll(PDO::FETCH_COLUMN);
}

$allPerms = [
    'add_vehicles' => 'Add / Edit Vehicles',
    'add_reservations' => 'Add / Edit Reservations',
    'do_delivery' => 'Perform Deliveries',
    'do_return' => 'Perform Returns',
    'add_leads' => 'Add / Edit Leads',
    'manage_clients' => 'Manage Clients',
    'view_finances' => 'View Financial Data',
    'manage_staff' => 'View Staff Section',
];

// Activity logs
$logs = [];
if ($userId) {
    $lStmt = $pdo->prepare(
        "SELECT * FROM staff_activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 100"
    );
    $lStmt->execute([$userId]);
    $logs = $lStmt->fetchAll();
}

// Action icon + color map
$actionMeta = [
    'delivery' => ['icon' => '🚗', 'color' => 'text-blue-400', 'label' => 'Vehicle Delivered'],
    'return' => ['icon' => '🔄', 'color' => 'text-orange-400', 'label' => 'Vehicle Returned'],
    'created_reservation' => ['icon' => '📋', 'color' => 'text-green-400', 'label' => 'Reservation Created'],
    'created_lead' => ['icon' => '👤', 'color' => 'text-purple-400', 'label' => 'Lead Created'],
    'converted_lead' => ['icon' => '🔄', 'color' => 'text-cyan-400', 'label' => 'Lead Converted To Client'],
    'imported_leads_batch' => ['icon' => '📥', 'color' => 'text-cyan-300', 'label' => 'Leads Imported (Batch)'],
    'updated_lead' => ['icon' => '✏️', 'color' => 'text-amber-400', 'label' => 'Lead Updated'],
    'updated_lead_status' => ['icon' => '📌', 'color' => 'text-indigo-400', 'label' => 'Lead Status Updated'],
    'deleted_lead' => ['icon' => '🗑️', 'color' => 'text-red-400', 'label' => 'Lead Deleted'],
    'scheduled_followup' => ['icon' => '⏰', 'color' => 'text-sky-400', 'label' => 'Follow-up Scheduled'],
    'completed_followup' => ['icon' => '✅', 'color' => 'text-emerald-400', 'label' => 'Follow-up Completed'],
    'gps_update' => ['icon' => '📍', 'color' => 'text-blue-300', 'label' => 'GPS Updated'],
];

$pageTitle = 'My Profile';
require_once __DIR__ . '/../includes/header.php';
$s = getFlash('success');
?>

<div class="space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <span class="text-white">My Profile</span>
    </div>

    <?php if ($s): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl px-5 py-3 text-sm">
            ✓
            <?= e($s) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left: Profile -->
        <div class="lg:col-span-1 space-y-5">

            <!-- Profile Card -->
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 text-center">
                <div
                    class="w-16 h-16 rounded-2xl bg-mb-accent/10 border border-mb-accent/30 flex items-center justify-center text-2xl font-semibold text-mb-accent mx-auto mb-4">
                    <?= strtoupper(substr($staff['name'], 0, 2)) ?>
                </div>
                <h2 class="text-white text-lg font-medium">
                    <?= e($staff['name']) ?>
                </h2>
                <p class="text-mb-subtle text-sm mt-0.5">
                    <?= e($staff['role'] ?? 'Staff') ?>
                </p>

                <?php if ($staff['user_id']): ?>
                    <div class="mt-3 flex items-center justify-center gap-2">
                        <?php if ($staff['user_role'] === 'admin'): ?>
                            <span class="text-xs bg-mb-accent/10 text-mb-accent px-2.5 py-1 rounded-full">★ Admin</span>
                        <?php else: ?>
                            <span
                                class="text-xs bg-mb-surface border border-mb-subtle/20 text-mb-silver px-2.5 py-1 rounded-full">Staff
                                Account</span>
                        <?php endif; ?>
                        <?php if ($staff['is_active']): ?>
                            <span class="text-xs bg-green-500/10 text-green-400 px-2.5 py-1 rounded-full">Active</span>
                        <?php else: ?>
                            <span class="text-xs bg-red-500/10 text-red-400 px-2.5 py-1 rounded-full">Disabled</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (($_currentUser['role'] ?? '') === 'admin'): ?>
                    <a href="edit.php?id=<?= $id ?>"
                        class="mt-5 inline-block w-full bg-mb-black border border-mb-subtle/20 text-mb-silver px-4 py-2.5 rounded-xl hover:border-mb-accent/40 hover:text-white transition-colors text-sm">
                        Edit Profile
                    </a>
                <?php endif; ?>
            </div>

            <!-- Details -->
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 space-y-3">
                <h3 class="text-white text-sm font-medium mb-4 border-l-2 border-mb-accent pl-3">Details</h3>
                <?php
                $details = [
                    'Username' => $staff['username'] ? '@' . $staff['username'] : null,
                    'Phone' => $staff['phone'] ?? null,
                    'Email' => $staff['email'] ?? null,
                    'Salary' => $staff['salary'] !== null ? '$' . number_format($staff['salary'], 2) : null,
                    'Joined' => $staff['joined_date'] ? date('d M Y', strtotime($staff['joined_date'])) : null,
                ];
                foreach ($details as $label => $val):
                    if (!$val)
                        continue;
                    ?>
                    <div class="flex items-start justify-between gap-3">
                        <span class="text-mb-subtle text-xs">
                            <?= $label ?>
                        </span>
                        <span class="text-mb-silver text-xs text-right">
                            <?= e($val) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php if ($staff['notes']): ?>
                    <div class="pt-3 border-t border-mb-subtle/10">
                        <p class="text-mb-subtle text-xs mb-1">Notes</p>
                        <p class="text-mb-silver text-xs leading-relaxed">
                            <?= nl2br(e($staff['notes'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ID Proof -->
            <?php if (!empty($staff['id_proof_path'])): ?>
                <?php
                $proofUrl = '../' . ltrim($staff['id_proof_path'], '/');
                $proofExt = strtolower(pathinfo($staff['id_proof_path'], PATHINFO_EXTENSION));
                $isImage = in_array($proofExt, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                ?>
                <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
                    <h3 class="text-white text-sm font-medium mb-3 border-l-2 border-mb-accent pl-3">ID Proof / Document
                    </h3>
                    <?php if ($isImage): ?>
                        <a href="<?= e($proofUrl) ?>" target="_blank" title="View full size">
                            <img src="<?= e($proofUrl) ?>" alt="ID Proof"
                                class="w-full rounded-lg border border-mb-subtle/20 object-cover max-h-52 hover:opacity-90 transition-opacity cursor-zoom-in">
                        </a>
                        <p class="text-mb-subtle text-xs mt-2 text-center">Click image to view full size</p>
                    <?php else: ?>
                        <a href="<?= e($proofUrl) ?>" target="_blank"
                            class="flex items-center gap-3 p-3 bg-mb-black/30 border border-mb-subtle/20 rounded-lg hover:border-mb-accent/30 transition-colors">
                            <span class="text-2xl">📄</span>
                            <div>
                                <p class="text-mb-silver text-sm">View Document</p>
                                <p class="text-mb-subtle text-xs">PDF — click to open</p>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            
        <?php if ($hasAdvanceTable && $userId): ?>
            <!-- Staff Advances Card -->
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-white text-sm font-medium border-l-2 border-orange-400 pl-3">Staff Advances</h3>
                    <?php if ($advanceBalance > 0): ?>
                        <span class="text-xs bg-orange-500/10 text-orange-300 border border-orange-500/20 px-2.5 py-1 rounded-full">Due: $<?= number_format($advanceBalance, 2) ?></span>
                    <?php else: ?>
                        <span class="text-xs bg-green-500/10 text-green-400 border border-green-500/20 px-2.5 py-1 rounded-full">No Balance</span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-mb-subtle">For new advances, please contact admin/HR.</p>
                <?php if (!empty($advanceHistory)): ?>
                    <div class="mt-4 pt-4 border-t border-mb-subtle/10 space-y-2.5">
                        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Recent Advances</p>
                        <?php foreach ($advanceHistory as $adv): ?>
                            <div class="flex items-start justify-between gap-2 text-xs">
                                <div>
                                    <span class="<?= $adv['status'] === 'recovered' ? 'text-green-400' : 'text-orange-300' ?>">$<?= number_format($adv['amount'], 2) ?></span>
                                    <?php if ($adv['note']): ?><span class="text-mb-subtle ml-1">— <?= e($adv['note']) ?></span><?php endif; ?>
                                    <p class="text-mb-subtle/60 mt-0.5"><?= date('d M Y', strtotime($adv['given_at'])) ?></p>
                                </div>
                                <span class="mt-0.5 shrink-0 px-1.5 py-0.5 rounded text-[10px] <?= $adv['status'] === 'recovered' ? 'bg-green-500/10 text-green-400' : ($adv['status'] === 'partially_recovered' ? 'bg-yellow-500/10 text-yellow-400' : 'bg-orange-500/10 text-orange-300') ?>">
                                    <?= $adv['status'] === 'partially_recovered' ? 'Partial' : ucfirst($adv['status']) ?>
                                    <?php if ($adv['status'] !== 'recovered'): ?>(rem: $<?= number_format($adv['remaining_amount'], 2) ?>)<?php endif; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($hasIncentiveTable && $userId): ?>
            <!-- Staff Incentives Card -->
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-white text-sm font-medium border-l-2 border-green-400 pl-3">Staff Incentives</h3>
                    <?php if ($totalIncentives > 0): ?>
                        <span class="text-xs bg-green-500/10 text-green-400 border border-green-500/20 px-2.5 py-1 rounded-full">Total: $<?= number_format($totalIncentives, 2) ?></span>
                    <?php else: ?>
                        <span class="text-xs bg-mb-subtle/10 text-mb-subtle border border-mb-subtle/20 px-2.5 py-1 rounded-full">No Incentives</span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-mb-subtle">Incentives are managed by admin/HR.</p>
                <?php if (!empty($incentiveHistory)): ?>
                    <div class="mt-4 pt-4 border-t border-mb-subtle/10 space-y-2.5">
                        <p class="text-xs text-mb-subtle uppercase tracking-wider mb-2">Incentive History</p>
                        <?php foreach ($incentiveHistory as $inc): ?>
                            <div class="flex items-start justify-between gap-2 text-xs">
                                <div>
                                    <span class="text-green-400">$<?= number_format($inc['amount'], 2) ?></span>
                                    <?php if ($inc['note']): ?><span class="text-mb-subtle ml-1">— <?= e($inc['note']) ?></span><?php endif; ?>
                                    <p class="text-mb-subtle/60 mt-0.5"><?= date('M Y', strtotime($inc['created_at'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
<?php if ($userId): ?>
                <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
                    <h3 class="text-white text-sm font-medium mb-4 border-l-2 border-mb-accent pl-3">Permissions</h3>
                    <?php if ($staff['user_role'] === 'admin'): ?>
                        <p class="text-mb-subtle text-xs">Admin has full access to everything.</p>
                    <?php elseif (empty($perms)): ?>
                        <p class="text-mb-subtle text-xs italic">No permissions assigned.</p>
                    <?php else: ?>
                        <div class="space-y-2">
                            <?php foreach ($allPerms as $key => $label): ?>
                                <?php $has = in_array($key, $perms, true); ?>
                                <div class="flex items-center gap-2">
                                    <?php if ($has): ?>
                                        <span
                                            class="w-4 h-4 rounded-full bg-green-500/20 text-green-400 flex items-center justify-center text-[10px]">✓</span>
                                        <span class="text-mb-silver text-xs">
                                            <?= e($label) ?>
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="w-4 h-4 rounded-full bg-mb-black text-mb-subtle/40 flex items-center justify-center text-[10px]">✗</span>
                                        <span class="text-mb-subtle/50 text-xs line-through">
                                            <?= e($label) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Activity Log -->
        <div class="lg:col-span-2">
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
                <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
                    <h3 class="text-white font-light text-lg">Activity History</h3>
                    <span class="text-xs text-mb-subtle">
                        <?= count($logs) ?> actions
                    </span>
                </div>

                <?php if (empty($logs)): ?>
                    <div class="px-6 py-16 text-center">
                        <p class="text-3xl mb-3">📋</p>
                        <p class="text-mb-subtle text-sm">No activity recorded yet.</p>
                        <p class="text-mb-subtle/50 text-xs mt-1">Actions appear here as this staff member works.</p>
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-mb-subtle/10 max-h-[600px] overflow-y-auto">
                        <?php foreach ($logs as $log):
                            $meta = $actionMeta[$log['action']] ?? ['icon' => '📝', 'color' => 'text-mb-silver', 'label' => ucwords(str_replace('_', ' ', $log['action']))];
                            ?>
                            <div class="px-6 py-4 flex items-start gap-4 hover:bg-mb-black/20 transition-colors">
                                <div
                                    class="flex-shrink-0 w-9 h-9 rounded-xl bg-mb-black/40 flex items-center justify-center text-base">
                                    <?= $meta['icon'] ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-medium <?= $meta['color'] ?>">
                                            <?= $meta['label'] ?>
                                        </span>
                                        <?php if ($log['entity_type'] && $log['entity_id']): ?>
                                            <span class="text-xs text-mb-subtle/60 capitalize">
                                                <?= e($log['entity_type']) ?> #
                                                <?= (int) $log['entity_id'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($log['description']): ?>
                                        <p class="text-mb-subtle text-xs mt-0.5 leading-relaxed">
                                            <?= e($log['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="text-mb-subtle/50 text-xs mt-1">
                                        <?= date('d M Y, h:i A', strtotime($log['created_at'])) ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
