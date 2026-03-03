<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/vehicle_helpers.php';
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to edit vehicles.');
    redirect('index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();
vehicle_ensure_schema($pdo);

$vStmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = ?');
$vStmt->execute([$id]);
$v = $vStmt->fetch();
if (!$v) {
    flash('error', 'Vehicle not found.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    app_log('ACTION', "Vehicle edit submit attempt (ID: $id)");
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = (int) ($_POST['year'] ?? 0);
    $plate = trim($_POST['license_plate'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $vin = trim($_POST['vin'] ?? '');
    $status = $_POST['status'] ?? 'available';
    $maintenanceExpectedReturn = trim($_POST['maintenance_expected_return'] ?? '');
    $maintenanceWorkshopName = trim($_POST['maintenance_workshop_name'] ?? '');
    if ($status !== 'maintenance') {
        $maintenanceExpectedReturn = null;
        $maintenanceWorkshopName = null;
    } else {
        if ($maintenanceExpectedReturn !== '') {
            $dateObj = DateTime::createFromFormat('Y-m-d', $maintenanceExpectedReturn);
            if (!$dateObj || $dateObj->format('Y-m-d') !== $maintenanceExpectedReturn) {
                $errors['maintenance_expected_return'] = 'Invalid expected return date.';
            }
        } else {
            $maintenanceExpectedReturn = null;
        }

        if ($maintenanceWorkshopName === '') {
            $maintenanceWorkshopName = null;
        } elseif (mb_strlen($maintenanceWorkshopName) > 255) {
            $errors['maintenance_workshop_name'] = 'Workshop name is too long (max 255 characters).';
        }
    }
    $daily = (float) ($_POST['daily_rate'] ?? 0);
    $monthly = (isset($_POST['monthly_rate']) && $_POST['monthly_rate'] !== '') ? (float) $_POST['monthly_rate'] : null;
    $rate1 = (isset($_POST['rate_1day']) && $_POST['rate_1day'] !== '') ? (float) $_POST['rate_1day'] : null;
    $rate7 = (isset($_POST['rate_7day']) && $_POST['rate_7day'] !== '') ? (float) $_POST['rate_7day'] : null;
    $rate15 = (isset($_POST['rate_15day']) && $_POST['rate_15day'] !== '') ? (float) $_POST['rate_15day'] : null;
    $rate30 = (isset($_POST['rate_30day']) && $_POST['rate_30day'] !== '') ? (float) $_POST['rate_30day'] : null;
    $image = trim($_POST['image_url'] ?? '');

    if (!$brand)
        $errors['brand'] = 'Brand is required.';
    if (!$model)
        $errors['model'] = 'Model is required.';
    if ($year < 1900 || $year > (date('Y') + 1))
        $errors['year'] = 'Invalid year.';
    if (!$plate)
        $errors['license_plate'] = 'License plate is required.';
    if ($daily <= 0)
        $errors['daily_rate'] = 'Daily rate must be greater than 0.';
    if (!in_array($status, ['available', 'rented', 'maintenance'], true))
        $errors['status'] = 'Invalid status.';

    // Unique plate check (exclude current)
    if (!isset($errors['license_plate'])) {
        $chk = $pdo->prepare('SELECT id FROM vehicles WHERE license_plate = ? AND id != ?');
        $chk->execute([$plate, $id]);
        if ($chk->fetch())
            $errors['license_plate'] = 'License plate already used by another vehicle.';
    }

    if (empty($errors)) {
        $upd = $pdo->prepare('UPDATE vehicles
                              SET brand=?,
                                  model=?,
                                  year=?,
                                  license_plate=?,
                                  color=?,
                                  vin=?,
                                  status=?,
                                  maintenance_started_at = CASE
                                      WHEN ? = "maintenance" AND status <> "maintenance" THEN NOW()
                                      WHEN ? = "maintenance" THEN COALESCE(maintenance_started_at, NOW())
                                      ELSE NULL
                                  END,
                                  maintenance_expected_return=?,
                                  maintenance_workshop_name=?,
                                  daily_rate=?,
                                  monthly_rate=?,
                                  rate_1day=?,
                                  rate_7day=?,
                                  rate_15day=?,
                                  rate_30day=?,
                                  image_url=?
                              WHERE id=?');
        $upd->execute([$brand, $model, $year, $plate, $color, $vin, $status, $status, $status, $maintenanceExpectedReturn, $maintenanceWorkshopName, $daily, $monthly, $rate1, $rate7, $rate15, $rate30, $image, $id]);

        // Additional docs (images only)
        if (!empty($_FILES['documents']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/documents/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);
            foreach ($_FILES['documents']['tmp_name'] as $i => $tmp) {
                if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                    $origName = basename($_FILES['documents']['name'][$i]);
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $name = uniqid() . '.' . $ext;
                        move_uploaded_file($tmp, $uploadDir . $name);
                        $doc = $pdo->prepare('INSERT INTO documents (vehicle_id,title,type,file_path) VALUES (?,?,?,?)');
                        $doc->execute([$id, $origName, $ext, 'uploads/documents/' . $name]);
                    }
                }
            }
        }

        // Auto-migrate vehicle_images
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_images (id INT AUTO_INCREMENT PRIMARY KEY, vehicle_id INT NOT NULL, file_path VARCHAR(255) NOT NULL, sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {
        }
        // Handle new uploaded photos
        if (!empty($_FILES['vehicle_photos']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/vehicles/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);
            $count = (int) $pdo->query("SELECT COUNT(*) FROM vehicle_images WHERE vehicle_id=$id")->fetchColumn();
            foreach ($_FILES['vehicle_photos']['tmp_name'] as $i => $tmp) {
                if ($count >= 5)
                    break;
                if ($_FILES['vehicle_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['vehicle_photos']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $fname = 'veh_' . $id . '_' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($tmp, $uploadDir . $fname)) {
                            $pdo->prepare('INSERT INTO vehicle_images (vehicle_id, file_path, sort_order) VALUES (?,?,?)')->execute([$id, 'uploads/vehicles/' . $fname, $count]);
                            $count++;
                        }
                    }
                }
            }
        }
        app_log('ACTION', "Updated vehicle (ID: $id)");
        flash('success', 'Vehicle updated successfully.');
        redirect("show.php?id=$id");
    }
    if (!empty($errors)) {
        app_log('ERROR', "Vehicle update validation failed (ID: $id)", ['errors' => $errors]);
    }
    // Re-populate from POST on error
    $v = array_merge($v, $_POST);
}

// Load existing uploaded images
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_images (id INT AUTO_INCREMENT PRIMARY KEY, vehicle_id INT NOT NULL, file_path VARCHAR(255) NOT NULL, sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
}
$existingImgs = $pdo->prepare('SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY sort_order, id');
$existingImgs->execute([$id]);
$existingImages = $existingImgs->fetchAll();

$pageTitle = 'Edit Vehicle';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors">
            <?= e($v['brand']) ?>
            <?= e($v['model']) ?>
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
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Vehicle Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php
                function field2(string $name, string $label, string $type = 'text', array $v = [], array $errors = [], bool $required = true): void
                {
                    $val = htmlspecialchars($v[$name] ?? '', ENT_QUOTES);
                    $err = $errors[$name] ?? '';
                    echo "<div><label class='block text-sm text-mb-silver mb-2'>$label" . ($required ? " <span class='text-red-400'>*</span>" : '') . "</label>
                    <input type='$type' name='$name' value='$val' " . ($required ? 'required' : '') . "
                        class='w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm'>
                    " . ($err ? "<p class='text-red-400 text-xs mt-1'>$err</p>" : '') . "</div>";
                }
                field2('brand', 'Brand', 'text', $v, $errors);
                field2('model', 'Model', 'text', $v, $errors);
                field2('year', 'Year', 'number', $v, $errors);
                field2('license_plate', 'License Plate', 'text', $v, $errors);
                field2('color', 'Color', 'text', $v, $errors, false);
                field2('vin', 'VIN', 'text', $v, $errors, false);
                ?>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Status <span class="text-red-400">*</span></label>
                    <select name="status" id="statusSelect" required onchange="toggleMaintenanceFields()"
                        class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <?php foreach (['available', 'rented', 'maintenance'] as $s): ?>
                            <option value="<?= $s ?>" <?= $v['status'] === $s ? 'selected' : '' ?>>
                                <?= ucfirst($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="maintenanceMetaWrap" style="<?= $v['status'] === 'maintenance' ? '' : 'display:none' ?>">
                    <label class="block text-sm text-mb-silver mb-2">Expected Return from Workshop <span
                            class="text-mb-subtle text-xs">(optional)</span></label>
                    <input type="date" name="maintenance_expected_return"
                        value="<?= e($v['maintenance_expected_return'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <?php if (!empty($errors['maintenance_expected_return'])): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['maintenance_expected_return']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-mb-subtle mt-1">Estimated date the vehicle returns from workshop.</p>
                    <label class="block text-sm text-mb-silver mt-3 mb-2">Workshop Name <span
                            class="text-mb-subtle text-xs">(optional)</span></label>
                    <input type="text" name="maintenance_workshop_name" maxlength="255"
                        value="<?= e($v['maintenance_workshop_name'] ?? '') ?>" placeholder="Enter workshop name"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <?php if (!empty($errors['maintenance_workshop_name'])): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['maintenance_workshop_name']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Pricing</h3>
            <p class="text-xs text-mb-subtle">Set the standard daily rate. Package rates are optional - if set, they
                override the daily rate for that duration when creating a reservation.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php field2('daily_rate', 'Daily Rate (USD) *', 'number', $v, $errors); ?>
                <?php field2('monthly_rate', 'Monthly Rate (USD)', 'number', $v, $errors, false); ?>
                <?php field2('rate_1day', '1-Day Package Rate', 'number', $v, $errors, false); ?>
                <?php field2('rate_7day', '7-Day Package Rate', 'number', $v, $errors, false); ?>
                <?php field2('rate_15day', '15-Day Package Rate', 'number', $v, $errors, false); ?>
                <?php field2('rate_30day', '30-Day Package Rate', 'number', $v, $errors, false); ?>
            </div>
        </div>

        <!-- Vehicle Photos -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Vehicle Photos <span
                    class="text-sm text-mb-subtle font-normal">- up to 5</span></h3>

            <?php if (!empty($existingImages)): ?>
                <div>
                    <p class="text-xs text-mb-subtle uppercase mb-2 tracking-wider">Uploaded Photos
                        (<?= count($existingImages) ?>/5)</p>
                    <div class="grid grid-cols-3 gap-3">
                        <?php foreach ($existingImages as $img): ?>
                            <div class="relative group h-28 rounded-lg overflow-hidden border border-mb-subtle/20">
                                <img src="../<?= e($img['file_path']) ?>" class="w-full h-full object-cover">
                                <div
                                    class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <button type="button" onclick="deleteVehicleImage(<?= (int) $img['id'] ?>, <?= $id ?>)"
                                        class="bg-red-500 text-white rounded-full w-8 h-8 flex items-center justify-center hover:bg-red-600 transition-colors"
                                        title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (count($existingImages ?? []) < 5): ?>
                <div class="flex gap-1 bg-mb-black/40 rounded-lg p-1 w-fit">
                    <button type="button" id="tab-url" onclick="switchTab('url')"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors bg-mb-accent text-white">
                        URL</button>
                    <button type="button" id="tab-upload" onclick="switchTab('upload')"
                        class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors text-mb-subtle hover:text-white">
                        Upload</button>
                </div>
                <div id="pane-url" class="space-y-3">
                    <label class="block text-sm text-mb-silver">Image URL</label>
                    <input type="url" name="image_url" id="image_url" value="<?= e($v['image_url'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        oninput="previewImage(this.value)">
                    <div id="image-preview-wrap" style="display: <?= !empty($v['image_url']) ? 'block' : 'none' ?>">
                        <div class="h-44 rounded-lg overflow-hidden border border-mb-subtle/20">
                            <img id="image-preview" src="<?= e($v['image_url'] ?? '') ?>" alt="Preview"
                                class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>
                <div id="pane-upload" style="display:none" class="space-y-3">
                    <div id="drop-zone"
                        class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-8 text-center hover:border-mb-accent/50 transition-colors cursor-pointer"
                        onclick="document.getElementById('vehicle_photos').click()"
                        ondragover="event.preventDefault();this.classList.add('border-mb-accent')"
                        ondragleave="this.classList.remove('border-mb-accent')" ondrop="handleDrop(event)">
                        <svg class="mx-auto h-10 w-10 text-mb-subtle/30 mb-2" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-mb-silver text-sm"><span class="text-mb-accent font-medium">Click to upload</span> or
                            drag & drop</p>
                        <p class="text-mb-subtle/60 text-xs mt-1">JPG, PNG, WEBP - up to 5 photos</p>
                        <input id="vehicle_photos" name="vehicle_photos[]" type="file" class="sr-only" multiple
                            accept="image/jpeg,image/png,image/webp">
                        <div id="upload-previews" class="grid grid-cols-3 gap-3" style="display:none"></div>
                        <p id="upload-count" class="text-xs text-mb-subtle" style="display:none"></p>
                    </div>
                <?php else: ?>
                    <p class="text-sm text-mb-subtle">Maximum 5 photos reached. Delete one above to add more.</p>
                <?php endif; ?>
            </div>

            <!-- Vehicle Documents - div+onclick, NOT label-for (prevents black screen scroll bug) -->
            <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Vehicle Documents <span
                        class="text-sm text-mb-subtle font-normal">- up to 5</span></h3>
                <p class="text-mb-subtle text-sm">Upload photos of registration, insurance, or any other documents.</p>
                <div class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-8 text-center hover:border-mb-accent/50 transition-colors cursor-pointer"
                    onclick="document.getElementById('documents').click()"
                    ondragover="event.preventDefault();this.classList.add('border-mb-accent')"
                    ondragleave="this.classList.remove('border-mb-accent')"
                    ondrop="event.preventDefault();event.currentTarget.classList.remove('border-mb-accent');addDocs(event.dataTransfer.files)">
                    <svg class="mx-auto h-10 w-10 text-mb-subtle/40 mb-3" stroke="currentColor" fill="none"
                        viewBox="0 0 48 48">
                        <path
                            d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <p class="text-mb-silver text-sm"><span class="text-mb-accent font-medium">Click to upload</span> or
                        drag &amp; drop</p>
                    <p class="text-mb-subtle/60 text-xs mt-2">JPG, PNG, WEBP - up to 5 document photos</p>
                    <input id="documents" name="documents[]" type="file" class="sr-only" multiple
                        accept="image/jpeg,image/png,image/webp">
                </div>
                <p id="doc-count" class="text-xs text-mb-subtle" style="display:none"></p>
                <div id="file-list" style="display:none" class="space-y-1"></div>
            </div>

            <div class="flex items-center justify-end gap-4">
                <a href="show.php?id=<?= $id ?>"
                    class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
                <button type="submit"
                    class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                    Save Changes
                </button>
            </div>
    </form>
</div>

<script>
    function toggleMaintenanceFields() {
        const st = document.getElementById('statusSelect').value;
        const wrap = document.getElementById('maintenanceMetaWrap');
        if (wrap) wrap.style.display = (st === 'maintenance') ? 'block' : 'none';
    }
    toggleMaintenanceFields();
    //  Vehicle Photos 
    let photoFiles = [];
    const PHOTO_MAX = 5;
    function deleteVehicleImage(imgId, vehicleId) {
        if (!confirm("Delete this photo?")) return;
        const form = document.createElement("form");
        form.method = "POST";
        form.action = "delete_image.php";
        const imgInput = document.createElement("input");
        imgInput.type = "hidden";
        imgInput.name = "img_id";
        imgInput.value = String(imgId);
        const vehicleInput = document.createElement("input");
        vehicleInput.type = "hidden";
        vehicleInput.name = "vehicle_id";
        vehicleInput.value = String(vehicleId);
        form.appendChild(imgInput);
        form.appendChild(vehicleInput);
        document.body.appendChild(form);
        form.submit();
    }
    function addPhotos(files) {
        const slots = PHOTO_MAX - photoFiles.length;
        if (slots <= 0) return;
        photoFiles = [...photoFiles, ...Array.from(files).slice(0, slots)];
        renderPhotoPreview();
    }
    function removePhoto(idx) { photoFiles.splice(idx, 1); renderPhotoPreview(); }
    function renderPhotoPreview() {
        const grid = document.getElementById("upload-previews");
        const cnt = document.getElementById("upload-count");
        const inp = document.getElementById("vehicle_photos");
        if (!grid) return;
        grid.innerHTML = "";
        const dt = new DataTransfer();
        photoFiles.forEach(f => dt.items.add(f));
        if (inp) inp.files = dt.files;
        if (!photoFiles.length) {
            grid.style.display = "none";
            if (cnt) cnt.style.display = "none";
            return;
        }
        grid.style.display = "grid";
        if (cnt) {
            cnt.style.display = "block";
            cnt.textContent = photoFiles.length + "/" + PHOTO_MAX + " photo" + (photoFiles.length > 1 ? "s" : "") + " selected";
            cnt.style.color = photoFiles.length >= PHOTO_MAX ? "#f87171" : "";
        }
        photoFiles.forEach((f, i) => {
            const wrap = document.createElement("div");
            wrap.className = "relative h-28 rounded-lg overflow-hidden border border-mb-subtle/20 group";
            grid.appendChild(wrap);
            const reader = new FileReader();
            reader.onload = e => {
                wrap.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">
                <button type="button" onclick="removePhoto(${i})" title="Remove"
                    class="absolute top-1 right-1 bg-black/70 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-500 transition-colors opacity-0 group-hover:opacity-100">&#x2715;</button>`;
            };
            reader.readAsDataURL(f);
        });
    }
    function previewUploads(files) { addPhotos(files); }
    function handleDrop(e) {
        e.preventDefault();
        const dz = document.getElementById("drop-zone");
        if (dz) dz.classList.remove("border-mb-accent");
        addPhotos(e.dataTransfer.files);
    }
    document.getElementById("vehicle_photos")?.addEventListener("change", function () { addPhotos(this.files); });

    //  Vehicle Documents 
    let docFiles = [];
    const DOC_MAX = 5;
    function addDocs(files) {
        const slots = DOC_MAX - docFiles.length;
        if (slots <= 0) return;
        docFiles = [...docFiles, ...Array.from(files).slice(0, slots)];
        renderDocPreview();
    }
    function removeDoc(idx) { docFiles.splice(idx, 1); renderDocPreview(); }
    function renderDocPreview() {
        const list = document.getElementById("file-list");
        const cnt = document.getElementById("doc-count");
        const inp = document.getElementById("documents");
        if (!list) return;
        const dt = new DataTransfer();
        docFiles.forEach(f => dt.items.add(f));
        if (inp) inp.files = dt.files;
        list.innerHTML = "";
        if (!docFiles.length) {
            list.style.display = "none";
            if (cnt) cnt.style.display = "none";
            return;
        }
        list.style.display = "block";
        if (cnt) {
            cnt.style.display = "block";
            cnt.textContent = docFiles.length + "/" + DOC_MAX + " document" + (docFiles.length > 1 ? "s" : "") + " selected";
            cnt.style.color = docFiles.length >= DOC_MAX ? "#f87171" : "";
        }
        docFiles.forEach((f, i) => {
            const row = document.createElement("div");
            row.className = "flex items-center justify-between gap-2 bg-mb-surface border border-mb-subtle/20 rounded px-3 py-2";
            row.innerHTML = `<span class="text-xs text-mb-silver flex items-center gap-2 truncate min-w-0"><span>&#x1F4C4;</span><span class="truncate">${f.name}</span></span>
            <button type="button" onclick="removeDoc(${i})" title="Remove"
                class="flex-shrink-0 text-mb-subtle hover:text-red-400 transition-colors text-sm">&#x2715;</button>`;
            list.appendChild(row);
        });
    }
    document.getElementById("documents")?.addEventListener("change", function () { addDocs(this.files); });

    //  Tab / URL Preview 
    function switchTab(tab) {
        const pu = document.getElementById("pane-url");
        const pp = document.getElementById("pane-upload");
        const tu = document.getElementById("tab-url");
        const tp = document.getElementById("tab-upload");
        if (!pu || !pp) return;
        pu.style.display = (tab === "url") ? "block" : "none";
        pp.style.display = (tab === "upload") ? "block" : "none";
        if (tu) tu.className = "px-3 py-1.5 rounded-md text-sm font-medium transition-colors " + (tab === "url" ? "bg-mb-accent text-white" : "text-mb-subtle hover:text-white");
        if (tp) tp.className = "px-3 py-1.5 rounded-md text-sm font-medium transition-colors " + (tab === "upload" ? "bg-mb-accent text-white" : "text-mb-subtle hover:text-white");
    }
    function previewImage(url) {
        const wrap = document.getElementById("image-preview-wrap");
        const img = document.getElementById("image-preview");
        if (!wrap || !img) return;
        if (url && url.startsWith("http")) { img.src = url; wrap.style.display = "block"; }
        else { wrap.style.display = "none"; }
    }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
