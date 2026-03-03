<?php
require_once __DIR__ . '/../config/db.php';
auth_require_admin();
$pdo = db();

$id = (int) ($_REQUEST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM emi_investments WHERE id=?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) {
    flash('error', 'Investment not found.');
    redirect('index.php');
}

// Check if any EMIs paid — lock structural fields
$paidCount = (int) $pdo->prepare("SELECT COUNT(*) FROM emi_schedules WHERE investment_id=? AND status='paid'")->execute([$id]) ? $pdo->query("SELECT COUNT(*) FROM emi_schedules WHERE investment_id=$id AND status='paid'")->fetchColumn() : 0;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $lender = trim($_POST['lender'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$title)
        $errors['title'] = 'Title is required.';

    if (empty($errors)) {
        $pdo->prepare("UPDATE emi_investments SET title=?, lender=?, notes=? WHERE id=?")
            ->execute([$title, $lender ?: null, $notes ?: null, $id]);
        flash('success', 'Investment updated.');
        redirect("show.php?id=$id");
    }

    $inv = array_merge($inv, ['title' => $title, 'lender' => $lender, 'notes' => $notes]);
}

$pageTitle = 'Edit Investment';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-xl mx-auto space-y-6">
    <div class="flex items-center gap-2 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white">Investments</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <a href="show.php?id=<?= $id ?>" class="hover:text-white">
            <?= e($inv['title']) ?>
        </a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Edit</span>
    </div>

    <?php if ($paidCount > 0): ?>
        <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg px-4 py-3 text-yellow-400 text-sm">
            ⚠️
            <?= $paidCount ?> EMI(s) already paid — financial fields (cost, EMI amount, tenure) are locked.
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
        <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Edit Investment</h3>

        <div>
            <label class="block text-sm text-mb-silver mb-2">Title <span class="text-red-400">*</span></label>
            <input type="text" name="title" value="<?= e($inv['title']) ?>"
                class="w-full bg-mb-black border <?= isset($errors['title']) ? 'border-red-500/50' : 'border-mb-subtle/20' ?> rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent">
            <?php if (isset($errors['title'])): ?>
                <p class="text-red-400 text-xs mt-1">
                    <?= e($errors['title']) ?>
                </p>
            <?php endif; ?>
        </div>

        <div>
            <label class="block text-sm text-mb-silver mb-2">Lender <span
                    class="text-mb-subtle text-xs">(optional)</span></label>
            <input type="text" name="lender" value="<?= e($inv['lender'] ?? '') ?>"
                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent">
        </div>

        <!-- Read-only financial info -->
        <div class="bg-mb-black/40 rounded-lg p-4 space-y-2 text-sm">
            <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2">Financial Details (read-only)</p>
            <div class="grid grid-cols-2 gap-3">
                <?php foreach ([
                    'Total Cost' => '$' . number_format($inv['total_cost'], 2),
                    'Down Payment' => '$' . number_format($inv['down_payment'], 2),
                    'Loan Amount' => '$' . number_format($inv['loan_amount'], 2),
                    'EMI Amount' => '$' . number_format($inv['emi_amount'], 2),
                    'Tenure' => $inv['tenure_months'] . ' months',
                    'Start Date' => date('d M Y', strtotime($inv['start_date'])),
                ] as $lbl => $val): ?>
                    <div>
                        <p class="text-mb-subtle text-xs">
                            <?= $lbl ?>
                        </p>
                        <p class="text-mb-silver text-sm">
                            <?= $val ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div>
            <label class="block text-sm text-mb-silver mb-2">Notes <span
                    class="text-mb-subtle text-xs">(optional)</span></label>
            <textarea name="notes" rows="2"
                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-mb-accent resize-none"><?= e($inv['notes'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="show.php?id=<?= $id ?>"
                class="text-mb-silver hover:text-white text-sm transition-colors">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-6 py-2.5 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">Save
                Changes</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>