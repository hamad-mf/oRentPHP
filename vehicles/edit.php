<?php
require_once __DIR__ . '/../config/db.php';

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$vStmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = ?');
$vStmt->execute([$id]);
$v = $vStmt->fetch();
if (!$v) {
    flash('error', 'Vehicle not found.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = (int) ($_POST['year'] ?? 0);
    $plate = trim($_POST['license_plate'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $vin = trim($_POST['vin'] ?? '');
    $status = $_POST['status'] ?? 'available';
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

    // Unique plate check (exclude current)
    if (!isset($errors['license_plate'])) {
        $chk = $pdo->prepare('SELECT id FROM vehicles WHERE license_plate = ? AND id != ?');
        $chk->execute([$plate, $id]);
        if ($chk->fetch())
            $errors['license_plate'] = 'License plate already used by another vehicle.';
    }

    if (empty($errors)) {
        $upd = $pdo->prepare('UPDATE vehicles SET brand=?,model=?,year=?,license_plate=?,color=?,vin=?,status=?,daily_rate=?,monthly_rate=?,rate_1day=?,rate_7day=?,rate_15day=?,rate_30day=?,image_url=? WHERE id=?');
        $upd->execute([$brand, $model, $year, $plate, $color, $vin, $status, $daily, $monthly, $rate1, $rate7, $rate15, $rate30, $image, $id]);

        // Additional docs
        if (!empty($_FILES['documents']['name'][0])) {
            $uploadDir = __DIR__ . '/../uploads/documents/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);
            foreach ($_FILES['documents']['tmp_name'] as $i => $tmp) {
                if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
                    $origName = basename($_FILES['documents']['name'][$i]);
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $name = uniqid() . '.' . $ext;
                    move_uploaded_file($tmp, $uploadDir . $name);
                    $doc = $pdo->prepare('INSERT INTO documents (vehicle_id,title,type,file_path) VALUES (?,?,?,?)');
                    $doc->execute([$id, $origName, $ext, 'uploads/documents/' . $name]);
                }
            }
        }

        flash('success', 'Vehicle updated successfully.');
        redirect("show.php?id=$id");
    }
    // Re-populate from POST on error
    $v = array_merge($v, $_POST);
}

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
                    <select name="status" required
                        class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <?php foreach (['available', 'rented', 'maintenance'] as $s): ?>
                            <option value="<?= $s ?>" <?= $v['status'] === $s ? 'selected' : '' ?>>
                                <?= ucfirst($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Pricing</h3>
            <p class="text-xs text-mb-subtle">Set the standard daily rate. Package rates are optional — if set, they
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

        <!-- Vehicle Image -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Vehicle Image</h3>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Image URL</label>
                <input type="url" name="image_url" id="image_url" value="<?= e($v['image_url'] ?? '') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm"
                    oninput="previewImage(this.value)">
            </div>
            <div id="image-preview-wrap" class="<?= $v['image_url'] ? '' : 'hidden' ?>">
                <div class="h-40 rounded-lg overflow-hidden border border-mb-subtle/20">
                    <img id="image-preview" src="<?= e($v['image_url'] ?? '') ?>" alt="Preview"
                        class="w-full h-full object-cover">
                </div>
            </div>
        </div>

        <!-- Add More Documents -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Add More Documents</h3>
            <div
                class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-6 text-center hover:border-mb-accent/50 transition-colors">
                <label for="documents" class="cursor-pointer">
                    <span class="text-mb-accent hover:text-mb-accent/80 text-sm font-medium">Click to upload</span>
                    <span class="text-mb-subtle text-sm"> additional documents</span>
                    <input id="documents" name="documents[]" type="file" class="sr-only" multiple
                        accept=".pdf,.jpg,.jpeg,.png">
                </label>
                <p class="text-mb-subtle/60 text-xs mt-1">PDF, JPG, PNG up to 4MB each</p>
            </div>
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
    function previewImage(url) {
        const wrap = document.getElementById('image-preview-wrap');
        const img = document.getElementById('image-preview');
        if (url && url.startsWith('http')) {
            img.src = url;
            wrap.classList.remove('hidden');
        } else {
            wrap.classList.add('hidden');
        }
    }
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>