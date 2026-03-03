<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/reservation_payment_helpers.php';
require_once __DIR__ . '/includes/ledger_helpers.php';
require_once __DIR__ . '/includes/settings_helpers.php';
require_once __DIR__ . '/includes/gps_helpers.php';
$pdo = db();
reservation_payment_ensure_schema($pdo);
ledger_ensure_schema($pdo);
settings_ensure_table($pdo);
gps_tracking_ensure_schema($pdo);

// Fleet Status
$totalCars = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$availableCars = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='available'")->fetchColumn();
$rentedCars = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='rented'")->fetchColumn();
$maintenanceCars = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='maintenance'")->fetchColumn();

// Daily Operations
$todayReturns = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='active' AND DATE(end_date) = CURDATE()")->fetchColumn();
$notifications = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status='active' AND DATE(end_date) <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)")->fetchColumn();
$gpsWarnings = gps_active_warning_count($pdo);

// Business Performance — daily_target stored in DB (survives deploys, per-environment)
$dailyTarget = (float) ($pdo->query("SELECT `value` FROM system_settings WHERE `key`='daily_target' LIMIT 1")->fetchColumn() ?: 0);

// Handle daily target update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daily_target'])) {
    $newTarget = max(0, (float) ($_POST['daily_target'] ?? 0));
    $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES ('daily_target', ?)
                   ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
        ->execute([$newTarget]);
    app_log('ACTION', "Updated daily target to $newTarget");
    $dailyTarget = $newTarget;
}

// Use IST (India Standard Time) for all "today" calculations — resets at midnight IST
$istNow = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$istToday = $istNow->format('Y-m-d');

// Revenue should reset daily: sum reservation payments posted today (delivery + return).
$todayRevenue = 0.0;
try {
    $revStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0)
         FROM ledger_entries
         WHERE txn_type = 'income'
           AND source_type = 'reservation'
           AND source_event IN ('delivery', 'return')
           AND DATE(posted_at) = ?"
    );
    $revStmt->execute([$istToday]);
    $todayRevenue = (float) $revStmt->fetchColumn();
} catch (Throwable $e) {
    $todayRevenue = 0.0;
}

// Backward-safe fallback for older data where reservation events may not exist in ledger.
if ($todayRevenue == 0.0) {
    $legacyRevStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(
            CASE WHEN d.delivery_today = 1 THEN r.delivery_paid_amount ELSE 0 END +
            CASE WHEN rt.return_today = 1 THEN r.return_paid_amount ELSE 0 END
        ), 0)
        FROM reservations r
        LEFT JOIN (
            SELECT reservation_id, 1 AS delivery_today
            FROM vehicle_inspections
            WHERE type = 'delivery' AND DATE(created_at) = ?
            GROUP BY reservation_id
        ) d ON d.reservation_id = r.id
        LEFT JOIN (
            SELECT reservation_id, 1 AS return_today
            FROM vehicle_inspections
            WHERE type = 'return' AND DATE(created_at) = ?
            GROUP BY reservation_id
        ) rt ON rt.reservation_id = r.id
        WHERE d.delivery_today = 1 OR rt.return_today = 1"
    );
    $legacyRevStmt->execute([$istToday, $istToday]);
    $todayRevenue = (float) $legacyRevStmt->fetchColumn();
}

$enquiries = (function () use ($pdo, $istToday) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE DATE(created_at) = ?");
    $s->execute([$istToday]);
    return (int) $s->fetchColumn(); })();
// Closed Deals = reservations whose return inspection was processed today
$closedDeals = (function () use ($pdo, $istToday) {
    $s = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations r
         JOIN vehicle_inspections vi ON vi.reservation_id = r.id AND vi.type = 'return'
         WHERE r.status = 'completed' AND DATE(vi.created_at) = ?"
    );
    $s->execute([$istToday]);
    return (int) $s->fetchColumn();
})();
$newClients = (function () use ($pdo, $istToday) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE DATE(created_at) = ?");
    $s->execute([$istToday]);
    return (int) $s->fetchColumn(); })();

// CRM Lead Stats
$activeLeads = 0;
$overdueFollowups = 0;
try {
    $activeLeads = $pdo->query("SELECT COUNT(*) FROM leads WHERE status NOT IN ('closed_won','closed_lost')")->fetchColumn();
    $overdueFollowups = $pdo->query("SELECT COUNT(*) FROM lead_followups WHERE scheduled_at < NOW() AND is_done=0")->fetchColumn();
} catch (Exception $e) { /* leads table may not exist yet */
}



