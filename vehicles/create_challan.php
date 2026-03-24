<?php
require_once __DIR__ . '/../config/db.php';

if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to add challans.');
    redirect('index.php');
}

$pdo = db();
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $amount    = (float)($_POST['amount'] ?? 0);
    $dueDate   = trim($_POST['due_date'] ?? '');
    $clientId  = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    $notes     = trim($_POST['notes'] ?? '');

    if ($vehicleId <= 0) $errors['vehicle_id'] = 'Vehicle is required.';
    if (!$title)         $errors['title']      = 'Title is required.';
    if ($amount <= 0)    $errors['amount']     = 'Amount must be greater than 0.';

    // Block challan creation for sold vehicles
    if ($vehicleId > 0 && empty($errors['vehicle_id'])) {
        $vSoldCheck = $pdo->prepare('SELECT status FROM vehicles WHERE id = ?');
        $vSoldCheck->execute([$vehicleId]);
        $vSoldStatus = $vSoldCheck->fetchColumn();
        if ($vSoldStatus === 'sold') {
            $errors['vehicle_id'] = 'Cannot create a challan for a sold vehicle.';
        }
    }

    if (empty($errors)) {
        $dueDateValue = $dueDate !== '' ? $dueDate : null;

        $stmt = $pdo->prepare('INSERT INTO vehicle_challans (vehicle_id, client_id, title, amount, due_date, notes) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$vehicleId, $clientId, $title, $amount, $dueDateValue, $notes ?: null]);

        $newId = $pdo->lastInsertId();
        app_log('ACTION', "Created challan (ID: $newId) for vehicle ID: $vehicleId");
        flash('success', 'Challan added successfully.');
        redirect('challans.php');
    }
}

$vehicles = $pdo->query("SELECT id, brand, model, license_plate FROM vehicles WHERE status != 'sold' ORDER BY brand, model")->fetchAll();
$clients  = $pdo->query("SELECT id, name, phone FROM clients WHERE is_blacklisted = 0 ORDER BY name")->fetchAll();

$pageTitle = 'Add Challan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        <a href="challans.php" class="hover:text-white transition-colors">Challans</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        <span class="text-white">Add Challan</span>
    </div>

    <h2 class="text-white text-2xl font-light">Add New Challan</h2>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400 space-y-1">
            <?php foreach ($errors as $err): ?><p>&bull; <?= e($err) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">

            <!-- Vehicle -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Vehicle <span class="text-red-400">*</span></label>
                <select name="vehicle_id" required
                    class="w-full bg-mb-black border <?= isset($errors['vehicle_id']) ? 'border-red-500' : 'border-mb-subtle/20' ?> rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <option value="">Select vehicle...</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>" <?= ($old['vehicle_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                            <?= e($v['brand']) ?> <?= e($v['model']) ?> — <?= e($v['license_plate']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['vehicle_id'])): ?><p class="text-red-400 text-xs mt-1"><?= e($errors['vehicle_id']) ?></p><?php endif; ?>
            </div>

            <!-- Client (optional) -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Client <span class="text-mb-subtle font-normal">(optional)</span></label>
                <select name="client_id"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <option value="">Select client (optional)...</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($old['client_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?><?= $c['phone'] ? ' — ' . e($c['phone']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-mb-subtle text-xs mt-1">Link a client if this challan is related to a rental.</p>
            </div>

            <!-- Title -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Challan Title <span class="text-red-400">*</span></label>
                <input type="text" name="title" required value="<?= e($old['title'] ?? '') ?>"
                    placeholder="e.g., Speed violation — Highway 44"
                    class="w-full bg-mb-black border <?= isset($errors['title']) ? 'border-red-500' : 'border-mb-subtle/20' ?> rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                <?php if (!empty($errors['title'])): ?><p class="text-red-400 text-xs mt-1"><?= e($errors['title']) ?></p><?php endif; ?>
            </div>

            <!-- Amount + Due Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Amount <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                        <input type="number" name="amount" required step="0.01" min="0.01" value="<?= e($old['amount'] ?? '') ?>"
                            placeholder="0.00"
                            class="w-full bg-mb-black border <?= isset($errors['amount']) ? 'border-red-500' : 'border-mb-subtle/20' ?> rounded-lg pl-8 pr-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    </div>
                    <?php if (!empty($errors['amount'])): ?><p class="text-red-400 text-xs mt-1"><?= e($errors['amount']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Due Date <span class="text-mb-subtle font-normal">(optional)</span></label>
                    <input type="date" name="due_date" value="<?= e($old['due_date'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Notes <span class="text-mb-subtle font-normal">(optional)</span></label>
                <textarea name="notes" rows="3"
                    placeholder="Additional details about this challan..."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white placeholder-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm resize-y"><?= e($old['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="challans.php"
                class="px-6 py-3 rounded-full border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-white/30 transition-colors text-sm font-medium">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium shadow-lg shadow-mb-accent/20">Add Challan</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>