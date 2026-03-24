<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/export_enabled.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
$pdo = db();

settings_ensure_table($pdo);
ledger_ensure_schema($pdo);
if (settings_get($pdo, 'late_return_rate_per_hour', '') === '') {
    settings_set($pdo, 'late_return_rate_per_hour', '0');
}
if (settings_get($pdo, 'deposit_percentage', '') === '') {
    settings_set($pdo, 'deposit_percentage', '0');
}
if (settings_get($pdo, 'delivery_charge_default', '') === '') {
    settings_set($pdo, 'delivery_charge_default', '0');
}
if (settings_get($pdo, 'return_pickup_charge_default', '') === '') {
    settings_set($pdo, 'return_pickup_charge_default', '0');
}
if (settings_get($pdo, 'lead_incentive_per_lead', '') === '') {
    settings_set($pdo, 'lead_incentive_per_lead', '0');
}
if (settings_get($pdo, 'delivery_incentive_per_delivery', '') === '') {     settings_set($pdo, 'delivery_incentive_per_delivery', '0'); }
if (settings_get($pdo, 'per_page', '') === '') {
    settings_set($pdo, 'per_page', '25');
}
if (settings_get($pdo, 'pipeline_pagination_enabled', '') === '') {
    settings_set($pdo, 'pipeline_pagination_enabled', '1');
}
if (settings_get($pdo, 'auto_close_lost_after_followups', '') === '') {
    settings_set($pdo, 'auto_close_lost_after_followups', '0');
}
if (settings_get($pdo, 'security_deposit_bank_account_id', '') === '') {
    settings_set($pdo, 'security_deposit_bank_account_id', '0');
}

