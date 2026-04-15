<?php
require_once __DIR__ . '/../config/db.php';
if (!auth_has_perm('add_vehicles')) {
    http_response_code(403);
    exit('Forbidden');
}

$docId = (int) ($_POST['doc_id'] ?? 0);
$vehicleId = (int) ($_POST['vehicle_id'] ?? 0);

if ($docId <= 0 || $vehicleId <= 0) {
    http_response_code(400);
    exit('Invalid');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM documents WHERE id = ? AND vehicle_id = ?');
$stmt->execute([$docId, $vehicleId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    exit('Not found');
}

// Delete file from disk
$filePath = __DIR__ . '/../' . $doc['file_path'];
if (file_exists($filePath)) {
    @unlink($filePath);
}

$pdo->prepare('DELETE FROM documents WHERE id = ?')->execute([$docId]);

redirect('edit.php?id=' . $vehicleId);
