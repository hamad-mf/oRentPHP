<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/client_helpers.php';
if (!auth_has_perm('manage_clients')) {
    flash('error', 'You do not have permission to edit clients.');
    redirect('index.php');
}
$id = (int) ($_GET['id'] ?? 0);
$pdo = db();
clients_ensure_schema($pdo);
$supportsAlternativeNumber = clients_has_column($pdo, 'alternative_number');
$supportsClientProofs = clients_has_table($pdo, 'client_proofs');
$cStmt = $pdo->prepare('SELECT * FROM clients WHERE id=?');
$cStmt->execute([$id]);
$c = $cStmt->fetch();
if (!$c) {
    flash('error', 'Client not found.');
    redirect('index.php');
}

$clientProofs = [];
if ($supportsClientProofs) {
    try {
        $pStmt = $pdo->prepare('SELECT * FROM client_proofs WHERE client_id = ? ORDER BY created_at DESC');
        $pStmt->execute([$id]);
        $clientProofs = $pStmt->fetchAll();
    } catch (Throwable $e) {
        $clientProofs = [];
    }
}
$legacyProofFile = $c['proof_file'] ?? null;
$existingProofCount = count($clientProofs) + ($legacyProofFile ? 1 : 0);
$remainingProofSlots = max(0, 5 - $existingProofCount);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $alternativeNumber = trim($_POST['alternative_number'] ?? '');
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
    if ($phone && !isset($errors['phone'])) {
        $chk = $pdo->prepare('SELECT id FROM clients WHERE phone=? AND id!=?');
        $chk->execute([$phone, $id]);
        if ($chk->fetch())
            $errors['phone'] = 'Phone already used.';
    }
    if (empty($errors)) {
        // Handle proof file upload(s)
        $proofUploads = [];
        $proofFile = $c['proof_file'] ?? null;
        if ($supportsClientProofs) {
            if (!empty($_FILES['proof_files']['name'][0])) {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                $selectedCount = 0;
                foreach ($_FILES['proof_files']['name'] as $fileName) {
                    if ($fileName !== '') {
                        $selectedCount++;
                    }
                }
                if ($selectedCount > $remainingProofSlots) {
                    $errors['proof_files'] = $remainingProofSlots > 0
                        ? "You can upload up to {$remainingProofSlots} more proof file(s)."
                        : 'Maximum of 5 proof files already uploaded.';
                } else {
                    foreach ($_FILES['proof_files']['tmp_name'] as $i => $tmp) {
                        $origName = $_FILES['proof_files']['name'][$i] ?? '';
                        if ($origName === '') {
                            continue;
                        }
                        $err = $_FILES['proof_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
                        if ($err !== UPLOAD_ERR_OK) {
                            $errors['proof_files'] = 'Failed to upload one of the proof files.';
                            break;
                        }
                        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowed, true)) {
                            $errors['proof_files'] = 'Only JPG or PNG images allowed.';
                            break;
                        }
                        if (($_FILES['proof_files']['size'][$i] ?? 0) > 5 * 1024 * 1024) {
                            $errors['proof_files'] = 'Each file must be under 5MB.';
                            break;
                        }
                        $proofUploads[] = [
                            'tmp' => $tmp,
                            'name' => basename($origName),
                            'ext' => $ext,
                        ];
                    }
                }
            }
        } else {
            if (!empty($_FILES['proof_file']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/clients/';
                if (!is_dir($uploadDir))
                    mkdir($uploadDir, 0775, true);
                $ext = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                if (!in_array($ext, $allowed)) {
                    $errors['proof_file'] = 'Only JPG or PNG images allowed.';
                } elseif ($_FILES['proof_file']['size'] > 5 * 1024 * 1024) {
                    $errors['proof_file'] = 'File must be under 5MB.';
                } else {
                    $proofFile = 'uploads/clients/' . uniqid('proof_') . '.' . $ext;
                    move_uploaded_file($_FILES['proof_file']['tmp_name'], __DIR__ . '/../' . $proofFile);
                }
            }
        }
        if (empty($errors)) {
            if ($supportsClientProofs) {
                if ($supportsAlternativeNumber) {
                    $pdo->prepare('UPDATE clients SET name=?,email=?,phone=?,alternative_number=?,address=?,notes=? WHERE id=?')
                        ->execute([$name, $email ?: null, $phone, $alternativeNumber ?: null, $address, $notes, $id]);
                } else {
                    $pdo->prepare('UPDATE clients SET name=?,email=?,phone=?,address=?,notes=? WHERE id=?')
                        ->execute([$name, $email ?: null, $phone, $address, $notes, $id]);
                }
            } else {
                if ($supportsAlternativeNumber) {
                    $pdo->prepare('UPDATE clients SET name=?,email=?,phone=?,alternative_number=?,address=?,notes=?,proof_file=? WHERE id=?')
                        ->execute([$name, $email ?: null, $phone, $alternativeNumber ?: null, $address, $notes, $proofFile, $id]);
                } else {
                    $pdo->prepare('UPDATE clients SET name=?,email=?,phone=?,address=?,notes=?,proof_file=? WHERE id=?')
                        ->execute([$name, $email ?: null, $phone, $address, $notes, $proofFile, $id]);
                }
            }
            if ($supportsClientProofs && !empty($proofUploads)) {
                $uploadDir = __DIR__ . '/../uploads/clients/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                foreach ($proofUploads as $upload) {
                    $fileName = uniqid('proof_', true) . '.' . $upload['ext'];
                    if (!move_uploaded_file($upload['tmp'], $uploadDir . $fileName)) {
                        continue;
                    }
                    $pdo->prepare('INSERT INTO client_proofs (client_id,title,type,file_path) VALUES (?,?,?,?)')
                        ->execute([$id, $upload['name'], $upload['ext'], 'uploads/clients/' . $fileName]);
                }
            }
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
            <?php
            $clientFormFields = [
                ['name', 'Full Name', 'text', true],
                ['email', 'Email (optional)', 'email', false],
                ['phone', 'Phone', 'text', true],
            ];
            if ($supportsAlternativeNumber) {
                $clientFormFields[] = ['alternative_number', 'Alternative Number', 'text', false];
            }
            foreach ($clientFormFields as [$n, $l, $t, $r]):
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
            <!-- Proof Documents -->
            <div>
                <?php if ($supportsClientProofs): ?>
                    <label class="block text-sm text-mb-silver mb-2">ID / Proof Documents
                        <span class="text-mb-subtle text-xs">(JPG, PNG or PDF, max 5MB each - up to 5 files)</span></label>
                    <?php if (!empty($legacyProofFile)): ?>
                        <div class="mb-2 flex items-center gap-3">
                            <span class="text-mb-subtle text-xs">Legacy proof:</span>
                            <a href="<?= $root . e($legacyProofFile) ?>" target="_blank"
                                class="text-mb-accent text-xs hover:underline">View existing file</a>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($clientProofs)): ?>
                        <div class="mb-3 space-y-2">
                            <?php foreach ($clientProofs as $doc): ?>
                                <div class="flex items-center justify-between gap-3 bg-mb-black/30 border border-mb-subtle/20 rounded-lg px-3 py-2">
                                    <div class="min-w-0">
                                        <p class="text-white text-xs truncate"><?= e($doc['title'] ?: basename($doc['file_path'])) ?></p>
                                        <p class="text-mb-subtle text-[10px] uppercase"><?= e($doc['type'] ?? '') ?></p>
                                    </div>
                                    <a href="<?= $root . e($doc['file_path']) ?>" target="_blank"
                                        class="text-mb-accent text-xs hover:underline">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <input id="proofFiles" type="file" name="proof_files[]" accept=".jpg,.jpeg,.png" multiple data-max="<?= (int) $remainingProofSlots ?>"
                        class="block w-full text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-mb-accent hover:file:bg-mb-surface/80 cursor-pointer border border-mb-subtle/20 rounded-lg p-2" <?= $remainingProofSlots === 0 ? 'disabled' : '' ?>>
                    <p class="text-mb-subtle text-xs mt-1">You can add up to <?= $remainingProofSlots ?> more file(s).</p>
                    <div id="proofPreview" class="mt-3 space-y-2 hidden"></div>
                    <p id="proofFilesError" class="text-red-400 text-xs mt-1 <?= ($errors['proof_files'] ?? '') ? '' : 'hidden' ?>">
                        <?= e($errors['proof_files'] ?? '') ?>
                    </p>
                <?php else: ?>
                    <label class="block text-sm text-mb-silver mb-2">ID / Proof Document
                        <span class="text-mb-subtle text-xs">(JPG, PNG or PDF, max 5MB)</span></label>
                    <?php if (!empty($c['proof_file'])): ?>
                        <div class="mb-2 flex items-center gap-3">
                            <span class="text-mb-subtle text-xs">Current:</span>
                            <a href="<?= $root . e($c['proof_file']) ?>" target="_blank"
                                class="text-mb-accent text-xs hover:underline">View existing file</a>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="proof_file" accept=".jpg,.jpeg,.png"
                        class="block w-full text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-mb-accent hover:file:bg-mb-surface/80 cursor-pointer border border-mb-subtle/20 rounded-lg p-2">
                    <p class="text-mb-subtle text-xs mt-1">Leave blank to keep existing file.</p>
                    <?php if ($errors['proof_file'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['proof_file']) ?></p>
                    <?php endif; ?>
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
<?php if ($supportsClientProofs && $remainingProofSlots > 0): ?>
<script>
(function () {
    var input = document.getElementById('proofFiles');
    if (!input) return;
    var max = parseInt(input.dataset.max || '0', 10);
    if (!max || max <= 0) return;
    var preview = document.getElementById('proofPreview');
    var errorEl = document.getElementById('proofFilesError');
    var selected = [];
    var objectUrls = [];

    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c];
        });
    }

    function setError(msg) {
        if (!errorEl) return;
        if (msg) {
            errorEl.textContent = msg;
            errorEl.classList.remove('hidden');
        } else {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }
    }

    function clearUrls() {
        objectUrls.forEach(function (u) { URL.revokeObjectURL(u); });
        objectUrls = [];
    }

    function render() {
        if (!preview) return;
        clearUrls();
        if (!selected.length) {
            preview.innerHTML = '';
            preview.classList.add('hidden');
            return;
        }
        preview.classList.remove('hidden');
        var html = '';
        selected.forEach(function (file, idx) {
            var isImage = (file.type && file.type.indexOf('image/') === 0) || /\.(jpg|jpeg|png)$/i.test(file.name || '');
            var thumb = '';
            if (isImage) {
                var url = URL.createObjectURL(file);
                objectUrls.push(url);
                thumb = '<img src="' + url + '" alt="" class="w-full h-full object-cover">';
            } else {
                thumb = '<span class="text-[10px] text-mb-subtle">PDF</span>';
            }
            var sizeMb = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            html += '<div class="flex items-center justify-between gap-3 bg-mb-black/30 border border-mb-subtle/20 rounded-lg px-3 py-2">'
                + '<div class="flex items-center gap-3 min-w-0">'
                + '<div class="w-10 h-10 rounded border border-mb-subtle/20 bg-mb-black/40 flex items-center justify-center overflow-hidden">' + thumb + '</div>'
                + '<div class="min-w-0"><p class="text-white text-xs truncate">' + escapeHtml(file.name || 'file') + '</p>'
                + '<p class="text-mb-subtle text-[10px] uppercase">' + sizeMb + '</p></div></div>'
                + '<button type="button" data-idx="' + idx + '" class="text-red-400 text-xs hover:underline">Remove</button></div>';
        });
        preview.innerHTML = html;
    }

    function sync() {
        var dt = new DataTransfer();
        selected.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
        render();
        setError('');
    }

    input.addEventListener('change', function () {
        var added = Array.from(input.files || []);
        if (!added.length) return;
        selected = selected.concat(added);
        var seen = {};
        var deduped = [];
        selected.forEach(function (f) {
            var key = [f.name, f.size, f.lastModified].join('|');
            if (seen[key]) return;
            seen[key] = true;
            deduped.push(f);
        });
        selected = deduped;
        if (selected.length > max) {
            setError('You can upload up to ' + max + ' proof files.');
            selected = selected.slice(0, max);
        }
        sync();
    });

    if (preview) {
        preview.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-idx]');
            if (!btn) return;
            var idx = parseInt(btn.getAttribute('data-idx'), 10);
            if (isNaN(idx)) return;
            selected.splice(idx, 1);
            sync();
        });
    }
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
