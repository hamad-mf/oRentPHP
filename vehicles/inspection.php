<?php
require_once __DIR__ . '/../config/db.php';

// Permission check: require 'add_vehicles' permission
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to access vehicle inspection.');
    redirect('../index.php');
}

$pdo = db();
$pageTitle = 'Vehicle Inspection';

// Define 37 inspection items
$inspectionItems = [
    'Car Number', 'Kilometer', 'Scratches', 'Service Kilometer Checkin',
    'Alignment Kilometer Checkin', 'Tyre Condition', 'Tyre Pressure',
    'Engine Oil', 'Air Filter', 'Coolant', 'Brake Fluid', 'Fuel Filter',
    'Washer Fluid', 'Electric Checking', 'Brake Pads', 'Hand Brake',
    'Head Lights', 'Indicators', 'Seat Belts', 'Wipers', 'Battery Terminal',
    'Battery Water', 'AC', 'AC filter', 'Music System', 'Lights',
    'Stepni Tyre', 'Jacky', 'Interior Cleaning', 'Washing',
    'Car Small Checking', 'Seat Condition and Cleaning', 'Tyre Polishing',
    'Papers Checking', 'Fine Checking', 'Complaints', 'Final Check Up and Note'
];

$errors = [];
$loadedJobCard = null;
$loadedItems = [];
$loadedCustomPoints = [];
$permanentScratches = [];

// Get selected vehicle ID from URL
$selectedVehicleId = (int)($_GET['vehicle_id'] ?? 0);

// Get active tab from URL (default to job-card)
$activeTab = $_GET['tab'] ?? 'job-card';
if (!in_array($activeTab, ['job-card', 'permanent-scratches'])) {
    $activeTab = 'job-card';
}

