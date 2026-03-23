<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/client_helpers.php';

$pdo = db();
clients_ensure_schema($pdo);
$supportsAlternativeNumber = clients_has_column($pdo, 'alternative_number');
require_once __DIR__ . '/../includes/settings_helpers.php';
$perPage = get_per_page($pdo);
$page    = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? '';

$where = ['1=1'];
$params = [];

if ($search !== '') {
    if ($supportsAlternativeNumber) {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR alternative_number LIKE ?)';
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
    } else {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
}
switch ($filter) {
    case 'blacklisted':
        $where[] = 'is_blacklisted = 1';
        break;
    case 'rated':
        $where[] = 'rating IS NOT NULL';
        break;
    case 'unrated':
        $where[] = 'rating IS NULL';
        break;
}

$baseFrom = 'FROM clients c WHERE ' . implode(' AND ', $where);
$countSql = 'SELECT COUNT(*) ' . $baseFrom;
$sql      = 'SELECT c.*, (SELECT COUNT(*) FROM reservations r WHERE r.client_id = c.id) AS reservations_count ' . $baseFrom . ' ORDER BY c.created_at DESC';
$pgResult = paginate_query($pdo, $sql, $countSql, $params, $page, $perPage);
$clients  = $pgResult['rows'];

$totalCount = $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
$blacklisted = $pdo->query('SELECT COUNT(*) FROM clients WHERE is_blacklisted = 1')->fetchColumn();
$topRated = $pdo->query('SELECT COUNT(*) FROM clients WHERE rating >= 4')->fetchColumn();

$success = getFlash('success');
$error = getFlash('error');
$pageTitle = 'Clients';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <?php if ($success): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div
            class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-4 text-center">
            <p class="text-3xl font-light text-white">
                <?= $totalCount ?>
            </p>
            <p class="text-mb-silver text-xs uppercase mt-1">Total Clients</p>
        </div>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-4 text-center">
            <p class="text-3xl font-light text-red-400">
                <?= $blacklisted ?>
            </p>
            <p class="text-mb-silver text-xs uppercase mt-1">Blacklisted</p>
        </div>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-lg p-4 text-center">
            <p class="text-3xl font-light text-yellow-400">
                <?= $topRated ?>
            </p>
            <p class="text-mb-silver text-xs uppercase mt-1">Top Rated </p>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <form method="GET" class="flex items-center gap-3 flex-1 flex-wrap">
            <div class="relative flex-1 max-w-sm">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, email or phone numbers..."
                    class="w-full bg-mb-surface border border-mb-subtle/20 rounded-full py-2 pl-10 pr-4 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent text-sm transition-colors">
                <svg class="w-4 h-4 text-mb-subtle absolute left-4 top-2.5" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <?php foreach (['' => 'All', 'blacklisted' => 'Blacklisted', 'rated' => 'Rated', 'unrated' => 'Unrated'] as $val => $lbl): ?>
                <a href="?<?= http_build_query(array_filter(['filter' => $val, 'search' => $search])) ?>"
                    class="text-xs px-3 py-1 rounded-full border transition-colors <?= $filter === $val ? 'border-mb-accent text-mb-accent' : 'border-mb-subtle/20 text-mb-subtle hover:text-white' ?>">
                    <?= $lbl ?>
                </a>
            <?php endforeach; ?>
            <?php if ($search || $filter): ?><a href="index.php"
                    class="text-mb-subtle hover:text-white text-sm transition-colors">Clear</a>
            <?php endif; ?>
        </form>
        <?php if (auth_has_perm('manage_clients')): ?>
            <a href="create.php"
                class="bg-mb-accent text-white px-6 py-2 rounded-full hover:bg-mb-accent/80 transition-colors flex items-center gap-2 text-sm font-medium flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                </svg>
                Add Client
            </a>
        <?php endif; ?>
    </div>

    <!-- Table -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <?php if (empty($clients)): ?>
            <div class="py-20 text-center">
                <svg class="w-16 h-16 text-mb-subtle/20 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <p class="text-mb-subtle text-lg">No clients found.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-mb-subtle/10 bg-mb-black/30">
                        <tr class="text-mb-subtle text-xs uppercase">
                            <th class="px-6 py-4 text-left">Client</th>
                            <th class="px-6 py-4 text-left">Contact</th>
                            <th class="px-6 py-4 text-center">Rating</th>
                            <th class="px-6 py-4 text-center">Rentals</th>
                            <th class="px-6 py-4 text-left">Status</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($clients as $c): ?>
                            <tr class="hover:bg-mb-black/30 transition-colors <?= $c['is_blacklisted'] ? 'opacity-70' : '' ?>">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-9 h-9 rounded-full bg-mb-black flex items-center justify-center text-sm font-medium text-mb-accent border border-mb-subtle/20 flex-shrink-0">
                                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <a href="show.php?id=<?= $c['id'] ?>"
                                            class="text-white hover:text-mb-accent transition-colors font-light">
                                            <?= e($c['name']) ?>
                                        </a>
                                        <?php if ($c['is_blacklisted']): ?>
                                            <span
                                                class="ml-2 text-xs bg-red-500/20 text-red-400 border border-red-500/30 rounded-full px-2 py-0.5">Blacklisted</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-mb-silver">
                                    <?= e($c['email']) ?>
                                </p>
                                <p class="text-mb-subtle text-xs">
                                    <?= e($c['phone']) ?>
                                </p>
                                <?php if ($supportsAlternativeNumber && !empty($c['alternative_number'])): ?>
                                    <p class="text-mb-subtle text-xs">
                                        Alt:
                                        <?= e($c['alternative_number']) ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-center text-yellow-400">
                                <?= $c['rating'] ? starDisplay($c['rating']) : '<span class="text-mb-subtle text-xs"> ”</span>' ?>
                            </td>
                            <td class="px-6 py-4 text-center text-mb-silver">
                                <?= $c['reservations_count'] ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($c['is_blacklisted']): ?>
                                    <span class="text-xs text-red-400"> š  Blacklisted</span>
                                <?php else: ?>
                                    <span class="text-xs text-green-400">Active</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="show.php?id=<?= $c['id'] ?>"
                                        class="text-mb-subtle hover:text-white transition-colors p-1.5 rounded hover:bg-white/5"
                                        title="View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <?php if (auth_has_perm('manage_clients')): ?>
                                        <a href="edit.php?id=<?= $c['id'] ?>"
                                            class="text-mb-subtle hover:text-white transition-colors p-1.5 rounded hover:bg-white/5"
                                            title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <a href="delete.php?id=<?= $c['id'] ?>"
                                            onclick="return confirm('Remove <?= e($c['name']) ?>?')"
                                            class="text-mb-subtle hover:text-red-400 transition-colors p-1.5 rounded hover:bg-red-500/5"
                                            title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>


<?php
$_qp=array_filter(['search'=>$search,'filter'=>$filter],fn($v)=>$v!=='');echo render_pagination($pgResult,$_qp);
?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
