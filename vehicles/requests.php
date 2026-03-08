<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();
require_once __DIR__ . '/../includes/settings_helpers.php';
$perPage = get_per_page($pdo);
$page    = max(1, (int) ($_GET['page'] ?? 1));

// Auto-migrate table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT DEFAULT NULL,
        client_name_free VARCHAR(120) DEFAULT NULL,
        vehicle_brand VARCHAR(80) NOT NULL,
        vehicle_model VARCHAR(80) NOT NULL,
        people_count INT NOT NULL DEFAULT 1,
        notes TEXT DEFAULT NULL,
        status ENUM('pending','contacted','acquired','cancelled') NOT NULL DEFAULT 'pending',
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* already exists */
}

// Backward-safe migration for older installs.
try {
    $peopleColExists = (int) $pdo->query("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'vehicle_requests'
          AND COLUMN_NAME = 'people_count'
    ")->fetchColumn();
    if ($peopleColExists === 0) {
        $pdo->exec("ALTER TABLE vehicle_requests ADD COLUMN people_count INT NOT NULL DEFAULT 1 AFTER vehicle_model");
    }
} catch (Throwable $e) {
    app_log('ERROR', 'Vehicle requests: people_count migration check failed - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

}

// Fetch clients for dropdown
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();

$errors = [];
$success = '';

// Handle POST  ” add new request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $brand = trim($_POST['vehicle_brand'] ?? '');
    $model = trim($_POST['vehicle_model'] ?? '');
    $peopleCount = max(1, (int) ($_POST['people_count'] ?? 1));
    $clientId = (int) ($_POST['client_id'] ?? 0) ?: null;
    $freeName = trim($_POST['client_name_free'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$brand)
        $errors[] = 'Vehicle brand is required.';
    if (!$model)
        $errors[] = 'Vehicle model is required.';
    if (!$clientId && !$freeName)
        $errors[] = 'Please select a client or enter a client name.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("INSERT INTO vehicle_requests (client_id, client_name_free, vehicle_brand, vehicle_model, people_count, notes) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$clientId, $clientId ? null : $freeName, $brand, $model, $peopleCount, $notes]);
        $success = 'Request logged successfully.';
    }
}

// Handle POST  ” update people count
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'people_count') {
    $reqId = (int) ($_POST['req_id'] ?? 0);
    $peopleCount = max(1, (int) ($_POST['people_count'] ?? 1));
    if ($reqId) {
        $pdo->prepare("UPDATE vehicle_requests SET people_count=? WHERE id=?")->execute([$peopleCount, $reqId]);
    }
    $redirectParams = array_filter([
        'filter' => trim((string)($_POST['filter'] ?? '')),
        'page' => max(1, (int)($_POST['page'] ?? 1)),
    ], static fn($v) => $v !== '' && $v !== null && $v !== 1);
    header('Location: requests.php' . ($redirectParams ? '?' . http_build_query($redirectParams) : ''));
    exit;
}

// Handle POST  ” update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'status') {
    $reqId = (int) ($_POST['req_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($reqId && in_array($status, ['pending', 'contacted', 'acquired', 'cancelled'])) {
        $pdo->prepare("UPDATE vehicle_requests SET status=? WHERE id=?")->execute([$status, $reqId]);
    }
    $redirectParams = array_filter([
        'filter' => trim((string)($_POST['filter'] ?? '')),
        'page' => max(1, (int)($_POST['page'] ?? 1)),
    ], static fn($v) => $v !== '' && $v !== null && $v !== 1);
    header('Location: requests.php' . ($redirectParams ? '?' . http_build_query($redirectParams) : ''));
    exit;
}

// Handle POST  ” delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $reqId = (int) ($_POST['req_id'] ?? 0);
    if ($reqId)
        $pdo->prepare("DELETE FROM vehicle_requests WHERE id=?")->execute([$reqId]);
    $redirectParams = array_filter([
        'filter' => trim((string)($_POST['filter'] ?? '')),
        'page' => max(1, (int)($_POST['page'] ?? 1)),
    ], static fn($v) => $v !== '' && $v !== null && $v !== 1);
    header('Location: requests.php' . ($redirectParams ? '?' . http_build_query($redirectParams) : ''));
    exit;
}

// Fetch requests
$filter = $_GET['filter'] ?? '';
$validStatuses = ['pending', 'contacted', 'acquired', 'cancelled'];
if (!in_array($filter, $validStatuses, true)) {
    $filter = '';
}

$where = ['1=1'];
$queryParams = [];
if ($filter !== '') {
    $where[] = 'vr.status = ?';
    $queryParams[] = $filter;
}

$baseFrom = "FROM vehicle_requests vr
        LEFT JOIN clients c ON c.id = vr.client_id
        WHERE " . implode(' AND ', $where);

