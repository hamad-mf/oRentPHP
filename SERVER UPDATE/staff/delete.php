<?php
require_once __DIR__ . '/../config/db.php';
auth_require_admin();
$pdo = db();

$id = (int) ($_POST['id'] ?? 0);
if (!$id)
    redirect('index.php');

// Get staff to check name and related user
$stmt = $pdo->prepare("SELECT s.*, u.id as user_id FROM staff s LEFT JOIN users u ON u.staff_id = s.id WHERE s.id = ?");
$stmt->execute([$id]);
$staff = $stmt->fetch();
if (!$staff)
    redirect('index.php');

// Prevent deleting your own staff record
$currentUserId = (int) ($_SESSION['user']['id'] ?? 0);
if ($staff['user_id'] && (int) $staff['user_id'] === $currentUserId) {
    flash('error', 'You cannot delete your own account.');
    redirect('index.php');
}

$pdo->beginTransaction();
try {
    // Delete user (cascades activity_log and permissions)
    if ($staff['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$staff['user_id']]);
    }
    // Delete staff
    $pdo->prepare("DELETE FROM staff WHERE id = ?")->execute([$id]);
    $pdo->commit();
    app_log('ACTION', "Deleted staff: {$staff['name']} (ID: $id)");
flash('success', "Staff member '{$staff['name']}' deleted.");
} catch (Throwable $e) {
    $pdo->rollBack();
    flash('error', 'Delete failed: ' . $e->getMessage());
}

redirect('index.php');
