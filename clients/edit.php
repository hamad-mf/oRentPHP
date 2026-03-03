<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('manage_clients')) {
    flash('error', 'You do not have permission to edit clients.');
    redirect('index.php');
}
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();
$cStmt = $pdo->prepare('SELECT * FROM clients WHERE id=?');
$cStmt->execute([$id]);
$c = $cStmt->fetch();
if (!$c) {
    flash('error', 'Client not found.');
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
        $errors['email'] = 'Invalid email.';
    if (!$phone)
        $errors['phone'] = 'Phone is required.';
    if ($email && !isset($errors['email'])) {
        $chk = $pdo->prepare('SELECT id FROM clients WHERE email=? AND id!=?');
        $chk->execute([$email, $id]);
        if ($chk->fetch())
            $errors['email'] = 'Email already used.';
    }
    if (empty($errors)) {
        // Handle proof file upload
        $proofFile = $c['proof_file'] ?? null;
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
            $pdo->prepare('UPDATE clients SET name=?,email=?,phone=?,address=?,notes=?,proof_file=? WHERE id=?')
                ->execute([$name, $email ?: null, $phone, $address, $notes, $proofFile, $id]);
            app_log('ACTION', "Updated client: $name (ID: $id)");
            flash('success', 'Client updated successfully.');
            redirect("show.php?id=$id");
        }
    }
    $c = array_merge($c, $_POST);
}
$pageTitle = 'Edit Client';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Clients</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors">
            <?= e($c['name']) ?>
        </a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Edit</span>
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
            <?php foreach ([['name', 'Full Name', 'text', true], ['email', 'Email (optional)', 'email', false], ['phone', 'Phone', 'text', true]] as [$n, $l, $t, $r]):
                $val = htmlspecialchars($c[$n] ?? '', ENT_QUOTES);
                $err = $errors[$n] ?? ''; ?>
                <div><label class="block text-sm text-mb-silver mb-2">
                        <?= $l ?>
                        <?= $r ? " <span class='text-red-400'>*</span>" : '' ?>
                    </label>
                    <input type="<?= $t ?>" name="<?= $n ?>" value="<?= $val ?>" <?= $r ? 'required' : '' ?> class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none
                focus:border-mb-accent transition-colors text-sm">
                    <?= $err ? "<p class='text-red-400 text-xs mt-1'>$err</p>" : '' ?>
                </div>
            <?php endforeach; ?>
            <div><label class="block text-sm text-mb-silver mb-2">Address</label>
                <textarea name="address" rows="2"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($c['address'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <div><label class="block text-sm text-mb-silver mb-2">Notes</label>
                <textarea name="notes" rows="3"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($c['notes'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <!-- Proof Document -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">ID / Proof Document
                    <span class="text-mb-subtle text-xs">(JPG, PNG or PDF, max 5MB)</span></label>
                <?php if (!empty($c['proof_file'])): ?>
                    <div class="mb-2 flex items-center gap-3">
                        <span class="text-mb-subtle text-xs">Current:</span>
                        <a href="<?= $root . e($c['proof_file']) ?>" target="_blank"
                            class="text-mb-accent text-xs hover:underline">📎 View existing file</a>
                    </div>
                <?php endif; ?>
                <input type="file" name="proof_file" accept=".jpg,.jpeg,.png,.pdf"
                    class="block w-full text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-mb-accent hover:file:bg-mb-surface/80 cursor-pointer border border-mb-subtle/20 rounded-lg p-2">
                <p class="text-mb-subtle text-xs mt-1">Leave blank to keep existing file.</p>
                <?php if ($errors['proof_file'] ?? ''): ?>
                    <p class="text-red-400 text-xs mt-1"><?= e($errors['proof_file']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center justify-end gap-4">
            <a href="show.php?id=<?= $id ?>"
                class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium">Save
                Changes</button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>