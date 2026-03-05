<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();
$leadSourcesMap = lead_sources_get_map($pdo);

// Align legacy lead statuses with the current pipeline stages.
try {
    $columnTypeStmt = $pdo->query("SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'leads'
          AND COLUMN_NAME = 'status'
        LIMIT 1");
    $columnType = strtolower((string) $columnTypeStmt->fetchColumn());
    if ($columnType !== '') {
        if (strpos($columnType, "'negotiation'") !== false) {
            $pdo->exec("UPDATE leads SET status='interested' WHERE status='negotiation'");
        }
        if (strpos($columnType, "'future'") === false || strpos($columnType, "'negotiation'") !== false) {
            $pdo->exec("ALTER TABLE leads MODIFY COLUMN status ENUM('new','contacted','interested','future','closed_won','closed_lost') DEFAULT 'new'");
        }
    }
} catch (Throwable $e) {
}

// Fetch all leads grouped by status
$stages = ['new', 'contacted', 'interested', 'future', 'closed_won', 'closed_lost'];
$stageLabels = [
    'new' => 'New',
    'contacted' => 'Contacted',
    'interested' => 'Interested',
    'future' => 'Book Later',
    'closed_won' => 'Closed Won',
    'closed_lost' => 'Closed Lost',
];
$stageColors = [
    'new' => 'border-t-sky-500',
    'contacted' => 'border-t-yellow-500',
    'interested' => 'border-t-purple-500',
    'future' => 'border-t-indigo-500',
    'closed_won' => 'border-t-green-500',
    'closed_lost' => 'border-t-red-500/50',
];
$stageBadge = [
    'new' => 'bg-sky-500/20 text-sky-400',
    'contacted' => 'bg-yellow-500/20 text-yellow-400',
    'interested' => 'bg-purple-500/20 text-purple-400',
    'future' => 'bg-indigo-500/20 text-indigo-400',
    'closed_won' => 'bg-green-500/20 text-green-400',
    'closed_lost' => 'bg-red-500/10 text-red-400/60',
];

// ── Filter params ───────────────────────────────────────────
$currentUser = current_user();
$isAdmin = (($currentUser['role'] ?? '') === 'admin');
$currentUserId = (int) ($currentUser['id'] ?? 0);
$isStaffScopeLocked = (!$isAdmin && $currentUserId > 0);
$importReport = null;
if ($isAdmin && isset($_SESSION['lead_import_report']) && is_array($_SESSION['lead_import_report'])) {
    $importReport = $_SESSION['lead_import_report'];
    unset($_SESSION['lead_import_report']);
}

$requestedStaffFilter = (int) ($_GET['staff_filter'] ?? 0);
$filterStaff = $isStaffScopeLocked ? $currentUserId : $requestedStaffFilter;
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo = trim($_GET['date_to'] ?? '');
$filterSource = trim($_GET['source_filter'] ?? '');
$perPage = get_per_page($pdo);
$page = max(1, (int) ($_GET['page'] ?? 1));
$pipelinePaginationEnabled = settings_get($pdo, 'pipeline_pagination_enabled', '1') !== '0';

// Fetch all users for the staff filter dropdown
$allStaffUsers = [];
if (!$isStaffScopeLocked) {
    $allStaffUsers = $pdo->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
}
$importAssignableStaff = [];
if ($isAdmin) {
    $importAssignableStaff = $pdo->query("SELECT id, name FROM users WHERE is_active = 1 AND role = 'staff' ORDER BY name ASC")->fetchAll();
}

// ── Build dynamic leads query ────────────────────────────────
$where = ['1=1'];
$params = [];

if ($filterStaff > 0) {
    $where[] = 'l.assigned_staff_id = ?';
    $params[] = $filterStaff;
}
if ($filterDateFrom !== '') {
    $where[] = 'DATE(l.created_at) >= ?';
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $where[] = 'DATE(l.created_at) <= ?';
    $params[] = $filterDateTo;
}
if ($filterSource !== '') {
    $where[] = 'l.source = ?';
    $params[] = $filterSource;
}

$whereSql = implode(' AND ', $where);
$baseFrom = 'FROM leads l
        LEFT JOIN users u ON l.assigned_staff_id = u.id
        WHERE ' . $whereSql;
$sql = 'SELECT l.*, u.name AS assigned_user_name ' . $baseFrom . ' ORDER BY l.created_at DESC';
$countSql = 'SELECT COUNT(*) FROM leads l WHERE ' . $whereSql;
$pgLeads = null;
if ($pipelinePaginationEnabled) {
    $pgLeads = paginate_query($pdo, $sql, $countSql, $params, $page, $perPage);
    $allLeads = $pgLeads['rows'];
} else {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allLeads = $stmt->fetchAll();
}

$grouped = [];
foreach ($stages as $s) {
    $grouped[$s] = [];
}
foreach ($allLeads as $lead) {
    $normalizedStatus = ($lead['status'] ?? '') === 'negotiation' ? 'interested' : ($lead['status'] ?? '');
    if (isset($grouped[$normalizedStatus])) {
        $lead['status'] = $normalizedStatus;
        $grouped[$normalizedStatus][] = $lead;
    }
}

// Overdue follow-up counts
$overdueMap = [];
$oStmt = $pdo->prepare('SELECT lead_id, COUNT(*) as cnt FROM lead_followups WHERE scheduled_at < ? AND is_done=0 GROUP BY lead_id');
$oStmt->execute([app_now_sql()]);
foreach ($oStmt->fetchAll() as $row) {
    $overdueMap[$row['lead_id']] = $row['cnt'];
}

// Allowed quick transitions from each stage
$stageTransitions = [
    'new' => ['contacted', 'future', 'closed_lost'],
    'contacted' => ['new', 'interested', 'future', 'closed_lost'],
    'interested' => ['contacted', 'future', 'closed_won', 'closed_lost'],
    'future' => ['contacted', 'interested', 'closed_won', 'closed_lost'],
    'closed_won' => [],
    'closed_lost' => [],
];

$activeCountSql = "SELECT COUNT(*) FROM leads l WHERE $whereSql AND l.status NOT IN ('closed_won','closed_lost')";
$activeCountStmt = $pdo->prepare($activeCountSql);
$activeCountStmt->execute($params);
$activeLeadCount = (int) $activeCountStmt->fetchColumn();

$pageTitle = 'Pipeline';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-5">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h2 class="text-white text-xl font-light">Pipeline</h2>
            <p class="text-mb-subtle text-sm mt-0.5">
                <?= $activeLeadCount ?> active lead<?= $activeLeadCount !== 1 ? 's' : '' ?>
            </p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($isAdmin): ?>
                <button type="button" id="openLeadImportModal"
                    class="bg-mb-black border border-mb-subtle/20 text-mb-silver px-5 py-2 rounded-full hover:border-mb-accent/40 hover:text-white transition-colors text-sm font-medium">
                    Import Leads
                </button>
            <?php endif; ?>
            <?php if (auth_has_perm('add_leads')): ?>
                <a href="create.php"
                    class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">
                    + Add Lead
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── Filter Bar ── -->
    <?php
    if ($isAdmin && is_array($importReport)):
        $importTotal = max(0, (int) ($importReport['total_rows'] ?? 0));
        $importSuccess = max(0, (int) ($importReport['success_count'] ?? 0));
        $importFailed = max(0, (int) ($importReport['failed_count'] ?? 0));
        $importSuccessPct = $importTotal > 0 ? round(($importSuccess / $importTotal) * 100, 1) : 0;
        $importFailedPct = $importTotal > 0 ? round(($importFailed / $importTotal) * 100, 1) : 0;
        $importFailedEntries = (array) ($importReport['failed_entries'] ?? []);
        $importFailedHiddenCount = max(0, (int) ($importReport['failed_hidden_count'] ?? 0));
        $importFileName = (string) ($importReport['filename'] ?? 'Import file');
        $importStaffNames = array_values(array_filter((array) ($importReport['staff_names'] ?? []), static fn($v) => trim((string) $v) !== ''));
        ?>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <p class="text-white text-sm font-medium">Lead Import Result</p>
                    <p class="text-mb-subtle text-xs mt-0.5">
                        File: <?= e($importFileName) ?>
                        <?php if (!empty($importStaffNames)): ?>
                            | Staff: <?= e(implode(', ', $importStaffNames)) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <a href="import.php"
                    class="text-xs bg-mb-black border border-mb-subtle/20 text-mb-silver px-3 py-1.5 rounded-full hover:border-mb-accent/40 hover:text-white transition-colors">
                    Import Another File
                </a>
            </div>
            <div class="h-2 bg-mb-black rounded-full overflow-hidden flex">
                <div class="h-full bg-green-500/80" style="width: <?= $importSuccessPct ?>%"></div>
                <div class="h-full bg-red-500/70" style="width: <?= $importFailedPct ?>%"></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg px-3 py-2">
                    <span class="text-mb-subtle">Total Rows</span>
                    <p class="text-white text-sm mt-0.5"><?= $importTotal ?></p>
                </div>
                <div class="bg-green-500/10 border border-green-500/30 rounded-lg px-3 py-2">
                    <span class="text-green-300/80">Success</span>
                    <p class="text-green-300 text-sm mt-0.5"><?= $importSuccess ?></p>
                </div>
                <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-3 py-2">
                    <span class="text-red-300/80">Failed</span>
                    <p class="text-red-300 text-sm mt-0.5"><?= $importFailed ?></p>
                </div>
            </div>
            <?php if (!empty($importFailedEntries) || $importFailedHiddenCount > 0): ?>
                <details class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg">
                    <summary class="px-3 py-2 text-xs text-mb-silver cursor-pointer select-none">
                        View Failed Entries
                        <?php if ($importFailedHiddenCount > 0): ?>
                            (showing first <?= count($importFailedEntries) ?> of <?= $importFailed ?>)
                        <?php endif; ?>
                    </summary>
                    <div class="border-t border-mb-subtle/20 max-h-64 overflow-y-auto">
                        <?php foreach ($importFailedEntries as $failedEntry): ?>
                            <div class="px-3 py-2 border-b border-mb-subtle/10 text-xs space-y-0.5">
                                <p class="text-red-300">
                                    Row <?= (int) ($failedEntry['row_number'] ?? 0) ?>:
                                    <?= e((string) ($failedEntry['reason'] ?? 'Failed')) ?>
                                </p>
                                <p class="text-mb-subtle">
                                    <?= e(trim((string) (($failedEntry['name'] ?? '') . ' | ' . ($failedEntry['phone'] ?? '') . ' | ' . ($failedEntry['email'] ?? '')))) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($importFailedHiddenCount > 0): ?>
                            <p class="px-3 py-2 text-xs text-mb-subtle">...and <?= $importFailedHiddenCount ?> more failed rows.</p>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    <?php endif;
    $activeFilters = (int) ((!$isStaffScopeLocked) && ($filterStaff > 0)) + (int) ($filterDateFrom !== '') + (int) ($filterDateTo !== '') + (int) ($filterSource !== '');
    ?>
    <form method="GET" id="pipelineFilters"
        class="flex flex-wrap items-center gap-3 bg-mb-surface border border-mb-subtle/20 rounded-xl px-4 py-3">

        <!-- Staff Filter -->
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-mb-subtle flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <?php if ($isStaffScopeLocked): ?>
                <span
                    class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-mb-silver whitespace-nowrap">
                    Your leads only
                </span>
            <?php else: ?>
                <select name="staff_filter" onchange="document.getElementById('pipelineFilters').submit()"
                    class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors cursor-pointer">
                    <option value="0">All Staff</option>
                    <?php foreach ($allStaffUsers as $su): ?>
                        <option value="<?= $su['id'] ?>" <?= $filterStaff === (int) $su['id'] ? 'selected' : '' ?>>
                            <?= e($su['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>

        <div class="w-px h-5 bg-mb-subtle/20"></div>

        <!-- Source Filter -->
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-mb-subtle flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            <select name="source_filter" onchange="document.getElementById('pipelineFilters').submit()"
                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors cursor-pointer">
                <option value="">All Sources</option>
                <?php foreach ($leadSourcesMap as $slug => $label): ?>
                    <option value="<?= e($slug) ?>" <?= $filterSource === $slug ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="w-px h-5 bg-mb-subtle/20"></div>


        <!-- Date From -->
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-mb-subtle flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <label class="text-xs text-mb-subtle whitespace-nowrap">From</label>
            <input type="date" name="date_from" value="<?= e($filterDateFrom) ?>"
                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors cursor-pointer">
        </div>

        <!-- Date To -->
        <div class="flex items-center gap-2">
            <label class="text-xs text-mb-subtle whitespace-nowrap">To</label>
            <input type="date" name="date_to" value="<?= e($filterDateTo) ?>"
                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors cursor-pointer">
        </div>

        <!-- Apply date button -->
        <button type="submit"
            class="flex items-center gap-1.5 bg-mb-accent/20 hover:bg-mb-accent/40 border border-mb-accent/30 text-mb-accent text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            Apply
        </button>

        <!-- Active count badge + Clear -->
        <div class="flex items-center gap-2 ml-auto">
            <?php if ($activeFilters > 0): ?>
                <span class="text-xs bg-mb-accent/20 text-mb-accent border border-mb-accent/30 px-2 py-0.5 rounded-full">
                    <?= $activeFilters ?> filter<?= $activeFilters > 1 ? 's' : '' ?> active
                </span>
                <a href="pipeline.php"
                    class="text-xs text-mb-subtle hover:text-white transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Clear
                </a>
            <?php else: ?>
                <span class="text-xs text-mb-subtle">Showing all leads</span>
            <?php endif; ?>
        </div>
    </form>

    <div class="flex gap-4 overflow-x-auto pb-4" style="min-height:70vh">
        <?php foreach ($stages as $stage):
            $cards = $grouped[$stage];
            ?>
            <div data-stage="<?= e($stage) ?>"
                class="pipeline-column flex-shrink-0 w-72 bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden border-t-2 <?= $stageColors[$stage] ?> flex flex-col transition-colors">
                <div class="px-4 py-3 border-b border-mb-subtle/10 flex items-center justify-between">
                    <span class="text-white text-sm font-medium">
                        <?= $stageLabels[$stage] ?>
                    </span>
                    <span data-stage-count class="<?= $stageBadge[$stage] ?> text-xs px-2 py-0.5 rounded-full font-medium">
                        <?= count($cards) ?>
                    </span>
                </div>
                <div class="px-3 py-2 border-b border-mb-subtle/10">
                    <input type="text" data-stage="<?= e($stage) ?>"
                        placeholder="Search in <?= e($stageLabels[$stage]) ?>..."
                        class="columnLeadSearch w-full bg-mb-black border border-mb-subtle/20 rounded-md px-3 py-2 text-xs text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors">
                </div>

                <div class="flex-1 p-3 space-y-2 overflow-y-auto">
                    <?php if (empty($cards)): ?>
                        <p class="text-mb-subtle/40 text-xs text-center py-8">No leads here</p>
                    <?php endif; ?>

                    <?php foreach ($cards as $l):
                        $moveOptions = $stageTransitions[$stage] ?? [];
                        $isMovable = !empty($moveOptions);
                        $sourceLabel = $leadSourcesMap[$l['source']] ?? lead_source_guess_label((string) $l['source']);
                        ?>
                        <div id="lead-card-<?= (int) $l['id'] ?>" data-lead-id="<?= (int) $l['id'] ?>"
                            data-current-stage="<?= e($stage) ?>" data-lead-name="<?= e(strtolower((string) $l['name'])) ?>"
                            data-lead-phone="<?= e(strtolower((string) $l['phone'])) ?>"
                            draggable="<?= $isMovable ? 'true' : 'false' ?>"
                            class="pipeline-card relative bg-mb-black/40 border border-mb-subtle/10 rounded-lg p-3 hover:border-mb-subtle/30 transition-all cursor-pointer group"
                            onclick="window.location='show.php?id=<?= (int) $l['id'] ?>'">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-white text-sm font-medium group-hover:text-mb-accent transition-colors">
                                    <?= e($l['name']) ?>
                                </p>
                                <?php if ($overdueMap[$l['id']] ?? 0): ?>
                                    <span
                                        class="text-[10px] bg-red-500/20 text-red-400 px-1.5 py-0.5 rounded-full animate-pulse flex-shrink-0">&#9888;</span>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center gap-1.5 mt-0.5" onclick="event.stopPropagation()">
                                <p class="text-mb-subtle text-xs"><?= e($l['phone']) ?></p>
                                <?php if (!empty($l['phone'])): ?>
                                    <?php
                                    $phoneDigits = preg_replace('/\D/', '', (string) $l['phone']);
                                    $phoneDial = preg_replace('/[^0-9+]/', '', (string) $l['phone']);
                                    if ($phoneDial === '') {
                                        $phoneDial = $phoneDigits;
                                    }
                                    ?>
                                    <button type="button" title="Contact options"
                                        data-dial="<?= e($phoneDial) ?>" data-wa="<?= e($phoneDigits) ?>"
                                        onclick="event.stopPropagation(); openContactChoice(this);"
                                        class="flex-shrink-0 text-mb-accent hover:text-white transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                                            class="w-3.5 h-3.5">
                                            <path
                                                d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.01-.24 11.36 11.36 0 003.56.57 1 1 0 011 1V20a1 1 0 01-1 1C10.07 21 3 13.93 3 5a1 1 0 011-1h3.49a1 1 0 011 1 11.36 11.36 0 00.57 3.56 1 1 0 01-.24 1.01l-2.2 2.22z" />
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($l['assigned_to'])): ?>
                                <p class="text-mb-subtle text-[11px] mt-1">
                                    Assigned: <span class="text-white/90"><?= e($l['assigned_to']) ?></span>
                                </p>
                            <?php endif; ?>

                            <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                                <span class="text-[10px] bg-mb-surface/80 text-mb-silver px-2 py-0.5 rounded-full capitalize">
                                    <?= str_replace('_', ' ', $l['inquiry_type']) ?>
                                </span>
                                <span class="text-[10px] bg-mb-surface/80 text-mb-silver px-2 py-0.5 rounded-full capitalize">
                                    <?= e($sourceLabel) ?>
                                </span>
                                <?php if ($l['vehicle_interest']): ?>
                                    <span class="text-[10px] bg-mb-accent/10 text-mb-accent px-2 py-0.5 rounded-full">
                                        &#128663; <?= e($l['vehicle_interest']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php $noteText = trim((string) ($l['notes'] ?? '')); ?>
                            <p class="text-[10px] mt-1.5 truncate <?= $noteText !== '' ? 'text-mb-silver' : 'text-mb-subtle/40' ?>"
                                title="<?= e($noteText !== '' ? $noteText : 'No notes') ?>">
                                <?= $noteText !== '' ? '📝 ' . e($noteText) : 'No notes' ?>
                            </p>

                            <?php if ($l['status'] === 'closed_lost' && !empty($l['lost_reason'])): ?>
                                <div
                                    class="mt-2 flex items-start gap-1.5 bg-red-500/10 border border-red-500/30 rounded-lg px-2.5 py-1.5">
                                    <span class="text-red-400 text-[10px] mt-0.5 flex-shrink-0">✕</span>
                                    <p class="text-red-300 text-[10px] leading-relaxed font-medium"><?= e($l['lost_reason']) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($isMovable): ?>
                                <div class="mt-2 flex items-center gap-1.5" onclick="event.stopPropagation()">
                                    <select id="move-stage-<?= (int) $l['id'] ?>"
                                        class="flex-1 bg-mb-black border border-mb-subtle/20 rounded-md px-2 py-1 text-[10px] text-mb-silver focus:outline-none focus:border-mb-accent">
                                        <option value="">Move to...</option>
                                        <?php foreach ($moveOptions as $targetStage): ?>
                                            <option value="<?= $targetStage ?>">
                                                <?= $stageLabels[$targetStage] ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" onclick="event.stopPropagation(); moveFromSelect(<?= (int) $l['id'] ?>)"
                                        class="text-[10px] border border-mb-subtle/20 text-mb-subtle rounded-md px-2 py-1 hover:border-mb-accent/40 hover:text-mb-accent transition-all">
                                        Go
                                    </button>
                                </div>
                            <?php endif; ?>

                            <?php if (auth_has_perm('add_leads')): ?>
                                <div class="mt-2 flex items-center gap-2" onclick="event.stopPropagation()">
                                    <a href="edit.php?id=<?= (int) $l['id'] ?>"
                                        class="flex-1 text-center text-[10px] border border-mb-subtle/20 text-mb-subtle rounded-md py-1 hover:border-mb-accent/40 hover:text-white transition-colors">
                                        Edit
                                    </a>
                                    <form action="delete.php" method="POST" class="flex-1"
                                        onsubmit="event.stopPropagation(); return confirm('Delete this lead permanently?')">
                                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                                        <button type="submit"
                                            class="w-full text-[10px] border border-red-500/20 text-red-400/70 rounded-md py-1 hover:border-red-500/50 hover:text-red-400 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($cards)): ?>
                        <p class="search-empty-message hidden text-mb-subtle/50 text-xs text-center py-8">No matching leads</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php if ($pipelinePaginationEnabled && is_array($pgLeads)): ?>
    <?php
    $_pqp = array_filter(
        [
            'staff_filter' => (!$isStaffScopeLocked && $filterStaff > 0) ? $filterStaff : null,
            'date_from' => $filterDateFrom,
            'date_to' => $filterDateTo,
            'source_filter' => $filterSource,
        ],
        static fn($v) => $v !== null && $v !== ''
    );
    echo render_pagination($pgLeads, $_pqp);
    ?>
<?php endif; ?>

<div id="contactChoiceModal" class="hidden fixed inset-0 z-50 bg-black/60 items-center justify-center p-4">
    <div class="w-full max-w-sm bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 space-y-4">
        <div>
            <h3 class="text-white text-base font-medium">Contact Lead</h3>
            <p class="text-mb-subtle text-xs mt-1">Choose where to open this lead contact.</p>
        </div>
        <div class="grid grid-cols-1 gap-2">
            <button id="contactDialBtn" type="button"
                class="w-full text-left bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-sm text-white hover:border-mb-accent/50 transition-colors">
                Open Phone Dialer
            </button>
            <button id="contactWhatsBtn" type="button"
                class="w-full text-left bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-sm text-white hover:border-[#25D366]/50 transition-colors">
                Open WhatsApp
            </button>
        </div>
        <div class="flex justify-end pt-1">
            <button id="contactCancelBtn" type="button"
                class="text-xs text-mb-subtle hover:text-white transition-colors px-2 py-1">
                Cancel
            </button>
        </div>
    </div>
</div>

<?php if ($isAdmin): ?>
    <div id="leadImportModal" class="hidden fixed inset-0 z-50 bg-black/60 items-center justify-center p-4">
        <div class="w-full max-w-2xl bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-white text-base font-medium">Import Leads</h3>
                    <p class="text-mb-subtle text-xs mt-1">Download template, upload file, map columns, and import with round robin assignment.</p>
                </div>
                <button type="button" id="closeLeadImportModalTop"
                    class="text-mb-subtle hover:text-white transition-colors text-sm">Close</button>
            </div>

            <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg p-3 flex items-center justify-between flex-wrap gap-2">
                <p class="text-xs text-mb-silver">Step 1: Download the sample Excel template.</p>
                <a href="import.php?action=template"
                    class="text-xs bg-mb-accent/15 border border-mb-accent/35 text-mb-accent px-3 py-1.5 rounded-full hover:bg-mb-accent/25 transition-colors">
                    Download Template (.xlsx)
                </a>
            </div>

            <form method="POST" action="import.php" enctype="multipart/form-data" id="pipelineLeadImportForm" class="space-y-4">
                <input type="hidden" name="stage" value="prepare">

                <div>
                    <label class="block text-sm text-mb-silver mb-2">Import File</label>
                    <input type="file" name="import_file" accept=".xlsx,.csv" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-sm text-white file:mr-4 file:rounded-md file:border-0 file:bg-mb-accent/20 file:px-3 file:py-1.5 file:text-xs file:text-mb-accent hover:file:bg-mb-accent/30">
                    <p class="text-xs text-mb-subtle mt-1">Supported formats: .xlsx and .csv</p>
                </div>

                <div>
                    <label class="block text-sm text-mb-silver mb-2">Round Robin Staff <span class="text-red-400">*</span></label>
                    <?php if (empty($importAssignableStaff)): ?>
                        <p class="text-sm text-red-400">No active staff accounts found. Create active staff first.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-44 overflow-y-auto pr-1">
                            <?php foreach ($importAssignableStaff as $staffUser): ?>
                                <label
                                    class="flex items-center gap-2 bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-mb-silver hover:border-mb-accent/35 transition-colors cursor-pointer">
                                    <input type="checkbox" name="staff_ids[]" value="<?= (int) $staffUser['id'] ?>" class="accent-[#00adef]">
                                    <span><?= e($staffUser['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="pipelineLeadImportProgress" class="hidden bg-mb-black/60 border border-mb-subtle/20 rounded-lg px-4 py-3">
                    <p class="text-xs text-mb-silver mb-2">Preparing import and loading mapping screen...</p>
                    <div class="h-2 bg-mb-surface rounded-full overflow-hidden">
                        <div class="h-full bg-mb-accent animate-pulse w-2/3"></div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" id="closeLeadImportModalBottom"
                        class="text-xs text-mb-subtle hover:text-white transition-colors px-3 py-1.5">
                        Cancel
                    </button>
                    <button type="submit" id="pipelineLeadImportSubmit"
                        class="bg-mb-accent text-white px-4 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                        Upload and Map
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<style>
</style>

<script>
    const stageTransitions = <?= json_encode($stageTransitions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const columnLeadSearchInputs = document.querySelectorAll('.columnLeadSearch');
    const pipelineColumns = document.querySelectorAll('.pipeline-column');
    const contactChoiceModal = document.getElementById('contactChoiceModal');
    const contactDialBtn = document.getElementById('contactDialBtn');
    const contactWhatsBtn = document.getElementById('contactWhatsBtn');
    const contactCancelBtn = document.getElementById('contactCancelBtn');
    const openLeadImportModalBtn = document.getElementById('openLeadImportModal');
    const leadImportModal = document.getElementById('leadImportModal');
    const closeLeadImportModalTop = document.getElementById('closeLeadImportModalTop');
    const closeLeadImportModalBottom = document.getElementById('closeLeadImportModalBottom');
    const pipelineLeadImportForm = document.getElementById('pipelineLeadImportForm');
    const pipelineLeadImportSubmit = document.getElementById('pipelineLeadImportSubmit');
    const pipelineLeadImportProgress = document.getElementById('pipelineLeadImportProgress');
    let pendingDialNumber = '';
    let pendingWaNumber = '';

    function normalizeSearchTerm(value) {
        return (value || '').toString().trim().toLowerCase();
    }

    function applyPipelineFilters() {
        pipelineColumns.forEach((column) => {
            const stageSearchInput = column.querySelector('.columnLeadSearch');
            const stageTerm = normalizeSearchTerm(stageSearchInput ? stageSearchInput.value : '');
            const cards = column.querySelectorAll('.pipeline-card');
            let visibleCount = 0;

            cards.forEach((card) => {
                const leadName = card.dataset.leadName || '';
                const leadPhone = card.dataset.leadPhone || '';
                const matchesStage = !stageTerm || leadName.includes(stageTerm) || leadPhone.includes(stageTerm);
                const shouldShow = matchesStage;
                card.classList.toggle('hidden', !shouldShow);
                if (shouldShow) {
                    visibleCount++;
                }
            });

            const stageCountBadge = column.querySelector('[data-stage-count]');
            if (stageCountBadge) {
                stageCountBadge.textContent = String(visibleCount);
            }

            const searchEmptyMessage = column.querySelector('.search-empty-message');
            if (searchEmptyMessage) {
                searchEmptyMessage.classList.toggle('hidden', visibleCount !== 0);
            }
        });
    }

    function isMoveAllowed(fromStage, toStage) {
        const allowed = stageTransitions[fromStage] || [];
        return allowed.includes(toStage);
    }

    function closeContactChoice() {
        if (!contactChoiceModal) {
            return;
        }
        contactChoiceModal.classList.add('hidden');
        contactChoiceModal.classList.remove('flex');
        pendingDialNumber = '';
        pendingWaNumber = '';
    }

    function setContactActionState(button, isEnabled) {
        if (!button) {
            return;
        }
        button.disabled = !isEnabled;
        button.classList.toggle('opacity-50', !isEnabled);
        button.classList.toggle('cursor-not-allowed', !isEnabled);
    }

    function closeLeadImportModal() {
        if (!leadImportModal) {
            return;
        }
        leadImportModal.classList.add('hidden');
        leadImportModal.classList.remove('flex');
    }

    function openLeadImportModal() {
        if (!leadImportModal) {
            return;
        }
        leadImportModal.classList.remove('hidden');
        leadImportModal.classList.add('flex');
    }

    function openContactChoice(buttonEl) {
        const dialNumber = (buttonEl.dataset.dial || '').trim();
        const waNumber = (buttonEl.dataset.wa || '').trim();

        if (!dialNumber && !waNumber) {
            alert('No valid phone number found for this lead.');
            return;
        }

        pendingDialNumber = dialNumber;
        pendingWaNumber = waNumber;

        setContactActionState(contactDialBtn, dialNumber !== '');
        setContactActionState(contactWhatsBtn, waNumber !== '');

        if (contactChoiceModal) {
            contactChoiceModal.classList.remove('hidden');
            contactChoiceModal.classList.add('flex');
        }
    }

    function promptLostReason(targetStage) {
        if (targetStage !== 'closed_lost') {
            return '';
        }

        const reason = window.prompt('Enter lost reason (required):', '');
        if (reason === null) {
            return null;
        }

        const trimmed = reason.trim();
        if (!trimmed) {
            alert('Lost reason is required to close as lost.');
            return null;
        }

        return trimmed;
    }

    async function moveStage(leadId, fromStage, targetStage) {
        if (!isMoveAllowed(fromStage, targetStage)) {
            alert('This move is not allowed from the pipeline card. Use Edit for manual changes.');
            return false;
        }

        const lostReason = promptLostReason(targetStage);
        if (lostReason === null) {
            return false;
        }

        const body = new FormData();
        body.append('id', leadId);
        body.append('status', targetStage);
        body.append('quick_update', '1');
        if (lostReason) {
            body.append('lost_reason', lostReason);
        }

        let response;
        try {
            response = await fetch('edit.php', { method: 'POST', body });
        } catch (error) {
            alert('Network error while updating lead stage.');
            return false;
        }

        let payload = null;
        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (!response.ok || !payload || payload.ok !== true) {
            alert((payload && payload.message) ? payload.message : 'Could not update lead status.');
            return false;
        }

        return true;
    }

    async function moveFromSelect(leadId) {
        const select = document.getElementById(`move-stage-${leadId}`);
        const card = document.getElementById(`lead-card-${leadId}`);
        if (!select || !card || !select.value) {
            return;
        }

        const fromStage = card.dataset.currentStage;
        const moved = await moveStage(leadId, fromStage, select.value);
        if (moved) {
            location.reload();
        }
    }

    function setupDragDrop() {
        const columns = document.querySelectorAll('.pipeline-column');
        const cards = document.querySelectorAll('.pipeline-card[draggable="true"]');

        let draggingLeadId = null;
        let draggingFromStage = null;

        cards.forEach((card) => {
            card.addEventListener('dragstart', (event) => {
                draggingLeadId = card.dataset.leadId;
                draggingFromStage = card.dataset.currentStage;
                card.classList.add('opacity-60');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', draggingLeadId || '');
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('opacity-60');
                columns.forEach((column) => {
                    column.style.borderColor = '';
                });
                draggingLeadId = null;
                draggingFromStage = null;
            });
        });

        columns.forEach((column) => {
            column.addEventListener('dragover', (event) => {
                if (!draggingLeadId) {
                    return;
                }
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                column.style.borderColor = 'rgba(0, 173, 239, 0.55)';
            });

            column.addEventListener('dragleave', () => {
                column.style.borderColor = '';
            });

            column.addEventListener('drop', async (event) => {
                event.preventDefault();
                column.style.borderColor = '';

                const targetStage = column.dataset.stage;
                if (!draggingLeadId || !draggingFromStage || !targetStage || targetStage === draggingFromStage) {
                    return;
                }

                const moved = await moveStage(Number(draggingLeadId), draggingFromStage, targetStage);
                if (moved) {
                    location.reload();
                }
            });
        });
    }

    setupDragDrop();
    columnLeadSearchInputs.forEach((input) => {
        input.addEventListener('input', applyPipelineFilters);
    });
    applyPipelineFilters();

    if (contactCancelBtn) {
        contactCancelBtn.addEventListener('click', function () {
            closeContactChoice();
        });
    }
    if (contactDialBtn) {
        contactDialBtn.addEventListener('click', function () {
            if (!pendingDialNumber) {
                return;
            }
            closeContactChoice();
            window.location.href = 'tel:' + pendingDialNumber;
        });
    }
    if (contactWhatsBtn) {
        contactWhatsBtn.addEventListener('click', function () {
            if (!pendingWaNumber) {
                return;
            }
            closeContactChoice();
            window.open('https://wa.me/' + pendingWaNumber, '_blank', 'noopener,noreferrer');
        });
    }
    if (contactChoiceModal) {
        contactChoiceModal.addEventListener('click', function (event) {
            if (event.target === contactChoiceModal) {
                closeContactChoice();
            }
        });
    }
    if (openLeadImportModalBtn) {
        openLeadImportModalBtn.addEventListener('click', function () {
            openLeadImportModal();
        });
    }
    if (closeLeadImportModalTop) {
        closeLeadImportModalTop.addEventListener('click', function () {
            closeLeadImportModal();
        });
    }
    if (closeLeadImportModalBottom) {
        closeLeadImportModalBottom.addEventListener('click', function () {
            closeLeadImportModal();
        });
    }
    if (leadImportModal) {
        leadImportModal.addEventListener('click', function (event) {
            if (event.target === leadImportModal) {
                closeLeadImportModal();
            }
        });
    }
    if (pipelineLeadImportForm) {
        pipelineLeadImportForm.addEventListener('submit', function (event) {
            const checkedStaff = pipelineLeadImportForm.querySelector('input[name="staff_ids[]"]:checked');
            if (!checkedStaff) {
                event.preventDefault();
                alert('Select at least one staff member for round robin assignment.');
                return;
            }
            if (pipelineLeadImportSubmit) {
                pipelineLeadImportSubmit.disabled = true;
                pipelineLeadImportSubmit.textContent = 'Preparing...';
            }
            if (pipelineLeadImportProgress) {
                pipelineLeadImportProgress.classList.remove('hidden');
            }
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        if (contactChoiceModal && !contactChoiceModal.classList.contains('hidden')) {
            closeContactChoice();
        }
        if (leadImportModal && !leadImportModal.classList.contains('hidden')) {
            closeLeadImportModal();
        }
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
