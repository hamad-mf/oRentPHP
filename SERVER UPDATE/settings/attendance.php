<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();

auth_require_admin();

settings_ensure_table($pdo);

// Defaults
$keys = ['att_punchin_start', 'att_punchin_end', 'att_punchout_start', 'att_punchout_end'];
$defaults = [
    'att_punchin_start' => '08:30 AM',
    'att_punchin_end' => '10:00 AM',
    'att_punchout_start' => '05:00 PM',
    'att_punchout_end' => '08:00 PM',
];
foreach ($defaults as $k => $v) {
    if (settings_get($pdo, $k, '') === '')
        settings_set($pdo, $k, $v);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Accept "HH:MM" + "AM/PM" split inputs, format as "hh:mm AM"
    foreach ($keys as $k) {
        $hhmm = trim($_POST[$k . '_hhmm'] ?? '');
        $ampm = strtoupper(trim($_POST[$k . '_ampm'] ?? 'AM'));
        if (!in_array($ampm, ['AM', 'PM'], true))
            $ampm = 'AM';
        $parsed = DateTime::createFromFormat('h:i', $hhmm) ?: DateTime::createFromFormat('H:i', $hhmm);
        if ($parsed) {
            settings_set($pdo, $k, $parsed->format('h:i') . ' ' . $ampm);
        }
    }
    flash('success', 'Attendance settings saved.');
    redirect('attendance.php');
}

// Load current values
$vals = [];
foreach ($keys as $k) {
    $raw = settings_get($pdo, $k, $defaults[$k] ?? '');
    // Parse stored "hh:mm AM" → split
    $dt = DateTime::createFromFormat('h:i A', strtoupper($raw));
    $vals[$k . '_hhmm'] = $dt ? $dt->format('h:i') : '08:00';
    $vals[$k . '_ampm'] = $dt ? $dt->format('A') : 'AM';
}

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Settings Tab Nav (shared) -->
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
        <a href="staff_permissions.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Staff
            Permissions</a>
        <a href="attendance.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all bg-mb-accent text-white shadow-lg shadow-mb-accent/20">Attendance</a>
    </div>

    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <span class="text-white">Settings</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Attendance</span>
    </div>

    <?php $s = getFlash('success');
    if ($s): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <?= e($s) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <!-- Punch-In Window -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Punch-In Window</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Staff punching in outside this window will see a warning,
                    but the punch will still be recorded.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <?php foreach (['att_punchin_start' => 'Earliest (Start)', 'att_punchin_end' => 'Latest (End)'] as $k => $label): ?>
                    <div>
                        <label class="block text-sm text-mb-silver mb-2">
                            <?= $label ?>
                        </label>
                        <div class="flex gap-2">
                            <input type="time" name="<?= $k ?>_hhmm" value="<?= e($vals[$k . '_hhmm']) ?>"
                                class="flex-1 bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                            <select name="<?= $k ?>_ampm"
                                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm cursor-pointer">
                                <option value="AM" <?= $vals[$k . '_ampm'] === 'AM' ? 'selected' : '' ?>>AM</option>
                                <option value="PM" <?= $vals[$k . '_ampm'] === 'PM' ? 'selected' : '' ?>>PM</option>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Punch-Out Window -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Punch-Out Window</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Staff punching out outside this window will see a warning,
                    but the punch will still be recorded.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <?php foreach (['att_punchout_start' => 'Earliest (Start)', 'att_punchout_end' => 'Latest (End)'] as $k => $label): ?>
                    <div>
                        <label class="block text-sm text-mb-silver mb-2">
                            <?= $label ?>
                        </label>
                        <div class="flex gap-2">
                            <input type="time" name="<?= $k ?>_hhmm" value="<?= e($vals[$k . '_hhmm']) ?>"
                                class="flex-1 bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                            <select name="<?= $k ?>_ampm"
                                class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm cursor-pointer">
                                <option value="AM" <?= $vals[$k . '_ampm'] === 'AM' ? 'selected' : '' ?>>AM</option>
                                <option value="PM" <?= $vals[$k . '_ampm'] === 'PM' ? 'selected' : '' ?>>PM</option>
                            </select>
                        </div>
                    </div>
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