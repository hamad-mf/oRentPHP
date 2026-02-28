<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('add_vehicles')) {
    http_response_code(403);
    exit('Forbidden');
}

$pdo = db();
$imgId = (int) ($_POST['img_id'] ?? 0);
$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);

if ($imgId && $vehicleId) {
    $row = $pdo->prepare('SELECT * FROM vehicle_images WHERE id = ? AND vehicle_id = ?');
    $row->execute([$imgId, $vehicleId]);
    $img = $row->fetch();
    if ($img) {
        $pdo->prepare('DELETE FROM vehicle_images WHERE id = ?')->execute([$imgId]);
        $fullPath = __DIR__ . '/../' . $img['file_path'];
        if (file_exists($fullPath))
            @unlink($fullPath);
    }
}

header('Location: edit.php?id=' . $vehicleId);
exit;
