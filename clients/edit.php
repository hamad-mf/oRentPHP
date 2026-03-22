<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/client_helpers.php';
if (!auth_has_perm('manage_clients')) {
    flash('error', 'You do not have permission to edit clients.');
    redirect('index.php');
}
$id  = (int)($_GET['id'] ?? 0);
$pdo = db();
clients_ensure_schema($pdo);
$supportsAlternativeNumber = clients_has_column($pdo, 'alternative_number');
$supportsClientProofs      = clients_has_table($pdo, 'client_proofs');
$supportsClientPhoto       = clients_has_column($pdo, 'photo');

$cStmt = $pdo->prepare('SELECT * FROM clients WHERE id=?');
$cStmt->execute([$id]);
$c = $cStmt->fetch();
if (!$c) {
    flash('error', 'Client not found.');
    redirect('index.php');
}

$existingPhoto = $supportsClientPhoto ? ($c['photo'] ?? null) : null;

$clientProofs = [];
if ($supportsClientProofs) {
    try {
        $pStmt = $pdo->prepare('SELECT * FROM client_proofs WHERE client_id = ? ORDER BY created_at DESC');
        $pStmt->execute([$id]);
        $clientProofs = $pStmt->fetchAll();
    } catch (Throwable $e) { $clientProofs = []; }
}
$legacyProofFile    = $c['proof_file'] ?? null;
$existingProofCount = count($clientProofs) + ($legacyProofFile ? 1 : 0);
$remainingProofSlots = max(0, 5 - $existingProofCount);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name              = trim($_POST['name'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $phone             = trim($_POST['phone'] ?? '');
    $alternativeNumber = trim($_POST['alternative_number'] ?? '');
    $address           = trim($_POST['address'] ?? '');
    $notes             = trim($_POST['notes'] ?? '');

    if (!$name)  $errors['name']  = 'Name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email.';
    if (!$phone) $errors['phone'] = 'Phone is required.';

    if ($email && !isset($errors['email'])) {
        $chk = $pdo->prepare('SELECT id FROM clients WHERE email=? AND id!=?');
        $chk->execute([$email, $id]);
        if ($chk->fetch()) $errors['email'] = 'Email already used.';
    }
    if ($phone && !isset($errors['phone'])) {
        $chk = $pdo->prepare('SELECT id FROM clients WHERE phone=? AND id!=?');
        $chk->execute([$phone, $id]);
        if ($chk->fetch()) $errors['phone'] = 'Phone already used.';
    }

    if (empty($errors)) {
        // ── Proof documents — saved from base64 POST data (cropped) ──────────
        $proofUploads = [];
        $proofFile    = $c['proof_file'] ?? null;

        if ($supportsClientProofs) {
            $croppedProofs = $_POST['cropped_proof_data'] ?? [];
            $proofNames    = $_POST['proof_original_names'] ?? [];

            if (!empty($croppedProofs) && is_array($croppedProofs)) {
                $newCount = count(array_filter($croppedProofs));
                if ($newCount > $remainingProofSlots) {
                    $errors['proof_files'] = $remainingProofSlots > 0
                        ? "You can add up to {$remainingProofSlots} more proof file(s)."
                        : 'Maximum of 5 proof files already uploaded.';
                } else {
                    $uploadDir = __DIR__ . '/../uploads/clients/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                    foreach ($croppedProofs as $i => $dataUri) {
                        if (empty($dataUri)) continue;
                        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUri, $m)) {
                            $errors['proof_files'] = 'Invalid proof image data.'; break;
                        }
                        $ext      = strtolower($m[1]);
                        $imgBytes = base64_decode($m[2]);
                        if ($imgBytes === false || strlen($imgBytes) > 5 * 1024 * 1024) {
                            $errors['proof_files'] = 'Each proof file must be under 5MB.'; break;
                        }
                        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                            $errors['proof_files'] = 'Only JPG, PNG, or WEBP images allowed.'; break;
                        }
                        $fileName = uniqid('proof_', true) . '.' . $ext;
                        if (!file_put_contents($uploadDir . $fileName, $imgBytes)) {
                            $errors['proof_files'] = 'Failed to save proof file.'; break;
                        }
                        $origName = $proofNames[$i] ?? ('proof_' . ($i + 1) . '.' . $ext);
                        $proofUploads[] = [
                            'path' => 'uploads/clients/' . $fileName,
                            'name' => basename($origName),
                            'ext'  => $ext,
                        ];
                    }
                }
            }
            // (no proofs submitted = keep existing, no error)
        } else {
            // Legacy single-file path
            if (!empty($_FILES['proof_file']['name'])) {
                $uploadDir = __DIR__ . '/../uploads/clients/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                $ext     = strtolower(pathinfo($_FILES['proof_file']['name'], PATHINFO_EXTENSION));
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

            // ── Client photo update ──────────────────────────────────────────
            if ($supportsClientPhoto && !empty($_POST['cropped_photo_data'])) {
                $photoData = $_POST['cropped_photo_data'];
                if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $photoData, $m)) {
                    $ext      = strtolower($m[1]);
                    $imgBytes = base64_decode($m[2]);
                    if ($imgBytes !== false
                        && strlen($imgBytes) <= 5 * 1024 * 1024
                        && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)
                    ) {
                        $uploadDir = __DIR__ . '/../uploads/clients/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                        if ($existingPhoto && file_exists(__DIR__ . '/../' . $existingPhoto)) {
                            @unlink(__DIR__ . '/../' . $existingPhoto);
                        }
                        $fileName = 'client_photo_' . uniqid() . '.' . $ext;
                        if (file_put_contents($uploadDir . $fileName, $imgBytes)) {
                            $pdo->prepare('UPDATE clients SET photo = ? WHERE id = ?')
                                ->execute(['uploads/clients/' . $fileName, $id]);
                            app_log('ACTION', "Updated client photo (ID: $id)");
                        }
                    }
                }
            }

            // ── Save new proof uploads ───────────────────────────────────────
            if ($supportsClientProofs && !empty($proofUploads)) {
                foreach ($proofUploads as $upload) {
                    $pdo->prepare('INSERT INTO client_proofs (client_id,title,type,file_path) VALUES (?,?,?,?)')
                        ->execute([$id, $upload['name'], $upload['ext'], $upload['path']]);
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
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors"><?= e($c['name']) ?></a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Edit</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400 space-y-1">
            <?php foreach ($errors as $e): ?>
                <p>&bull; <?= e($e) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="clientForm" class="space-y-6">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Client Information</h3>

            <!-- ── Client Photo ─────────────────────────────────────────────── -->
            <?php if ($supportsClientPhoto): ?>
            <div>
                <label class="block text-sm text-mb-silver mb-3">Client Photo <span class="text-mb-subtle font-normal">(optional)</span></label>
                <div class="flex items-start gap-6">
                    <div class="relative flex-shrink-0">
                        <div id="photoPreviewContainer" class="w-28 h-28 rounded-full overflow-hidden bg-mb-black border-2 <?= $existingPhoto ? 'border-mb-accent' : 'border-dashed border-mb-subtle/30' ?> flex items-center justify-center">
                            <?php if ($existingPhoto): ?>
                                <img id="photoPreview" class="w-full h-full object-cover" src="../<?= e($existingPhoto) ?>" alt="Client photo">
                                <svg id="photoPlaceholder" class="w-10 h-10 text-mb-subtle/40 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            <?php else: ?>
                                <svg id="photoPlaceholder" class="w-10 h-10 text-mb-subtle/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                <img id="photoPreview" class="w-full h-full object-cover hidden" src="" alt="Client photo preview">
                            <?php endif; ?>
                        </div>
                        <button type="button" onclick="document.getElementById('client_photo_input').click()"
                            class="absolute -bottom-2 -right-2 w-8 h-8 bg-mb-accent rounded-full flex items-center justify-center text-white shadow-lg hover:bg-mb-accent/80 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </button>
                        <input type="file" id="client_photo_input" accept="image/*" class="hidden" onchange="handlePhotoSelect(this)">
                        <input type="hidden" id="cropped_photo_data" name="cropped_photo_data">
                    </div>
                    <div class="flex-1 pt-2">
                        <p class="text-mb-subtle text-xs">Upload a new profile photo. Click the camera icon to select and crop.</p>
                        <p class="text-mb-subtle text-xs mt-1">Supported: JPG, PNG, WEBP. Max 5MB.</p>
                        <?php if ($existingPhoto): ?>
                            <p class="text-mb-subtle text-xs mt-2">Leave unchanged to keep the current photo.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $clientFormFields = [
                ['name', 'Full Name', 'text', true],
                ['email', 'Email (optional)', 'email', false],
                ['phone', 'Phone', 'text', true],
            ];
            if ($supportsAlternativeNumber) { $clientFormFields[] = ['alternative_number', 'Alternative Number', 'text', false]; }
            foreach ($clientFormFields as [$n, $l, $t, $r]):
                $val = htmlspecialchars($c[$n] ?? '', ENT_QUOTES);
                $err = $errors[$n] ?? '';
            ?>
                <div>
                    <label class="block text-sm text-mb-silver mb-2"><?= $l ?><?= $r ? " <span class='text-red-400'>*</span>" : '' ?></label>
                    <input type="<?= $t ?>" name="<?= $n ?>" value="<?= $val ?>" <?= $r ? 'required' : '' ?>
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <?= $err ? "<p class='text-red-400 text-xs mt-1'>$err</p>" : '' ?>
                </div>
            <?php endforeach; ?>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Address</label>
                <textarea name="address" rows="2"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($c['address'] ?? '', ENT_QUOTES) ?></textarea>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Notes</label>
                <textarea name="notes" rows="3"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($c['notes'] ?? '', ENT_QUOTES) ?></textarea>
            </div>

            <!-- ── Proof Documents ──────────────────────────────────────────── -->
            <div>
                <?php if ($supportsClientProofs): ?>
                    <label class="block text-sm text-mb-silver mb-2">
                        ID / Proof Documents
                        <span class="text-mb-subtle text-xs font-normal">(JPG, PNG, WEBP — each will be cropped before upload)</span>
                    </label>

                    <!-- Existing proofs -->
                    <?php if (!empty($legacyProofFile)): ?>
                        <div class="mb-2 flex items-center gap-3">
                            <span class="text-mb-subtle text-xs">Legacy proof:</span>
                            <a href="<?= $root . e($legacyProofFile) ?>" target="_blank" class="text-mb-accent text-xs hover:underline">View existing file</a>
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
                                    <a href="<?= $root . e($doc['file_path']) ?>" target="_blank" class="text-mb-accent text-xs hover:underline">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Hidden inputs for cropped proof data -->
                    <div id="proofHiddenInputs"></div>

                    <?php if ($remainingProofSlots > 0): ?>
                        <div id="proofDropZone"
                            class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-6 text-center hover:border-mb-accent/50 transition-colors cursor-pointer"
                            onclick="document.getElementById('proof_file_picker').click()"
                            ondragover="event.preventDefault();this.classList.add('border-mb-accent')"
                            ondragleave="this.classList.remove('border-mb-accent')"
                            ondrop="handleProofDrop(event)">
                            <svg class="mx-auto h-8 w-8 text-mb-subtle/40 mb-2" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-mb-silver text-sm"><span class="text-mb-accent font-medium">Click to add</span> or drag &amp; drop</p>
                            <p class="text-mb-subtle text-xs mt-1">Up to <?= $remainingProofSlots ?> more · JPG, PNG, WEBP</p>
                            <input id="proof_file_picker" type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                        </div>
                        <div id="proofQueue" class="mt-3 space-y-2"></div>
                    <?php else: ?>
                        <p class="text-mb-subtle text-xs">Maximum of 5 proof files already uploaded.</p>
                    <?php endif; ?>

                    <p id="proofFilesError" class="text-red-400 text-xs mt-1 <?= ($errors['proof_files'] ?? '') ? '' : 'hidden' ?>">
                        <?= e($errors['proof_files'] ?? '') ?>
                    </p>

                <?php else: ?>
                    <!-- Legacy single-file path -->
                    <label class="block text-sm text-mb-silver mb-2">ID / Proof Document <span class="text-mb-subtle text-xs">(JPG, PNG or PDF, max 5MB)</span></label>
                    <?php if (!empty($c['proof_file'])): ?>
                        <div class="mb-2 flex items-center gap-3">
                            <span class="text-mb-subtle text-xs">Current:</span>
                            <a href="<?= $root . e($c['proof_file']) ?>" target="_blank" class="text-mb-accent text-xs hover:underline">View existing file</a>
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
            <a href="show.php?id=<?= $id ?>" class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium">Save Changes</button>
        </div>
    </form>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- Cropper.js                                                                  -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>

<div id="cropModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,0.88);">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-2xl p-6 w-full max-w-lg shadow-2xl">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-white font-semibold" id="cropModalTitle">Crop Image</h3>
                <p class="text-mb-subtle text-xs mt-0.5" id="cropModalSubtitle"></p>
            </div>
            <button type="button" onclick="cancelCrop()" class="text-mb-subtle hover:text-white transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div style="height:300px;overflow:hidden;background:#000;border-radius:8px;">
            <img id="cropImage" style="display:block;max-width:100%;" src="" alt="Crop preview">
        </div>
        <p class="text-mb-subtle text-xs mt-3 text-center">Drag to reposition · Scroll to zoom · Drag corners to resize</p>
        <div class="flex justify-end gap-3 mt-4">
            <button type="button" onclick="cancelCrop()"
                class="px-5 py-2 rounded-full border border-mb-subtle/30 text-mb-silver hover:text-white text-sm transition-colors">Cancel</button>
            <button type="button" onclick="confirmCrop()"
                class="px-6 py-2 rounded-full bg-mb-accent text-white text-sm hover:bg-mb-accent/80 transition-colors">Crop &amp; Save</button>
        </div>
    </div>
</div>

<script>
var _cropper    = null;
var _cropMode   = '';
var _cropQueue  = [];
var _proofItems = [];
var PROOF_MAX   = <?= (int)$remainingProofSlots ?>;

function _initCropper(aspectRatio) {
    if (_cropper) { _cropper.destroy(); _cropper = null; }
    setTimeout(function () {
        _cropper = new Cropper(document.getElementById('cropImage'), {
            aspectRatio:      aspectRatio,
            viewMode:         1,
            dragMode:         'move',
            autoCropArea:     0.9,
            cropBoxMovable:   true,
            cropBoxResizable: true,
            guides:           true,
            center:           true,
            highlight:        false,
            background:       false,
        });
    }, 120);
}

function _openModal(src, title, subtitle, aspectRatio) {
    document.getElementById('cropImage').src = src;
    document.getElementById('cropModalTitle').textContent    = title;
    document.getElementById('cropModalSubtitle').textContent = subtitle;
    document.getElementById('cropModal').classList.remove('hidden');
    _initCropper(aspectRatio);
}

function cancelCrop() {
    document.getElementById('cropModal').classList.add('hidden');
    if (_cropper) { _cropper.destroy(); _cropper = null; }
    _cropQueue = [];
}

function confirmCrop() {
    if (!_cropper) return;
    var w = _cropMode === 'photo' ? 400 : 1200;
    var h = _cropMode === 'photo' ? 400 : 1200;
    var canvas = _cropper.getCroppedCanvas({ width: w, height: h, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' });
    if (!canvas) return;
    var dataUrl = canvas.toDataURL('image/jpeg', 0.92);

    if (_cropMode === 'photo') {
        document.getElementById('cropped_photo_data').value = dataUrl;
        var preview     = document.getElementById('photoPreview');
        var placeholder = document.getElementById('photoPlaceholder');
        if (preview)     { preview.src = dataUrl; preview.classList.remove('hidden'); }
        if (placeholder) { placeholder.classList.add('hidden'); }
        var container = document.getElementById('photoPreviewContainer');
        if (container) {
            container.classList.remove('border-dashed', 'border-mb-subtle/30');
            container.classList.add('border-mb-accent');
        }
        document.getElementById('cropModal').classList.add('hidden');
        if (_cropper) { _cropper.destroy(); _cropper = null; }
    } else {
        var item = _cropQueue.shift();
        _proofItems.push({ dataUri: dataUrl, name: item.name });
        document.getElementById('cropModal').classList.add('hidden');
        if (_cropper) { _cropper.destroy(); _cropper = null; }
        renderProofQueue();
        processNextProofInQueue();
    }
}

// Photo
function handlePhotoSelect(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    if (!file.type.startsWith('image/')) { alert('Please select an image file.'); return; }
    if (file.size > 5 * 1024 * 1024)    { alert('File size must be under 5MB.'); return; }
    _cropMode = 'photo';
    var reader = new FileReader();
    reader.onload = function (e) { _openModal(e.target.result, 'Crop Photo', 'Square crop for profile picture', 1); };
    reader.readAsDataURL(file);
    input.value = '';
}

// Proofs
<?php if ($remainingProofSlots > 0): ?>
document.getElementById('proof_file_picker')?.addEventListener('change', function () {
    addProofFiles(Array.from(this.files || []));
    this.value = '';
});

function handleProofDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('border-mb-accent');
    addProofFiles(Array.from(e.dataTransfer.files || []));
}

function addProofFiles(files) {
    var allowed = ['image/jpeg', 'image/png', 'image/webp'];
    var remaining = PROOF_MAX - _proofItems.length - _cropQueue.length;
    if (remaining <= 0) { showProofError('Maximum of ' + PROOF_MAX + ' new proof images.'); return; }
    var toAdd = files.filter(function (f) { return allowed.includes(f.type); }).slice(0, remaining);
    if (toAdd.length < files.length) showProofError('Only JPG, PNG, WEBP images are supported.');
    else hideProofError();
    toAdd.forEach(function (f) { _cropQueue.push({ file: f, name: f.name }); });
    processNextProofInQueue();
}

function processNextProofInQueue() {
    if (_cropQueue.length === 0) return;
    if (!document.getElementById('cropModal').classList.contains('hidden')) return;
    var item    = _cropQueue[0];
    var subtitle = 'Image ' + (_proofItems.length + 1) + ' of up to ' + PROOF_MAX +
                   (_cropQueue.length > 1 ? ' (' + _cropQueue.length + ' more after this)' : '');
    _cropMode = 'proof';
    var reader = new FileReader();
    reader.onload = function (e) { _openModal(e.target.result, 'Crop Proof Document', subtitle, NaN); };
    reader.readAsDataURL(item.file);
}

function removeProofItem(idx) {
    _proofItems.splice(idx, 1);
    renderProofQueue();
    syncProofHiddenInputs();
}

function renderProofQueue() {
    var container = document.getElementById('proofQueue');
    if (!container) return;
    syncProofHiddenInputs();
    var html = '';
    _proofItems.forEach(function (item, idx) {
        html += '<div class="flex items-center justify-between gap-3 bg-mb-black/30 border border-mb-subtle/20 rounded-lg px-3 py-2">'
              + '<div class="flex items-center gap-3 min-w-0">'
              + '<img src="' + item.dataUri + '" alt="" class="w-10 h-10 rounded object-cover border border-mb-subtle/20">'
              + '<div class="min-w-0"><p class="text-white text-xs truncate">' + escHtml(item.name) + '</p>'
              + '<p class="text-green-400 text-[10px] uppercase">Cropped ✓</p></div></div>'
              + '<button type="button" onclick="removeProofItem(' + idx + ')" class="text-red-400 text-xs hover:underline flex-shrink-0">Remove</button></div>';
    });
    _cropQueue.forEach(function (item) {
        html += '<div class="flex items-center gap-3 bg-mb-black/30 border border-mb-subtle/20 rounded-lg px-3 py-2 opacity-50">'
              + '<div class="w-10 h-10 rounded border border-mb-subtle/20 bg-mb-black/40 flex items-center justify-center">'
              + '<svg class="w-4 h-4 text-mb-subtle animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>'
              + '</div><p class="text-mb-subtle text-xs truncate">' + escHtml(item.name) + ' — waiting…</p></div>';
    });
    container.innerHTML = html;
}

function syncProofHiddenInputs() {
    var container = document.getElementById('proofHiddenInputs');
    if (!container) return;
    var html = '';
    _proofItems.forEach(function (item) {
        html += '<input type="hidden" name="cropped_proof_data[]" value="' + escAttr(item.dataUri) + '">';
        html += '<input type="hidden" name="proof_original_names[]" value="' + escAttr(item.name) + '">';
    });
    container.innerHTML = html;
}
<?php endif; ?>

function showProofError(msg) { var el = document.getElementById('proofFilesError'); if (el) { el.textContent = msg; el.classList.remove('hidden'); } }
function hideProofError()    { var el = document.getElementById('proofFilesError'); if (el) { el.textContent = ''; el.classList.add('hidden'); } }

function escHtml(str)  { return String(str).replace(/[&<>"']/g, function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[c];}); }
function escAttr(str)  { return String(str).replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>