<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/client_helpers.php';
require_once __DIR__ . '/../includes/activity_log.php';

$id  = (int)($_GET['id'] ?? 0);
$pdo = db();
$supportsClientProofs = clients_has_table($pdo, 'client_proofs');
$supportsClientPhoto  = clients_has_column($pdo, 'photo');

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'rate') {
        $rating = (int)($_POST['rating'] ?? 0);
        $review = trim($_POST['rating_review'] ?? '');
        if ($rating >= 1 && $rating <= 5) {
            $pdo->prepare('UPDATE clients SET rating=?, rating_review=? WHERE id=?')->execute([$rating, $review, $id]);
            app_log('ACTION', "Updated client rating to $rating stars with review (ID: $id)");
            log_activity($pdo, 'rate_client', 'client', $id, "Rated client $rating stars");
            flash('success', "Rating updated to $rating stars.");
        }
        redirect("show.php?id=$id");
    }

    if ($action === 'blacklist') {
        $cStmt = $pdo->prepare('SELECT is_blacklisted, name FROM clients WHERE id=?');
        $cStmt->execute([$id]);
        $c = $cStmt->fetch();
        if ($c['is_blacklisted']) {
            $pdo->prepare('UPDATE clients SET is_blacklisted=0, blacklist_reason=NULL WHERE id=?')->execute([$id]);
            app_log('ACTION', "Removed client from blacklist: {$c['name']} (ID: $id)");
            log_activity($pdo, 'unblacklist_client', 'client', $id, "Removed {$c['name']} from blacklist");
            flash('success', "{$c['name']} removed from blacklist.");
        } else {
            $reason = trim($_POST['blacklist_reason'] ?? '');
            $pdo->prepare('UPDATE clients SET is_blacklisted=1, blacklist_reason=? WHERE id=?')->execute([$reason, $id]);
            app_log('ACTION', "Added client to blacklist: {$c['name']} (ID: $id)");
            log_activity($pdo, 'blacklist_client', 'client', $id, "Blacklisted {$c['name']}" . ($reason ? " — $reason" : ''));
            flash('success', "{$c['name']} added to blacklist.");
        }
        redirect("show.php?id=$id");
    }
}

$cStmt = $pdo->prepare('SELECT * FROM clients WHERE id=?');
$cStmt->execute([$id]);
$c = $cStmt->fetch();
if (!$c) {
    flash('error', 'Client not found.');
    redirect('index.php');
}

// Client photo
$clientPhoto = ($supportsClientPhoto && !empty($c['photo'])) ? $c['photo'] : null;

$clientProofs = [];
if ($supportsClientProofs) {
    try {
        $pStmt = $pdo->prepare('SELECT * FROM client_proofs WHERE client_id = ? ORDER BY created_at DESC');
        $pStmt->execute([$id]);
        $clientProofs = $pStmt->fetchAll();
    } catch (Throwable $e) { $clientProofs = []; }
}
$legacyProofFile = $c['proof_file'] ?? null;

$resStmt = $pdo->prepare('SELECT r.*, v.brand, v.model, v.license_plate FROM reservations r JOIN vehicles v ON r.vehicle_id = v.id WHERE r.client_id = ? ORDER BY r.created_at DESC');
$resStmt->execute([$id]);
$reservations = $resStmt->fetchAll();

// Held deposits: find all reservations for this client with unresolved held deposits
$heldDeposits = [];
try {
    $heldColExists = (int) $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='deposit_held'")->fetchColumn();
    if ($heldColExists) {
        $heldStmt = $pdo->prepare("SELECT r.id, r.deposit_held, r.deposit_hold_reason, r.deposit_held_at, r.deposit_held_action, v.brand, v.model, v.license_plate FROM reservations r JOIN vehicles v ON r.vehicle_id = v.id WHERE r.client_id = ? AND r.deposit_held > 0 AND (r.deposit_held_action IS NULL OR r.deposit_held_action = '') ORDER BY r.deposit_held_at ASC");
        $heldStmt->execute([$id]);
        $heldDeposits = $heldStmt->fetchAll();
    }
} catch (Throwable $e) { $heldDeposits = []; }

try {
    $reviewsStmt = $pdo->prepare('SELECT cr.*, cr.reservation_id AS res_id, u.name AS reviewer_name FROM client_reviews cr LEFT JOIN users u ON cr.created_by = u.id WHERE cr.client_id = ? ORDER BY cr.created_at DESC');
    $reviewsStmt->execute([$id]);
    $clientReviews = $reviewsStmt->fetchAll();
} catch (Exception $e) { $clientReviews = []; }