// Load job card data if vehicle selected
if ($selectedVehicleId > 0) {
    // Get the latest job card for this vehicle
    $cardStmt = $pdo->prepare('SELECT * FROM vehicle_job_cards WHERE vehicle_id = ? ORDER BY inspection_date DESC LIMIT 1');
    $cardStmt->execute([$selectedVehicleId]);
    $loadedJobCard = $cardStmt->fetch();
    
    if ($loadedJobCard) {
        // Load all items for this job card
        $itemsStmt = $pdo->prepare('SELECT * FROM vehicle_job_card_items WHERE job_card_id = ? ORDER BY item_number ASC');
        $itemsStmt->execute([$loadedJobCard['id']]);
        $items = $itemsStmt->fetchAll();
        
        // Index by item_number for easy lookup
        foreach ($items as $item) {
            $loadedItems[$item['item_number']] = $item;
        }
        
        // Load custom points for this job card
        $customStmt = $pdo->prepare('SELECT * FROM vehicle_job_card_custom_points WHERE job_card_id = ? ORDER BY point_number ASC');
        $customStmt->execute([$loadedJobCard['id']]);
        $loadedCustomPoints = $customStmt->fetchAll();
    }
    
    // Load permanent scratches for this vehicle
    $scratchStmt = $pdo->prepare('SELECT * FROM vehicle_permanent_scratches WHERE vehicle_id = ? ORDER BY created_at ASC');
    $scratchStmt->execute([$selectedVehicleId]);
    $permanentScratches = $scratchStmt->fetchAll();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Job Card Save
    if ($action === 'save_job_card') {
        $vehicleId = (int) ($_POST['vehicle_id'] ?? 0);
        $items = $_POST['items'] ?? [];
        
        // Validation
        if ($vehicleId <= 0) {
            $errors['vehicle_id'] = 'Please select a vehicle.';
        } else {
            // Verify vehicle exists
            $vCheck = $pdo->prepare('SELECT id FROM vehicles WHERE id = ?');
            $vCheck->execute([$vehicleId]);
            if (!$vCheck->fetch()) {
                $errors['vehicle_id'] = 'Selected vehicle does not exist.';
            }
        }
        
        // Validate items array structure
        $missingKeys = [];
        for ($i = 1; $i <= 37; $i++) {
            if (!isset($items[$i])) {
                $missingKeys[] = $i;
            }
        }
        
        if (!empty($missingKeys)) {
            $errors['items'] = 'Invalid inspection data. Please refresh and try again.';
            app_log('ERROR', 'Job card validation failed: missing item keys', [
                'missing_keys' => $missingKeys,
                'items_count' => count($items),
                'vehicle_id' => $vehicleId
            ]);
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Insert header record
                $headerStmt = $pdo->prepare(
                    'INSERT INTO vehicle_job_cards (vehicle_id, created_by) 
                     VALUES (?, ?)'
                );
                $headerStmt->execute([
                    $vehicleId,
                    $_SESSION['user']['id'] ?? null
                ]);
                $jobCardId = (int) $pdo->lastInsertId();
                
                // Insert all 37 item records
                $itemStmt = $pdo->prepare(
                    'INSERT INTO vehicle_job_card_items 
                     (job_card_id, item_number, item_name, check_value, note) 
                     VALUES (?, ?, ?, ?, ?)'
                );
                
                foreach ($items as $itemNumber => $itemData) {
                    $itemName = trim($itemData['name'] ?? '');
                    $checkValue = isset($itemData['check_value']) && trim($itemData['check_value']) !== '' 
                        ? trim(substr($itemData['check_value'], 0, 100)) 
                        : null;
                    $note = isset($itemData['note']) && trim($itemData['note']) !== '' 
                        ? trim(substr($itemData['note'], 0, 255)) 
                        : null;
                    
                    $itemStmt->execute([
                        $jobCardId,
                        (int) $itemNumber,
                        $itemName,
                        $checkValue,
                        $note
                    ]);
                }
                
                // Save custom points if any
                $customPoints = $_POST['custom_points'] ?? [];
                if (!empty($customPoints) && is_array($customPoints)) {
                    $customStmt = $pdo->prepare(
                        'INSERT INTO vehicle_job_card_custom_points 
                         (job_card_id, point_number, note) 
                         VALUES (?, ?, ?)'
                    );
                    
                    foreach ($customPoints as $index => $pointData) {
                        $note = trim($pointData['note'] ?? '');
                        if (!empty($note)) {
                            $pointNumber = 38 + (int)$index;
                            $customStmt->execute([
                                $jobCardId,
                                $pointNumber,
                                $note
                            ]);
                        }
                    }
                }
                
                $pdo->commit();
                
                app_log('ACTION', "Vehicle inspection job card created (ID: $jobCardId, Vehicle: $vehicleId)");
                flash('success', 'Vehicle inspection saved successfully.');
                redirect('inspection.php?tab=job-card&vehicle_id=' . $vehicleId);
                
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                app_log('ERROR', 'Job card save failed: ' . $e->getMessage(), [
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'vehicle_id' => $vehicleId
                ]);
                $errors['db'] = 'Could not save inspection. Please try again.';
            }
        }
    }
    
    // Add Permanent Scratch
    if ($action === 'add_scratch') {
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
                    redirect("inspection.php?tab=permanent-scratches&vehicle_id={$vehicleId}");
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
    
    // Delete Permanent Scratch
    if ($action === 'delete_scratch') {
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
            
            redirect("inspection.php?tab=permanent-scratches&vehicle_id={$vehicleId}");
        }
    }
}

