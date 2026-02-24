<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM leads WHERE id=?');
$stmt->execute([$id]);
$lead = $stmt->fetch();
if (!$lead) {
    flash('error', 'Lead not found.');
    redirect('index.php');
}

// Fetch followups
$followups = $pdo->prepare('SELECT * FROM lead_followups WHERE lead_id=? ORDER BY scheduled_at ASC');
$followups->execute([$id]);
$followups = $followups->fetchAll();

// Fetch activities
$activities = $pdo->prepare('SELECT * FROM lead_activities WHERE lead_id=? ORDER BY created_at DESC');
$activities->execute([$id]);
$activities = $activities->fetchAll();

// Flash messages
$success = getFlash('success');
$error = getFlash('error');

$now = new DateTime();
$statusColors = [
    'new' => 'bg-sky-500/10 text-sky-400 border border-sky-500/30',
    'contacted' => 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/30',
    'interested' => 'bg-purple-500/10 text-purple-400 border border-purple-500/30',
    'negotiation' => 'bg-orange-500/10 text-orange-400 border border-orange-500/30',
    'closed_won' => 'bg-green-500/10 text-green-400 border border-green-500/30',
    'closed_lost' => 'bg-red-500/10 text-red-400/60 border border-red-500/20',
];
$followupIcons = ['call' => '📞', 'email' => '📧', 'whatsapp' => '💬', 'meeting' => '🤝'];

