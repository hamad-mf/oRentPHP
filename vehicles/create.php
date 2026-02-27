<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to add vehicles.');
    redirect('index.php');
}

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
    $daily = (float) ($_POST['daily_rate'] ?? 0);
    $monthly = $_POST['monthly_rate'] !== '' ? (float) $_POST['monthly_rate'] : null;
    $rate1   = $_POST['rate_1day']  !== '' ? (float) $_POST['rate_1day']  : null;
    $rate7   = $_POST['rate_7day']  !== '' ? (float) $_POST['rate_7day']  : null;
    $rate15  = $_POST['rate_15day'] !== '' ? (float) $_POST['rate_15day'] : null;
    $rate30  = $_POST['rate_30day'] !== '' ? (float) $_POST['rate_30day'] : null;
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
    if (!in_array($status, ['available', 'rented', 'maintenance']))
        $errors['status'] = 'Invalid status.';

    // Unique plate check
    if (!isset($errors['license_plate'])) {
        $chk = db()->prepare('SELECT id FROM vehicles WHERE license_plate = ?');
        $chk->execute([$plate]);
        if ($chk->fetch())
            $errors['license_plate'] = 'License plate already exists.';
    }

    if (empty($errors)) {
        $stmt = db()->prepare('INSERT INTO vehicles (brand,model,year,license_plate,color,vin,status,daily_rate,monthly_rate,rate_1day,rate_7day,rate_15day,rate_30day,image_url) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$brand, $model, $year, $plate, $color, $vin, $status, $daily, $monthly, $rate1, $rate7, $rate15, $rate30, $image]);
        $id = db()->lastInsertId();

        // Handle document uploads
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
                    $doc = db()->prepare('INSERT INTO documents (vehicle_id,title,type,file_path) VALUES (?,?,?,?)');
                    $doc->execute([$id, $origName, $ext, 'uploads/documents/' . $name]);
                }
            }
        }

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
                <p>&bull;
                    <?= e($e) ?>
                </p>
            <?php endforeach; ?>
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
                    <select name="status" required
                        class="select2 w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <option value="available" <?= (($_POST['status'] ?? 'available') === 'available') ? 'selected' : '' ?>
                            >Available</option>
                        <option value="rented" <?= (($_POST['status'] ?? '') === 'rented') ? 'selected' : '' ?>>Rented</option>
                        <option value="maintenance" <?= (($_POST['status'] ?? '') === 'maintenance') ? 'selected' : '' ?>
                            >Maintenance</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Pricing -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Pricing</h3>
            <p class="text-xs text-mb-subtle">Set the standard daily rate. Package rates are optional — if set, they override the daily rate for that duration when creating a reservation.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php field('daily_rate', 'Daily Rate (USD) *', 'number', $_POST, $errors, true, '0.00'); ?>
                <?php field('monthly_rate', 'Monthly Rate (USD)', 'number', $_POST, $errors, false, 'Optional'); ?>
                <?php field('rate_1day',  '1-Day Package Rate', 'number', $_POST, $errors, false, 'Optional'); ?>
                <?php field('rate_7day',  '7-Day Package Rate', 'number', $_POST, $errors, false, 'Optional'); ?>
                <?php field('rate_15day', '15-Day Package Rate', 'number', $_POST, $errors, false, 'Optional'); ?>
                <?php field('rate_30day', '30-Day Package Rate', 'number', $_POST, $errors, false, 'Optional'); ?>
            </div>
        </div>

        <!-- Vehicle Image -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Vehicle Image</h3>
            <div>
                <label class="block text-sm text-mb-silver mb-2">Image URL</label>
                <input type="url" name="image_url" id="image_url" value="<?= e($_POST['image_url'] ?? '') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors placeholder-mb-subtle/50 text-sm"
                    placeholder="https://example.com/car-photo.jpg" oninput="previewImage(this.value)">
                <p class="text-mb-subtle/60 text-xs mt-1">Paste a direct link to the car image.</p>
            </div>
            <div id="image-preview-wrap" class="hidden">
                <p class="text-mb-subtle text-xs mb-2 uppercase tracking-wide">Preview</p>
                <div class="h-48 rounded-lg overflow-hidden border border-mb-subtle/20">
                    <img id="image-preview" src="" alt="Preview" class="w-full h-full object-cover">
                </div>
            </div>
        </div>

        <!-- Vehicle Documents -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Vehicle Documents</h3>
            <p class="text-mb-subtle text-sm">Upload registration, insurance, or any other official vehicle documents.</p>
            <div class="bg-mb-black/50 border-2 border-dashed border-mb-subtle/30 rounded-lg p-8 text-center hover:border-mb-accent/50 transition-colors">
                <svg class="mx-auto h-10 w-10 text-mb-subtle/40 mb-3" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <label for="documents" class="cursor-pointer">
                    <span class="text-mb-accent hover:text-mb-accent/80 text-sm font-medium transition-colors">Click to upload files</span>
                    <span class="text-mb-subtle text-sm"> or drag and drop</span>
                    <input id="documents" name="documents[]" type="file" class="sr-only" multiple accept=".pdf,.jpg,.jpeg,.png">
                </label>
                <p class="text-mb-subtle/60 text-xs mt-2">Registration, Insurance, Pollution Cert — PDF, JPG, PNG up to 4MB each</p>
                <div id="file-list" class="mt-3 space-y-1 text-xs text-mb-silver text-left hidden"></div>
            </div>
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
document.getElementById('documents').addEventListener('change', function () {
    const list = document.getElementById('file-list');
    list.innerHTML = '';
    if (this.files.length > 0) {
        list.classList.remove('hidden');
        Array.from(this.files).forEach(f => {
            list.innerHTML += `<p class="flex items-center gap-2"><svg class="w-3 h-3 text-mb-accent flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V6.414A2 2 0 0016.414 5L14 2.586A2 2 0 0012.586 2H9z"/></svg>${f.name}</p>`;
        });
    } else {
        list.classList.add('hidden');
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>