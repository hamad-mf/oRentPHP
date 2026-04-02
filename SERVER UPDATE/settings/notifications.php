<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
require_once __DIR__ . '/../includes/activity_log.php';
$pdo = db();

auth_require_admin();
settings_ensure_table($pdo);

$notificationSettings = [
    'notify_due_soon' => [
        'label' => 'Due Soon',
        'description' => 'Notify when an active reservation is due within the next 2 days.',
    ],
    'notify_due_today' => [
        'label' => 'Due Today',
        'description' => 'Notify when an active reservation is due today.',
    ],
    'notify_overdue' => [
        'label' => 'Overdue',
        'description' => 'Notify when an active reservation is overdue.',
    ],
    'notify_res_created' => [
        'label' => 'Reservation Created',
        'description' => 'Notify when a new reservation is created.',
    ],
    'notify_res_delivered' => [
        'label' => 'Vehicle Delivered',
        'description' => 'Notify when a vehicle is delivered.',
    ],
    'notify_res_returned' => [
        'label' => 'Vehicle Returned',
        'description' => 'Notify when a vehicle is returned.',
    ],
    'notify_res_cancelled' => [
        'label' => 'Reservation Cancelled',
        'description' => 'Notify when a reservation is cancelled.',
    ],
    'notify_emi_due' => [
        'label' => 'EMI Due Alert',
        'description' => 'Notify when an EMI payment is due within the next 2 days.',
    ],
    'notify_gps_pending' => [
        'label' => 'GPS Check Pending',
        'description' => 'Notify when active reservations have incomplete GPS checks for today.',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($notificationSettings) as $key) {
        $enabled = isset($_POST[$key]) ? '1' : '0';
        settings_set($pdo, $key, $enabled);
    }
    app_log('ACTION', 'Updated notification settings');
    log_activity($pdo, 'update_settings', 'settings', 0, 'Updated notification settings');
    flash('success', 'Notification settings saved.');
    redirect('notifications.php');
}

$values = [];
foreach (array_keys($notificationSettings) as $key) {
    $values[$key] = settings_get($pdo, $key, '1') !== '0';
}

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex gap-1 bg-mb-surface border border-mb-subtle/20 p-1 rounded-full w-fit flex-wrap">
        <a href="general.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Return
            Charges</a>
        <a href="damage_costs.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Damage
            Costs</a>
        <a href="lead_sources.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Lead
            Sources</a>
        <a href="expense_categories.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Expense
            Categories</a>
        <a href="staff_permissions.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Staff
            Permissions</a>
        <a href="attendance.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Attendance</a>
        <a href="notifications.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all bg-mb-accent text-white shadow-lg shadow-mb-accent/20">Notifications</a>
    </div>

    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <span class="text-white">Settings</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Notifications</span>
    </div>

    <?php if ($s = getFlash('success')): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <?= e($s) ?>
        </div>
    <?php endif; ?>
    <?php if ($e = getFlash('error')): ?>
        <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm">
            <?= e($e) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">In-App Notifications</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Control which notifications show in the top-right bell.</p>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <?php foreach ($notificationSettings as $key => $meta): ?>
                    <label class="flex items-start gap-4 bg-mb-black/40 border border-mb-subtle/20 rounded-xl p-4 cursor-pointer hover:border-mb-accent/40 transition-colors">
                        <input type="checkbox" name="<?= e($key) ?>" value="1"
                            class="mt-1 h-4 w-4 rounded border-mb-subtle/40 text-mb-accent focus:ring-mb-accent"
                            <?= $values[$key] ? 'checked' : '' ?>>
                        <span>
                            <span class="text-white text-sm font-medium"><?= e($meta['label']) ?></span>
                            <span class="block text-xs text-mb-subtle mt-1"><?= e($meta['description']) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
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
