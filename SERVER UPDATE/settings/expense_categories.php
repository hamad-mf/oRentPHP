<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();

auth_require_admin();

$errors = [];
$categories = expense_categories_get_list($pdo);
$categoriesInput = implode("\n", $categories);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoriesInput = trim((string) ($_POST['categories_text'] ?? ''));
    $parsedCategories = expense_categories_parse_textarea($categoriesInput);

    if (empty($parsedCategories)) {
        $errors['categories_text'] = 'Please provide at least one expense category.';
    }

    if (empty($errors)) {
        settings_set($pdo, 'expense_categories', expense_categories_encode_list($parsedCategories));
        app_log('ACTION', 'Updated expense categories settings');
        flash('success', 'Expense categories updated successfully.');
        redirect('expense_categories.php');
    }

    $categories = $parsedCategories;
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
            class="px-6 py-2 rounded-full text-sm font-medium transition-all bg-mb-accent text-white shadow-lg shadow-mb-accent/20">Expense
            Categories</a>
        <a href="staff_permissions.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Staff
            Permissions</a>
        <a href="attendance.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Attendance</a>
        <a href="notifications.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Notifications</a>
    </div>

    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <span class="text-white">Settings</span>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Expense Categories</span>
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
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Expense Category Options</h3>
                <p class="text-xs text-mb-subtle mt-2 ml-5">
                    Add one category per line. These categories appear in the manual expense dropdown on Accounts screen.
                </p>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Configured Categories</label>
                <textarea name="categories_text" rows="10"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-y"
                    placeholder="Fuel&#10;Office Expense&#10;Rent&#10;Salary&#10;Miscellaneous"><?= e($categoriesInput) ?></textarea>
                <?php if ($errors['categories_text'] ?? ''): ?>
                    <p class="text-red-400 text-xs mt-2">
                        <?= e($errors['categories_text']) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6">
            <h4 class="text-white text-sm font-medium mb-3">Preview</h4>
            <?php if (empty($categories)): ?>
                <p class="text-xs text-mb-subtle">No categories configured yet.</p>
            <?php else: ?>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($categories as $category): ?>
                        <span class="px-3 py-1 rounded-full text-xs bg-mb-black border border-mb-subtle/20 text-mb-silver">
                            <?= e($category) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex items-center justify-end gap-4">
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Save Categories
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
