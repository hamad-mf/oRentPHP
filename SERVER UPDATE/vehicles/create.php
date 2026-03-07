<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/vehicle_helpers.php';
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to add vehicles.');
    redirect('index.php');
}

$pdo = db();
vehicle_ensure_schema($pdo);

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = (int) ($_POST['year'] ?? 0);
    $plate = trim($_POST['license_plate'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $vin = trim($_POST['vin'] ?? '');
    $status = $_POST['status'] ?? 'available';
    $maintenanceExpectedReturn = trim($_POST['maintenance_expected_return'] ?? '');
    $maintenanceWorkshopName = trim($_POST['maintenance_workshop_name'] ?? '');
    $daily = (float) ($_POST['daily_rate'] ?? 0);
    $monthly = $_POST['monthly_rate'] !== '' ? (float) $_POST['monthly_rate'] : null;
    $rate1 = $_POST['rate_1day'] !== '' ? (float) $_POST['rate_1day'] : null;
    $rate7 = $_POST['rate_7day'] !== '' ? (float) $_POST['rate_7day'] : null;
    $rate15 = $_POST['rate_15day'] !== '' ? (float) $_POST['rate_15day'] : null;
    $rate30 = $_POST['rate_30day'] !== '' ? (float) $_POST['rate_30day'] : null;
    $image = trim($_POST['image_url'] ?? '');
    $insuranceType = strtolower(trim((string) ($_POST['insurance_type'] ?? '')));
    $insuranceType = $insuranceType !== '' ? $insuranceType : null;
    if ($insuranceType === 'thrid class') {
        $insuranceType = 'third class';
    } elseif ($insuranceType === 'bumber to bumber') {
        $insuranceType = 'bumper to bumper';
    }
    $insuranceExpiryDate = trim((string) ($_POST['insurance_expiry_date'] ?? ''));
    $insuranceExpiryDate = $insuranceExpiryDate !== '' ? $insuranceExpiryDate : null;

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
    if (!in_array($status, ['available', 'rented', 'maintenance']))
        $errors['status'] = 'Invalid status.';
    if ($insuranceType !== null && !in_array($insuranceType, ['third class', 'first class', 'bumper to bumper'], true)) {
        $errors['insurance_type'] = 'Invalid insurance type.';
    }
    if ($insuranceExpiryDate !== null) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $insuranceExpiryDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $insuranceExpiryDate) {
            $errors['insurance_expiry_date'] = 'Invalid insurance expiry date.';
        }
    }
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

    if (!isset($errors['license_plate'])) {
        $chk = db()->prepare('SELECT id FROM vehicles WHERE license_plate = ?');
        $chk->execute([$plate]);
        if ($chk->fetch())
            $errors['license_plate'] = 'License plate already exists.';
    }

    if (empty($errors)) {
        $maintenanceStartedAt = ($status === 'maintenance') ? date('Y-m-d H:i:s') : null;
        $stmt = db()->prepare('INSERT INTO vehicles (brand,model,year,license_plate,color,vin,status,maintenance_started_at,maintenance_expected_return,maintenance_workshop_name,daily_rate,monthly_rate,rate_1day,rate_7day,rate_15day,rate_30day,image_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$brand, $model, $year, $plate, $color, $vin, $status, $maintenanceStartedAt, $maintenanceExpectedReturn, $maintenanceWorkshopName, $daily, $monthly, $rate1, $rate7, $rate15, $rate30, $image]);
        $id = db()->lastInsertId();

        try {
            $metaStmt = $pdo->prepare('UPDATE vehicles SET insurance_type = ?, insurance_expiry_date = ? WHERE id = ?');
            $metaStmt->execute([$insuranceType, $insuranceExpiryDate, $id]);
        } catch (Throwable $e) {
            app_log('ERROR', 'Vehicle create insurance metadata save failed - ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'screen' => 'vehicles/create.php',
                'vehicle_id' => $id,
            ]);
        }

        try {
            db()->exec("CREATE TABLE IF NOT EXISTS vehicle_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vehicle_id INT NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                sort_order INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {
        }

        // Handle uploaded vehicle photos (max 5)
        if (!empty($_FILES['vehicle_photos']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/vehicles/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);
            $count = 0;
            foreach ($_FILES['vehicle_photos']['tmp_name'] as $i => $tmp) {
                if ($count >= 5)
                    break;
                if ($_FILES['vehicle_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['vehicle_photos']['name'][$i], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $fname = 'veh_' . $id . '_' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($tmp, $uploadDir . $fname)) {
                            db()->prepare('INSERT INTO vehicle_images (vehicle_id, file_path, sort_order) VALUES (?,?,?)')->execute([$id, 'uploads/vehicles/' . $fname, $count]);
                            $count++;
                        }
                    }
                }
            }
        }

        $saveVehicleDocs = static function (string $inputKey, int $max, ?string $titlePrefix = null) use ($id, $pdo): void {
            if (empty($_FILES[$inputKey]['name'][0])) {
                return;
            }

            $uploadDir = __DIR__ . '/../uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $saved = 0;
            foreach ($_FILES[$inputKey]['tmp_name'] as $i => $tmp) {
                if ($saved >= $max) {
                    break;
                }
                if (($_FILES[$inputKey]['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }

                $origName = basename((string) $_FILES[$inputKey]['name'][$i]);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $name = uniqid('doc_', true) . '.' . $ext;
                if (!move_uploaded_file($tmp, $uploadDir . $name)) {
                    continue;
                }

                $title = $titlePrefix !== null ? ($titlePrefix . ' - ' . $origName) : $origName;
                $pdo->prepare('INSERT INTO documents (vehicle_id,title,type,file_path) VALUES (?,?,?,?)')
                    ->execute([$id, $title, $ext, 'uploads/documents/' . $name]);
                $saved++;
            }
        };

        $saveVehicleDocs('documents', 5);
        $saveVehicleDocs('insurance_docs', 5, 'Insurance');
        $saveVehicleDocs('pollution_docs', 5, 'Pollution');

        app_log('ACTION', "Created vehicle: $brand $model (ID: $id)");
        flash('success', "Vehicle $brand $model added successfully.");
        redirect("show.php?id=$id");
    }
}

$pageTitle = 'Add New Vehicle';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Add Vehicle</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400 space-y-1">
            <?php foreach ($errors as $e): ?>
                <p>&bull; <?= e($e) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Basic Info -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Vehicle Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php
                function field(string $name, string $label, string $type = 'text', array $old = [], array $errors = [], bool $required = true, string $placeholder = ''): void
                {
                    $val = htmlspecialchars($old[$name] ?? '', ENT_QUOTES);
                    $err = $errors[$name] ?? '';
                    echo "<div><label class='block text-sm text-mb-silver mb-2'>$label" . ($required ? " <span class='text-red-400'>*</span>" : '') . "</label>
                    <input type='$type' name='$name' value='$val' placeholder='$placeholder' " . ($required ? 'required' : '') . "
                        class='w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm'>
                    " . ($err ? "<p class='text-red-400 text-xs mt-1'>$err</p>" : '') . "</div>";
                }
                field('brand', 'Brand', 'text', $_POST, $errors, true, 'e.g. Toyota');
                field('model', 'Model', 'text', $_POST, $errors, true, 'e.g. Camry');
                field('year', 'Year', 'number', $_POST, $errors, true, date('Y'));
                field('license_plate', 'License Plate', 'text', $_POST, $errors, true, 'ABC-1234');
                field('color', 'Color', 'text', $_POST, $errors, false, 'e.g. Black');
                field('vin', 'VIN', 'text', $_POST, $errors, false, 'Vehicle Identification Number');
                ?>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Status <span class="text-red-400">*</span></label>
                    <select name="status" id="statusSelect" onchange="toggleMaintenanceFields()" required
                        class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <option value="available" <?= (($_POST['status'] ?? 'available') === 'available') ? 'selected' : '' ?>>Available</option>
                        <option value="rented" <?= (($_POST['status'] ?? '') === 'rented') ? 'selected' : '' ?>>Rented
                        </option>
                        <option value="maintenance" <?= (($_POST['status'] ?? '') === 'maintenance') ? 'selected' : '' ?>>
                            Maintenance</option>
                    </select>
                </div>
                <div id="maintenanceMetaWrap"
                    style="<?= (($_POST['status'] ?? 'available') === 'maintenance') ? '' : 'display:none' ?>">
                    <label class="block text-sm text-mb-silver mb-2">Expected Return from Workshop <span
                            class="text-mb-subtle text-xs">(optional)</span></label>
                    <input type="date" name="maintenance_expected_return"
                        value="<?= e($_POST['maintenance_expected_return'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <?php if (!empty($errors['maintenance_expected_return'])): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['maintenance_expected_return']) ?></p>
                    <?php endif; ?>
                    <label class="block text-sm text-mb-silver mt-3 mb-2">Workshop Name <span
                            class="text-mb-subtle text-xs">(optional)</span></label>
                    <input type="text" name="maintenance_workshop_name" maxlength="255"
                        value="<?= e($_POST['maintenance_workshop_name'] ?? '') ?>" placeholder="Enter workshop name"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <?php if (!empty($errors['maintenance_workshop_name'])): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['maintenance_workshop_name']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Pricing -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Pricing</h3>
            <p class="text-xs text-mb-subtle">Set the standard daily rate. Package rates are optional — if set, they
                override the daily rate for that duration.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php field('daily_rate', 'Daily Rate (USD) *', 'number', $_POST, $errors, true, '0.00'); ?>
                <?php field('monthly_rate', 'Monthly Rate (USD)', 'number', $_POST, $errors, false, 'Optional'); ?>
                <?php field('rate_1day', '1-Day Package Rate', 'number', $_POST, $errors, false, 'Optional'); ?>
                <?php field('rate_7day', '7-Day Package Rate', 'number', $_POST, $errors, false, 'Optional'); ?>
                <?php field('rate_15day', '15-Day Package Rate', 'number', $_POST, $errors, false, 'Optional'); ?>
                <?php field('rate_30day', '30-Day Package Rate', 'number', $_POST, $errors, false, 'Optional'); ?>
            </div>
        </div>

        <!-- Vehicle Photos -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Vehicle Photos <span
                    class="text-sm text-mb-subtle font-normal">— up to 5</span></h3>
            <div class="flex gap-1 bg-mb-black/40 rounded-lg p-1 w-fit">
                <button type="button" id="tab-url" onclick="switchTab('url')"
                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors bg-mb-accent text-white">🔗
                    URL</button>
                <button type="button" id="tab-upload" onclick="switchTab('upload')"
                    class="px-3 py-1.5 rounded-md text-sm font-medium transition-colors text-mb-subtle hover:text-white">📤
                    Upload</button>
            </div>
            <div id="pane-url" class="space-y-3">
                <label class="block text-sm text-mb-silver">Image URL</label>
                <input type="url" name="image_url" id="image_url" value="<?= e($_POST['image_url'] ?? '') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                    oninput="previewImage(this.value)">
                <div id="image-preview-wrap" style="display:<?= !empty($_POST['image_url']) ? 'block' : 'none' ?>">
                    <div class="h-44 rounded-lg overflow-hidden border border-mb-subtle/20">
                        <img id="image-preview" src="<?= e($_POST['image_url'] ?? '') ?>" alt="Preview"
                            class="w-full h-full object-cover">
                    </div>
                </div>
            </div>
            <div id="pane-upload" style="display:none" class="space-y-3">
                <!-- div+onclick = no label-for, so browser won't scroll-to-focus after dialog -->
                <div id="drop-zone"
                    class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-8 text-center hover:border-mb-accent/50 transition-colors cursor-pointer"
                    onclick="document.getElementById('vehicle_photos').click()"
                    ondragover="event.preventDefault();this.classList.add('border-mb-accent')"
                    ondragleave="this.classList.remove('border-mb-accent')"
                    ondrop="handleDrop(event,'addPhotos')">
                    <svg class="mx-auto h-10 w-10 text-mb-subtle/30 mb-2" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-mb-silver text-sm"><span class="text-mb-accent font-medium">Click to upload</span> or
                        drag &amp; drop</p>
                    <p class="text-mb-subtle/60 text-xs mt-1">JPG, PNG, WEBP — up to 5 photos</p>
                    <input id="vehicle_photos" name="vehicle_photos[]" type="file" class="sr-only" multiple
                        accept="image/jpeg,image/png,image/webp">
                </div>
                <p id="upload-count" class="text-xs text-mb-subtle" style="display:none"></p>
                <div id="upload-previews" class="grid grid-cols-3 gap-3" style="display:none"></div>
            </div>
        </div>

        <!-- Vehicle Documents — same div+onclick pattern, NO label-for -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Vehicle Documents <span
                    class="text-sm text-mb-subtle font-normal">— up to 5</span></h3>
            <p class="text-mb-subtle text-sm">Upload photos of registration, insurance, or any other documents.</p>
            <!-- div+onclick prevents browser scroll-to-focus after file dialog closes -->
            <div class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-8 text-center hover:border-mb-accent/50 transition-colors cursor-pointer"
                onclick="document.getElementById('documents').click()"
                ondragover="event.preventDefault();this.classList.add('border-mb-accent')"
                ondragleave="this.classList.remove('border-mb-accent')"
                ondrop="handleDrop(event,'addDocs')">
                <svg class="mx-auto h-10 w-10 text-mb-subtle/40 mb-3" stroke="currentColor" fill="none"
                    viewBox="0 0 48 48">
                    <path
                        d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <p class="text-mb-silver text-sm"><span class="text-mb-accent font-medium">Click to upload</span> or
                    drag &amp; drop</p>
                <p class="text-mb-subtle/60 text-xs mt-2">JPG, PNG, WEBP — up to 5 document photos</p>
                <input id="documents" name="documents[]" type="file" class="sr-only" multiple
                    accept="image/jpeg,image/png,image/webp">
            </div>
            <p id="doc-count" class="text-xs text-mb-subtle" style="display:none"></p>
            <div id="file-list" style="display:none" class="space-y-1"></div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Insurance Docs <span
                    class="text-sm text-mb-subtle font-normal">- optional, up to 5</span></h3>
            <p class="text-mb-subtle text-sm">Upload insurance document images for this vehicle.</p>
            <?php
            $insuranceTypeValue = strtolower(trim((string) ($_POST['insurance_type'] ?? '')));
            if ($insuranceTypeValue === 'thrid class') {
                $insuranceTypeValue = 'third class';
            } elseif ($insuranceTypeValue === 'bumber to bumber') {
                $insuranceTypeValue = 'bumper to bumper';
            }
            ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Insurance Type <span
                            class="text-mb-subtle text-xs">(optional)</span></label>
                    <select name="insurance_type"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <option value="">Select type</option>
                        <option value="third class" <?= $insuranceTypeValue === 'third class' ? 'selected' : '' ?>>Third Class</option>
                        <option value="first class" <?= $insuranceTypeValue === 'first class' ? 'selected' : '' ?>>First Class</option>
                        <option value="bumper to bumper" <?= $insuranceTypeValue === 'bumper to bumper' ? 'selected' : '' ?>>Bumper to Bumper</option>
                    </select>
                    <?php if (!empty($errors['insurance_type'])): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['insurance_type']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Insurance Expiry Date <span
                            class="text-mb-subtle text-xs">(optional)</span></label>
                    <input type="date" name="insurance_expiry_date"
                        value="<?= e($_POST['insurance_expiry_date'] ?? '') ?>"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                    <?php if (!empty($errors['insurance_expiry_date'])): ?>
                        <p class="text-red-400 text-xs mt-1"><?= e($errors['insurance_expiry_date']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-8 text-center hover:border-mb-accent/50 transition-colors cursor-pointer"
                onclick="document.getElementById('insurance_docs').click()"
                ondragover="event.preventDefault();this.classList.add('border-mb-accent')"
                ondragleave="this.classList.remove('border-mb-accent')"
                ondrop="handleDrop(event,'addInsuranceDocs')">
                <svg class="mx-auto h-10 w-10 text-mb-subtle/40 mb-3" stroke="currentColor" fill="none"
                    viewBox="0 0 48 48">
                    <path
                        d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <p class="text-mb-silver text-sm"><span class="text-mb-accent font-medium">Click to upload</span> or
                    drag &amp; drop</p>
                <p class="text-mb-subtle/60 text-xs mt-2">JPG, PNG, WEBP - up to 5 insurance photos</p>
                <input id="insurance_docs" name="insurance_docs[]" type="file" class="sr-only" multiple
                    accept="image/jpeg,image/png,image/webp">
            </div>
            <p id="insurance-doc-count" class="text-xs text-mb-subtle" style="display:none"></p>
            <div id="insurance-file-list" style="display:none" class="space-y-1"></div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Pollution Docs <span
                    class="text-sm text-mb-subtle font-normal">- optional, up to 5</span></h3>
            <p class="text-mb-subtle text-sm">Upload pollution certificate images for this vehicle.</p>
            <div class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-8 text-center hover:border-mb-accent/50 transition-colors cursor-pointer"
                onclick="document.getElementById('pollution_docs').click()"
                ondragover="event.preventDefault();this.classList.add('border-mb-accent')"
                ondragleave="this.classList.remove('border-mb-accent')"
                ondrop="handleDrop(event,'addPollutionDocs')">
                <svg class="mx-auto h-10 w-10 text-mb-subtle/40 mb-3" stroke="currentColor" fill="none"
                    viewBox="0 0 48 48">
                    <path
                        d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <p class="text-mb-silver text-sm"><span class="text-mb-accent font-medium">Click to upload</span> or
                    drag &amp; drop</p>
                <p class="text-mb-subtle/60 text-xs mt-2">JPG, PNG, WEBP - up to 5 pollution photos</p>
                <input id="pollution_docs" name="pollution_docs[]" type="file" class="sr-only" multiple
                    accept="image/jpeg,image/png,image/webp">
            </div>
            <p id="pollution-doc-count" class="text-xs text-mb-subtle" style="display:none"></p>
            <div id="pollution-file-list" style="display:none" class="space-y-1"></div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="index.php" class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">
                Save Vehicle
            </button>
        </div>
    </form>
</div>

<script>
    // ── Vehicle Photos ────────────────────────────────────
    function toggleMaintenanceFields() {
        const status = document.getElementById('statusSelect')?.value || 'available';
        const wrap = document.getElementById('maintenanceMetaWrap');
        if (wrap) {
            wrap.style.display = status === 'maintenance' ? 'block' : 'none';
        }
    }
    toggleMaintenanceFields();

    let photoFiles = [];
    const PHOTO_MAX = 5;

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
                    class="absolute top-1 right-1 bg-black/70 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-500 transition-colors opacity-0 group-hover:opacity-100">✕</button>`;
            };
            reader.readAsDataURL(f);
        });
    }
    document.getElementById("vehicle_photos")?.addEventListener("change", function () { addPhotos(this.files); });

    // ── Vehicle Documents ─────────────────────────────────
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
            row.innerHTML = `<span class="text-xs text-mb-silver flex items-center gap-2 truncate min-w-0"><span>📄</span><span class="truncate">${f.name}</span></span>
            <button type="button" onclick="removeDoc(${i})" title="Remove"
                class="flex-shrink-0 text-mb-subtle hover:text-red-400 transition-colors text-sm">✕</button>`;
            list.appendChild(row);
        });
    }
    document.getElementById("documents")?.addEventListener("change", function () { addDocs(this.files); });

    let insuranceFiles = [];
    const INSURANCE_MAX = 5;

    function addInsuranceDocs(files) {
        const slots = INSURANCE_MAX - insuranceFiles.length;
        if (slots <= 0) return;
        insuranceFiles = [...insuranceFiles, ...Array.from(files).slice(0, slots)];
        renderInsuranceDocPreview();
    }
    function removeInsuranceDoc(idx) { insuranceFiles.splice(idx, 1); renderInsuranceDocPreview(); }
    function renderInsuranceDocPreview() {
        const list = document.getElementById("insurance-file-list");
        const cnt = document.getElementById("insurance-doc-count");
        const inp = document.getElementById("insurance_docs");
        if (!list) return;
        const dt = new DataTransfer();
        insuranceFiles.forEach(f => dt.items.add(f));
        if (inp) inp.files = dt.files;
        list.innerHTML = "";
        if (!insuranceFiles.length) {
            list.style.display = "none";
            if (cnt) cnt.style.display = "none";
            return;
        }
        list.style.display = "block";
        if (cnt) {
            cnt.style.display = "block";
            cnt.textContent = insuranceFiles.length + "/" + INSURANCE_MAX + " insurance file" + (insuranceFiles.length > 1 ? "s" : "") + " selected";
            cnt.style.color = insuranceFiles.length >= INSURANCE_MAX ? "#f87171" : "";
        }
        insuranceFiles.forEach((f, i) => {
            const row = document.createElement("div");
            row.className = "flex items-center justify-between gap-2 bg-mb-surface border border-mb-subtle/20 rounded px-3 py-2";
            row.innerHTML = `<span class="text-xs text-mb-silver flex items-center gap-2 truncate min-w-0"><span>📄</span><span class="truncate">${f.name}</span></span>
            <button type="button" onclick="removeInsuranceDoc(${i})" title="Remove"
                class="flex-shrink-0 text-mb-subtle hover:text-red-400 transition-colors text-sm">✕</button>`;
            list.appendChild(row);
        });
    }
    document.getElementById("insurance_docs")?.addEventListener("change", function () { addInsuranceDocs(this.files); });

    let pollutionFiles = [];
    const POLLUTION_MAX = 5;

    function addPollutionDocs(files) {
        const slots = POLLUTION_MAX - pollutionFiles.length;
        if (slots <= 0) return;
        pollutionFiles = [...pollutionFiles, ...Array.from(files).slice(0, slots)];
        renderPollutionDocPreview();
    }
    function removePollutionDoc(idx) { pollutionFiles.splice(idx, 1); renderPollutionDocPreview(); }
    function renderPollutionDocPreview() {
        const list = document.getElementById("pollution-file-list");
        const cnt = document.getElementById("pollution-doc-count");
        const inp = document.getElementById("pollution_docs");
        if (!list) return;
        const dt = new DataTransfer();
        pollutionFiles.forEach(f => dt.items.add(f));
        if (inp) inp.files = dt.files;
        list.innerHTML = "";
        if (!pollutionFiles.length) {
            list.style.display = "none";
            if (cnt) cnt.style.display = "none";
            return;
        }
        list.style.display = "block";
        if (cnt) {
            cnt.style.display = "block";
            cnt.textContent = pollutionFiles.length + "/" + POLLUTION_MAX + " pollution file" + (pollutionFiles.length > 1 ? "s" : "") + " selected";
            cnt.style.color = pollutionFiles.length >= POLLUTION_MAX ? "#f87171" : "";
        }
        pollutionFiles.forEach((f, i) => {
            const row = document.createElement("div");
            row.className = "flex items-center justify-between gap-2 bg-mb-surface border border-mb-subtle/20 rounded px-3 py-2";
            row.innerHTML = `<span class="text-xs text-mb-silver flex items-center gap-2 truncate min-w-0"><span>📄</span><span class="truncate">${f.name}</span></span>
            <button type="button" onclick="removePollutionDoc(${i})" title="Remove"
                class="flex-shrink-0 text-mb-subtle hover:text-red-400 transition-colors text-sm">✕</button>`;
            list.appendChild(row);
        });
    }
    document.getElementById("pollution_docs")?.addEventListener("change", function () { addPollutionDocs(this.files); });

    // ── Drag-drop handler (shared) ────────────────────────
    function handleDrop(e, addFn) {
        e.preventDefault();
        e.currentTarget.classList.remove("border-mb-accent");
        if (addFn === "addPhotos") {
            addPhotos(e.dataTransfer.files);
        } else if (addFn === "addInsuranceDocs") {
            addInsuranceDocs(e.dataTransfer.files);
        } else if (addFn === "addPollutionDocs") {
            addPollutionDocs(e.dataTransfer.files);
        } else {
            addDocs(e.dataTransfer.files);
        }
    }

    // ── URL tab / preview ─────────────────────────────────
    function switchTab(tab) {
        const pu = document.getElementById("pane-url");
        const pp = document.getElementById("pane-upload");
        const tu = document.getElementById("tab-url");
        const tp = document.getElementById("tab-upload");
        if (!pu || !pp) return;
        pu.style.display = (tab === "url") ? "block" : "none";
        pp.style.display = (tab === "upload") ? "block" : "none";
        tu.className = "px-3 py-1.5 rounded-md text-sm font-medium transition-colors " + (tab === "url" ? "bg-mb-accent text-white" : "text-mb-subtle hover:text-white");
        tp.className = "px-3 py-1.5 rounded-md text-sm font-medium transition-colors " + (tab === "upload" ? "bg-mb-accent text-white" : "text-mb-subtle hover:text-white");
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

