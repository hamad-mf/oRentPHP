<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/export_enabled.php';

// Kill-switch: if disabled, pretend this page doesn't exist
if (!defined('EXPORT_ENABLED') || !EXPORT_ENABLED) {
    $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 1);
    header('Location: ' . str_repeat('../', $depth) . 'index.php');
    exit;
}

auth_require_admin();
$pdo = db();

// Row count previews
function safeCount(PDO $pdo, string $table): int
{
    try {
        return (int) $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

$sheets = [
    ['icon' => '🚗', 'label' => 'Vehicles', 'table' => 'vehicles'],
    ['icon' => '👤', 'label' => 'Clients', 'table' => 'clients'],
    ['icon' => '📅', 'label' => 'Reservations', 'table' => 'reservations'],
    ['icon' => '🎯', 'label' => 'Pipeline Leads', 'table' => 'leads'],
    ['icon' => '👥', 'label' => 'Staff', 'table' => 'staff'],
    ['icon' => '💸', 'label' => 'Expenses', 'table' => 'expenses'],
    ['icon' => '📈', 'label' => 'Investments', 'table' => 'investments'],
    ['icon' => '🚦', 'label' => 'Challans', 'table' => 'challans'],
    ['icon' => '🎟️', 'label' => 'Voucher Transactions', 'table' => 'client_voucher_transactions'],
    ['icon' => '🕐', 'label' => 'Attendance', 'table' => 'staff_attendance'],
];

foreach ($sheets as &$s) {
    $s['count'] = safeCount($pdo, $s['table']);
}
unset($s);

$totalRows = array_sum(array_column($sheets, 'count'));

$pageTitle = 'Export Data';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-white text-xl font-light flex items-center gap-2">
                <svg class="w-5 h-5 text-mb-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export Full Database
            </h2>
            <p class="text-mb-subtle text-sm mt-1">
                Downloads a <strong class="text-mb-silver">.xlsx</strong> file with
                <strong class="text-mb-silver">
                    <?= count($sheets) ?> sheets
                </strong> —
                <strong class="text-mb-silver">
                    <?= number_format($totalRows) ?>
                </strong> total rows.
            </p>
        </div>
        <a href="download.php"
            class="flex items-center gap-2 bg-mb-accent text-white px-6 py-2.5 rounded-full hover:bg-mb-accent/80 transition-all font-medium shadow-lg shadow-mb-accent/20 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Download .xlsx
        </a>
    </div>

    <!-- Warning banner -->
    <div
        class="flex items-start gap-3 bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 rounded-xl px-5 py-4 text-sm">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <p>This export is intended as a <strong>full data backup</strong> before wiping the database. Keep the
            downloaded
            file safe and confidential — it contains all client, financial, and operational data.</p>
    </div>

    <!-- Sheet preview list -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10">
            <h3 class="text-white font-light">Sheets Included</h3>
        </div>
        <div class="divide-y divide-mb-subtle/10">
            <?php foreach ($sheets as $s): ?>
                <div class="flex items-center justify-between px-6 py-3.5">
                    <div class="flex items-center gap-3">
                        <span class="text-xl leading-none">
                            <?= $s['icon'] ?>
                        </span>
                        <span class="text-mb-silver text-sm">
                            <?= e($s['label']) ?>
                        </span>
                    </div>
                    <span class="text-xs font-mono <?= $s['count'] > 0 ? 'text-mb-accent' : 'text-mb-subtle' ?>">
                        <?= number_format($s['count']) ?> row
                        <?= $s['count'] !== 1 ? 's' : '' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="px-6 py-3 bg-mb-black/20 flex items-center justify-between border-t border-mb-subtle/10">
            <span class="text-xs text-mb-subtle">Total</span>
            <span class="text-xs font-mono text-white font-medium">
                <?= number_format($totalRows) ?> rows
            </span>
        </div>
    </div>

    <!-- Back link -->
    <div>
        <a href="../settings/general.php"
            class="text-sm text-mb-subtle hover:text-white transition-colors flex items-center gap-1.5 w-fit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Settings
        </a>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>