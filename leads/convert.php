<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/client_helpers.php';
auth_check();
if (!auth_has_perm('add_leads')) {
    flash('error', 'You are not allowed to convert leads.');
    redirect('index.php');
}

$pdo = db();
clients_ensure_schema($pdo);

function leads_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];
    $key = $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'leads'
              AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        app_log('ERROR', "Lead convert: column check failed for leads.{$column} - " . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

        $cache[$key] = false;
    }

    return $cache[$key];
}

$supportsAlternativeNumber = clients_has_column($pdo, 'alternative_number');
$supportsClientProofs = clients_has_table($pdo, 'client_proofs');
$supportsLeadAlternative = leads_has_column($pdo, 'alternative_number');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) {
    flash('error', 'Invalid lead selected.');
    redirect('index.php');
}

$errors = [];
$lead = null;
$formData = [];

function load_lead(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    $sql = 'SELECT * FROM leads WHERE id=?' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $lead = $stmt->fetch();
    return $lead ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $lead = load_lead($pdo, $id, true);
        if (!$lead) {
            $pdo->rollBack();
            flash('error', 'Lead not found.');
            redirect('index.php');
        }
        if ($lead['status'] !== 'closed_won') {
            $pdo->rollBack();
            flash('error', 'Only Closed Won leads can be converted.');
            redirect("show.php?id=$id");
        }
        if ($lead['converted_client_id']) {
            $pdo->rollBack();
            flash('error', 'This lead has already been converted.');
            redirect("show.php?id=$id");
        }

        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $alternativeNumber = trim($_POST['alternative_number'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $formData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'alternative_number' => $alternativeNumber,
            'address' => $address,
            'notes' => $notes,
        ];

        if (!$name)
            $errors['name'] = 'Name is required.';
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors['email'] = 'Invalid email format.';
        if (!$phone)
            $errors['phone'] = 'Phone is required.';
        if (!$address)
            $errors['address'] = 'Address is required.';

        if ($email && !isset($errors['email'])) {
            $chk = $pdo->prepare('SELECT id FROM clients WHERE email = ?');
            $chk->execute([$email]);
            if ($chk->fetch())
                $errors['email'] = 'Email already in use.';
        }
        if ($phone && !isset($errors['phone'])) {
            $chk = $pdo->prepare('SELECT id FROM clients WHERE phone = ?');
            $chk->execute([$phone]);
            if ($chk->fetch())
                $errors['phone'] = 'Phone already in use.';
        }

        $proofFile = null;
        $proofUploads = [];
        if ($supportsClientProofs) {
            if (empty($_FILES['proof_files']['name'][0])) {
                $errors['proof_files'] = 'At least one proof document is required.';
            } else {
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                $maxFiles = 5;
                $selectedCount = 0;
                foreach ($_FILES['proof_files']['name'] as $fileName) {
                    if ($fileName !== '') {
                        $selectedCount++;
                    }
                }
                if ($selectedCount > $maxFiles) {
                    $errors['proof_files'] = 'You can upload up to 5 proof files.';
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
            if (empty($_FILES['proof_file']['name'])) {
                $errors['proof_file'] = 'Proof document is required.';
            } else {
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

        if ($errors) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } else {
            $fullNotes = $notes;
            $conversionNote = 'Converted from lead #' . $id;
            if ($fullNotes !== '') {
                $fullNotes .= "\n" . $conversionNote;
            } else {
                $fullNotes = $conversionNote;
            }

            if ($supportsAlternativeNumber) {
                $stmt = $pdo->prepare('INSERT INTO clients (name,email,phone,alternative_number,address,notes,proof_file) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$name, $email ?: null, $phone, $alternativeNumber ?: null, $address, $fullNotes, $proofFile]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO clients (name,email,phone,address,notes,proof_file) VALUES (?,?,?,?,?,?)');
                $stmt->execute([$name, $email ?: null, $phone, $address, $fullNotes, $proofFile]);
            }
            $clientId = (int) $pdo->lastInsertId();

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
                        ->execute([$clientId, $upload['name'], $upload['ext'], 'uploads/clients/' . $fileName]);
                }
            }

            $nowSql = app_now_sql();
            $pdo->prepare("UPDATE leads SET converted_client_id=?, status='closed_won', updated_at=?,
                closed_at = IF(closed_at IS NULL, ?, closed_at) WHERE id=?")
                ->execute([$clientId, $nowSql, $nowSql, $id]);

            $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')
                ->execute([$id, "Lead converted to client #$clientId."]);

            $pdo->commit();

            log_activity($pdo, 'converted_lead', 'lead', $id, "Converted lead #$id ({$lead['name']}) to new client #$clientId.");
            app_log('ACTION', "Converted lead (ID: $id) to client (ID: $clientId)");
            flash('success', 'Lead converted to client successfully! You can now create a reservation for them.');
            redirect("../clients/show.php?id=$clientId");
        }
    } catch (Throwable $e) {
        app_log('ERROR', 'Lead conversion failed for lead #' . $id . ' - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Lead conversion failed for lead #' . $id . ': ' . $e->getMessage());
        $errors['form'] = 'Could not convert this lead right now. Please try again.';
    }
} else {
    $lead = load_lead($pdo, $id, false);
    if (!$lead) {
        flash('error', 'Lead not found.');
        redirect('index.php');
    }
    if ($lead['status'] !== 'closed_won') {
        flash('error', 'Only Closed Won leads can be converted.');
        redirect("show.php?id=$id");
    }
    if ($lead['converted_client_id']) {
        flash('error', 'This lead has already been converted.');
        redirect("../clients/show.php?id={$lead['converted_client_id']}");
    }

    $formData = [
        'name' => $lead['name'] ?? '',
        'email' => $lead['email'] ?? '',
        'phone' => $lead['phone'] ?? '',
        'alternative_number' => $supportsLeadAlternative ? ($lead['alternative_number'] ?? '') : '',
        'address' => '',
        'notes' => $lead['notes'] ?? '',
    ];
}

$pageTitle = 'Convert Lead to Client';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Leads</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors">
            <?= e($lead['name'] ?? 'Lead') ?>
        </a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Convert to Client</span>
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
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Client Information</h3>
            <?php
            function cf(string $name, string $label, string $type = 'text', array $errors = [], bool $required = true, string $placeholder = '', array $formData = []): void
            {
                $val = htmlspecialchars($formData[$name] ?? '', ENT_QUOTES);
                $err = $errors[$name] ?? '';
                $req = $required ? 'required' : '';
                echo "<div><label class='block text-sm text-mb-silver mb-2'>$label" . ($required ? " <span class='text-red-400'>*</span>" : '') . "</label>
                <input type='$type' name='$name' value='$val' placeholder='$placeholder' $req
                    class='w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm'>
                " . ($err ? "<p class='text-red-400 text-xs mt-1'>$err</p>" : '') . "</div>";
            }
            ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php cf('name', 'Full Name', 'text', $errors, true, 'John Doe', $formData); ?>
                <?php cf('email', 'Email Address', 'email', $errors, false, 'john@example.com (optional)', $formData); ?>
                <?php cf('phone', 'Phone Number', 'text', $errors, true, '+1 234 567 8900', $formData); ?>
                <?php if ($supportsAlternativeNumber): ?>
                    <?php cf('alternative_number', 'Alternative Number', 'text', $errors, false, '+1 234 567 8901 (optional)', $formData); ?>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Address <span class="text-red-400">*</span></label>
                <textarea name="address" rows="2" placeholder="Street, City, Country" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($formData['address'] ?? '', ENT_QUOTES) ?></textarea>
                <?php if ($errors['address'] ?? ''): ?>
                    <p class="text-red-400 text-xs mt-1"><?= e($errors['address']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Notes</label>
                <textarea name="notes" rows="3" placeholder="Any additional notes..."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($formData['notes'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <!-- Proof Document -->
            <div>
                <?php if ($supportsClientProofs): ?>
                    <label class="block text-sm text-mb-silver mb-2">ID / Proof Documents <span class="text-red-400">*</span> <span
                            class="text-mb-subtle text-xs">(required - up to 5 files, JPG/PNG images only, max 5MB each)</span></label>
                    <input id="proofFiles" type="file" name="proof_files[]" accept=".jpg,.jpeg,.png" multiple data-max="5" required
                        class="block w-full text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-mb-accent hover:file:bg-mb-surface/80 cursor-pointer border border-mb-subtle/20 rounded-lg p-2">
                    <div id="proofPreview" class="mt-3 space-y-2 hidden"></div>
                    <p id="proofFilesError" class="text-red-400 text-xs mt-1 <?= ($errors['proof_files'] ?? '') ? '' : 'hidden' ?>">
                        <?= e($errors['proof_files'] ?? '') ?>
                    </p>
                <?php else: ?>
                    <label class="block text-sm text-mb-silver mb-2">ID / Proof Document <span class="text-red-400">*</span> <span
                            class="text-mb-subtle text-xs">(required - JPG or PNG images only, max 5MB)</span></label>
                    <input type="file" name="proof_file" accept=".jpg,.jpeg,.png" required
                        class="block w-full text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-mb-accent hover:file:bg-mb-surface/80 cursor-pointer border border-mb-subtle/20 rounded-lg p-2">
                    <?php if ($errors['proof_file'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['proof_file']) ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center justify-end gap-4">
            <a href="show.php?id=<?= $id ?>" class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Convert Lead
            </button>
        </div>
    </form>
</div>

<?php if ($supportsClientProofs): ?>
<script>
(function () {
    var input = document.getElementById('proofFiles');
    if (!input) return;
    var max = parseInt(input.dataset.max || '5', 10);
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
