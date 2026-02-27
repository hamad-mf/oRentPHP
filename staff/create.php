<?php
require_once __DIR__ . '/../config/db.php';
auth_require_admin();
$pdo = db();

// All permissions list
$allPerms = [
    'add_vehicles' => 'Add / Edit Vehicles',
    'add_reservations' => 'Add / Edit Reservations',
    'do_delivery' => 'Perform Deliveries',
    'do_return' => 'Perform Returns',
    'add_leads' => 'Add / Edit Leads',
    'manage_clients' => 'Manage Clients',
    'view_finances' => 'View Financial Data',
    'manage_staff' => 'View Staff Section',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_field = $_POST['role_field'] ?? 'staff';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $salary = $_POST['salary'] ?? '';
    $joined = $_POST['joined_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $perms = $_POST['permissions'] ?? [];

    if (!$name)
        $errors[] = 'Name is required.';
    if (!$username)
        $errors[] = 'Username is required.';
    if (!$password)
        $errors[] = 'Password is required.';
    if (strlen($password) < 6)
        $errors[] = 'Password must be at least 6 characters.';

    // Check username uniqueness
    if (!$errors) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $chk->execute([$username]);
        if ($chk->fetch())
            $errors[] = "Username '$username' is already taken.";
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            // Insert staff record
            $is = $pdo->prepare("INSERT INTO staff (name, role, phone, email, salary, joined_date, notes) VALUES (?,?,?,?,?,?,?)");
            $is->execute([
                $name,
                $role_field === 'admin' ? 'Admin' : trim($_POST['staff_role'] ?? ''),
                $phone ?: null,
                $email ?: null,
                $salary !== '' ? (float) $salary : null,
                $joined ?: null,
                $notes ?: null,
            ]);
            $staffId = (int) $pdo->lastInsertId();

            // Insert user account
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $iu = $pdo->prepare("INSERT INTO users (name, username, password_hash, role, staff_id) VALUES (?,?,?,?,?)");
            $iu->execute([$name, $username, $hash, $role_field === 'admin' ? 'admin' : 'staff', $staffId]);
            $userId = (int) $pdo->lastInsertId();

            // Insert permissions
            if ($role_field !== 'admin' && !empty($perms)) {
                $ip = $pdo->prepare("INSERT INTO staff_permissions (user_id, permission) VALUES (?,?)");
                foreach ($perms as $perm) {
                    if (isset($allPerms[$perm])) {
                        $ip->execute([$userId, $perm]);
                    }
                }
            }

            $pdo->commit();
            flash('success', "Staff member '$name' added successfully.");
            redirect('index.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Staff';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Staff</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Add Staff</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl px-5 py-4 text-sm space-y-1">
            <?php foreach ($errors as $err): ?>
                <p>•
                    <?= e($err) ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <!-- Personal Info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Personal Information</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Full Name <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="name" value="<?= old('name') ?>" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="e.g. Ahmed Ali">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Job Title / Role</label>
                    <input type="text" name="staff_role" value="<?= old('staff_role') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="e.g. Driver, Coordinator">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Phone</label>
                    <input type="text" name="phone" value="<?= old('phone') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="+971 50 000 0000">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Email</label>
                    <input type="email" name="email" value="<?= old('email') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="staff@example.com">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Salary</label>
                    <input type="number" name="salary" value="<?= old('salary') ?>" min="0" step="0.01"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Joined Date</label>
                    <input type="date" name="joined_date" value="<?= old('joined_date') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-1.5">Notes</label>
                <textarea name="notes" rows="2"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"
                    placeholder="Optional internal notes..."><?= old('notes') ?></textarea>
            </div>
        </div>

        <!-- Login Credentials -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Login Credentials</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Account Type</label>
                    <select name="role_field" id="role_field" onchange="togglePerms()"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin (full access)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Username <span
                            class="text-red-400">*</span></label>
                    <input type="text" name="username" value="<?= old('username') ?>" required autocomplete="off"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="e.g. ahmed_driver">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Password <span
                            class="text-red-400">*</span></label>
                    <input type="password" name="password" required autocomplete="new-password"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="Min. 6 characters">
                </div>
            </div>
        </div>

        <!-- Permissions -->
        <div id="perms-section" class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Permissions</h3>
            <p class="text-xs text-mb-subtle">Select what this staff member can access.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ($allPerms as $key => $label): ?>
                    <label
                        class="flex items-center gap-3 p-3 bg-mb-black/40 border border-mb-subtle/10 rounded-lg hover:border-mb-accent/30 cursor-pointer transition-colors group">
                        <input type="checkbox" name="permissions[]" value="<?= $key ?>"
                            class="w-4 h-4 rounded accent-mb-accent">
                        <span class="text-sm text-mb-silver group-hover:text-white transition-colors">
                            <?= e($label) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="index.php" class="text-mb-subtle hover:text-white transition-colors text-sm px-6 py-3">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Add Staff Member
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