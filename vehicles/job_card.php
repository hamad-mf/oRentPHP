<?php
require_once __DIR__ . '/../config/db.php';

// Permission check: require 'add_vehicles' permission
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to access vehicle inspection job cards.');
    redirect('../index.php');
}

$pdo = db();

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

// Load existing job card if vehicle is selected via GET
if (isset($_GET['vehicle_id']) && (int)$_GET['vehicle_id'] > 0) {
    $vehicleId = (int)$_GET['vehicle_id'];
    
    // Get the latest job card for this vehicle
    $cardStmt = $pdo->prepare('SELECT * FROM vehicle_job_cards WHERE vehicle_id = ? ORDER BY inspection_date DESC LIMIT 1');
    $cardStmt->execute([$vehicleId]);
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
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    // Validate items array structure - check that all 37 item keys exist
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
            
            $pdo->commit();
            
            app_log('ACTION', "Vehicle inspection job card created (ID: $jobCardId, Vehicle: $vehicleId)");
            flash('success', 'Vehicle inspection saved successfully.');
            redirect('job_card.php?vehicle_id=' . $vehicleId);
            
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

// Fetch vehicles for dropdown
$vehicles = $pdo->query("SELECT id, brand, model, license_plate, status 
                         FROM vehicles 
                         WHERE status != 'sold' 
                         ORDER BY brand, model")->fetchAll();

// Resolve selected vehicle name for print header
$selectedVehicleName = '';
$selectedVehicleId = (int)($_GET['vehicle_id'] ?? 0);
if ($selectedVehicleId > 0) {
    foreach ($vehicles as $v) {
        if ($v['id'] === $selectedVehicleId) {
            $selectedVehicleName = $v['brand'] . ' ' . $v['model'] . ' - ' . $v['license_plate'];
            break;
        }
    }
}

$pageTitle = 'Vehicle Inspection Job Card';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Company Header -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <!-- Logo placeholder -->
                <div class="w-16 h-16 bg-mb-accent/10 rounded-lg flex items-center justify-center">
                    <svg class="w-8 h-8 text-mb-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-light text-white">Vehicle Inspection Job Card</h1>
                    <p class="text-sm text-mb-subtle mt-1">oRent Vehicle Management System</p>
                </div>
            </div>
            <div class="text-right text-sm text-mb-subtle">
                <p>Mob: 7591955531</p>
                <p>Mob: 7591955532</p>
            </div>
        </div>
    </div>

    <?php if (!empty($errors['db'])): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6">
            <p class="text-red-400 text-sm"><?= e($errors['db']) ?></p>
        </div>
    <?php endif; ?>

    <form method="POST" action="job_card.php">
        <!-- Vehicle Selection -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 mb-6">
            <label class="block text-sm font-medium text-mb-silver mb-3">
                Select Vehicle <span class="text-red-400">*</span>
            </label>
            <select name="vehicle_id" id="vehicleSelect" required
                    onchange="if(this.value) window.location.href='job_card.php?vehicle_id='+this.value"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 
                           text-white focus:outline-none focus:border-mb-accent transition-colors">
                <option value="">-- Select Vehicle --</option>
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= (isset($_GET['vehicle_id']) && (int)$_GET['vehicle_id'] === $v['id']) ? 'selected' : '' ?>>
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

        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden mb-6">
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
                </table>
            </div>
        </div>
        </div><!-- /printArea -->

    </form>
</div>

<style>
/* Hide print header on screen */
.print-header { display: none; }
</style>

<script>
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
                td { border: 1px solid #bbb; padding: 5px 8px; color: #000; }
                tr { page-break-inside: avoid; }
                input { border: none; background: transparent; color: #000; font-size: 12px; font-family: Arial, sans-serif; }
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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
