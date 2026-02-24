<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();

// Ensure table & default row exist
$pdo->exec("CREATE TABLE IF NOT EXISTS system_settings (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$pdo->exec("INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('late_return_rate_per_hour', '0')");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rate = max(0, (float) ($_POST['late_return_rate_per_hour'] ?? 0));
    $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES ('late_return_rate_per_hour', ?)
        ON DUPLICATE KEY UPDATE `value` = ?")->execute([$rate, $rate]);
    flash('success', 'Settings saved successfully.');
    redirect('general.php');
}

$stmt = $pdo->query("SELECT `key`, `value` FROM system_settings");
$settings = [];
foreach ($stmt->fetchAll() as $row) {
    $settings[$row['key']] = $row['value'];
}
$lateRate = (float) ($settings['late_return_rate_per_hour'] ?? 0);

$pageTitle = 'General Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Navigation Tabs -->
    <div class="flex gap-1 bg-mb-surface border border-mb-subtle/20 p-1 rounded-full w-fit">
        <a href="general.php" class="px-6 py-2 rounded-full text-sm font-medium transition-all bg-mb-accent text-white shadow-lg shadow-mb-accent/20">General</a>
        <a href="damage_costs.php" class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Damage Costs</a>
    </div>
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <span class="text-white">Settings</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">General</span>
    </div>

    <?php $s = getFlash('success');
    if ($s): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            ✓
            <?= e($s) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <!-- Late Return Charges -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Late Return Charges</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">
                    Charged when a vehicle is returned after the scheduled end time.
                    <strong class="text-mb-silver">Grace period: 30 minutes</strong> — no charge if late by less than 30
                    mins.
                    Charge is rounded up to the nearest hour after the grace period.
                </p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">
                    Late Return Charge per Hour (
                    <?= e($_SERVER['REQUEST_SCHEME'] ?? 'USD') ?>)
                </label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                    <input type="number" name="late_return_rate_per_hour" value="<?= number_format($lateRate, 2) ?>"
                        min="0" step="0.01" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="0.00">
                </div>
                <p class="text-xs text-mb-subtle mt-1">Set to 0 to disable late return charges.</p>
            </div>

            <div class="bg-mb-black/30 rounded-lg p-4 text-xs text-mb-subtle space-y-1 border border-mb-subtle/10">
                <p class="text-mb-silver font-medium mb-2">Example:</p>
                <p>• Vehicle due at <strong class="text-white">3:00 PM</strong>, returned at <strong
                        class="text-white">3:20 PM</strong> → <strong class="text-green-400">No charge</strong> (within
                    30-min grace)</p>
                <p>• Vehicle due at <strong class="text-white">3:00 PM</strong>, returned at <strong
                        class="text-white">3:45 PM</strong> → <strong class="text-orange-400">1 hour charged</strong>
                    (45 min rounds up to 1h)</p>
                <p>• Vehicle due at <strong class="text-white">3:00 PM</strong>, returned at <strong
                        class="text-white">5:30 PM</strong> → <strong class="text-orange-400">3 hours charged</strong>
                </p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Save Settings
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>