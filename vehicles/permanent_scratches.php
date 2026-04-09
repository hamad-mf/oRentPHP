<?php
require_once __DIR__ . '/../config/db.php';

// Permission check: require 'add_vehicles' permission or admin role
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to manage permanent scratches.');
    redirect('../index.php');
}

$pdo = db();
$pageTitle = 'Permanent Scratches';

$errors = [];
$selectedVehicle = null;
$permanentScratches = [];

// Handle POST request to delete permanent scratch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_scratch'])) {
    $scratchId = (int)($_POST['scratch_id'] ?? 0);
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    
    if ($scratchId > 0) {
        try {
            // Fetch scratch record to get file path
            $fetchStmt = $pdo->prepare('SELECT file_path FROM vehicle_permanent_scratches WHERE id = ?');
            $fetchStmt->execute([$scratchId]);
            $scratchRecord = $fetchStmt->fetch();
            
            if ($scratchRecord) {
                // Delete database record
                $deleteStmt = $pdo->prepare('DELETE FROM vehicle_permanent_scratches WHERE id = ?');
                $deleteStmt->execute([$scratchId]);
                
                // Delete photo file (graceful degradation if file doesn't exist)
                $filePath = __DIR__ . '/../' . $scratchRecord['file_path'];
                if (file_exists($filePath)) {
                    if (!unlink($filePath)) {
                        // Log error but don't fail the operation
                        error_log("Failed to delete permanent scratch photo: {$filePath}");
                    }
                }
                
                flash('success', 'Permanent scratch deleted successfully.');
            } else {
                flash('error', 'Scratch not found.');
            }
        } catch (Exception $e) {
            flash('error', 'Failed to delete scratch. Please try again.');
            error_log("Error deleting permanent scratch: " . $e->getMessage());
        }
        
        redirect("permanent_scratches.php?vehicle_id={$vehicleId}");
    }
}

// Handle POST request to add new permanent scratch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_scratch'])) {
    $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    // Validation
    if ($vehicleId <= 0) {
        $errors['vehicle_id'] = 'Invalid vehicle selected.';
    }
    
    if (empty($description)) {
        $errors['description'] = 'Description is required.';
    } elseif (strlen($description) > 255) {
        $errors['description'] = 'Description must not exceed 255 characters.';
    }
    
    // Photo validation
    if (empty($_FILES['photo']['name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $errors['photo'] = 'Photo is required.';
    } else {
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowedTypes)) {
            $errors['photo'] = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
        }
        
        // Check file size (5MB max)
        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
            $errors['photo'] = 'File size must not exceed 5MB.';
        }
    }
    
    // If no errors, process the upload
    if (empty($errors)) {
        $dir = __DIR__ . '/../uploads/permanent_scratches/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // Get next slot index for this vehicle
        $slotStmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 AS next_slot FROM vehicle_permanent_scratches WHERE vehicle_id = ?");
        $slotStmt->execute([$vehicleId]);
        $slotIndex = (int)$slotStmt->fetchColumn();
        
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $filename = "permanent_{$vehicleId}_{$slotIndex}_" . time() . ".{$ext}";
        
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $filename)) {
            try {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO vehicle_permanent_scratches (vehicle_id, description, file_path, created_by) VALUES (?, ?, ?, ?)"
                );
                $insertStmt->execute([
                    $vehicleId,
                    $description,
                    'uploads/permanent_scratches/' . $filename,
                    $_SESSION['user']['id'] ?? null
                ]);
                
                flash('success', 'Permanent scratch added successfully.');
                redirect("permanent_scratches.php?vehicle_id={$vehicleId}");
            } catch (Exception $e) {
                // If database insert fails, delete the uploaded file
                unlink($dir . $filename);
                $errors['general'] = 'Failed to save scratch. Please try again.';
            }
        } else {
            $errors['photo'] = 'Failed to upload photo. Please try again.';
        }
    }
}

// Load vehicle if selected via GET
if (isset($_GET['vehicle_id']) && (int)$_GET['vehicle_id'] > 0) {
    $vehicleId = (int)$_GET['vehicle_id'];
    
    // Get vehicle details
    $vStmt = $pdo->prepare('SELECT id, brand, model, license_plate FROM vehicles WHERE id = ?');
    $vStmt->execute([$vehicleId]);
    $selectedVehicle = $vStmt->fetch();
    
    if ($selectedVehicle) {
        // Load permanent scratches for this vehicle
        $scratchStmt = $pdo->prepare('SELECT * FROM vehicle_permanent_scratches WHERE vehicle_id = ? ORDER BY created_at ASC');
        $scratchStmt->execute([$vehicleId]);
        $permanentScratches = $scratchStmt->fetchAll();
    }
}

