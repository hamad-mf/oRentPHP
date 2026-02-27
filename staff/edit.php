<?php
require_once __DIR__ . '/../config/db.php';
auth_require_admin();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect('index.php');

// Load staff + user
$stmt = $pdo->prepare("SELECT s.*, u.id as user_id, u.username, u.role as user_role, u.is_active
    FROM staff s LEFT JOIN users u ON u.staff_id = s.id WHERE s.id = ? LIMIT 1");
$stmt->execute([$id]);
$staff = $stmt->fetch();
if (!$staff) redirect('index.php');

$userId = (int)($staff['user_id'] ?? 0);

// Load permissions
$currentPerms = [];
if ($userId) {
    $pStmt = $pdo->prepare("SELECT permission FROM staff_permissions WHERE user_id = ?");
    $pStmt->execute([$userId]);
    $currentPerms = $pStmt->fetchAll(PDO::FETCH_COLUMN);
}

$allPerms = [
    'add_vehicles'      => 'Add / Edit Vehicles',
    'add_reservations'  => 'Add / Edit Reservations',
    'do_delivery'       => 'Perform Deliveries',
    'do_return'         => 'Perform Returns',
    'add_leads'         => 'Add / Edit Leads',
    'manage_clients'    => 'Manage Clients',
    'view_finances'     => 'View Financial Data',
    'manage_staff'      => 'View Staff Section',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $staffRole  = trim($_POST['staff_role'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $newPassword= trim($_POST['new_password'] ?? '');
    $userRole   = $_POST['role_field'] ?? 'staff';
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $salary     = $_POST['salary'] ?? '';
    $joined     = $_POST['joined_date'] ?? '';
    $notes      = trim($_POST['notes'] ?? '');
    $perms      = $_POST['permissions'] ?? [];

    if (!$name)     $errors[] = 'Name is required.';
    if (!$username) $errors[] = 'Username is required.';
    if ($newPassword && strlen($newPassword) < 6) $errors[] = 'New password must be at least 6 characters.';

    // Check username uniqueness (excluding current user)
    if (!$errors) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $chk->execute([$username, $userId]);
        if ($chk->fetch()) $errors[] = "Username '$username' is already taken.";
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            // Update staff
            $us = $pdo->prepare("UPDATE staff SET name=?, role=?, phone=?, email=?, salary=?, joined_date=?, notes=?, updated_at=NOW() WHERE id=?");
            $us->execute([$name, $staffRole ?: null, $phone ?: null, $email ?: null, $salary !== '' ? (float)$salary : null, $joined ?: null, $notes ?: null, $id]);

            // Update user
            if ($userId) {
                if ($newPassword) {
                    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $uu = $pdo->prepare("UPDATE users SET name=?, username=?, password_hash=?, role=?, is_active=? WHERE id=?");
                    $uu->execute([$name, $username, $hash, $userRole, $isActive, $userId]);
                } else {
                    $uu = $pdo->prepare("UPDATE users SET name=?, username=?, role=?, is_active=? WHERE id=?");
                    $uu->execute([$name, $username, $userRole, $isActive, $userId]);
                }

                // Sync permissions
                $pdo->prepare("DELETE FROM staff_permissions WHERE user_id=?")->execute([$userId]);
                if ($userRole !== 'admin' && !empty($perms)) {
                    $ip = $pdo->prepare("INSERT INTO staff_permissions (user_id, permission) VALUES (?,?)");
                    foreach ($perms as $perm) {
                        if (isset($allPerms[$perm])) $ip->execute([$userId, $perm]);
                    }
                }
            }

            $pdo->commit();
            flash('success', "Staff member '$name' updated successfully.");
            redirect('show.php?id=' . $id);
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Staff';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Staff</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors"><?= e($staff['name']) ?></a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-white">Edit</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl px-5 py-4 text-sm space-y-1">
            <?php foreach ($errors as $err): ?><p>• <?= e($err) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <!-- Personal Info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Personal Information</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Full Name <span class="text-red-400">*</span></label>
                    <input type="text" name="name" value="<?= e($staff['name']) ?>" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Job Title / Role</label>
                    <input type="text" name="staff_role" value="<?= e($staff['role'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="e.g. Driver">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Phone</label>
                    <input type="text" name="phone" value="<?= e($staff['phone'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Email</label>
                    <input type="email" name="email" value="<?= e($staff['email'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Salary</label>
                    <input type="number" name="salary" value="<?= e($staff['salary'] ?? '') ?>" min="0" step="0.01"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Joined Date</label>
                    <input type="date" name="joined_date" value="<?= e($staff['joined_date'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Notes</label>
                <textarea name="notes" rows="2"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= e($staff['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Login -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Login Account</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Account Type</label>
                    <select name="role_field" id="role_field" onchange="togglePerms()"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <option value="staff" <?= ($staff['user_role'] ?? 'staff') === 'staff' ? 'selected' : '' ?>>Staff</option>
                        <option value="admin" <?= ($staff['user_role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin (full access)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Username <span class="text-red-400">*</span></label>
                    <input type="text" name="username" value="<?= e($staff['username'] ?? '') ?>" required autocomplete="off"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">New Password <span class="text-mb-subtle text-xs">(leave blank to keep current)</span></label>
                    <input type="password" name="new_password" autocomplete="new-password"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="Leave blank to keep">
                </div>
                <div class="flex items-center gap-3 pt-7">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" <?= ($staff['is_active'] ?? 1) ? 'checked' : '' ?>
                            class="w-4 h-4 rounded accent-mb-accent">
                        <span class="text-sm text-mb-silver">Account Active</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Permissions -->
        <div id="perms-section" class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Permissions</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ($allPerms as $key => $label): ?>
                    <label class="flex items-center gap-3 p-3 bg-mb-black/40 border border-mb-subtle/10 rounded-lg hover:border-mb-accent/30 cursor-pointer transition-colors group">
                        <input type="checkbox" name="permissions[]" value="<?= $key ?>"
                            <?= in_array($key, $currentPerms, true) ? 'checked' : '' ?>
                            class="w-4 h-4 rounded accent-mb-accent">
                        <span class="text-sm text-mb-silver group-hover:text-white transition-colors"><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="show.php?id=<?= $id ?>" class="text-mb-subtle hover:text-white transition-colors text-sm px-6 py-3">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Save Changes
            </button>
        </div>
    </form>
</div>

<script>
function togglePerms() {
    const role = document.getElementById('role_field').value;
    document.getElementById('perms-section').style.display = role === 'admin' ? 'none' : '';
}
togglePerms();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
