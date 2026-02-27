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
$filterStaff   = (int) ($_GET['staff_filter'] ?? 0);
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo   = trim($_GET['date_to'] ?? '');

// Fetch all users for the staff filter dropdown
$allStaffUsers = $pdo->query("SELECT id, name FROM users WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// ── Build dynamic leads query ────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($filterStaff > 0) {
    $where[]  = 'l.assigned_staff_id = ?';
    $params[] = $filterStaff;
}
if ($filterDateFrom !== '') {
    $where[]  = 'DATE(l.created_at) >= ?';
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $where[]  = 'DATE(l.created_at) <= ?';
    $params[] = $filterDateTo;
}

$sql = 'SELECT l.*, u.name AS assigned_user_name
        FROM leads l
        LEFT JOIN users u ON l.assigned_staff_id = u.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY l.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allLeads = $stmt->fetchAll();

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
$oStmt = $pdo->query('SELECT lead_id, COUNT(*) as cnt FROM lead_followups WHERE scheduled_at < NOW() AND is_done=0 GROUP BY lead_id');
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

$activeLeadCount = 0;
foreach ($allLeads as $leadRow) {
    if (!in_array($leadRow['status'], ['closed_won', 'closed_lost'], true)) {
        $activeLeadCount++;
    }
}

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
    $activeFilters = (int)($filterStaff > 0) + (int)($filterDateFrom !== '') + (int)($filterDateTo !== '');
    ?>
    <form method="GET" id="pipelineFilters"
        class="flex flex-wrap items-center gap-3 bg-mb-surface border border-mb-subtle/20 rounded-xl px-4 py-3">

        <!-- Staff Filter -->
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-mb-subtle flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <select name="staff_filter"
                onchange="document.getElementById('pipelineFilters').submit()"
                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors cursor-pointer">
                <option value="0">All Staff</option>
                <?php foreach ($allStaffUsers as $su): ?>
                    <option value="<?= $su['id'] ?>" <?= $filterStaff === (int)$su['id'] ? 'selected' : '' ?>>
                        <?= e($su['name']) ?>
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
                onchange="document.getElementById('pipelineFilters').submit()"
                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors cursor-pointer">
        </div>

        <!-- Date To -->
        <div class="flex items-center gap-2">
            <label class="text-xs text-mb-subtle whitespace-nowrap">To</label>
            <input type="date" name="date_to" value="<?= e($filterDateTo) ?>"
                onchange="document.getElementById('pipelineFilters').submit()"
                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors cursor-pointer">
        </div>

        <!-- Active count badge + Clear -->
        <div class="flex items-center gap-2 ml-auto">
            <?php if ($activeFilters > 0): ?>
                <span class="text-xs bg-mb-accent/20 text-mb-accent border border-mb-accent/30 px-2 py-0.5 rounded-full">
                    <?= $activeFilters ?> filter<?= $activeFilters > 1 ? 's' : '' ?> active
                </span>
                <a href="pipeline.php"
                    class="text-xs text-mb-subtle hover:text-white transition-colors flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
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

                            <p class="text-mb-subtle text-xs mt-0.5">
                                <?= e($l['phone']) ?>
                            </p>
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

                            <div class="mt-2 flex items-center justify-end" onclick="event.stopPropagation()">
                                <div class="note-hover-area relative">
                                    <span
                                        class="text-[10px] border border-mb-subtle/20 text-mb-subtle rounded-md px-2 py-1 hover:border-mb-accent/40 hover:text-mb-accent transition-all">
                                        📝 Note
                                    </span>
                                    <div
                                        class="note-hover-popup hidden absolute z-[80] right-0 bottom-full mb-2 w-64 max-h-44 overflow-y-auto bg-mb-surface border border-mb-subtle/20 rounded-lg p-3 shadow-xl shadow-black/30">
                                        <p class="text-[11px] text-mb-silver leading-relaxed whitespace-pre-wrap break-all">
                                            <?= trim((string) ($l['notes'] ?? '')) !== '' ? e($l['notes']) : 'No notes added.' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

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

<style>
    .note-hover-area:hover .note-hover-popup {
        display: block;
    }

    .note-hover-popup {
        overflow-wrap: anywhere;
        word-break: break-word;
    }
</style>

<script>
    const stageTransitions = <?= json_encode($stageTransitions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const columnLeadSearchInputs = document.querySelectorAll('.columnLeadSearch');
    const pipelineColumns = document.querySelectorAll('.pipeline-column');

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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>