<?php
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$challanId  = (int)($_POST['id'] ?? 0);
$vehicleId  = (int)($_POST['vehicle_id'] ?? 0);
$redirectTo = trim($_POST['redirect_to'] ?? '');

if ($challanId <= 0) {
    flash('error', 'Invalid challan request.');
    redirect($redirectTo === 'challans' ? 'challans.php' : 'index.php');
}

if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to delete challans.');
    redirect($redirectTo === 'challans' ? 'challans.php' : "show.php?id=$vehicleId");
}

try {
    // Build DELETE query — when coming from the challans screen vehicle_id may be 0 or omitted
    if ($vehicleId > 0) {
        $stmt = db()->prepare('DELETE FROM vehicle_challans WHERE id = ? AND vehicle_id = ?');
        $stmt->execute([$challanId, $vehicleId]);
    } else {
        $stmt = db()->prepare('DELETE FROM vehicle_challans WHERE id = ?');
        $stmt->execute([$challanId]);
    }

    app_log('ACTION', "Deleted vehicle challan (ID: $challanId)" . ($vehicleId > 0 ? " from vehicle ID: $vehicleId" : ''));
    flash('success', 'Challan deleted successfully.');
} catch (Throwable $e) {
    app_log('ERROR', 'Failed to delete challan - ' . $e->getMessage(), [
        'file'       => $e->getFile() . ':' . $e->getLine(),
        'challan_id' => $challanId,
        'vehicle_id' => $vehicleId,
    ]);
    flash('error', 'Failed to delete challan. Please try again.');
}

if ($redirectTo === 'challans') {
    redirect('challans.php');
}
redirect("show.php?id=$vehicleId");