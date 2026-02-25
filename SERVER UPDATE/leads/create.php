<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();
$errors = [];
$leadSourcesMap = lead_sources_get_map($pdo);
$defaultSource = array_key_exists('phone', $leadSourcesMap) ? 'phone' : (array_key_first($leadSourcesMap) ?? 'phone');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $inquiry = $_POST['inquiry_type'] ?? 'daily';
    $vehicle = trim($_POST['vehicle_interest'] ?? '');
    $source = $_POST['source'] ?? $defaultSource;
    $assigned = trim($_POST['assigned_to'] ?? '');
    $status = $_POST['status'] ?? 'new';
    $notes = trim($_POST['notes'] ?? '');

    // Map workflow wording "Uncontacted" to the DB enum value "new".
    if ($status === 'uncontacted') {
        $status = 'new';
    }
    $allowedInitialStatuses = ['new', 'contacted'];

    if (!$name)
        $errors['name'] = 'Name is required.';
    if (!$phone)
        $errors['phone'] = 'Phone is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Invalid email format.';
    if (!array_key_exists($source, $leadSourcesMap))
        $errors['source'] = 'Please select a valid lead source.';
    if (!in_array($status, $allowedInitialStatuses, true))
        $errors['status'] = 'Please select a valid initial lead status.';

    if (empty($errors)) {
        $pdo->prepare('INSERT INTO leads (name,phone,email,inquiry_type,vehicle_interest,source,assigned_to,status,notes) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$name, $phone, $email ?: null, $inquiry, $vehicle ?: null, $source, $assigned ?: null, $status, $notes ?: null]);
        $newId = $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')
            ->execute([$newId, 'Lead created with status: ' . str_replace('_', ' ', $status) . '.']);
        flash('success', "Lead \"$name\" added successfully.");
        redirect("show.php?id=$newId");
    }
}

$pageTitle = 'Add New Lead';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Leads</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Add Lead</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400 space-y-1">
            <?php foreach ($errors as $e): ?>
                <p>&bull;
                    <?= e($e) ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Lead Information</h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Name -->
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Full Name <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required
                        placeholder="Ahmed Al Rashid"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <?php if ($errors['name'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['name']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Phone -->
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Phone / WhatsApp <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" required
                        placeholder="+971 50 123 4567"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <?php if ($errors['phone'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['phone']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Email -->
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Email <span
                            class="text-mb-subtle text-xs">(optional)</span></label>
                    <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>"
                        placeholder="ahmed@example.com"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <?php if ($errors['email'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['email']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Inquiry Type -->
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Inquiry Type <span
                            class="text-red-400">*</span></label>
                    <select name="inquiry_type"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <?php foreach (['daily' => 'Daily Rental', 'weekly' => 'Weekly Rental', 'monthly' => 'Monthly Rental', 'other' => 'Other'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($_POST['inquiry_type'] ?? 'daily') === $v ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Vehicle Interest -->
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Vehicle Interest <span
                            class="text-mb-subtle text-xs">(optional)</span></label>
                    <input type="text" name="vehicle_interest" value="<?= e($_POST['vehicle_interest'] ?? '') ?>"
                        placeholder="e.g. SUV, Toyota Camry"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <!-- Source -->
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Lead Source <span
                            class="text-red-400">*</span></label>
                    <select name="source"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <?php foreach ($leadSourcesMap as $v => $l): ?>
                            <option value="<?= e($v) ?>" <?= ($_POST['source'] ?? $defaultSource) === $v ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errors['source'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['source']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Initial Status -->
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Initial Status <span
                            class="text-red-400">*</span></label>
                    <select name="status"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <option value="new" <?= ($_POST['status'] ?? 'new') === 'new' ? 'selected' : '' ?>>New (Uncontacted)</option>
                        <option value="contacted" <?= ($_POST['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                    </select>
                    <?php if ($errors['status'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['status']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <!-- Assigned To -->
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Assigned To <span
                            class="text-mb-subtle text-xs">(optional)</span></label>
                    <input type="text" name="assigned_to" value="<?= e($_POST['assigned_to'] ?? '') ?>"
                        placeholder="Staff member name"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Notes <span
                        class="text-mb-subtle text-xs">(optional)</span></label>
                <textarea name="notes" rows="3" placeholder="Any additional notes about this lead..."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= e($_POST['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="index.php" class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Add Lead
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