$pageTitle = e($lead['name']) . ' — Lead';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <?php if ($success): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div
            class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z" />
            </svg>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Leads</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">
            <?= e($lead['name']) ?>
        </span>
    </div>

    <!-- Lead Info Card -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-white text-xl font-light">
                        <?= e($lead['name']) ?>
                    </h2>
                    <span
                        class="px-2.5 py-1 rounded-full text-xs capitalize <?= $statusColors[$lead['status']] ?? '' ?>">
                        <?= str_replace('_', ' ', $lead['status']) ?>
                    </span>
                </div>
                <div class="flex flex-wrap gap-5 mt-3 text-sm text-mb-silver">
                    <span>📞
                        <?= e($lead['phone']) ?>
                    </span>
                    <?php if ($lead['email']): ?><span>✉️
                            <?= e($lead['email']) ?>
                        </span>
                    <?php endif; ?>
                    <span>🎯
                        <?= ucfirst(str_replace('_', ' ', $lead['inquiry_type'])) ?>
                    </span>
                    <span>📌
                        <?= ucfirst(str_replace('_', ' ', $lead['source'])) ?>
                    </span>
                    <?php if ($lead['vehicle_interest']): ?><span>🚗
                            <?= e($lead['vehicle_interest']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($lead['assigned_to']): ?><span>👤
                            <?= e($lead['assigned_to']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($lead['notes']): ?>
                    <p class="mt-3 text-mb-subtle text-sm">
                        <?= nl2br(e($lead['notes'])) ?>
                    </p>
                <?php endif; ?>
                <?php if ($lead['lost_reason']): ?>
                    <div class="mt-3 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-2 text-sm text-red-400">
                        <strong>Lost reason:</strong>
                        <?= e($lead['lost_reason']) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <?php if ($lead['status'] === 'closed_won'): ?>
                    <?php if ($lead['converted_client_id']): ?>
                        <a href="../clients/show.php?id=<?= $lead['converted_client_id'] ?>"
                            class="bg-green-600/20 border border-green-500/40 text-green-400 px-4 py-2 rounded-full text-sm hover:bg-green-600/30 transition-colors">✅
                            View Client</a>
                    <?php else: ?>
                        <form action="convert.php" method="POST"
                            onsubmit="return confirm('Convert this lead into a new Client?')">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <button type="submit"
                                class="bg-mb-accent/20 border border-mb-accent/40 text-mb-accent px-4 py-2 rounded-full text-sm hover:bg-mb-accent/30 transition-colors">🔄
                                Convert to Client</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="edit.php?id=<?= $id ?>"
                    class="border border-mb-subtle/30 text-mb-silver px-4 py-2 rounded-full text-sm hover:border-mb-accent/50 hover:text-white transition-colors">Edit</a>
                <form action="delete.php" method="POST" onsubmit="return confirm('Delete this lead permanently?')">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit"
                        class="border border-red-500/20 text-red-400/70 px-4 py-2 rounded-full text-sm hover:border-red-500/50 hover:text-red-400 transition-colors">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Two Column: Followups + Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Follow-ups Panel -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10">
                <h3 class="text-white font-light">Follow-ups</h3>
            </div>
            <div class="p-6 space-y-3">
                <?php if (empty($followups)): ?>
                    <p class="text-mb-subtle text-sm text-center py-4">No follow-ups scheduled yet.</p>
                <?php else: ?>
                    <?php foreach ($followups as $fu):
                        $schDt = new DateTime($fu['scheduled_at']);
                        $isOver = !$fu['is_done'] && $schDt < $now;
                        ?>
                        <div
                            class="flex items-start gap-3 p-3 rounded-lg <?= $fu['is_done'] ? 'bg-mb-black/20 opacity-50' : ($isOver ? 'bg-red-500/5 border border-red-500/20' : 'bg-mb-black/30') ?>">
                            <span class="text-lg">
                                <?= $followupIcons[$fu['type']] ?? '📋' ?>
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span
                                        class="text-white text-sm capitalize font-medium <?= $fu['is_done'] ? 'line-through' : '' ?>">
                                        <?= ucfirst($fu['type']) ?>
                                    </span>
                                    <?php if ($isOver): ?><span
                                            class="text-[10px] bg-red-500/20 text-red-400 px-2 py-0.5 rounded-full animate-pulse">Overdue</span>
                                    <?php endif; ?>
                                    <?php if ($fu['is_done']): ?><span
                                            class="text-[10px] bg-green-500/10 text-green-400 px-2 py-0.5 rounded-full">Done</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-mb-subtle text-xs mt-0.5">
                                    <?= date('d M Y, h:i A', strtotime($fu['scheduled_at'])) ?>
                                </p>
                                <?php if ($fu['notes']): ?>
                                    <p class="text-mb-silver text-xs mt-1">
                                        <?= e($fu['notes']) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <?php if (!$fu['is_done']): ?>
                                <form action="followup_done.php" method="POST">
                                    <input type="hidden" name="followup_id" value="<?= $fu['id'] ?>">
                                    <input type="hidden" name="lead_id" value="<?= $id ?>">
                                    <button type="submit" title="Mark done"
                                        class="w-6 h-6 rounded-full border border-green-500/30 text-green-400 hover:bg-green-500/20 transition-colors text-xs flex items-center justify-center">✓</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Add followup form -->
                <div class="border-t border-mb-subtle/10 pt-4 mt-4">
                    <p class="text-mb-subtle text-xs uppercase tracking-wider mb-3">Schedule Follow-up</p>
                    <form action="followup_save.php" method="POST" class="space-y-2">
                        <input type="hidden" name="lead_id" value="<?= $id ?>">
                        <div class="grid grid-cols-2 gap-2">
                            <select name="type"
                                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                                <option value="call">📞 Call</option>
                                <option value="whatsapp">💬 WhatsApp</option>
                                <option value="email">📧 Email</option>
                                <option value="meeting">🤝 Meeting</option>
                            </select>
                            <input type="date" name="date" value="<?= date('Y-m-d') ?>" required
                                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                        </div>
                        <input type="time" name="time" value="10:00"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
                        <textarea name="notes" rows="2" placeholder="What to discuss / objective…"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent resize-none"></textarea>
                        <button type="submit"
                            class="w-full bg-mb-accent/20 border border-mb-accent/40 text-mb-accent rounded-lg py-2 text-sm hover:bg-mb-accent/30 transition-colors">+
                            Schedule</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Activity Log Panel -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10">
                <h3 class="text-white font-light">Activity Log</h3>
            </div>
            <div class="p-6 space-y-4">
                <!-- Log note form -->
                <form action="activity_save.php" method="POST" class="space-y-2">
                    <input type="hidden" name="lead_id" value="<?= $id ?>">
                    <textarea name="note" rows="2" placeholder="Log an interaction, email sent, note…"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent resize-none"></textarea>
                    <button type="submit"
                        class="bg-mb-black border border-mb-subtle/20 text-mb-silver px-4 py-1.5 rounded-lg text-xs hover:border-mb-accent/50 hover:text-white transition-colors">Save
                        Note</button>
                </form>

                <!-- Activity timeline -->
                <div class="space-y-3 border-l-2 border-mb-subtle/10 pl-4">
                    <?php if (empty($activities)): ?>
                        <p class="text-mb-subtle text-sm">No activity yet.</p>
                    <?php else: ?>
                        <?php foreach ($activities as $act): ?>
                            <div>
                                <p class="text-mb-silver text-sm">
                                    <?= e($act['note']) ?>
                                </p>
                                <p class="text-mb-subtle text-[10px] mt-0.5">
                                    <?= date('d M Y, h:i A', strtotime($act['created_at'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