// Fetch vehicles for dropdown
$vehicles = $pdo->query("SELECT id, brand, model, license_plate, status 
                         FROM vehicles 
                         WHERE status != 'sold' 
                         ORDER BY brand, model")->fetchAll();

// Resolve selected vehicle name for print header
$selectedVehicleName = '';
if ($selectedVehicleId > 0) {
    foreach ($vehicles as $v) {
        if ($v['id'] === $selectedVehicleId) {
            $selectedVehicleName = $v['brand'] . ' ' . $v['model'] . ' - ' . $v['license_plate'];
            break;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-light text-white mb-2">Vehicle Inspection</h1>
        <p class="text-mb-silver text-sm">Manage vehicle inspection job cards and permanent scratches</p>
    </div>

    <!-- Tab Navigation -->
    <div class="bg-mb-surface rounded-t-xl border-b border-mb-subtle/20">
        <div class="flex gap-2 px-6 pt-4">
            <button 
                id="tab-job-card" 
                onclick="switchTab('job-card')"
                class="tab-button px-6 py-3 rounded-t-lg font-medium transition-all <?= $activeTab === 'job-card' ? 'active' : '' ?>"
            >
                Job Card
            </button>
            <button 
                id="tab-permanent-scratches" 
                onclick="switchTab('permanent-scratches')"
                class="tab-button px-6 py-3 rounded-t-lg font-medium transition-all <?= $activeTab === 'permanent-scratches' ? 'active' : '' ?>"
            >
                Permanent Scratches
            </button>
        </div>
    </div>

    <!-- Tab Content Area -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-b-xl rounded-tr-xl p-6">

        <!-- Job Card Tab Content -->
        <div id="content-job-card" class="tab-content" style="display: <?= $activeTab === 'job-card' ? 'block' : 'none' ?>;">
            <?php if (!empty($errors['db'])): ?>
                <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6">
                    <p class="text-red-400 text-sm"><?= e($errors['db']) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="inspection.php?tab=job-card">
                <input type="hidden" name="action" value="save_job_card">
                
                <!-- Vehicle Selection -->
                <div class="bg-mb-black border border-mb-subtle/20 rounded-xl p-6 mb-6">
                    <label class="block text-sm font-medium text-mb-silver mb-3">
                        Select Vehicle <span class="text-red-400">*</span>
                    </label>
                    <select name="vehicle_id" id="vehicleSelectJobCard" required
                            onchange="syncVehicleSelection(this.value, 'job-card')"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 
                                   text-white focus:outline-none focus:border-mb-accent transition-colors">
                        <option value="">-- Select Vehicle --</option>
                        <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= $selectedVehicleId === $v['id'] ? 'selected' : '' ?>>
                                <?= e($v['brand']) ?> <?= e($v['model']) ?> - <?= e($v['license_plate']) ?>
                                <?php if ($v['status'] !== 'available'): ?>
                                    (<?= ucfirst($v['status']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['vehicle_id'])): ?>
                        <p class="text-red-400 text-sm mt-2"><?= e($errors['vehicle_id']) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 mb-6">
                    <?php if ($selectedVehicleId > 0): ?>
                    <button type="button" onclick="printJobCard()" 
                            class="px-6 py-3 border border-mb-subtle/30 text-mb-silver rounded-lg 
                                   hover:border-white/30 hover:text-white transition-all font-medium flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Print
                    </button>
                    <?php endif; ?>
                    <button type="submit" 
                            class="px-6 py-3 bg-mb-accent text-white rounded-lg 
                                   hover:bg-mb-accent/80 transition-colors font-medium">
                        Save Inspection
                    </button>
                </div>

                <!-- Inspection Checklist -->
                <div id="printArea">
                    <!-- Print-only header (hidden on screen, shown on print) -->
                    <div class="print-header">
                        <h1>ORENTINCARS</h1>
                        <p class="print-subtitle">Vehicle Inspection Job Card</p>
                        <?php if ($selectedVehicleName): ?>
                            <p class="print-vehicle">Vehicle: <?= e($selectedVehicleName) ?></p>
                        <?php endif; ?>
                        <p class="print-date">Date: <?= date('d M Y, h:i A') ?></p>
                        <p class="print-contacts">Mob: 7591955531 | 7591955532</p>
                    </div>

                    <div class="bg-mb-black border border-mb-subtle/20 rounded-xl overflow-hidden mb-6">
                        <div class="px-6 py-4 border-b border-mb-subtle/10 no-print">
                            <h3 class="text-white font-light">Inspection Checklist</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-mb-black/40">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-mb-subtle uppercase tracking-wider w-20">
                                            S No.
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-mb-subtle uppercase tracking-wider">
                                            Content
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-mb-subtle uppercase tracking-wider w-48">
                                            Check Table
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-mb-subtle uppercase tracking-wider">
                                            Note
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-mb-subtle/10">
                                    <?php foreach ($inspectionItems as $index => $itemName): 
                                        $serialNumber = $index + 1;
                                    ?>
                                    <tr class="hover:bg-mb-black/20 transition-colors">
                                        <td class="px-4 py-3 text-sm text-mb-silver">
                                            <?= $serialNumber ?>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-white">
                                            <?= e($itemName) ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text" 
                                                   name="items[<?= $serialNumber ?>][check_value]" 
                                                   maxlength="100"
                                                   placeholder=""
                                                   value="<?= !empty($loadedItems[$serialNumber]) ? e($loadedItems[$serialNumber]['check_value'] ?? '') : '' ?>"
                                                   class="w-full bg-mb-black border border-mb-subtle/20 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors">
                                            <input type="hidden" 
                                                   name="items[<?= $serialNumber ?>][name]" 
                                                   value="<?= e($itemName) ?>">
                                        </td>
                                        <td class="px-4 py-3">
                                            <input type="text" 
                                                   name="items[<?= $serialNumber ?>][note]" 
                                                   maxlength="255"
                                                   placeholder="Optional note..."
                                                   value="<?= !empty($loadedItems[$serialNumber]) ? e($loadedItems[$serialNumber]['note'] ?? '') : '' ?>"
                                                   class="w-full bg-mb-black border border-mb-subtle/20 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <!-- Add Point Button after table (hidden on print) -->
                                <tfoot class="no-print">
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-center bg-mb-black/20">
                                            <button type="button" 
                                                    onclick="addCustomPoint()"
                                                    class="text-sm text-mb-accent hover:text-mb-accent/80 transition-colors font-medium">
                                                + Add Custom Point
                                            </button>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Custom Points Container (inside printArea) -->
                    <div id="customPointsContainer" class="space-y-3 mt-6"></div>
                    
                </div><!-- /printArea -->
            </form>
        </div>

        <!-- Permanent Scratches Tab Content -->
        <div id="content-permanent-scratches" class="tab-content" style="display: <?= $activeTab === 'permanent-scratches' ? 'block' : 'none' ?>;">
            <!-- Vehicle Selection -->
            <div class="bg-mb-black border border-mb-subtle/20 rounded-xl p-6 mb-6">
                <h2 class="text-xl font-light text-white mb-4">Select Vehicle</h2>
                <select name="vehicle_id_scratches" id="vehicleSelectScratches" 
                        onchange="syncVehicleSelection(this.value, 'permanent-scratches')"
                        class="w-full px-4 py-3 bg-mb-black border border-mb-subtle/30 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-mb-accent focus:border-transparent">
                    <option value="">-- Select a vehicle --</option>
                    <?php foreach ($vehicles as $v): ?>
                        <option value="<?= e($v['id']) ?>" <?= $selectedVehicleId === $v['id'] ? 'selected' : '' ?>>
                            <?= e($v['license_plate']) ?> - <?= e($v['brand']) ?> <?= e($v['model']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($selectedVehicleId > 0): ?>
                <?php 
                // Get selected vehicle details
                $selectedVehicle = null;
                foreach ($vehicles as $v) {
                    if ($v['id'] === $selectedVehicleId) {
                        $selectedVehicle = $v;
                        break;
                    }
                }
                ?>
                
                <?php if ($selectedVehicle): ?>
                    <!-- Vehicle Information -->
                    <div class="bg-mb-black rounded-xl p-6 mb-6 border border-mb-subtle/20">
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
                    <div class="bg-mb-black rounded-xl p-6 mb-6 border border-mb-subtle/20">
                        <h2 class="text-xl font-light text-white mb-4">
                            Current Permanent Scratches 
                            <span class="text-mb-silver text-sm">(<?= count($permanentScratches) ?>)</span>
                        </h2>
                        
                        <?php if (empty($permanentScratches)): ?>
                            <p class="text-mb-silver text-sm">No permanent scratches recorded for this vehicle.</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <?php foreach ($permanentScratches as $scratch): ?>
                                    <div class="bg-mb-surface rounded-lg p-4 border border-mb-subtle/20">
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
                                        <form method="POST" action="inspection.php?tab=permanent-scratches" onsubmit="return confirm('Are you sure you want to delete this permanent scratch? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete_scratch">
                                            <input type="hidden" name="scratch_id" value="<?= e($scratch['id']) ?>">
                                            <input type="hidden" name="vehicle_id" value="<?= e($selectedVehicleId) ?>">
                                            <button 
                                                type="submit"
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
                    <div class="bg-mb-black rounded-xl p-6 border border-mb-subtle/20">
                        <h2 class="text-xl font-light text-white mb-4">Add New Permanent Scratch</h2>
                        
                        <?php if (!empty($errors['general'])): ?>
                            <div class="mb-4 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                                <p class="text-red-400 text-sm"><?= e($errors['general']) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="inspection.php?tab=permanent-scratches" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="add_scratch">
                            <input type="hidden" name="vehicle_id" value="<?= e($selectedVehicleId) ?>">
                            
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
                                    class="w-full px-4 py-3 bg-mb-surface border border-mb-subtle/30 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-mb-accent focus:border-transparent"
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
                                    class="w-full px-4 py-3 bg-mb-surface border border-mb-subtle/30 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-mb-accent focus:border-transparent"
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
                                class="w-full px-6 py-3 bg-mb-accent hover:bg-mb-accent/90 text-white font-medium rounded-lg transition-colors"
                            >
                                Add Permanent Scratch
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.tab-button {
    background: transparent;
    color: rgba(255, 255, 255, 0.6);
    border-bottom: 2px solid transparent;
}

.tab-button:hover {
    color: rgba(255, 255, 255, 0.8);
    background: rgba(255, 255, 255, 0.05);
}

.tab-button.active {
    color: #00d4ff; /* mb-accent */
    background: rgba(0, 212, 255, 0.1);
    border-bottom-color: #00d4ff;
}

/* Hide print header on screen */
.print-header { display: none; }

/* Custom point styling */
.custom-point-row {
    background: rgba(0, 212, 255, 0.05);
    border-left: 3px solid #00d4ff;
}

.custom-point-row td {
    padding: 12px 16px;
}

.custom-point-textarea {
    min-height: 60px;
    resize: vertical;
}

/* Print styles */
@media print {
    .no-print { display: none !important; }
    .print-header { display: block !important; }
    
    /* Custom points print styling */
    .custom-point-row {
        background: transparent !important;
        border-left: 2px solid #000 !important;
        page-break-inside: avoid;
    }
    
    .custom-point-textarea {
        border: 1px solid #000 !important;
        background: transparent !important;
        color: #000 !important;
        min-height: 50px !important;
    }
    
    .custom-point-checkbox {
        width: 30px !important;
        height: 30px !important;
        border: 2px solid #000 !important;
    }
}
</style>

<script>
let customPointCounter = 1;

function addCustomPoint() {
    const container = document.getElementById('customPointsContainer');
    
    // Create the custom point row
    const pointRow = document.createElement('div');
    pointRow.className = 'custom-point-row bg-mb-black border border-mb-subtle/20 rounded-lg p-4 mb-3';
    
    const currentIndex = customPointCounter - 1;
    
    pointRow.innerHTML = `
        <div class="flex items-start gap-4">
            <div class="text-mb-accent font-medium" style="min-width: 60px;">
                ${37 + customPointCounter}.
            </div>
            <div class="flex-1">
                <textarea 
                    name="custom_points[${currentIndex}][note]"
                    class="custom-point-textarea w-full bg-mb-surface border border-mb-subtle/20 rounded px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors"
                    placeholder="Enter custom point..."
                    rows="2"
                ></textarea>
            </div>
            <div class="flex flex-col items-center gap-2">
                <div class="custom-point-checkbox w-8 h-8 border-2 border-mb-subtle/30 rounded"></div>
                <button type="button" onclick="removeCustomPoint(this)" class="text-xs text-red-400 hover:text-red-300 transition-colors no-print">
                    Remove
                </button>
            </div>
        </div>
    `;
    
    container.appendChild(pointRow);
    customPointCounter++;
}

function removeCustomPoint(button) {
    const pointRow = button.closest('.custom-point-row');
    pointRow.remove();
    
    // Renumber remaining custom points
    const container = document.getElementById('customPointsContainer');
    const points = container.querySelectorAll('.custom-point-row');
    points.forEach((point, index) => {
        const numberDiv = point.querySelector('div > div:first-child');
        numberDiv.textContent = (37 + index + 1) + '.';
        
        // Update textarea name attribute
        const textarea = point.querySelector('textarea');
        if (textarea) {
            textarea.name = `custom_points[${index}][note]`;
        }
    });
    customPointCounter = points.length + 1;
}

function switchTab(tabName) {
    // Update tab button states
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // Toggle content visibility
    document.getElementById('content-job-card').style.display = 
        tabName === 'job-card' ? 'block' : 'none';
    document.getElementById('content-permanent-scratches').style.display = 
        tabName === 'permanent-scratches' ? 'block' : 'none';
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

function syncVehicleSelection(vehicleId, sourceTab) {
    // Update both dropdowns
    const jobCardSelect = document.getElementById('vehicleSelectJobCard');
    const scratchesSelect = document.getElementById('vehicleSelectScratches');
    
    if (jobCardSelect) jobCardSelect.value = vehicleId;
    if (scratchesSelect) scratchesSelect.value = vehicleId;
    
    // Update URL parameter and reload to fetch vehicle-specific data
    const url = new URL(window.location);
    url.searchParams.set('vehicle_id', vehicleId);
    window.location.href = url.toString();
}

function printJobCard() {
    var printArea = document.getElementById('printArea');
    if (!printArea) return;

    // Build clean HTML for print window
    var printWindow = window.open('', '_blank', 'width=800,height=600');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Job Card - Print</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; color: #000; background: #fff; padding: 15px; }
                .print-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 14px; }
                .print-header h1 { font-size: 22px; font-weight: bold; margin: 0; }
                .print-header .print-subtitle { font-size: 13px; color: #555; margin-top: 2px; }
                .print-header .print-vehicle { font-size: 15px; font-weight: 600; margin-top: 6px; }
                .print-header .print-date { font-size: 11px; color: #777; margin-top: 4px; }
                .print-header .print-contacts { font-size: 11px; color: #777; margin-top: 2px; }
                table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 5px; }
                th { background: #f0f0f0; color: #000; border: 1px solid #bbb; padding: 6px 8px; font-size: 11px; text-transform: uppercase; }
                td { border: 1px solid #bbb; padding: 5px 8px; color: #000; vertical-align: top; }
                tr { page-break-inside: avoid; }
                .custom-section { margin-top: 20px; page-break-inside: avoid; }
                .custom-section h3 { font-size: 14px; margin-bottom: 8px; font-weight: bold; }
                .custom-table { width: 100%; border-collapse: collapse; }
                .custom-table th { background: #e8e8e8; }
                @page { size: A4 portrait; margin: 10mm; }
            </style>
        </head>
        <body>
    `);

    // Clone the print area and clean it up
    var clone = printArea.cloneNode(true);

    // Make the print-header visible in the clone
    var header = clone.querySelector('.print-header');
    if (header) header.style.display = 'block';

    // Remove the "Inspection Checklist" sub-header (no-print)
    var noPrintEls = clone.querySelectorAll('.no-print');
    noPrintEls.forEach(function(el) { el.remove(); });

    // Replace inputs with their values as plain text for clean printing
    var inputs = clone.querySelectorAll('input[type="text"]');
    inputs.forEach(function(input) {
        var span = document.createElement('span');
        span.textContent = input.value || '';
        input.parentNode.replaceChild(span, input);
    });

    // Remove hidden inputs
    var hiddenInputs = clone.querySelectorAll('input[type="hidden"]');
    hiddenInputs.forEach(function(el) { el.remove(); });

    // Remove dark-mode styling classes
    var surface = clone.querySelector('.bg-mb-surface');
    if (surface) {
        surface.style.background = 'transparent';
        surface.style.border = 'none';
    }

    // Build custom points section as separate table
    var customPoints = document.querySelectorAll('#customPointsContainer .custom-point-row');
    var customContainer = clone.querySelector('#customPointsContainer');
    
    if (customPoints.length > 0 && customContainer) {
        var customHTML = '<div class="custom-section"><h3>Additional Custom Points:</h3><table class="custom-table">';
        customHTML += '<thead><tr>';
        customHTML += '<th style="width: 80px;">S NO.</th>';
        customHTML += '<th>CONTENT</th>';
        customHTML += '<th style="width: 100px;">CHECK</th>';
        customHTML += '</tr></thead><tbody>';
        
        customPoints.forEach(function(point, index) {
            var serialNum = 38 + index;
            var textarea = point.querySelector('textarea');
            var noteValue = textarea ? textarea.value : '';
            
            customHTML += '<tr>';
            customHTML += '<td>' + serialNum + '</td>';
            customHTML += '<td>' + noteValue + '</td>';
            customHTML += '<td style="text-align: center;">☐</td>';
            customHTML += '</tr>';
        });
        
        customHTML += '</tbody></table></div>';
        customContainer.innerHTML = customHTML;
    } else if (customContainer) {
        customContainer.remove();
    }

    printWindow.document.write(clone.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();

    // Wait for content to render, then print
    printWindow.onload = function() {
        printWindow.print();
        printWindow.close();
    };
    // Fallback if onload doesn't fire
    setTimeout(function() {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Initialize tab on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'job-card';
    switchTab(activeTab);
    
    // Load saved custom points if any
    <?php if (!empty($loadedCustomPoints)): ?>
        <?php foreach ($loadedCustomPoints as $customPoint): ?>
            addCustomPoint();
            const lastTextarea = document.querySelector('#customPointsContainer .custom-point-row:last-child textarea');
            if (lastTextarea) {
                lastTextarea.value = <?= json_encode($customPoint['note']) ?>;
            }
        <?php endforeach; ?>
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
