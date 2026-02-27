<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('manage_clients')) {
    flash('error', 'You do not have permission to add clients.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (!$name)
        $errors['name'] = 'Name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Invalid email format.';
    if (!$phone)
        $errors['phone'] = 'Phone is required.';

    if ($email && !isset($errors['email'])) {
        $chk = db()->prepare('SELECT id FROM clients WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch())
            $errors['email'] = 'Email already in use.';
    }

    if (empty($errors)) {
        // Handle proof file upload
        $proofFile = null;
        if (!empty($_FILES['proof_file']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/clients/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0775, true);
            $ext = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            if (!in_array($ext, $allowed)) {
                $errors['proof_file'] = 'Only JPG, PNG, or PDF allowed.';
            } elseif ($_FILES['proof_file']['size'] > 5 * 1024 * 1024) {
                $errors['proof_file'] = 'File must be under 5MB.';
            } else {
                $proofFile = 'uploads/clients/' . uniqid('proof_') . '.' . $ext;
                move_uploaded_file($_FILES['proof_file']['tmp_name'], __DIR__ . '/../' . $proofFile);
            }
        }

        if (empty($errors)) {
            $stmt = db()->prepare('INSERT INTO clients (name,email,phone,address,notes,proof_file) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$name, $email ?: null, $phone, $address, $notes, $proofFile]);
            $id = db()->lastInsertId();
            flash('success', "Client $name added successfully.");
            redirect("show.php?id=$id");
        }
    }
}

$pageTitle = 'Add New Client';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Clients</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Add Client</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400 space-y-1">
            <?php foreach ($errors as $e): ?>
                <p>&bull;
                    <?= e($e) ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Client Information</h3>
            <?php
            function cf(string $name, string $label, string $type = 'text', array $errors = [], bool $required = true, string $placeholder = ''): void
            {
                $val = htmlspecialchars($_POST[$name] ?? '', ENT_QUOTES);
                $err = $errors[$name] ?? '';
                $req = $required ? 'required' : '';
                echo "<div><label class='block text-sm text-mb-silver mb-2'>$label" . ($required ? " <span class='text-red-400'>*</span>" : '') . "</label>
                <input type='$type' name='$name' value='$val' placeholder='$placeholder' $req
                    class='w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm'>
                " . ($err ? "<p class='text-red-400 text-xs mt-1'>$err</p>" : '') . "</div>";
            }
            ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php cf('name', 'Full Name', 'text', $errors, true, 'John Doe'); ?>
                <?php cf('email', 'Email Address', 'email', $errors, false, 'john@example.com (optional)'); ?>
                <?php cf('phone', 'Phone Number', 'text', $errors, true, '+1 234 567 8900'); ?>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Address</label>
                <textarea name="address" rows="2" placeholder="Street, City, Country"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Notes</label>
                <textarea name="notes" rows="3" placeholder="Any additional notes..."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <!-- Proof Document -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">ID / Proof Document <span
                        class="text-mb-subtle text-xs">(optional — JPG, PNG or PDF, max 5MB)</span></label>
                <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf"
                    class="block w-full text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-mb-accent hover:file:bg-mb-surface/80 cursor-pointer border border-mb-subtle/20 rounded-lg p-2">
                <?php if ($errors['proof_file'] ?? ''): ?>
                    <p class="text-red-400 text-xs mt-1"><?= e($errors['proof_file']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center justify-end gap-4">
            <a href="index.php" class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Add Client
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>