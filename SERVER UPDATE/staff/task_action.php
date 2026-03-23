<?php
// staff/task_action.php — Staff marks a task complete (AJAX POST)
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
auth_check();
$user = current_user();
if (!$user || ($user['role'] ?? '') === 'admin') {
    echo json_encode(['ok'=>false,'message'=>'Not allowed.']); exit;
}
$act  = $_POST['action'] ?? '';
$tid  = (int)($_POST['task_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
if ($act === 'complete' && $tid > 0) {
    $pdo = db();
    // Verify task belongs to this staff
    $t = $pdo->prepare('SELECT id FROM staff_tasks WHERE id=? AND assigned_to=? AND status="pending"');
    $t->execute([$tid, $user['id']]);
    if (!$t->fetch()) {
        echo json_encode(['ok'=>false,'message'=>'Task not found or already completed.']); exit;
    }
    $pdo->prepare('UPDATE staff_tasks SET status="completed",completion_note=?,completed_at=? WHERE id=?')
        ->execute([$note ?: null, app_now_sql(), $tid]);
    echo json_encode(['ok'=>true,'message'=>'Task marked as complete!']);
} else {
    echo json_encode(['ok'=>false,'message'=>'Invalid request.']);
}