$totalSpent     = array_sum(array_column($reservations, 'total_price'));
$activeRentals  = count(array_filter($reservations, fn($r) => $r['status'] === 'active'));
$completedCount = count(array_filter($reservations, fn($r) => $r['status'] === 'completed'));

$success = getFlash('success');
$error   = getFlash('error');
$pageTitle = e($c['name']);
require_once __DIR__ . '/../includes/header.php';
?>

<div class="space-y-6">
    <?php if ($success): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Clients</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        <span class="text-white"><?= e($c['name']) ?></span>
    </div>

    <?php if (!empty($heldDeposits)): ?>
        <div class="bg-yellow-500/5 border border-yellow-500/30 rounded-xl p-5 space-y-3">
            <div class="flex items-center gap-2 text-yellow-400 font-medium">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                Held Security Deposit<?= count($heldDeposits) > 1 ? 's' : '' ?> Pending Resolution
            </div>
            <p class="text-mb-subtle text-xs ml-7">The following reservation<?= count($heldDeposits) > 1 ? 's have' : ' has a' ?> security deposit on hold that has not been released or converted.</p>
            <div class="space-y-2 ml-7">
                <?php foreach ($heldDeposits as $hd):
                    $heldAt = $hd['deposit_held_at'] ? new DateTime($hd['deposit_held_at']) : null;
                    $daysHeld = $heldAt ? (int) $heldAt->diff(new DateTime())->days : 0;
                    $isOverdue = $daysHeld >= 7;
                ?>
                    <div class="flex items-center justify-between gap-4 bg-mb-black/40 border border-yellow-500/20 rounded-lg px-4 py-3 text-sm <?= $isOverdue ? 'border-red-500/40' : '' ?>">
                        <div>
                            <a href="../reservations/show.php?id=<?= $hd['id'] ?>" class="text-mb-accent hover:underline font-medium">
                                Reservation #<?= $hd['id'] ?>
                            </a>
                            <span class="text-mb-subtle ml-2"><?= e($hd['brand'] . ' ' . $hd['model']) ?> · <?= e($hd['license_plate']) ?></span>
                            <?php if ($hd['deposit_hold_reason']): ?>
                                <p class="text-mb-subtle text-xs mt-0.5">Reason: <?= e($hd['deposit_hold_reason']) ?></p>
                            <?php endif; ?>
                            <?php if ($heldAt): ?>
                                <p class="text-xs mt-0.5 <?= $isOverdue ? 'text-red-400' : 'text-mb-subtle' ?>">
                                    Held <?= $daysHeld ?> day<?= $daysHeld !== 1 ? 's' : '' ?> ago
                                    <?= $isOverdue ? '⚠ Overdue' : '' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="text-yellow-400 font-bold">$<?= number_format((float)$hd['deposit_held'], 2) ?></span>
                            <div class="mt-1">
                                <a href="../reservations/show.php?id=<?= $hd['id'] ?>"
                                    class="text-xs text-mb-accent hover:underline">Resolve →</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Profile Card -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 <?= $c['is_blacklisted'] ? 'border-red-500/30' : '' ?>">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
            <div class="flex items-start gap-5">
                <!-- Avatar: photo if available, otherwise letter avatar -->
                <?php if ($clientPhoto): ?>
                    <img src="../<?= e($clientPhoto) ?>" alt="<?= e($c['name']) ?>"
                        class="w-16 h-16 rounded-full object-cover border-2 border-mb-accent/50 flex-shrink-0">
                <?php else: ?>
                    <div class="w-16 h-16 rounded-full flex items-center justify-center text-2xl font-light flex-shrink-0
                        <?= $c['is_blacklisted'] ? 'bg-red-500/20 text-red-400' : 'bg-mb-accent/10 text-mb-accent' ?>">
                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-white text-2xl font-light"><?= e($c['name']) ?></h2>
                        <?php if ($c['is_blacklisted']): ?>
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-red-500/15 text-red-400 border border-red-500/25 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" /></svg>
                                BLACKLISTED
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-mb-silver text-sm mt-1"><?= e($c['email']) ?></p>
                    <p class="text-mb-subtle text-sm"><?= e($c['phone']) ?></p>
                    <?php if (!empty($c['alternative_number'])): ?>
                        <p class="text-mb-subtle text-sm">Alt: <?= e($c['alternative_number']) ?></p>
                    <?php endif; ?>
                    <?php if ($c['address']): ?>
                        <p class="text-mb-subtle text-xs mt-1"><?= e($c['address']) ?></p>
                    <?php endif; ?>
                    <?php if ($c['is_blacklisted'] && $c['blacklist_reason']): ?>
                        <p class="text-red-400 text-xs mt-2 italic">Reason: <?= e($c['blacklist_reason']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <a href="edit.php?id=<?= $id ?>"
                    class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm">Edit</a>
                <?php if (!$activeRentals): ?>
                    <a href="delete.php?id=<?= $id ?>" onclick="return confirm('Remove this client?')"
                        class="border border-red-500/30 text-red-400 px-5 py-2 rounded-full hover:bg-red-500/10 transition-colors text-sm">Delete</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($c['is_blacklisted'] && $c['blacklist_reason']): ?>
            <div class="mt-4 bg-red-500/10 border border-red-500/20 rounded-lg px-4 py-3">
                <p class="text-red-400 text-xs uppercase font-medium mb-1">Blacklist Reason</p>
                <p class="text-red-300 text-sm"><?= e($c['blacklist_reason']) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($c['notes']): ?>
            <div class="mt-4 bg-mb-black/30 border border-mb-subtle/10 rounded-lg px-4 py-3">
                <p class="text-mb-subtle text-xs uppercase font-medium mb-1">Internal Notes</p>
                <p class="text-mb-silver text-sm"><?= e($c['notes']) ?></p>
            </div>
        <?php endif; ?>
        <?php $totalProofs = ($legacyProofFile ? 1 : 0) + count($clientProofs); ?>
        <?php if ($legacyProofFile || !empty($clientProofs)): ?>
            <div class="mt-4 bg-mb-black/30 border border-mb-subtle/10 rounded-lg px-4 py-3">
                <p class="text-mb-subtle text-xs uppercase font-medium mb-3">ID / Proof Documents
                    <span class="text-mb-subtle text-[10px] ml-2"><?= $totalProofs ?> file(s)</span>
                </p>
                <?php if ($legacyProofFile): ?>
                    <?php $proofExt = strtolower(pathinfo($legacyProofFile, PATHINFO_EXTENSION)); ?>
                    <?php if (in_array($proofExt, ['jpg', 'jpeg', 'png'])): ?>
                        <a href="<?= $root . e($legacyProofFile) ?>" target="_blank">
                            <img src="<?= $root . e($legacyProofFile) ?>" alt="Proof document"
                                class="max-h-48 rounded-lg border border-mb-subtle/20 hover:opacity-80 transition-opacity">
                        </a>
                    <?php else: ?>
                        <a href="<?= $root . e($legacyProofFile) ?>" target="_blank"
                            class="inline-flex items-center gap-2 bg-mb-accent/10 border border-mb-accent/30 text-mb-accent px-4 py-2 rounded-lg text-sm hover:bg-mb-accent/20 transition-colors">
                            View legacy document
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($clientProofs)): ?>
                    <div class="mt-3 space-y-3">
                        <?php foreach ($clientProofs as $doc):
                            $docExt  = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                            $isImage = in_array($docExt, ['jpg', 'jpeg', 'png']);
                        ?>
                            <?php if ($isImage): ?>
                                <div class="bg-mb-black/30 border border-mb-subtle/20 rounded-lg p-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-white text-xs truncate mb-1"><?= e($doc['title'] ?: basename($doc['file_path'])) ?></p>
                                            <p class="text-mb-subtle text-[10px] uppercase mb-2"><?= e($doc['type'] ?? '') ?></p>
                                            <a href="<?= $root . e($doc['file_path']) ?>" target="_blank">
                                                <img src="<?= $root . e($doc['file_path']) ?>" alt="Proof document"
                                                    class="max-h-32 rounded-lg border border-mb-subtle/20 hover:opacity-80 transition-opacity">
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center justify-between gap-3 bg-mb-black/30 border border-mb-subtle/20 rounded-lg px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="text-white text-xs truncate"><?= e($doc['title'] ?: basename($doc['file_path'])) ?></p>
                                        <p class="text-mb-subtle text-[10px] uppercase"><?= e($doc['type'] ?? '') ?></p>
                                    </div>
                                    <a href="<?= $root . e($doc['file_path']) ?>" target="_blank" class="text-mb-accent text-xs hover:underline">View</a>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left column: Stats + Rating + Blacklist -->
        <div class="space-y-6">
            <!-- Stats -->
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 space-y-4">
                <h3 class="text-white font-light border-l-2 border-mb-accent pl-3">Overview</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-mb-black/50 rounded-lg p-3 text-center">
                        <p class="text-2xl font-light text-white"><?= count($reservations) ?></p>
                        <p class="text-mb-subtle text-xs">Total Rentals</p>
                    </div>
                    <div class="bg-mb-black/50 rounded-lg p-3 text-center">
                        <p class="text-2xl font-light text-green-400">$<?= number_format($totalSpent, 0) ?></p>
                        <p class="text-mb-subtle text-xs">Total Spent</p>
                    </div>
                    <div class="bg-mb-black/50 rounded-lg p-3 text-center">
                        <p class="text-2xl font-light text-mb-accent"><?= $activeRentals ?></p>
                        <p class="text-mb-subtle text-xs">Active Now</p>
                    </div>
                    <div class="bg-mb-black/50 rounded-lg p-3 text-center">
                        <p class="text-2xl font-light text-mb-silver"><?= $completedCount ?></p>
                        <p class="text-mb-subtle text-xs">Completed</p>
                    </div>
                </div>
            </div>

            <!-- Star Rating Widget -->
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5">
                <h3 class="text-white font-light border-l-2 border-mb-accent pl-3 mb-4">Client Rating</h3>
                <div class="text-center mb-4">
                    <?php if ($c['rating']): ?>
                        <div class="text-3xl tracking-wider text-yellow-400"><?= starDisplay($c['rating']) ?></div>
                        <p class="text-mb-silver text-sm mt-1"><?= ['', 'Poor', 'Below Average', 'Average', 'Good', 'Excellent'][$c['rating']] ?></p>
                    <?php else: ?>
                        <div class="text-3xl tracking-wider text-mb-subtle/30">☆☆☆☆☆</div>
                        <p class="text-mb-subtle text-sm mt-1 italic">Not yet rated</p>
                    <?php endif; ?>
                </div>
                <form method="POST" id="rateForm">
                    <input type="hidden" name="_action" value="rate">
                    <input type="hidden" name="rating" id="ratingInput" value="<?= $c['rating'] ?? 0 ?>">
                    <div class="flex justify-center gap-2 mb-4" id="starPicker">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <button type="button" onclick="setRating(<?= $i ?>)"
                                class="star-btn text-3xl transition-all duration-150 hover:scale-125" data-star="<?= $i ?>">
                                <?= $i <= ($c['rating'] ?? 0) ? '★' : '☆' ?>
                            </button>
                        <?php endfor; ?>
                    </div>
                    <div class="text-center"><span id="ratingLabel" class="text-mb-subtle text-sm"><?= ['', 'Poor', 'Below Average', 'Average', 'Good', 'Excellent'][$c['rating'] ?? 0] ?? '' ?></span></div>
                    <div class="space-y-1.5 mb-4">
                        <label class="text-xs text-mb-subtle uppercase tracking-wider font-medium ml-1">Review Notes</label>
                        <textarea name="rating_review" rows="3" placeholder="Enter review or notes about this client..."
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-mb-accent/50 transition-colors placeholder-mb-subtle/50 resize-none"><?= e($c['rating_review'] ?? '') ?></textarea>
                    </div>
                    <button type="submit"
                        class="mt-4 w-full bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 py-2 rounded-lg hover:bg-yellow-500/20 transition-colors text-sm font-medium">Save Rating</button>
                </form>
            </div>

            <!-- Blacklist Controls -->
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-5 <?= $c['is_blacklisted'] ? 'border-red-500/30' : '' ?>">
                <h3 class="text-white font-light border-l-2 border-<?= $c['is_blacklisted'] ? 'red-500' : 'mb-accent' ?> pl-3 mb-4">Blacklist</h3>
                <?php if ($c['is_blacklisted']): ?>
                    <p class="text-red-400/80 text-sm mb-4">This client is currently blacklisted and cannot make new reservations.</p>
                    <form method="POST">
                        <input type="hidden" name="_action" value="blacklist">
                        <button type="submit" class="w-full bg-green-500/10 border border-green-500/30 text-green-400 py-2.5 rounded-lg hover:bg-green-500/20 transition-colors text-sm font-medium">✓ Remove from Blacklist</button>
                    </form>
                <?php else: ?>
                    <p class="text-mb-subtle text-sm mb-4">Blacklisting will flag this client across all operations.</p>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="_action" value="blacklist">
                        <textarea name="blacklist_reason" rows="2" placeholder="Reason for blacklisting (optional)..."
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-red-500/50 transition-colors placeholder-mb-subtle/50 resize-none"></textarea>
                        <button type="submit" onclick="return confirm('Blacklist <?= addslashes(e($c['name'])) ?>? They will be flagged across all operations.')"
                            class="w-full bg-red-500/10 border border-red-500/30 text-red-400 py-2.5 rounded-lg hover:bg-red-500/20 transition-colors text-sm font-medium">🚫 Add to Blacklist</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right column: Rental History -->
        <div class="lg:col-span-2 bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10">
                <h3 class="text-white font-light text-lg">Rental History</h3>
            </div>
            <?php if (empty($reservations)): ?>
                <div class="py-16 text-center text-mb-subtle text-sm italic">No rental history yet.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-mb-black/40 text-mb-subtle uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-5 py-3">Vehicle</th>
                                <th class="px-5 py-3">Dates</th>
                                <th class="px-5 py-3">Type</th>
                                <th class="px-5 py-3 text-right">Total</th>
                                <th class="px-5 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-mb-subtle/10">
                            <?php foreach ($reservations as $r):
                                $statusCls = ['active' => 'bg-mb-accent/10 text-mb-accent', 'completed' => 'bg-green-500/10 text-green-400'][$r['status']] ?? 'bg-mb-subtle/10 text-mb-subtle';
                            ?>
                                <tr class="hover:bg-mb-black/10 transition-colors">
                                    <td class="px-5 py-3">
                                        <p class="text-white"><?= e($r['brand']) ?> <?= e($r['model']) ?></p>
                                        <p class="text-mb-subtle text-xs"><?= e($r['license_plate']) ?></p>
                                    </td>
                                    <td class="px-5 py-3 text-mb-silver"><?= e($r['start_date']) ?> – <?= e($r['end_date']) ?></td>
                                    <td class="px-5 py-3 text-mb-subtle capitalize"><?= e($r['rental_type']) ?></td>
                                    <td class="px-5 py-3 text-right text-mb-accent">$<?= number_format($r['total_price'], 0) ?></td>
                                    <td class="px-5 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-xs <?= $statusCls ?>"><?= ucfirst($r['status']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .star-btn { color: #4B5563; }
    .star-btn.active { color: #EAB308; }
</style>
<script>
    const labels = ['', 'Poor', 'Below Average', 'Average', 'Good', 'Excellent'];
    let currentRating = <?= (int)($c['rating'] ?? 0) ?>;
    function setRating(val) {
        currentRating = val;
        document.getElementById('ratingInput').value = val;
        document.getElementById('ratingLabel').textContent = labels[val];
        document.querySelectorAll('#starPicker .star-btn').forEach((btn, i) => {
            btn.textContent = (i + 1) <= val ? '★' : '☆';
            btn.style.color = (i + 1) <= val ? '#EAB308' : '#4B5563';
        });
    }
    document.querySelectorAll('#starPicker .star-btn').forEach((btn, i) => {
        if ((i + 1) <= currentRating) btn.style.color = '#EAB308';
        btn.addEventListener('mouseenter', () => {
            document.querySelectorAll('#starPicker .star-btn').forEach((b, j) => {
                b.textContent = (j + 1) <= (i + 1) ? '★' : '☆';
                b.style.color = (j + 1) <= (i + 1) ? '#EAB308' : '#6B7280';
            });
        });
        btn.addEventListener('mouseleave', () => {
            document.querySelectorAll('#starPicker .star-btn').forEach((b, j) => {
                b.textContent = (j + 1) <= currentRating ? '★' : '☆';
                b.style.color = (j + 1) <= currentRating ? '#EAB308' : '#4B5563';
            });
            document.getElementById('ratingLabel').textContent = labels[currentRating] || 'Not Rated';
        });
    });
</script>

<!-- Client Review History -->
<div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4 mt-6">
    <h3 class="text-white font-light border-l-2 border-mb-accent pl-3">Review History</h3>
    <?php if (empty($clientReviews)): ?>
        <p class="text-mb-subtle text-sm italic">No reviews recorded yet.</p>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($clientReviews as $rev): ?>
                <div class="bg-mb-black/40 border border-mb-subtle/10 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3">
                            <span class="text-yellow-400 text-sm"><?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?></span>
                            <a href="../reservations/show.php?id=<?= $rev['res_id'] ?>" class="text-xs text-mb-accent hover:underline">#<?= $rev['res_id'] ?></a>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-xs text-mb-subtle"><?= date('d M Y', strtotime($rev['created_at'])) ?></span>
                            <?php if (!empty($rev['reviewer_name'])): ?>
                                <span class="text-xs text-mb-accent/70 mt-0.5">by <?= e($rev['reviewer_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($rev['review']): ?>
                        <p class="text-sm text-mb-silver mt-1"><?= e($rev['review']) ?></p>
                    <?php else: ?>
                        <p class="text-xs text-mb-subtle italic">No written review for this rental.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>