// Get all vehicles for dropdown, ordered by license_plate
$vehiclesStmt = $pdo->query('SELECT id, brand, model, license_plate FROM vehicles ORDER BY license_plate ASC');
$vehicles = $vehiclesStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="flex-1 overflow-y-auto">
    <div class="min-h-full bg-gradient-to-br from-mb-black via-mb-surface to-mb-black p-4 md:p-8">
        <div class="max-w-5xl mx-auto">
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-light text-white mb-2">Permanent Scratches</h1>
                <p class="text-mb-silver text-sm">Manage permanent vehicle scratches that appear across all reservations</p>
            </div>

            <!-- Vehicle Selection -->
            <div class="bg-mb-surface rounded-xl p-6 mb-6 border border-mb-subtle/20">
                <h2 class="text-xl font-light text-white mb-4">Select Vehicle</h2>
                <form method="GET" action="permanent_scratches.php">
                    <div class="flex gap-4 items-end">
                        <div class="flex-1">
                            <label for="vehicle_id" class="block text-sm font-medium text-mb-silver mb-2">
                                Vehicle
                            </label>
                            <select 
                                name="vehicle_id" 
                                id="vehicle_id" 
                                class="w-full px-4 py-3 bg-mb-black border border-mb-subtle/30 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-mb-accent focus:border-transparent"
                                onchange="this.form.submit()"
                            >
                                <option value="">-- Select a vehicle --</option>
                                <?php foreach ($vehicles as $v): ?>
                                    <option 
                                        value="<?= e($v['id']) ?>"
                                        <?= $selectedVehicle && $selectedVehicle['id'] == $v['id'] ? 'selected' : '' ?>
                                    >
                                        <?= e($v['license_plate']) ?> - <?= e($v['brand']) ?> <?= e($v['model']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($selectedVehicle): ?>
                <!-- Vehicle Information -->
                <div class="bg-mb-surface rounded-xl p-6 mb-6 border border-mb-subtle/20">
                    <h2 class="text-xl font-light text-white mb-4">Vehicle Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-mb-silver mb-1">License Plate</p>
                            <p class="text-white font-medium"><?= e($selectedVehicle['license_plate']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-mb-silver mb-1">Brand</p>
                            <p class="text-white font-medium"><?= e($selectedVehicle['brand']) ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-mb-silver mb-1">Model</p>
                            <p class="text-white font-medium"><?= e($selectedVehicle['model']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Current Permanent Scratches -->
                <div class="bg-mb-surface rounded-xl p-6 mb-6 border border-mb-subtle/20">
                    <h2 class="text-xl font-light text-white mb-4">
                        Current Permanent Scratches 
                        <span class="text-mb-silver text-sm">(<?= count($permanentScratches) ?>)</span>
                    </h2>
                    
                    <?php if (empty($permanentScratches)): ?>
                        <p class="text-mb-silver text-sm">No permanent scratches recorded for this vehicle.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($permanentScratches as $scratch): ?>
                                <div class="bg-mb-black rounded-lg p-4 border border-mb-subtle/20">
                                    <!-- Photo -->
                                    <div class="mb-3">
                                        <img 
                                            src="<?= e('../' . $scratch['file_path']) ?>" 
                                            alt="Scratch photo"
                                            class="w-full h-48 object-cover rounded-lg"
                                        >
                                    </div>
                                    
                                    <!-- Description -->
                                    <p class="text-white text-sm mb-3"><?= e($scratch['description']) ?></p>
                                    
                                    <!-- Metadata -->
                                    <p class="text-mb-silver text-xs mb-3">
                                        Added: <?= date('M d, Y', strtotime($scratch['created_at'])) ?>
                                    </p>
                                    
                                    <!-- Delete Button -->
                                    <form method="POST" action="permanent_scratches.php" onsubmit="return confirm('Are you sure you want to delete this permanent scratch? This action cannot be undone.');">
                                        <input type="hidden" name="scratch_id" value="<?= e($scratch['id']) ?>">
                                        <input type="hidden" name="vehicle_id" value="<?= e($selectedVehicle['id']) ?>">
                                        <button 
                                            type="submit"
                                            name="delete_scratch"
                                            class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm rounded-lg transition-colors"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add New Scratch Form -->
                <div class="bg-mb-surface rounded-xl p-6 border border-mb-subtle/20">
                    <h2 class="text-xl font-light text-white mb-4">Add New Permanent Scratch</h2>
                    
                    <?php if (!empty($errors['general'])): ?>
                        <div class="mb-4 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                            <p class="text-red-400 text-sm"><?= e($errors['general']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="permanent_scratches.php" enctype="multipart/form-data">
                        <input type="hidden" name="vehicle_id" value="<?= e($selectedVehicle['id']) ?>">
                        
                        <!-- Photo Upload -->
                        <div class="mb-4">
                            <label for="photo" class="block text-sm font-medium text-mb-silver mb-2">
                                Photo <span class="text-red-400">*</span>
                            </label>
                            <input 
                                type="file" 
                                name="photo" 
                                id="photo" 
                                accept="image/jpeg,image/jpg,image/png,image/gif"
                                class="w-full px-4 py-3 bg-mb-black border border-mb-subtle/30 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-mb-accent focus:border-transparent"
                                required
                            >
                            <p class="text-xs text-mb-silver mt-1">Allowed: JPG, PNG, GIF. Max size: 5MB</p>
                            <?php if (!empty($errors['photo'])): ?>
                                <p class="text-red-400 text-sm mt-1"><?= e($errors['photo']) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-6">
                            <label for="description" class="block text-sm font-medium text-mb-silver mb-2">
                                Description <span class="text-red-400">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="description" 
                                id="description" 
                                maxlength="255"
                                value="<?= e($_POST['description'] ?? '') ?>"
                                placeholder="e.g., Front bumper dent on left side"
                                class="w-full px-4 py-3 bg-mb-black border border-mb-subtle/30 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-mb-accent focus:border-transparent"
                                required
                            >
                            <p class="text-xs text-mb-silver mt-1">Maximum 255 characters</p>
                            <?php if (!empty($errors['description'])): ?>
                                <p class="text-red-400 text-sm mt-1"><?= e($errors['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            name="add_scratch"
                            class="w-full px-6 py-3 bg-mb-accent hover:bg-mb-accent/90 text-white font-medium rounded-lg transition-colors"
                        >
                            Add Permanent Scratch
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
