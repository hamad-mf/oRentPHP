<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/export_enabled.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();

settings_ensure_table($pdo);
if (settings_get($pdo, 'late_return_rate_per_hour', '') === '') {
    settings_set($pdo, 'late_return_rate_per_hour', '0');
}
if (settings_get($pdo, 'deposit_percentage', '') === '') {
    settings_set($pdo, 'deposit_percentage', '0');
}
if (settings_get($pdo, 'delivery_charge_default', '') === '') {
    settings_set($pdo, 'delivery_charge_default', '0');
}

$leadSources = lead_sources_get_map($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rate = max(0, (float) ($_POST['late_return_rate_per_hour'] ?? 0));
    settings_set($pdo, 'late_return_rate_per_hour', (string) $rate);
    $depositPct = min(100, max(0, (float) ($_POST['deposit_percentage'] ?? 0)));
    settings_set($pdo, 'deposit_percentage', (string) $depositPct);
    $deliveryChargeDefault = max(0, (float) ($_POST['delivery_charge_default'] ?? 0));
    settings_set($pdo, 'delivery_charge_default', (string) $deliveryChargeDefault);
    app_log('ACTION', 'Updated general settings');
flash('success', 'Settings saved successfully.');
    redirect('general.php');
}

$lateRate = (float) settings_get($pdo, 'late_return_rate_per_hour', '0');
$depositPct = (float) settings_get($pdo, 'deposit_percentage', '0');
$deliveryChargeDefault = (float) settings_get($pdo, 'delivery_charge_default', '0');

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex gap-1 bg-mb-surface border border-mb-subtle/20 p-1 rounded-full w-fit flex-wrap">
        <a href="general.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all bg-mb-accent text-white shadow-lg shadow-mb-accent/20">Return
            Charges</a>
        <a href="damage_costs.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Damage
            Costs</a>
        <a href="lead_sources.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Lead
            Sources</a>
        <a href="staff_permissions.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Staff
            Permissions</a>
        <a href="attendance.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Attendance</a>
    </div>

    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <span class="text-white">Settings</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Return Charges</span>
    </div>

    <?php $s = getFlash('success');
    if ($s): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            Success:
            <?= e($s) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Late Return Charges</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">
                    Charged when a vehicle is returned after the scheduled end time.
                    <strong class="text-mb-silver">Grace period: 30 minutes</strong> - no charge if late by less than 30
                    mins.
                    Charge is calculated per minute after grace period using your hourly rate.
                </p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Late Return Charge per Hour ($)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                    <input type="number" name="late_return_rate_per_hour" value="<?= number_format($lateRate, 2) ?>"
                        min="0" step="0.01" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="0.00">
                </div>
                <p class="text-xs text-mb-subtle mt-1">Set to 0 to disable late return charges.</p>
            </div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Delivery Settings</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">
                    Configure default delivery charges and suggested deposit behavior for the delivery screen.
                </p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Default Delivery Charge ($)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                    <input type="number" name="delivery_charge_default"
                        value="<?= number_format($deliveryChargeDefault, 2) ?>" min="0" step="0.01" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="0.00">
                </div>
                <p class="text-xs text-mb-subtle mt-1">Prefilled in delivery screen, and still editable per reservation.
                </p>
            </div>
            <div class="pt-2 border-t border-mb-subtle/10">
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Delivery Deposit</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">
                    Configure a suggested deposit percentage based on the amount collected at delivery.
                </p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Suggested Deposit Percentage (%)</label>
                <div class="relative">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">%</span>
                    <input type="number" name="deposit_percentage" value="<?= number_format($depositPct, 0) ?>" min="0"
                        max="100" step="1" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="0">
                </div>
                <p class="text-xs text-mb-subtle mt-1">Suggested deposit will be calculated as a percentage of the
                    delivery collection amount.</p>
            </div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Lead Sources</h3>
                    <p class="text-xs text-mb-subtle mt-2 ml-5">
                        <?= count($leadSources) ?> source option<?= count($leadSources) !== 1 ? 's' : '' ?> configured.
                    </p>
                </div>
                <a href="lead_sources.php"
                    class="bg-mb-black border border-mb-subtle/20 text-mb-silver px-5 py-2 rounded-full hover:border-mb-accent/40 hover:text-white transition-colors text-sm">
                    Manage Lead Sources
                </a>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Save Settings
            </button>
        </div>
    </form>

    <?php if (defined('EXPORT_ENABLED') && EXPORT_ENABLED): ?>
        <!-- ── Data Export (admin only, kill-switch gated) ─────────────── -->
        <div class="bg-mb-surface border border-yellow-500/20 rounded-xl p-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h3 class="text-white font-light text-lg border-l-2 border-yellow-500 pl-3">Data Export</h3>
                    <p class="text-xs text-mb-subtle mt-2 ml-5">
                        Download a full <strong class="text-mb-silver">.xlsx</strong> backup of all data&nbsp;—
                        vehicles, clients, reservations, pipeline leads, staff, expenses &amp; more.
                    </p>
                </div>
                <a href="../export/index.php"
                    class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 px-5 py-2 rounded-full hover:bg-yellow-500/20 transition-all text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export Data
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>