<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();

// Fetch all leads grouped by status
$stages = ['new', 'contacted', 'interested', 'negotiation', 'closed_won', 'closed_lost'];
$stageLabels = [
    'new' => 'New',
    'contacted' => 'Contacted',
    'interested' => 'Interested',
    'negotiation' => 'Negotiation',
    'closed_won' => 'Closed Won',
    'closed_lost' => 'Closed Lost',
];
$stageColors = [
    'new' => 'border-t-sky-500',
    'contacted' => 'border-t-yellow-500',
    'interested' => 'border-t-purple-500',
    'negotiation' => 'border-t-orange-500',
    'closed_won' => 'border-t-green-500',
    'closed_lost' => 'border-t-red-500/50',
];
$stageBadge = [
    'new' => 'bg-sky-500/20 text-sky-400',
    'contacted' => 'bg-yellow-500/20 text-yellow-400',
    'interested' => 'bg-purple-500/20 text-purple-400',
    'negotiation' => 'bg-orange-500/20 text-orange-400',
    'closed_won' => 'bg-green-500/20 text-green-400',
    'closed_lost' => 'bg-red-500/10 text-red-400/60',
];

$allLeads = $pdo->query('SELECT * FROM leads ORDER BY created_at DESC')->fetchAll();

$grouped = [];
foreach ($stages as $s) {
    $grouped[$s] = [];
}
foreach ($allLeads as $lead) {
    if (isset($grouped[$lead['status']])) {
        $grouped[$lead['status']][] = $lead;
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
    'new' => ['contacted', 'closed_lost'],
    'contacted' => ['new', 'interested', 'closed_lost'],
    'interested' => ['contacted', 'negotiation', 'closed_lost'],
    'negotiation' => ['interested', 'closed_won', 'closed_lost'],
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
            <a href="create.php"
                class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">
                + Add Lead
            </a>
        </div>
    </div>

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
                    <span class="<?= $stageBadge[$stage] ?> text-xs px-2 py-0.5 rounded-full font-medium">
                        <?= count($cards) ?>
                    </span>
                </div>

                <div class="flex-1 p-3 space-y-2 overflow-y-auto">
                    <?php if (empty($cards)): ?>
                        <p class="text-mb-subtle/40 text-xs text-center py-8">No leads here</p>
                    <?php endif; ?>

                    <?php foreach ($cards as $l):
                        $moveOptions = $stageTransitions[$stage] ?? [];
                        $isMovable = !empty($moveOptions);
                        ?>
                        <div id="lead-card-<?= (int) $l['id'] ?>" data-lead-id="<?= (int) $l['id'] ?>"
                            data-current-stage="<?= e($stage) ?>" draggable="<?= $isMovable ? 'true' : 'false' ?>"
                            class="pipeline-card bg-mb-black/40 border border-mb-subtle/10 rounded-lg p-3 hover:border-mb-subtle/30 transition-all cursor-pointer group"
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

                            <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                                <span class="text-[10px] bg-mb-surface/80 text-mb-silver px-2 py-0.5 rounded-full capitalize">
                                    <?= str_replace('_', ' ', $l['inquiry_type']) ?>
                                </span>
                                <span class="text-[10px] bg-mb-surface/80 text-mb-silver px-2 py-0.5 rounded-full capitalize">
                                    <?= str_replace('_', ' ', $l['source']) ?>
                                </span>
                                <?php if ($l['vehicle_interest']): ?>
                                    <span class="text-[10px] bg-mb-accent/10 text-mb-accent px-2 py-0.5 rounded-full">
                                        &#128663; <?= e($l['vehicle_interest']) ?>
                                    </span>
                                <?php endif; ?>
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
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    const stageTransitions = <?= json_encode($stageTransitions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
