<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/client_helpers.php';
if (!auth_has_perm('manage_clients')) {
    flash('error', 'You do not have permission to add clients.');
    redirect('index.php');
}

$pdo = db();
clients_ensure_schema($pdo);
$supportsAlternativeNumber = clients_has_column($pdo, 'alternative_number');
$supportsClientProofs      = clients_has_table($pdo, 'client_proofs');
$supportsClientPhoto       = clients_has_column($pdo, 'photo');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name              = trim($_POST['name'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $phone             = trim($_POST['phone'] ?? '');
    $alternativeNumber = trim($_POST['alternative_number'] ?? '');
    $address           = trim($_POST['address'] ?? '');
    $notes             = trim($_POST['notes'] ?? '');

    if (!$name)  $errors['name']  = 'Name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Invalid email format.';
    if (!$phone) $errors['phone'] = 'Phone is required.';
    if (!$address) $errors['address'] = 'Address is required.';
    
    // Validate client photo is required
    if ($supportsClientPhoto && empty($_POST['cropped_photo_data'])) {
        $errors['client_photo'] = 'Client photo is required.';
    }

    if ($email && !isset($errors['email'])) {
        $chk = $pdo->prepare('SELECT id FROM clients WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) $errors['email'] = 'Email already in use.';
    }
    if ($phone && !isset($errors['phone'])) {
        $chk = $pdo->prepare('SELECT id FROM clients WHERE phone = ?');
        $chk->execute([$phone]);
        if ($chk->fetch()) $errors['phone'] = 'Phone already in use.';
    }

    // ── Client photo (base64 from cropper) ───────────────────────────────────
    $clientPhotoFile = null;
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
                $fileName = 'client_photo_' . uniqid() . '.' . $ext;
                if (file_put_contents($uploadDir . $fileName, $imgBytes)) {
                    $clientPhotoFile = 'uploads/clients/' . $fileName;
                }
            }
        }
    }

    if (empty($errors)) {
        // ── Proof documents — saved from base64 POST data (cropped) ──────────
        $proofFile    = null;
        $proofUploads = []; // [{path, name, ext}]

        if ($supportsClientProofs) {
            $croppedProofs = $_POST['cropped_proof_data'] ?? [];
            $proofNames    = $_POST['proof_original_names'] ?? [];

            if (empty($croppedProofs) || (is_array($croppedProofs) && count(array_filter($croppedProofs)) === 0)) {
                $errors['proof_files'] = 'At least one proof document is required.';
            } elseif (count($croppedProofs) > 5) {
                $errors['proof_files'] = 'You can upload up to 5 proof files.';
            } else {
                $uploadDir = __DIR__ . '/../uploads/clients/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                foreach ($croppedProofs as $i => $dataUri) {
                    if (empty($dataUri)) continue;
                    if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $dataUri, $m)) {
                        $errors['proof_files'] = 'Invalid proof image data.';
                        break;
                    }
                    $ext      = strtolower($m[1]);
                    $imgBytes = base64_decode($m[2]);
                    if ($imgBytes === false || strlen($imgBytes) > 5 * 1024 * 1024) {
                        $errors['proof_files'] = 'Each proof file must be under 5MB.';
                        break;
                    }
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $errors['proof_files'] = 'Only JPG, PNG, or WEBP images allowed.';
                        break;
                    }
                    $fileName = uniqid('proof_', true) . '.' . $ext;
                    if (!file_put_contents($uploadDir . $fileName, $imgBytes)) {
                        $errors['proof_files'] = 'Failed to save proof file.';
                        break;
                    }
                    $origName = $proofNames[$i] ?? ('proof_' . ($i + 1) . '.' . $ext);
                    $proofUploads[] = [
                        'path' => 'uploads/clients/' . $fileName,
                        'name' => basename($origName),
                        'ext'  => $ext,
                    ];
                }
            }
        } else {
            // Legacy single proof — still use file upload (no crop for legacy path)
            if (empty($_FILES['proof_file']['name'])) {
                $errors['proof_file'] = 'Proof document is required.';
            } else {
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
            if ($supportsClientPhoto) {
                if ($supportsAlternativeNumber) {
                    $stmt = $pdo->prepare('INSERT INTO clients (name,email,phone,alternative_number,address,notes,proof_file,photo) VALUES (?,?,?,?,?,?,?,?)');
                    $stmt->execute([$name, $email ?: null, $phone, $alternativeNumber ?: null, $address, $notes, $proofFile, $clientPhotoFile]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO clients (name,email,phone,address,notes,proof_file,photo) VALUES (?,?,?,?,?,?,?)');
                    $stmt->execute([$name, $email ?: null, $phone, $address, $notes, $proofFile, $clientPhotoFile]);
                }
            } else {
                if ($supportsAlternativeNumber) {
                    $stmt = $pdo->prepare('INSERT INTO clients (name,email,phone,alternative_number,address,notes,proof_file) VALUES (?,?,?,?,?,?,?)');
                    $stmt->execute([$name, $email ?: null, $phone, $alternativeNumber ?: null, $address, $notes, $proofFile]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO clients (name,email,phone,address,notes,proof_file) VALUES (?,?,?,?,?,?)');
                    $stmt->execute([$name, $email ?: null, $phone, $address, $notes, $proofFile]);
                }
            }
            $id = $pdo->lastInsertId();

            if ($supportsClientProofs && !empty($proofUploads)) {
                foreach ($proofUploads as $upload) {
                    $pdo->prepare('INSERT INTO client_proofs (client_id,title,type,file_path) VALUES (?,?,?,?)')
                        ->execute([$id, $upload['name'], $upload['ext'], $upload['path']]);
                }
            }

            app_log('ACTION', "Created client: $name (ID: $id)");
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
                <label class="block text-sm text-mb-silver mb-3">Client Photo <span class="text-red-400">*</span></label>
                <div class="flex items-start gap-6">
                    <div class="relative flex-shrink-0">
                        <div id="photoPreviewContainer" class="w-28 h-28 rounded-full overflow-hidden bg-mb-black border-2 border-dashed border-mb-subtle/30 flex items-center justify-center">
                            <svg id="photoPlaceholder" class="w-10 h-10 text-mb-subtle/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <img id="photoPreview" class="w-full h-full object-cover hidden" src="" alt="Client photo preview">
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
                        <p class="text-mb-subtle text-xs">Upload a profile photo. Click the camera icon to select and crop.</p>
                        <p class="text-mb-subtle text-xs mt-1">Supported: JPG, PNG, WEBP. Max 5MB.</p>
                        <?php if ($errors['client_photo'] ?? ''): ?>
                            <p class="text-red-400 text-xs mt-2"><?= e($errors['client_photo']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

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
                <?php if ($supportsAlternativeNumber): ?>
                    <?php cf('alternative_number', 'Alternative Number', 'text', $errors, false, '+1 234 567 8901 (optional)'); ?>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Address <span class="text-red-400">*</span></label>
                <textarea name="address" rows="2" placeholder="Street, City, Country" required
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($_POST['address'] ?? '', ENT_QUOTES) ?></textarea>
                <?php if ($errors['address'] ?? ''): ?>
                    <p class="text-red-400 text-xs mt-1"><?= e($errors['address']) ?></p>
                <?php endif; ?>
            </div>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Notes</label>
                <textarea name="notes" rows="3" placeholder="Any additional notes..."
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= htmlspecialchars($_POST['notes'] ?? '', ENT_QUOTES) ?></textarea>
            </div>

            <!-- ── Proof Documents ──────────────────────────────────────────── -->
            <div>
                <?php if ($supportsClientProofs): ?>
                    <label class="block text-sm text-mb-silver mb-2">
                        ID / Proof Documents <span class="text-red-400">*</span>
                        <span class="text-mb-subtle text-xs font-normal">(up to 5 images, max 5MB each — each will be cropped before upload)</span>
                    </label>

                    <!-- Hidden inputs populated by JS after cropping -->
                    <div id="proofHiddenInputs"></div>

                    <!-- Drop zone -->
                    <div id="proofDropZone"
                        class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-6 text-center hover:border-mb-accent/50 transition-colors cursor-pointer"
                        onclick="document.getElementById('proof_file_picker').click()"
                        ondragover="event.preventDefault();this.classList.add('border-mb-accent')"
                        ondragleave="this.classList.remove('border-mb-accent')"
                        ondrop="handleProofDrop(event)">
                        <svg class="mx-auto h-8 w-8 text-mb-subtle/40 mb-2" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-mb-silver text-sm"><span class="text-mb-accent font-medium">Click to select</span> or drag &amp; drop</p>
                        <p class="text-mb-subtle text-xs mt-1">JPG, PNG, WEBP — each opens in crop editor</p>
                        <input id="proof_file_picker" type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                    </div>

                    <!-- Queued proofs preview -->
                    <div id="proofQueue" class="mt-3 space-y-2"></div>

                    <p id="proofFilesError" class="text-red-400 text-xs mt-1 <?= ($errors['proof_files'] ?? '') ? '' : 'hidden' ?>">
                        <?= e($errors['proof_files'] ?? '') ?>
                    </p>

                <?php else: ?>
                    <!-- Legacy single-file path — unchanged -->
                    <label class="block text-sm text-mb-silver mb-2">ID / Proof Document <span class="text-red-400">*</span> <span class="text-mb-subtle text-xs">(JPG or PNG, max 5MB)</span></label>
                    <input type="file" name="proof_file" accept=".jpg,.jpeg,.png" required
                        class="block w-full text-sm text-mb-silver file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-mb-surface file:text-mb-accent hover:file:bg-mb-surface/80 cursor-pointer border border-mb-subtle/20 rounded-lg p-2">
                    <?php if ($errors['proof_file'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['proof_file']) ?></p>
                    <?php endif; ?>
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

<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<!-- Cropper.js                                                                  -->
<!-- ═══════════════════════════════════════════════════════════════════════════ -->
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.1/dist/cropper.min.js"></script>

<!-- Shared Crop Modal (used for both photo and proofs) -->
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
// ── Shared cropper state ──────────────────────────────────────────────────────
var _cropper    = null;
var _cropMode   = '';   // 'photo' | 'proof'
var _cropQueue  = [];   // files waiting to be cropped (proofs)
var _proofItems = [];   // [{dataUri, name}] — final cropped proofs
var PROOF_MAX   = 5;

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
    document.getElementById('cropModalTitle').textContent   = title;
    document.getElementById('cropModalSubtitle').textContent = subtitle;
    document.getElementById('cropModal').classList.remove('hidden');
    _initCropper(aspectRatio);
}

function cancelCrop() {
    document.getElementById('cropModal').classList.add('hidden');
    if (_cropper) { _cropper.destroy(); _cropper = null; }
    // If cancelling mid-proof-queue, drain the rest too
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
        // proof crop done — store and process next in queue
        var item = _cropQueue.shift(); // the file we just cropped
        _proofItems.push({ dataUri: dataUrl, name: item.name });
        document.getElementById('cropModal').classList.add('hidden');
        if (_cropper) { _cropper.destroy(); _cropper = null; }
        renderProofQueue();
        processNextProofInQueue();
    }
}

// ── Photo ─────────────────────────────────────────────────────────────────────
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

// ── Proof documents ───────────────────────────────────────────────────────────
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
    var errorEl = document.getElementById('proofFilesError');
    var remaining = PROOF_MAX - _proofItems.length - _cropQueue.length;
    if (remaining <= 0) {
        showProofError('Maximum of ' + PROOF_MAX + ' proof images already added.');
        return;
    }
    var toAdd = files.filter(function (f) { return allowed.includes(f.type); }).slice(0, remaining);
    if (toAdd.length < files.length) {
        showProofError('Only JPG, PNG, WEBP images are supported.');
    } else {
        hideProofError();
    }
    toAdd.forEach(function (f) { _cropQueue.push({ file: f, name: f.name }); });
    processNextProofInQueue();
}

function processNextProofInQueue() {
    if (_cropQueue.length === 0) return;
    // If modal already open (shouldn't happen), wait
    if (!document.getElementById('cropModal').classList.contains('hidden')) return;

    var item = _cropQueue[0]; // don't shift yet — we shift on confirm
    _cropMode = 'proof';
    var remaining = PROOF_MAX - _proofItems.length;
    var subtitle   = 'Image ' + (_proofItems.length + 1) + ' of up to ' + PROOF_MAX +
                     (_cropQueue.length > 1 ? ' (' + _cropQueue.length + ' more after this)' : '');

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
    if (_proofItems.length === 0) { container.innerHTML = ''; return; }
    var html = '';
    _proofItems.forEach(function (item, idx) {
        html += '<div class="flex items-center justify-between gap-3 bg-mb-black/30 border border-mb-subtle/20 rounded-lg px-3 py-2">'
              + '<div class="flex items-center gap-3 min-w-0">'
              + '<img src="' + item.dataUri + '" alt="" class="w-10 h-10 rounded object-cover border border-mb-subtle/20">'
              + '<div class="min-w-0"><p class="text-white text-xs truncate">' + escHtml(item.name) + '</p>'
              + '<p class="text-green-400 text-[10px] uppercase">Cropped ✓</p></div></div>'
              + '<button type="button" onclick="removeProofItem(' + idx + ')" class="text-red-400 text-xs hover:underline flex-shrink-0">Remove</button>'
              + '</div>';
    });
    // pending in queue
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
    _proofItems.forEach(function (item, idx) {
        html += '<input type="hidden" name="cropped_proof_data[]" value="' + escAttr(item.dataUri) + '">';
        html += '<input type="hidden" name="proof_original_names[]" value="' + escAttr(item.name) + '">';
    });
    container.innerHTML = html;
}

function showProofError(msg) {
    var el = document.getElementById('proofFilesError');
    if (el) { el.textContent = msg; el.classList.remove('hidden'); }
}
function hideProofError() {
    var el = document.getElementById('proofFilesError');
    if (el) { el.textContent = ''; el.classList.add('hidden'); }
}

// Client-side guard: ensure at least one proof is cropped before submitting
document.getElementById('clientForm')?.addEventListener('submit', function (e) {
    var hasError = false;
    
    <?php if ($supportsClientPhoto): ?>
    // Check if client photo is uploaded
    var photoData = document.getElementById('cropped_photo_data')?.value;
    if (!photoData || photoData.trim() === '') {
        e.preventDefault();
        alert('Client photo is required. Please upload and crop a photo.');
        document.getElementById('photoPreviewContainer')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        hasError = true;
    }
    <?php endif; ?>
    
    <?php if ($supportsClientProofs): ?>
    if (!hasError && _proofItems.length === 0) {
        e.preventDefault();
        showProofError('At least one proof document is required. Please select and crop an image.');
        document.getElementById('proofDropZone')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    <?php endif; ?>
    
    if (hasError) return false;
});

function escHtml(str) {
    return String(str).replace(/[&<>"']/g, function (c) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c];
    });
}
function escAttr(str) {
    return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>