$sql = "SELECT vr.*, c.name AS client_db_name
        $baseFrom
        ORDER BY vr.requested_at DESC";
$_reqCountSql = "SELECT COUNT(*) $baseFrom";
$pgRequests = paginate_query($pdo, $sql, $_reqCountSql, $queryParams, $page, $perPage);
$requests = $pgRequests['rows'];
$currentPage = (int) ($pgRequests['page'] ?? 1);

// Counts per status
$counts = ['all' => 0, 'pending' => 0, 'contacted' => 0, 'acquired' => 0, 'cancelled' => 0];
$allRows = $pdo->query("SELECT status, COUNT(*) c FROM vehicle_requests GROUP BY status")->fetchAll();
foreach ($allRows as $row) {
    $counts[$row['status']] = (int) $row['c'];
    $counts['all'] += (int) $row['c'];
}

$pageTitle = 'Vehicle Requests';
require_once __DIR__ . '/../includes/header.php';

$statusColors = [
    'pending' => 'bg-yellow-500/15 text-yellow-400 border border-yellow-500/30',
    'contacted' => 'bg-blue-500/15 text-blue-400 border border-blue-500/30',
    'acquired' => 'bg-green-500/15 text-green-400 border border-green-500/30',
    'cancelled' => 'bg-red-500/15 text-red-400 border border-red-500/30',
];
$statusNext = [
    'pending' => 'contacted',
    'contacted' => 'acquired',
    'acquired' => 'cancelled',
    'cancelled' => 'pending',
];
$statusLabel = [
    'pending' => 'Pending',
    'contacted' => 'Contacted',
    'acquired' => 'Acquired',
    'cancelled' => 'Cancelled',
];
?>

<div class="max-w-6xl mx-auto px-4 py-8">

    <!-- Header -->
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-light text-white">Vehicle Requests</h1>
            <p class="text-mb-subtle text-sm mt-1">Track vehicles clients ask for that aren't in your fleet</p>
        </div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
            class="bg-mb-accent hover:bg-mb-accent/80 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Log Request
        </button>
    </div>

    <?php if ($success): ?>
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-4 py-3 mb-5 text-sm">
               …
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-5 text-sm">
            <?= implode('<br>', array_map('e', $errors)) ?>
        </div>
    <?php endif; ?>

    <!-- Status Filter Tabs -->
    <div class="flex gap-2 mb-6 flex-wrap">
        <?php
        $tabs = ['' => 'All', 'pending' => 'Pending', 'contacted' => 'Contacted', 'acquired' => 'Acquired', 'cancelled' => 'Cancelled'];
        $tabCounts = ['' => $counts['all'], 'pending' => $counts['pending'], 'contacted' => $counts['contacted'], 'acquired' => $counts['acquired'], 'cancelled' => $counts['cancelled']];
        foreach ($tabs as $val => $label):
            $active = $filter === $val;
            ?>
            <a href="requests.php<?= $val ? '?filter=' . $val : '' ?>"
                class="px-3 py-1.5 rounded-lg text-sm transition-colors <?= $active ? 'bg-mb-accent text-white' : 'bg-mb-surface border border-mb-subtle/20 text-mb-silver hover:text-white' ?>">
                <?= $label ?> <span class="opacity-60">(
                    <?= $tabCounts[$val] ?>)
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Requests Table -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <?php if (empty($requests)): ?>
            <div class="py-16 text-center">
                <div class="text-mb-subtle text-4xl mb-3">ðŸš—</div>
                <p class="text-mb-silver text-sm">No requests found
                    <?= $filter ? ' for this status' : '' ?>.
                </p>
                <p class="text-mb-subtle text-xs mt-1">Click "Log Request" to add one.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-mb-subtle/20">
                            <th class="text-left px-5 py-3 text-xs uppercase tracking-wider text-mb-subtle">Date</th>
                            <th class="text-left px-5 py-3 text-xs uppercase tracking-wider text-mb-subtle">Client</th>
                            <th class="text-left px-5 py-3 text-xs uppercase tracking-wider text-mb-subtle">Vehicle Wanted</th>
                            <th class="text-left px-5 py-3 text-xs uppercase tracking-wider text-mb-subtle">People</th>
                            <th class="text-left px-5 py-3 text-xs uppercase tracking-wider text-mb-subtle">Notes</th>
                            <th class="text-left px-5 py-3 text-xs uppercase tracking-wider text-mb-subtle">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($requests as $req): ?>
                            <tr class="hover:bg-mb-black/20 transition-colors">
                            <td class="px-5 py-4 text-sm text-mb-subtle whitespace-nowrap">
                                <?= date('d M Y', strtotime($req['requested_at'])) ?>
                            </td>
                            <td class="px-5 py-4 text-sm text-white">
                                <?php if ($req['client_db_name']): ?>
                                    <a href="../clients/show.php?id=<?= $req['client_id'] ?>"
                                        class="text-mb-accent hover:underline">
                                        <?= e($req['client_db_name']) ?>
                                    </a>
                                <?php elseif ($req['client_name_free']): ?>
                                    <?= e($req['client_name_free']) ?>
                                    <span class="text-mb-subtle text-xs">(walk-in)</span>
                                <?php else: ?>
                                    <span class="text-mb-subtle"> ”</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-white font-medium text-sm">
                                    <?= e($req['vehicle_brand']) ?>
                                </div>
                                <div class="text-mb-subtle text-xs">
                                    <?= e($req['vehicle_model']) ?>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <form method="POST" class="flex items-center gap-2">
                                    <input type="hidden" name="action" value="people_count">
                                    <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                                    <input type="hidden" name="page" value="<?= $currentPage ?>">
                                    <input type="number" name="people_count" min="1" step="1"
                                        value="<?= (int) ($req['people_count'] ?? 1) ?>"
                                        class="w-20 bg-mb-black border border-mb-subtle/20 rounded-lg px-2 py-1.5 text-white text-sm focus:outline-none focus:border-mb-accent">
                                    <button type="submit"
                                        class="text-xs px-2.5 py-1.5 rounded-lg border border-mb-subtle/20 text-mb-silver hover:text-white hover:border-mb-accent transition-colors">
                                        Save
                                    </button>
                                </form>
                            </td>
                            <td class="px-5 py-4 text-mb-silver text-sm max-w-xs">
                                <?= $req['notes'] ? e(mb_substr($req['notes'], 0, 80)) . (mb_strlen($req['notes']) > 80 ? ' ¦' : '') : '<span class="text-mb-subtle"> ”</span>' ?>
                            </td>
                            <td class="px-5 py-4">
                                <!-- Clickable status badge  ” cycles to next status -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $statusNext[$req['status']] ?>">
                                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                                    <input type="hidden" name="page" value="<?= $currentPage ?>">
                                    <button type="submit" title="Click to advance status"
                                        class="px-2.5 py-1 rounded-full text-xs font-medium cursor-pointer transition-opacity hover:opacity-75 <?= $statusColors[$req['status']] ?>">
                                        <?= $statusLabel[$req['status']] ?>
                                    </button>
                                </form>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <!-- Set specific status -->
                                <form method="POST" class="inline mr-1" onsubmit="return confirm('Delete this request?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="req_id" value="<?= $req['id'] ?>">
                                    <input type="hidden" name="filter" value="<?= e($filter) ?>">
                                    <input type="hidden" name="page" value="<?= $currentPage ?>">
                                    <button type="submit" title="Delete"
                                        class="text-mb-subtle hover:text-red-400 transition-colors p-1 rounded">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php
    $_rqp = array_filter(['filter' => $filter], static fn($v) => $v !== null && $v !== '');
    echo render_pagination($pgRequests, $_rqp);
    ?>
