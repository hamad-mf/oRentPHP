<?php
require_once __DIR__ . '/../config/db.php';
auth_require_admin();
$pdo = db();

$allPerms = [
    'add_vehicles'      => 'Add / Edit Vehicles',
    'add_reservations'  => 'Add / Edit Reservations',
    'do_delivery'       => 'Perform Deliveries',
    'do_return'         => 'Perform Returns',
    'add_leads'         => 'Add / Edit Leads',
    'manage_clients'    => 'Manage Clients',
    'view_finances'     => 'View Financial Data',
    'manage_staff'      => 'View Staff Section',
];

// Handle bulk permission save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = $_POST['perms'] ?? []; // ['user_id' => ['perm1','perm2',...]]
    $staffUsers = $pdo->query("SELECT id FROM users WHERE role = 'staff' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($staffUsers as $uid) {
        $uid = (int)$uid;
        $userPerms = $submitted[$uid] ?? [];

        $pdo->prepare("DELETE FROM staff_permissions WHERE user_id = ?")->execute([$uid]);
        $ip = $pdo->prepare("INSERT INTO staff_permissions (user_id, permission) VALUES (?,?)");
        foreach ($userPerms as $perm) {
            if (isset($allPerms[$perm])) {
                $ip->execute([$uid, $perm]);
            }
        }
    }

    // Reload session permissions if current user is staff
    $me = $_SESSION['user'] ?? null;
    if ($me && $me['role'] === 'staff' && in_array($me['id'], $staffUsers, false)) {
        $fresh = $pdo->prepare("SELECT permission FROM staff_permissions WHERE user_id = ?");
        $fresh->execute([$me['id']]);
        $_SESSION['user']['permissions'] = $fresh->fetchAll(PDO::FETCH_COLUMN);
    }

    app_log('ACTION', 'Updated staff permissions settings');
flash('success', 'Permissions updated successfully.');
    redirect('staff_permissions.php');
}

// Load all staff users + current permissions
$staffUsers = $pdo->query(
    "SELECT u.id, u.name, u.username, u.role, u.is_active FROM users u ORDER BY u.role ASC, u.name ASC"
)->fetchAll();

$permMap = [];
if ($staffUsers) {
    $ids = array_column($staffUsers, 'id');
    $in = implode(',', array_map('intval', $ids));
    if ($in) {
        $rows = $pdo->query("SELECT user_id, permission FROM staff_permissions WHERE user_id IN($in)")->fetchAll();
        foreach ($rows as $r) $permMap[$r['user_id']][] = $r['permission'];
    }
}

$pageTitle = 'Staff Permissions';
require_once __DIR__ . '/../includes/header.php';
$s = getFlash('success');
?>

<div class="max-w-6xl mx-auto space-y-6">
    <!-- Settings nav tabs -->
    <div class="flex gap-1 bg-mb-surface border border-mb-subtle/20 p-1 rounded-full w-fit flex-wrap">
        <a href="general.php" class="px-5 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Return Charges</a>
        <a href="damage_costs.php" class="px-5 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Damage Costs</a>
        <a href="lead_sources.php" class="px-5 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Lead Sources</a>
        <a href="expense_categories.php" class="px-5 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Expense Categories</a>
        <a href="staff_permissions.php" class="px-5 py-2 rounded-full text-sm font-medium transition-all bg-mb-accent text-white shadow-lg shadow-mb-accent/20">Staff Permissions</a>
        <a href="notifications.php" class="px-5 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Notifications</a>
    </div>

    <?php if ($s): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl px-5 py-3 text-sm">✓ <?= e($s) ?></div>
    <?php endif; ?>

    <div>
        <h2 class="text-white text-xl font-light">Staff Permissions</h2>
        <p class="text-mb-subtle text-sm mt-1">Manage what each staff account can access. Admin accounts always have full access.</p>
    </div>

    <?php if (empty($staffUsers)): ?>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl px-6 py-16 text-center">
            <p class="text-3xl mb-3">👥</p>
            <p class="text-mb-subtle text-sm">No staff accounts yet.</p>
            <a href="../staff/create.php" class="mt-4 inline-block text-mb-accent hover:underline text-sm">Add your first staff member</a>
        </div>
    <?php else: ?>
        <form method="POST">
            <div class="space-y-4">
                <?php foreach ($staffUsers as $u): ?>
                    <?php $uid = (int)$u['id']; ?>
                    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-9 h-9 rounded-xl bg-mb-accent/10 border border-mb-accent/20 flex items-center justify-center text-sm font-semibold text-mb-accent">
                                <?= strtoupper(substr($u['name'], 0, 2)) ?>
                            </div>
                            <div>
                                <p class="text-white text-sm font-medium"><?= e($u['name']) ?></p>
                                <p class="text-mb-subtle text-xs">@<?= e($u['username']) ?>
                                    <?php if ($u['role'] === 'admin'): ?>
                                        · <span class="text-mb-accent">Admin — full access</span>
                                    <?php elseif (!$u['is_active']): ?>
                                        · <span class="text-red-400">Disabled</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <?php if ($u['role'] === 'admin'): ?>
                            <p class="text-mb-subtle/50 text-xs italic pl-1">Admin accounts have all permissions by default.</p>
                        <?php else: ?>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <?php foreach ($allPerms as $key => $label):
                                    $checked = in_array($key, $permMap[$uid] ?? [], true);
                                ?>
                                    <label class="flex items-center gap-2 p-2.5 bg-mb-black/40 border border-mb-subtle/10 rounded-lg hover:border-mb-accent/30 cursor-pointer transition-colors group <?= !$u['is_active'] ? 'opacity-50' : '' ?>">
                                        <input type="checkbox" name="perms[<?= $uid ?>][]" value="<?= $key ?>"
                                            <?= $checked ? 'checked' : '' ?>
                                            <?= !$u['is_active'] ? 'disabled' : '' ?>
                                            class="w-3.5 h-3.5 rounded accent-mb-accent">
                                        <span class="text-xs text-mb-silver group-hover:text-white transition-colors leading-snug"><?= e($label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex items-center justify-end gap-4 mt-6">
                <button type="submit"
                    class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                    Save Permissions
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
