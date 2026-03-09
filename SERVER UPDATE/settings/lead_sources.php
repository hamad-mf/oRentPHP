<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();

$errors = [];
$leadSources = lead_sources_get_map($pdo);
$sourcesInput = implode("\n", array_values($leadSources));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourcesInput = trim((string) ($_POST['sources_text'] ?? ''));
    $parsedSources = lead_sources_parse_textarea($sourcesInput);

    if (empty($parsedSources)) {
        $errors['sources_text'] = 'Please provide at least one lead source.';
    }

    if (empty($errors)) {
        settings_set($pdo, 'lead_sources', lead_sources_encode_map($parsedSources));
        app_log('ACTION', 'Updated lead sources settings');
flash('success', 'Lead sources updated successfully.');
        redirect('lead_sources.php');
    }

    $leadSources = $parsedSources;
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
            class="px-6 py-2 rounded-full text-sm font-medium transition-all bg-mb-accent text-white shadow-lg shadow-mb-accent/20">Lead
            Sources</a>
        <a href="expense_categories.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Expense
            Categories</a>
        <a href="staff_permissions.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Staff
            Permissions</a>
    </div>

    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <span class="text-white">Settings</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Lead Sources</span>
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
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Lead Source Options</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">
                    Add one source per line. Example: <span class="text-mb-silver">Walk-in</span>,
                    <span class="text-mb-silver">Phone Call</span>, <span class="text-mb-silver">Facebook Ads</span>.
                    These options will appear in lead create/edit forms.
                </p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Configured Sources</label>
                <textarea name="sources_text" rows="8"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-y"
                    placeholder="Walk-in&#10;Phone Call&#10;WhatsApp"><?= e($sourcesInput) ?></textarea>
                <?php if ($errors['sources_text'] ?? ''): ?>
                    <p class="text-red-400 text-xs mt-2">
                        <?= e($errors['sources_text']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10">
                <h4 class="text-white text-sm font-medium">Stored Keys (auto-generated)</h4>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr
                            class="bg-mb-black/40 text-mb-subtle text-xs uppercase tracking-widest border-b border-mb-subtle/10">
                            <th class="px-6 py-3 font-medium">Label</th>
                            <th class="px-6 py-3 font-medium">Key</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mb-subtle/10">
                        <?php foreach ($leadSources as $value => $label): ?>
                            <tr>
                                <td class="px-6 py-3 text-white"><?= e($label) ?></td>
                                <td class="px-6 py-3 text-mb-silver text-sm"><?= e($value) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Save Sources
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