</div>

<!-- Add Request Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl shadow-2xl w-full max-w-lg">
        <div class="flex items-center justify-between p-5 border-b border-mb-subtle/20">
            <h2 class="text-white font-medium">Log Vehicle Request</h2>
            <button onclick="document.getElementById('addModal').classList.add('hidden')"
                class="text-mb-subtle hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="action" value="add">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Vehicle Brand <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="vehicle_brand" placeholder="e.g. Toyota"
                        value="<?= e($_POST['vehicle_brand'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent placeholder-mb-subtle">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Vehicle Model <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="vehicle_model" placeholder="e.g. Land Cruiser"
                        value="<?= e($_POST['vehicle_model'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent placeholder-mb-subtle">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">People Requested</label>
                    <input type="number" name="people_count" min="1" step="1"
                        value="<?= e($_POST['people_count'] ?? '1') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent placeholder-mb-subtle">
                </div>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Client</label>
                <div class="space-y-2">
                    <select name="client_id" id="reqClientSelect"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent">
                        <option value=""> ” Select existing client  ”</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="client_name_free" id="reqClientFree"
                        placeholder="Or type client / walk-in name"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent placeholder-mb-subtle">
                    <p class="text-mb-subtle text-xs">Select from dropdown OR type a name below it  ” not both needed.
                    </p>
                </div>
            </div>

            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Notes</label>
                <textarea name="notes" rows="3" placeholder="Colour preference, budget, timeline, etc."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent placeholder-mb-subtle resize-none"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-1">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                    class="text-mb-subtle hover:text-white text-sm transition-colors px-3 py-2">Cancel</button>
                <button type="submit"
                    class="bg-mb-accent hover:bg-mb-accent/80 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors">
                    Log Request
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <script>document.getElementById('addModal').classList.remove('hidden');</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
