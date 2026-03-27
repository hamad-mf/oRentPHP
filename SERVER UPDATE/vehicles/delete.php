<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('add_vehicles')) {
    flash('error', 'You do not have permission to delete vehicles.');
    redirect('index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$v = $pdo->prepare('SELECT * FROM vehicles WHERE id = ?');
$v->execute([$id]);
$vehicle = $v->fetch();
if (!$vehicle) {
    flash('error', 'Vehicle not found.');
    redirect('index.php');
}

// Guard: cannot delete rented vehicles
if ($vehicle['status'] === 'rented') {
    flash('error', "Cannot delete a vehicle that is currently rented ({$vehicle['license_plate']}).");
    redirect('index.php');
}

// Guard: cannot delete sold vehicles
if ($vehicle['status'] === 'sold') {
    flash('error', "Cannot delete a sold vehicle ({$vehicle['license_plate']}).");
    redirect('show.php?id=' . $id);
}

// Delete documents files
$docs = $pdo->prepare('SELECT file_path FROM documents WHERE vehicle_id = ?');
$docs->execute([$id]);
foreach ($docs->fetchAll() as $doc) {
    $path = __DIR__ . '/../' . $doc['file_path'];
    if (file_exists($path))
        @unlink($path);
}

$pdo->prepare('DELETE FROM vehicles WHERE id = ?')->execute([$id]);
app_log('ACTION', "Deleted vehicle: {$vehicle['brand']} {$vehicle['model']} (ID: $id)");
flash('success', "{$vehicle['brand']} {$vehicle['model']} has been removed from the fleet.");
redirect('index.php');
