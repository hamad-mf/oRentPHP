<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['item_name'] ?? '');
        $cost = (float) ($_POST['cost'] ?? 0);
        if ($name !== '') {
            $pdo->prepare("INSERT INTO damage_costs (item_name, cost) VALUES (?, ?)")->execute([$name, $cost]);
            app_log('ACTION', 'Updated damage costs settings');
flash('success', 'Damage cost item added successfully.');
        }
    } elseif ($action === 'edit') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['item_name'] ?? '');
        $cost = (float) ($_POST['cost'] ?? 0);
        if ($id > 0 && $name !== '') {
            $pdo->prepare("UPDATE damage_costs SET item_name = ?, cost = ? WHERE id = ?")->execute([$name, $cost, $id]);
            app_log('ACTION', 'Updated damage costs settings');
flash('success', 'Damage cost item updated successfully.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM damage_costs WHERE id = ?")->execute([$id]);
            app_log('ACTION', 'Updated damage costs settings');
flash('success', 'Damage cost item deleted.');
        }
    }
    redirect('damage_costs.php');
}

$items = $pdo->query("SELECT * FROM damage_costs ORDER BY item_name ASC")->fetchAll();

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">
    <!-- Navigation Tabs -->
    <div class="flex gap-1 bg-mb-surface border border-mb-subtle/20 p-1 rounded-full w-fit flex-wrap">
        <a href="general.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all text-mb-silver hover:text-white">Return
            Charges</a>
        <a href="damage_costs.php"
            class="px-6 py-2 rounded-full text-sm font-medium transition-all bg-mb-accent text-white shadow-lg shadow-mb-accent/20">Damage
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
    </div>
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-light text-white tracking-wide">Damage Costs Management</h2>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
            class="bg-mb-accent text-white px-6 py-2 rounded-full hover:bg-opacity-90 transition-all font-medium shadow-lg shadow-mb-accent/20">
            Add New Item
        </button>
    </div>

    <!-- Items Table -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr
                        class="bg-mb-black/40 text-mb-subtle text-xs uppercase tracking-widest border-b border-mb-subtle/10">
                        <th class="px-6 py-4 font-medium">Item Name</th>
                        <th class="px-6 py-4 font-medium">Standard Cost</th>
                        <th class="px-6 py-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/10">
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-mb-subtle">
                                No damage cost items defined yet.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-mb-black/20 transition-colors group">
                            <td class="px-6 py-4 text-white font-light">
                                <?= e($item['item_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-mb-silver">$
                                <?= number_format($item['cost'], 2) ?>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <button onclick="openEditModal(<?= e(json_encode($item)) ?>)"
                                        class="text-mb-subtle hover:text-mb-accent transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this item?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="text-mb-subtle hover:text-red-400 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal"
    class="fixed inset-0 bg-mb-black/80 backdrop-blur-sm z-[100] flex items-center justify-center p-4 hidden">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-2xl w-full max-w-md overflow-hidden animate-fadeIn">
        <div class="p-6 border-b border-mb-subtle/10 flex items-center justify-between">
            <h3 class="text-lg font-light text-white uppercase tracking-widest">Add Damage Item</h3>
            <button onclick="document.getElementById('addModal').classList.add('hidden')"
                class="text-mb-subtle hover:text-white">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add">
            <div>
                <label class="block text-xs text-mb-subtle uppercase mb-2">Item Name</label>
                <input type="text" name="item_name" required placeholder="e.g. Bumper Damage"
                    class="w-full bg-mb-black/40 border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:border-mb-accent outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs text-mb-subtle uppercase mb-2">Standard Cost ($)</label>
                <input type="number" name="cost" step="0.01" required placeholder="0.00"
                    class="w-full bg-mb-black/40 border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:border-mb-accent outline-none transition-colors">
            </div>
            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
                    class="flex-1 px-6 py-3 rounded-xl border border-mb-subtle/20 text-white hover:bg-white/5 transition-all">Cancel</button>
                <button type="submit"
                    class="flex-1 px-6 py-3 rounded-xl bg-mb-accent text-white font-medium hover:bg-opacity-90 transition-all">Save
                    Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal"
    class="fixed inset-0 bg-mb-black/80 backdrop-blur-sm z-[100] flex items-center justify-center p-4 hidden">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-2xl w-full max-w-md overflow-hidden animate-fadeIn">
        <div class="p-6 border-b border-mb-subtle/10 flex items-center justify-between">
            <h3 class="text-lg font-light text-white uppercase tracking-widest">Edit Damage Item</h3>
            <button onclick="document.getElementById('editModal').classList.add('hidden')"
                class="text-mb-subtle hover:text-white">✕</button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">
            <div>
                <label class="block text-xs text-mb-subtle uppercase mb-2">Item Name</label>
                <input type="text" name="item_name" id="edit-name" required
                    class="w-full bg-mb-black/40 border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:border-mb-accent outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs text-mb-subtle uppercase mb-2">Standard Cost ($)</label>
                <input type="number" name="cost" id="edit-cost" step="0.01" required
                    class="w-full bg-mb-black/40 border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:border-mb-accent outline-none transition-colors">
            </div>
            <div class="pt-4 flex gap-3">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')"
                    class="flex-1 px-6 py-3 rounded-xl border border-mb-subtle/20 text-white hover:bg-white/5 transition-all">Cancel</button>
                <button type="submit"
                    class="flex-1 px-6 py-3 rounded-xl bg-mb-accent text-white font-medium hover:bg-opacity-90 transition-all">Update
                    Item</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(item) {
        document.getElementById('edit-id').value = item.id;
        document.getElementById('edit-name').value = item.item_name;
        document.getElementById('edit-cost').value = item.cost;
        document.getElementById('editModal').classList.remove('hidden');
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