// Accounts — rules:
//   'account' payment  = goes into bank account balance + ledger
//   'cash' payment     = ledger only (no bank balance change)
//   'credit' payment   = ledger only (receivable, no money yet)
$accounts = ['total' => 0.0, 'cash' => 0.0, 'ac' => 0.0, 'credit' => 0.0];
try {
    require_once __DIR__ . '/includes/ledger_helpers.php';
    ledger_ensure_schema($pdo);

    // Bank account balance (only 'account' mode payments land here)
    $bankBalance = (float) $pdo->query("SELECT COALESCE(SUM(balance),0) FROM bank_accounts WHERE is_active=1")->fetchColumn();
    $accounts['ac'] = $bankBalance;

    // Cash received — from ledger (payment_mode='cash'), does NOT touch bank balance
    $cashIncome = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='income'")->fetchColumn();
    $cashExpense = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='expense'")->fetchColumn();
    $accounts['cash'] = $cashIncome - $cashExpense;

    // Credit outstanding — ledger only, no bank
    $accounts['credit'] = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='income'")->fetchColumn();

    // Dashboard total = cash + bank + credit
    $accounts['total'] = $accounts['cash'] + $accounts['ac'] + $accounts['credit'];
} catch (Throwable $e) {
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-8">

    <!-- Flash messages -->
    <?php if ($flash = getFlash('success')): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($flash) ?>
        </div>
    <?php endif; ?>

    <!-- Row 1: Fleet Status -->
    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2">Fleet
            Status</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="vehicles/index.php"
                class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-white/20 transition-all group cursor-pointer">
                <p class="text-mb-silver text-sm uppercase mb-1">Total Cars</p>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-light text-white"><?= $totalCars ?></span>
                    <svg class="w-6 h-6 text-mb-silver/30 group-hover:text-white transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
            </a>
            <a href="vehicles/index.php?status=available"
                class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-green-500/50 transition-all group cursor-pointer">
                <p class="text-mb-silver text-sm uppercase mb-1">Available</p>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-light text-green-400"><?= $availableCars ?></span>
                    <div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div>
                </div>
            </a>
            <a href="vehicles/index.php?status=rented"
                class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-mb-accent/50 transition-all group cursor-pointer">
                <p class="text-mb-silver text-sm uppercase mb-1">Running / Rented</p>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-light text-mb-accent"><?= $rentedCars ?></span>
                    <svg class="w-6 h-6 text-mb-accent/30 group-hover:text-mb-accent transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
            </a>
            <a href="vehicles/index.php?status=maintenance"
                class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-red-500/50 transition-all group cursor-pointer">
                <p class="text-mb-silver text-sm uppercase mb-1">Workshop</p>
                <div class="flex items-end justify-between">
                    <span class="text-4xl font-light text-red-400"><?= $maintenanceCars ?></span>
                    <svg class="w-6 h-6 text-red-500/30 group-hover:text-red-500 transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
            </a>
        </div>
    </section>

    <!-- Row 2: Daily Operations -->
    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2">Daily
            Operations</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="reservations/index.php?due_today=1"
                class="bg-mb-surface border border-mb-subtle/20 p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer">
                <div>
                    <p class="text-mb-silver text-sm uppercase mb-1">Today Returns</p>
                    <span class="text-3xl font-light text-white"><?= $todayReturns ?> Vehicles</span>
                </div>
                <div class="w-12 h-12 rounded-full bg-mb-accent/10 flex items-center justify-center text-mb-accent">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </a>
            <div onclick="toggleNotif(true)"
                class="bg-mb-surface border border-mb-subtle/20 p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer">
                <div>
                    <p class="text-mb-silver text-sm uppercase mb-1">Notifications</p>
                    <span
                        class="text-3xl font-light <?= $_notifCount > 0 ? 'text-yellow-400' : 'text-white' ?>"><?= $_notifCount ?>
                        New</span>
                    <p class="text-xs text-mb-subtle mt-1">Click to view details</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-yellow-500/10 flex items-center justify-center text-yellow-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
            </div>
            <a href="gps/index.php?status=active&tracking=no"
                class="bg-mb-surface border <?= $gpsWarnings > 0 ? 'border-red-500/40 bg-red-500/5' : 'border-mb-subtle/20' ?> p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer">
                <div>
                    <p class="text-mb-silver text-sm uppercase mb-1">GPS Warnings</p>
                    <span
                        class="text-3xl font-light <?= $gpsWarnings > 0 ? 'text-red-400' : 'text-white' ?>"><?= $gpsWarnings ?>
                        Issue<?= $gpsWarnings === 1 ? '' : 's' ?></span>
                    <?php if ($gpsWarnings > 0): ?>
                        <p class="text-xs text-red-400/70 mt-1 animate-pulse">Active vehicles with GPS inactive</p>
                    <?php else: ?>
                        <p class="text-xs text-mb-subtle mt-1">All active vehicles tracking</p>
                    <?php endif; ?>
                </div>
                <div
                    class="w-12 h-12 rounded-full <?= $gpsWarnings > 0 ? 'bg-red-500/10 text-red-400' : 'bg-green-500/10 text-green-400' ?> flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 2a7 7 0 00-7 7c0 4.5 7 13 7 13s7-8.5 7-13a7 7 0 00-7-7zm0 10a3 3 0 110-6 3 3 0 010 6z" />
                    </svg>
                </div>
            </a>
        </div>
    </section>

    <!-- Row 3: Business Performance -->

    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2">
            Business Performance <span class="text-mb-subtle text-sm normal-case tracking-normal">(Today)</span></h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg" x-data="{editing:false}">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-mb-silver text-sm uppercase">Daily Target</p>
                    <button @click="editing=!editing" title="Edit target"
                        class="text-mb-subtle hover:text-mb-accent transition-colors p-1 rounded">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                </div>
                <!-- Edit form -->
                <form x-show="editing" method="POST" class="flex items-center gap-2 mb-2" x-cloak>
                    <span class="text-mb-silver text-sm">$</span>
                    <input type="number" name="daily_target" value="<?= (int) $dailyTarget ?>" min="0" step="100"
                        class="w-28 bg-mb-black border border-mb-accent/40 rounded px-2 py-1 text-white text-sm focus:outline-none focus:border-mb-accent">
                    <button type="submit"
                        class="bg-mb-accent text-white px-3 py-1 rounded text-xs font-medium hover:bg-mb-accent/80 transition-colors">Save</button>
                    <button type="button" @click="editing=false"
                        class="text-mb-subtle hover:text-white text-xs transition-colors">Cancel</button>
                </form>
                <!-- Display -->
                <div x-show="!editing">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-light text-white">$<?= number_format($dailyTarget) ?></span>
                        <span
                            class="text-xs <?= $todayRevenue >= $dailyTarget ? 'text-green-400' : 'text-mb-silver' ?>">
                            / $<?= number_format($todayRevenue) ?> achieved
                        </span>
                    </div>
                    <div class="w-full bg-mb-black h-1.5 mt-3 rounded-full overflow-hidden">
                        <div class="<?= $todayRevenue >= $dailyTarget ? 'bg-green-500' : 'bg-mb-accent' ?> h-full transition-all"
                            style="width: <?= min(100, ($dailyTarget > 0 ? ($todayRevenue / $dailyTarget) * 100 : 0)) ?>%">
                        </div>
                    </div>
                    <?php if ($todayRevenue >= $dailyTarget): ?>
                        <p class="text-green-400 text-xs mt-1.5">🎉 Target reached!</p>
                    <?php else: ?>
                        <p
                            class="mt-2 inline-flex items-center gap-1.5 bg-orange-500/15 border border-orange-500/30 text-orange-400 font-semibold text-sm px-3 py-1 rounded-full">
                            🔥 $<?= number_format($dailyTarget - $todayRevenue) ?> to go
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg">
                <p class="text-mb-silver text-sm uppercase mb-1">Enquiries</p>
                <span class="text-3xl font-light text-white"><?= $enquiries ?></span>
            </div>
            <div class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg">
                <p class="text-mb-silver text-sm uppercase mb-1">Closed Deals</p>
                <span class="text-3xl font-light text-white"><?= $closedDeals ?></span>
            </div>
            <div class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg">
                <p class="text-mb-silver text-sm uppercase mb-1">New Clients</p>
                <span class="text-3xl font-light text-white"><?= $newClients ?></span>
            </div>
        </div>
    </section>

    <!-- Row 3b: CRM Leads -->
    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2">CRM
            <span class="text-mb-subtle text-sm normal-case tracking-normal">Lead Pipeline</span>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="leads/pipeline.php"
                class="bg-mb-surface border border-mb-subtle/20 p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer">
                <div>
                    <p class="text-mb-silver text-sm uppercase mb-1">Active Leads</p>
                    <span class="text-3xl font-light text-white"><?= $activeLeads ?></span>
                    <p class="text-xs text-mb-subtle mt-1">In pipeline now</p>
                </div>
                <div class="w-12 h-12 rounded-full bg-mb-accent/10 flex items-center justify-center text-mb-accent">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
            </a>
            <a href="leads/pipeline.php"
                class="bg-mb-surface border <?= $overdueFollowups > 0 ? 'border-red-500/40 bg-red-500/5' : 'border-mb-subtle/20' ?> p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer">
                <div>
                    <p class="text-mb-silver text-sm uppercase mb-1">Overdue Follow-ups</p>
                    <span
                        class="text-3xl font-light <?= $overdueFollowups > 0 ? 'text-red-400' : 'text-white' ?>"><?= $overdueFollowups ?></span>
                    <?php if ($overdueFollowups > 0): ?>
                        <p class="text-xs text-red-400/70 mt-1 animate-pulse">⚠ Action required</p>
                    <?php else: ?>
                        <p class="text-xs text-mb-subtle mt-1">All clear</p>
                    <?php endif; ?>
                </div>
                <div
                    class="w-12 h-12 rounded-full <?= $overdueFollowups > 0 ? 'bg-red-500/10' : 'bg-mb-subtle/10' ?> flex items-center justify-center <?= $overdueFollowups > 0 ? 'text-red-400' : 'text-mb-silver' ?>">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </a>
        </div>
    </section>

    <!-- Row 4: Accounts -->
    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2">
            Accounts</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-mb-surface/50 border border-mb-subtle/20 p-4 rounded text-center">
                <p class="text-xs text-mb-silver uppercase mb-1">Total</p>
                <p class="text-2xl text-white font-light">$<?= number_format($accounts['total']) ?></p>
            </div>
            <div class="bg-mb-surface/50 border border-mb-subtle/20 p-4 rounded text-center">
                <p class="text-xs text-mb-silver uppercase mb-1">Cash</p>
                <p class="text-2xl text-green-400 font-light">$<?= number_format($accounts['cash']) ?></p>
            </div>
            <div class="bg-mb-surface/50 border border-mb-subtle/20 p-4 rounded text-center">
                <p class="text-xs text-mb-silver uppercase mb-1">Bank (AC)</p>
                <p class="text-2xl text-blue-400 font-light">$<?= number_format($accounts['ac']) ?></p>
            </div>
            <div class="bg-mb-surface/50 border border-mb-subtle/20 p-4 rounded text-center">
                <p class="text-xs text-mb-silver uppercase mb-1">Credit</p>
                <p class="text-2xl text-red-400 font-light">$<?= number_format($accounts['credit']) ?></p>
            </div>
        </div>
    </section>

    <!-- Row 5: Quick Links -->
    <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 text-center">
        <?php
        $links = [
            ['name' => 'Investments', 'href' => 'investments/index.php'],
            ['name' => 'GPS Tracking', 'href' => 'gps/index.php'],
            ['name' => 'Papers', 'href' => 'papers/index.php'],
            ['name' => 'Expenses', 'href' => 'expenses/index.php'],
            ['name' => 'Challans', 'href' => 'challans/index.php'],
            ['name' => 'Staff', 'href' => 'staff/index.php'],
        ];
        foreach ($links as $link):
            ?>
            <a href="<?= $link['href'] ?>"
                class="bg-mb-surface border border-mb-subtle/20 p-4 rounded-lg hover:bg-mb-black hover:border-mb-accent/30 transition-all group duration-300 transform hover:-translate-y-1">
                <p class="text-mb-silver group-hover:text-white transition-colors text-sm uppercase tracking-wide">
                    <?= $link['name'] ?>
                </p>
            </a>
        <?php endforeach; ?>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
