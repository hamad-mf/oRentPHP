<?php
require_once __DIR__ . '/../config/db.php';
$pageTitle = 'Papers';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="space-y-6">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10">
            <h3 class="text-white font-light text-lg">Vehicle Documents (Papers)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-mb-black text-mb-silver uppercase text-xs tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-medium">Vehicle</th>
                        <th class="px-6 py-4 font-medium">Document</th>
                        <th class="px-6 py-4 font-medium">Expiry</th>
                        <th class="px-6 py-4 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-mb-subtle/10 text-sm">
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-mb-subtle italic">No documents recorded.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
