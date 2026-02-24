<?php
require_once __DIR__ . '/../config/db.php';
$pageTitle = 'GPS Tracking';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="space-y-6">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10">
            <h3 class="text-white font-light text-lg">GPS Tracking</h3>
        </div>
        <table class="w-full text-left">
            <thead class="bg-mb-black text-mb-silver uppercase text-xs tracking-wider">
                <tr>
                    <th class="px-6 py-4 font-medium">Vehicle</th>
                    <th class="px-6 py-4 font-medium">Device ID</th>
                    <th class="px-6 py-4 font-medium">Last Location</th>
                    <th class="px-6 py-4 font-medium">Last Seen</th>
                    <th class="px-6 py-4 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-mb-subtle/10 text-sm">
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-mb-subtle italic">No GPS devices configured.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>