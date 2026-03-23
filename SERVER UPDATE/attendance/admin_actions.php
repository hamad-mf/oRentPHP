<?php
/**
 * attendance/admin_actions.php - AJAX handlers for Admin Attendance controls
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

header('Content-Type: application/json');
auth_require_admin();
$pdo = db();

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['ok' => false, 'message' => 'Attendance ID is required.']);
    exit;
}

// Ensure columns exist (Runtime Migration)
try {
    $cols = $pdo->query("SHOW COLUMNS FROM staff_attendance")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admin_note', $cols, true)) {
        $pdo->exec("ALTER TABLE staff_attendance ADD COLUMN admin_note VARCHAR(500) DEFAULT NULL");
    }
    if (!in_array('is_manual_punch', $cols, true)) {
        $pdo->exec("ALTER TABLE staff_attendance ADD COLUMN is_manual_punch TINYINT(1) DEFAULT 0");
    }
} catch (Throwable $e) {
    app_log('ERROR', 'Admin actions schema update failed - ' . $e->getMessage());
}

$ist    = new DateTimeZone('Asia/Kolkata');
$nowDt  = (new DateTime('now', $ist))->format('Y-m-d H:i:s');

// --- FORCE PUNCH OUT ---
if ($action === 'force_punch_out') {
    $stmt = $pdo->prepare("SELECT * FROM staff_attendance WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch();

    if (!$rec) {
        echo json_encode(['ok' => false, 'message' => 'Record not found.']);
        exit;
    }
    if ($rec['punch_out']) {
        echo json_encode(['ok' => false, 'message' => 'Staff is already punched out.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Auto-close any open breaks
        $pdo->prepare("UPDATE attendance_breaks SET break_end = ?, reason = CONCAT(COALESCE(reason,''), ' (Auto-closed by Admin)') WHERE attendance_id = ? AND break_end IS NULL")
            ->execute([$nowDt, $id]);

        // 2. Set punch out
        $pdo->prepare("UPDATE staff_attendance SET punch_out = ?, admin_note = 'Forced punch-out by Admin' WHERE id = ?")
            ->execute([$nowDt, $id]);

        $pdo->commit();
        app_log('ACTION', "Admin #" . current_user()['id'] . " forced punch-out for attendance #$id");
        echo json_encode(['ok' => true, 'message' => 'Staff forced to punch out successfully.']);
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo json_encode(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// --- EDIT ATTENDANCE ---
if ($action === 'edit_attendance') {
    $pin         = trim($_POST['punch_in'] ?? '');
    $pout        = trim($_POST['punch_out'] ?? '');
    $lateReason  = trim($_POST['late_reason'] ?? '');
    $earlyReason = trim($_POST['early_punchout_reason'] ?? '');
    $adminNote   = trim($_POST['admin_note'] ?? '');

    if (!$pin) {
        echo json_encode(['ok' => false, 'message' => 'Punch In time is required.']);
        exit;
    }

    try {
        $sql = "UPDATE staff_attendance SET 
                punch_in = ?, 
                punch_out = ?, 
                late_reason = ?, 
                early_punchout_reason = ?, 
                admin_note = ?, 
                is_manual_punch = 1 
                WHERE id = ?";
        
        $pdo->prepare($sql)->execute([
            $pin ?: null,
            $pout ?: null,
            $lateReason ?: null,
            $earlyReason ?: null,
            $adminNote ?: null,
            $id
        ]);

        app_log('ACTION', "Admin #" . current_user()['id'] . " edited attendance record #$id");
        echo json_encode(['ok' => true, 'message' => 'Attendance record updated successfully.']);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Invalid action.']);