$leadSources = lead_sources_get_map($pdo);
$mobileBottomNavCatalog = mobile_bottom_nav_catalog();
$activeBankAccounts = array_values(array_filter(ledger_get_accounts($pdo), fn($a) => (int) ($a['is_active'] ?? 0) === 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedBottomNavKeys = $_POST['mobile_bottom_nav_keys'] ?? [];
    if (!is_array($postedBottomNavKeys)) {
        $postedBottomNavKeys = [];
    }
    $selectedBottomNavKeys = [];
    $seenBottomNav = [];
    foreach ($postedBottomNavKeys as $rawKey) {
        $key = strtolower(trim((string) $rawKey));
        if ($key === '' || !isset($mobileBottomNavCatalog[$key]) || isset($seenBottomNav[$key])) {
            continue;
        }
        $seenBottomNav[$key] = true;
        $selectedBottomNavKeys[] = $key;
    }
    if (count($selectedBottomNavKeys) !== 5) {
        flash('error', 'Please select exactly 5 menu items for the mobile bottom bar.');
        redirect('general.php');
    }

    $rate = max(0, (float) ($_POST['late_return_rate_per_hour'] ?? 0));
    settings_set($pdo, 'late_return_rate_per_hour', (string) $rate);
    $depositPct = min(100, max(0, (float) ($_POST['deposit_percentage'] ?? 0)));
    settings_set($pdo, 'deposit_percentage', (string) $depositPct);
    $deliveryChargeDefault = max(0, (float) ($_POST['delivery_charge_default'] ?? 0));
    settings_set($pdo, 'delivery_charge_default', (string) $deliveryChargeDefault);
    $returnPickupChargeDefault = max(0, (float) ($_POST['return_pickup_charge_default'] ?? 0));
    settings_set($pdo, 'return_pickup_charge_default', (string) $returnPickupChargeDefault);
    $leadIncentivePerLead = max(0, (float) ($_POST['lead_incentive_per_lead'] ?? 0));
    settings_set($pdo, 'lead_incentive_per_lead', (string) $leadIncentivePerLead);
    $perPage = max(5, min(200, (int) ($_POST['per_page'] ?? 25)));
    settings_set($pdo, 'per_page', (string) $perPage);
    $pipelinePaginationEnabled = ((int) ($_POST['pipeline_pagination_enabled'] ?? 0)) === 1 ? '1' : '0';
    settings_set($pdo, 'pipeline_pagination_enabled', $pipelinePaginationEnabled);
    $autoCloseAfter = min(4, max(0, (int) ($_POST['auto_close_lost_after_followups'] ?? 0)));
    settings_set($pdo, 'auto_close_lost_after_followups', (string) $autoCloseAfter);
    $deliveryIncentivePer = max(0, (float) ($_POST['delivery_incentive_per_delivery'] ?? 0));     settings_set($pdo, 'delivery_incentive_per_delivery', (string)$deliveryIncentivePer);
    
    // Held deposit settings
    $heldDepositAlertDays = max(1, (int) ($_POST['held_deposit_alert_days'] ?? 7));
    settings_set($pdo, 'held_deposit_alert_days', (string) $heldDepositAlertDays);
    $heldDepositTestMode = ((int) ($_POST['held_deposit_test_mode'] ?? 0)) === 1 ? '1' : '0';
    settings_set($pdo, 'held_deposit_test_mode', $heldDepositTestMode);
    
    $securityDepositBankAccountId = (int) ($_POST['security_deposit_bank_account_id'] ?? 0);
    if ($securityDepositBankAccountId > 0) {
        $resolvedDepositBankId = ledger_get_active_bank_account_id($pdo, $securityDepositBankAccountId);
        if ($resolvedDepositBankId === null) {
            flash('error', 'Selected security deposit bank account is invalid or inactive.');
            redirect('general.php');
        }
        settings_set($pdo, 'security_deposit_bank_account_id', (string) $resolvedDepositBankId);
    } else {
        settings_set($pdo, 'security_deposit_bank_account_id', '0');
    }
    settings_set($pdo, 'mobile_bottom_nav_keys', mobile_bottom_nav_encode_keys($selectedBottomNavKeys));
    app_log('ACTION', 'Updated general settings');
    flash('success', 'Settings saved successfully.');
    redirect('general.php');
}

$lateRate = (float) settings_get($pdo, 'late_return_rate_per_hour', '0');
$depositPct = (float) settings_get($pdo, 'deposit_percentage', '0');
$deliveryChargeDefault = (float) settings_get($pdo, 'delivery_charge_default', '0');
$returnPickupChargeDefault = (float) settings_get($pdo, 'return_pickup_charge_default', '0');
$leadIncentivePerLead = (float) settings_get($pdo, 'lead_incentive_per_lead', '0');
$perPageSetting = (int) settings_get($pdo, 'per_page', '25');
$pipelinePaginationEnabledSetting = settings_get($pdo, 'pipeline_pagination_enabled', '1') !== '0';
$autoCloseAfterSetting = (int) settings_get($pdo, 'auto_close_lost_after_followups', '0');
$deliveryIncentiveSetting = (float) settings_get($pdo, 'delivery_incentive_per_delivery', '0');
$heldDepositAlertDaysSetting = (int) settings_get($pdo, 'held_deposit_alert_days', '7');
$heldDepositTestModeSetting = settings_get($pdo, 'held_deposit_test_mode', '0') === '1';
$securityDepositBankAccountIdSetting = (int) settings_get($pdo, 'security_deposit_bank_account_id', '0');
$mobileBottomNavSelectedKeys = mobile_bottom_nav_get_keys($pdo, 5);

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex gap-1 bg-mb-surface border border-mb-subtle/20 p-1 rounded-full w-fit flex-wrap">
        <a href="general.php" class="px-6 py-2 rounded-full text-sm font-medium transition-all bg-mb-accent text-white shadow-lg shadow-mb-accent/20">Return Charges</a>
        <a href="damage_costs.php" class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Damage Costs</a>
        <a href="lead_sources.php" class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Lead Sources</a>
        <a href="expense_categories.php" class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Expense Categories</a>
        <a href="staff_permissions.php" class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Staff Permissions</a>
        <a href="attendance.php" class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Attendance</a>
        <a href="notifications.php" class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Notifications</a>
    </div>

    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <span class="text-white">Settings</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
        <span class="text-white">General</span>
    </div>

    <?php $s = getFlash('success'); if ($s): ?>
        <div class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">Success: <?= e($s) ?></div>
    <?php endif; ?>
    <?php $e = getFlash('error'); if ($e): ?>
        <div class="flex items-center gap-3 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-5 py-3 text-sm"><?= e($e) ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <!-- Late Return -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Late Return Charges</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Charged when a vehicle is returned after the scheduled end time. <strong class="text-mb-silver">Grace period: 30 minutes</strong> — charge is calculated per minute after grace period.</p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Late Return Charge per Hour ($)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                    <input type="number" name="late_return_rate_per_hour" value="<?= $lateRate ?>" min="0" step="0.01" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm" placeholder="0.00">
                </div>
                <p class="text-xs text-mb-subtle mt-1">Set to 0 to disable late return charges.</p>
            </div>
        </div>

        <!-- Delivery Settings -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Delivery Settings</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Configure default delivery charges and suggested deposit behavior.</p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Default Delivery Charge ($)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                    <input type="number" name="delivery_charge_default" value="<?= $deliveryChargeDefault ?>" min="0" step="0.01" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm" placeholder="0.00">
                </div>
                <p class="text-xs text-mb-subtle mt-1">Prefilled in delivery screen, still editable per reservation.</p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Default Return Pickup Charge ($)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                    <input type="number" name="return_pickup_charge_default" value="<?= $returnPickupChargeDefault ?>" min="0" step="0.01" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm" placeholder="0.00">
                </div>
                <p class="text-xs text-mb-subtle mt-1">Prefilled in return screen as Return Pickup Charge, still editable per reservation.</p>
            </div>
            <div class="pt-2 border-t border-mb-subtle/10">
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Security Deposit</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Suggested deposit percentage based on the amount collected at delivery.</p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Suggested Deposit Percentage (%)</label>
                <div class="relative">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">%</span>
                    <input type="number" name="deposit_percentage" value="<?= $depositPct ?>" min="0" max="100" step="1" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm" placeholder="0">
                </div>
            </div>
            <div class="pt-2 border-t border-mb-subtle/10">
                <h3 class="text-white font-light text-lg border-l-2 border-yellow-500 pl-3">Held Deposit Alerts</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Configure automatic alerts for deposits that remain held beyond a threshold period.</p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Alert Threshold (Days)</label>
                <input type="number" name="held_deposit_alert_days" value="<?= $heldDepositAlertDaysSetting ?>" min="1" step="1" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm" placeholder="7">
                <p class="text-xs text-mb-subtle mt-1">Alert will trigger when a deposit has been held for this many days.</p>
            </div>
            <div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="held_deposit_test_mode" value="1" <?= $heldDepositTestModeSetting ? 'checked' : '' ?>
                        class="w-4 h-4 rounded border-mb-subtle/20 bg-mb-black text-mb-accent focus:ring-mb-accent focus:ring-offset-0">
                    <div>
                        <span class="text-sm text-mb-silver">Test Mode (Hours as Days)</span>
                        <p class="text-xs text-mb-subtle mt-0.5">For testing: treats hours as days so you don't have to wait. Disable in production.</p>
                    </div>
                </label>
            </div>
            <div class="pt-2 border-t border-mb-subtle/10">
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Security Deposit Ledger</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Security deposit collection/return posts to this bank account for record and balance tracking. These entries are excluded from income and target KPIs.</p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Security Deposit Bank Account</label>
                <select name="security_deposit_bank_account_id"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                    <option value="0">Not configured</option>
                    <?php foreach ($activeBankAccounts as $acc): ?>
                    <option value="<?= (int) $acc['id'] ?>" <?= $securityDepositBankAccountIdSetting === (int) $acc['id'] ? 'selected' : '' ?>>
                        <?= e($acc['name']) ?><?= !empty($acc['bank_name']) ? ' - ' . e($acc['bank_name']) : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-mb-subtle mt-1">Required if you want automatic deposit in/out entries in ledger.</p>
            </div>
        </div>

        <!-- Lead Incentive -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Payroll – Lead Incentive</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Auto-incentive amount added per <strong class="text-mb-silver">Closed Won</strong> lead when generating payroll. Set to 0 to disable.</p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Incentive Per Closed Won Lead ($)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                    <input type="number" name="lead_incentive_per_lead" value="<?= $leadIncentivePerLead ?>" min="0" step="0.01"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm" placeholder="0.00">
                </div>
            </div>
        </div>

        <!-- Delivery Incentive -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Payroll &ndash; Delivery Incentive</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Auto-incentive amount added per <strong class="text-mb-silver">vehicle delivery</strong> when generating payroll. Set to 0 to disable.</p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Incentive Per Delivery ($)</label>
                <div class="relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle text-sm">$</span>
                    <input type="number" name="delivery_incentive_per_delivery" value="<?= $deliveryIncentiveSetting ?>" min="0" step="0.01"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm" placeholder="0.00">
                </div>
            </div>
        </div>

        <!-- Lead Sources link -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Lead Sources</h3>
                    <p class="text-xs text-mb-subtle mt-2 ml-5"><?= count($leadSources) ?> source option<?= count($leadSources) !== 1 ? 's' : '' ?> configured.</p>
                </div>
                <a href="lead_sources.php" class="bg-mb-black border border-mb-subtle/20 text-mb-silver px-5 py-2 rounded-full hover:border-mb-accent/40 hover:text-white transition-colors text-sm">Manage Lead Sources</a>
            </div>
        </div>

        <!-- Pagination Setting -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Table Pagination</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Number of items shown per page across all list views (reservations, clients, vehicles, staff).</p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Items Per Page</label>
                <select name="per_page" class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                    <?php foreach ([10, 15, 25, 50, 100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $perPageSetting === $opt ? 'selected' : '' ?>><?= $opt ?> items per page</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-mb-subtle mt-1">Default: 25. Applies to all list screens.</p>
            </div>
            <div class="pt-4 border-t border-mb-subtle/10">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <label for="pipelinePaginationToggle" class="block text-sm text-mb-silver mb-1">Pipeline Pagination</label>
                        <p class="text-xs text-mb-subtle">Enable or disable pagination only for the Pipeline board.</p>
                    </div>
                    <label for="pipelinePaginationToggle" class="relative inline-flex items-center cursor-pointer select-none">
                        <input type="hidden" name="pipeline_pagination_enabled" value="0">
                        <input id="pipelinePaginationToggle" type="checkbox" name="pipeline_pagination_enabled" value="1"
                            class="sr-only peer" <?= $pipelinePaginationEnabledSetting ? 'checked' : '' ?>>
                        <span class="w-12 h-6 rounded-full bg-mb-black border border-mb-subtle/30 transition-colors peer-checked:bg-mb-accent peer-checked:border-mb-accent/60"></span>
                        <span class="absolute left-0.5 top-0.5 w-5 h-5 rounded-full bg-white transition-transform peer-checked:translate-x-6"></span>
                    </label>
                </div>
            </div>
            <div class="pt-4 border-t border-mb-subtle/10">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <label class="block text-sm text-mb-silver mb-1">Auto-close Lost after Follow-ups</label>
                        <p class="text-xs text-mb-subtle">Automatically move lead to Closed Lost after this many follow-ups.</p>
                    </div>
                    <select name="auto_close_lost_after_followups" class="bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-mb-accent text-sm">
                        <option value="0" <?= $autoCloseAfterSetting === 0 ? 'selected' : '' ?>>Disabled</option>
                        <option value="1" <?= $autoCloseAfterSetting === 1 ? 'selected' : '' ?>>1 follow-up</option>
                        <option value="2" <?= $autoCloseAfterSetting === 2 ? 'selected' : '' ?>>2 follow-ups</option>
                        <option value="3" <?= $autoCloseAfterSetting === 3 ? 'selected' : '' ?>>3 follow-ups</option>
                        <option value="4" <?= $autoCloseAfterSetting === 4 ? 'selected' : '' ?>>4 follow-ups</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <div>
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Mobile Bottom Bar Menus</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">Choose exactly 5 items for the mobile bottom navigation bar.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ($mobileBottomNavCatalog as $menuKey => $menuLabel): ?>
                    <label class="flex items-center gap-3 bg-mb-black/50 border border-mb-subtle/20 rounded-lg px-4 py-3 text-sm text-mb-silver hover:border-mb-accent/40 transition-colors cursor-pointer">
                        <input type="checkbox"
                            name="mobile_bottom_nav_keys[]"
                            value="<?= e($menuKey) ?>"
                            class="rounded border-mb-subtle/30 bg-mb-black text-mb-accent focus:ring-mb-accent/40 mobile-bottom-nav-checkbox"
                            <?= in_array($menuKey, $mobileBottomNavSelectedKeys, true) ? 'checked' : '' ?>>
                        <span><?= e($menuLabel) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p id="mobileBottomNavCount" class="text-xs text-mb-subtle">Selected: 0 / 5</p>
        </div>

        <div class="flex items-center justify-end gap-4">
            <button type="submit" class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Save Settings
            </button>
        </div>
    </form>

    <?php if (defined('EXPORT_ENABLED') && EXPORT_ENABLED): ?>
        <div class="bg-mb-surface border border-yellow-500/20 rounded-xl p-6">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <h3 class="text-white font-light text-lg border-l-2 border-yellow-500 pl-3">Data Export</h3>
                    <p class="text-xs text-mb-subtle mt-2 ml-5">Download a full <strong class="text-mb-silver">.xlsx</strong> backup of all data.</p>
                </div>
                <a href="../export/index.php" class="flex items-center gap-2 bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 px-5 py-2 rounded-full hover:bg-yellow-500/20 transition-all text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Export Data
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const boxes = Array.from(document.querySelectorAll('.mobile-bottom-nav-checkbox'));
    const counter = document.getElementById('mobileBottomNavCount');
    if (!boxes.length || !counter) return;

    function selectedCount() {
        return boxes.filter(function (box) { return box.checked; }).length;
    }

    function refreshCounter() {
        const count = selectedCount();
        counter.textContent = 'Selected: ' + count + ' / 5';
        if (count === 5) {
            counter.className = 'text-xs text-green-400';
        } else {
            counter.className = 'text-xs text-red-400';
        }
    }

    boxes.forEach(function (box) {
        box.addEventListener('change', function () {
            const count = selectedCount();
            if (count > 5) {
                box.checked = false;
                return;
            }
            refreshCounter();
        });
    });

    refreshCounter();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
