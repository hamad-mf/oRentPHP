<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
if (!auth_has_perm('manage_staff'))
    redirect('../index.php');
$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';
$perPage = get_per_page($pdo);
$page    = max(1, (int) ($_GET['page'] ?? 1));

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

// Load all staff with user link
// Pagination
$search = trim($_GET['search'] ?? '');
$searchWhere = $search !== '' ? "WHERE s.name LIKE ? OR s.role LIKE ?" : "";
$searchParams = $search !== '' ? ["%$search%", "%$search%"] : [];
$countSql = "SELECT COUNT(*) FROM staff s LEFT JOIN users u ON u.staff_id = s.id $searchWhere";
$staffSql = "SELECT s.*, u.id as user_id, u.username, u.role as user_role, u.is_active FROM staff s LEFT JOIN users u ON u.staff_id = s.id $searchWhere ORDER BY s.name ASC";
$pgResult  = paginate_query($pdo, $staffSql, $countSql, $searchParams, $page, $perPage);
$staffList = $pgResult['rows'];

// Load permissions per user
$permsByUser = [];
if ($staffList) {
    $userIds = array_filter(array_column($staffList, 'user_id'));
    if ($userIds) {
        $in = implode(',', array_map('intval', $userIds));
        $pRows = $pdo->query("SELECT user_id, permission FROM staff_permissions WHERE user_id IN($in)")->fetchAll();
        foreach ($pRows as $r) {
            $permsByUser[$r['user_id']][] = $r['permission'];
        }
    }
}

$pageTitle = 'Staff';
require_once __DIR__ . '/../includes/header.php';
$s = getFlash('success');
?>

<div class="space-y-6">

    <?php if ($s): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl px-5 py-3 text-sm">
            âœ“ <?= e($s) ?>
        </div>
    <?php endif; ?>

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-white text-xl font-light">Staff</h2>
            <p class="text-mb-subtle text-sm mt-0.5"><?= count($staffList) ?> team
                member<?= count($staffList) !== 1 ? 's' : '' ?></p>
        </div>
        <?php if (($_currentUser['role'] ?? '') === 'admin'): ?>
            <a href="create.php"
                class="bg-mb-accent text-white px-5 py-2.5 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium shadow-lg shadow-mb-accent/20">
                + Add Staff
            </a>
        <?php endif; ?>
    </div>

    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-mb-black text-mb-silver uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-6 py-4 font-medium">Name</th>
                    <th class="px-6 py-4 font-medium">Username</th>
                    <th class="px-6 py-4 font-medium">Role / Title</th>
                    <th class="px-6 py-4 font-medium">Account</th>
                    <th class="px-6 py-4 font-medium">Status</th>
                    <th class="px-6 py-4 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-mb-subtle/10 text-sm">
                <?php if (empty($staffList)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-mb-subtle italic">
                            No staff members added yet.
                            <?php if (($_currentUser['role'] ?? '') === 'admin'): ?>
                                <a href="create.php" class="text-mb-accent hover:underline ml-1">Add one now.</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($staffList as $m): ?>
                    <tr class="hover:bg-mb-black/20 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-8 h-8 rounded-full bg-mb-accent/10 border border-mb-accent/20 flex items-center justify-center text-xs font-semibold text-mb-accent">
                                    <?= strtoupper(substr($m['name'], 0, 2)) ?>
                                </div>
                                <div>
                                    <a href="show.php?id=<?= $m['id'] ?>"
                                        class="text-white hover:text-mb-accent transition-colors font-medium">
                                        <?= e($m['name']) ?>
                                    </a>
                                    <?php if ($m['phone']): ?>
                                        <p class="text-xs text-mb-subtle"><?= e($m['phone']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-mb-silver font-mono text-xs">
                            <?= $m['username'] ? e($m['username']) : '<span class="text-mb-subtle italic">â€”</span>' ?>
                        </td>
                        <td class="px-6 py-4 text-mb-silver">
                            <?= $m['role'] ? e($m['role']) : '<span class="text-mb-subtle">â€”</span>' ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($m['user_role'] === 'admin'): ?>
                                <span
                                    class="inline-flex items-center gap-1 text-xs bg-mb-accent/10 text-mb-accent px-2 py-0.5 rounded-full">
                                    â˜… Admin
                                </span>
                            <?php elseif ($m['user_id']): ?>
                                <span
                                    class="inline-flex items-center gap-1 text-xs bg-mb-surface text-mb-silver border border-mb-subtle/20 px-2 py-0.5 rounded-full">
                                    Staff
                                </span>
                            <?php else: ?>
                                <span class="text-xs text-mb-subtle italic">â€”</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($m['user_id']): ?>
                                <?php if ($m['is_active']): ?>
                                    <span class="text-xs bg-green-500/10 text-green-400 px-2 py-0.5 rounded-full">Active</span>
                                <?php else: ?>
                                    <span class="text-xs bg-red-500/10 text-red-400 px-2 py-0.5 rounded-full">Disabled</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-xs text-mb-subtle">â€”</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="show.php?id=<?= $m['id'] ?>"
                                    class="text-xs border border-mb-subtle/20 text-mb-subtle rounded-lg px-3 py-1.5 hover:border-mb-accent/40 hover:text-white transition-colors">
                                    View
                                </a>
                                <?php if (($_currentUser['role'] ?? '') === 'admin'): ?>
                                    <a href="edit.php?id=<?= $m['id'] ?>"
                                        class="text-xs border border-mb-subtle/20 text-mb-subtle rounded-lg px-3 py-1.5 hover:border-mb-accent/40 hover:text-white transition-colors">
                                        Edit
                                    </a>
                                    <form method="POST" action="delete.php"
                                        onsubmit="return confirm('Delete <?= e(addslashes($m['name'])) ?>? This cannot be undone.')">
                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                        <button type="submit"
                                            class="text-xs border border-red-500/20 text-red-400/60 rounded-lg px-3 py-1.5 hover:border-red-500/50 hover:text-red-400 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<?php
echo render_pagination($pgResult, array_filter(['search'=>$search], fn($v)=>$v!==''));
?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
