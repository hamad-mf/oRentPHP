<?php
/**
 * attendance/punch.php  – GPS + late reason + breaks + early punch-out reason
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

header('Content-Type: application/json');

auth_check();
$user = current_user();

if (($user['role'] ?? '') === 'admin') {
    echo json_encode(['ok'=>false,'message'=>'Admins do not use the punch system.']);
    exit;
}

$action = $_POST['action'] ?? '';
if (!in_array($action, ['punch_in','punch_out','break_start','break_resume'], true)) {
    echo json_encode(['ok'=>false,'message'=>'Invalid action.']);
    exit;
}

//  Location required 
$lat     = $_POST['lat']     ?? '';
$lng     = $_POST['lng']     ?? '';
$address = trim($_POST['address'] ?? '');
if ($lat === '' || $lng === '') {
    echo json_encode(['ok'=>false,'message'=>'Location is required. Please allow location access and try again.']);
    exit;
}
$lat = round((float)$lat, 7);
$lng = round((float)$lng, 7);
if ($address === '') $address = "$lat, $lng";

//  DB + runtime migrations 
$pdo = db();
settings_ensure_table($pdo);

try {
    $cols = $pdo->query("SHOW COLUMNS FROM staff_attendance")->fetchAll(PDO::FETCH_COLUMN);
    $toAdd = [
        'late_reason'           => "ALTER TABLE staff_attendance ADD COLUMN late_reason TEXT DEFAULT NULL AFTER pin_warning",
        'early_punchout_reason' => "ALTER TABLE staff_attendance ADD COLUMN early_punchout_reason TEXT DEFAULT NULL AFTER pout_warning",
        'punch_in_lat'          => "ALTER TABLE staff_attendance ADD COLUMN punch_in_lat DECIMAL(10,7) DEFAULT NULL",
        'punch_in_lng'          => "ALTER TABLE staff_attendance ADD COLUMN punch_in_lng DECIMAL(10,7) DEFAULT NULL",
        'punch_in_address'      => "ALTER TABLE staff_attendance ADD COLUMN punch_in_address VARCHAR(500) DEFAULT NULL",
        'punch_out_lat'         => "ALTER TABLE staff_attendance ADD COLUMN punch_out_lat DECIMAL(10,7) DEFAULT NULL",
        'punch_out_lng'         => "ALTER TABLE staff_attendance ADD COLUMN punch_out_lng DECIMAL(10,7) DEFAULT NULL",
        'punch_out_address'     => "ALTER TABLE staff_attendance ADD COLUMN punch_out_address VARCHAR(500) DEFAULT NULL",
    ];
    foreach ($toAdd as $col => $sql) {
        if (!in_array($col, $cols, true)) $pdo->exec($sql);
    }
    // Ensure DATETIME not TIME
    foreach (['punch_in','punch_out'] as $col) {
        $info = $pdo->query("SHOW COLUMNS FROM staff_attendance WHERE Field='$col'")->fetch();
        if ($info && stripos((string)$info['Type'], 'datetime') === false && stripos((string)$info['Type'], 'time') === 0) {
            $pdo->exec("ALTER TABLE staff_attendance MODIFY COLUMN $col DATETIME DEFAULT NULL");
        }
    }
} catch (Throwable $e) {
       app_log('ERROR', 'Attendance punch: staff_attendance runtime migration failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'attendance/punch.php',
    ]);
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_breaks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attendance_id INT NOT NULL,
        break_start DATETIME NOT NULL,
        break_end DATETIME DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        start_lat DECIMAL(10,7) DEFAULT NULL,
        start_lng DECIMAL(10,7) DEFAULT NULL,
        start_address VARCHAR(500) DEFAULT NULL,
        end_lat DECIMAL(10,7) DEFAULT NULL,
        end_lng DECIMAL(10,7) DEFAULT NULL,
        end_address VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (attendance_id) REFERENCES staff_attendance(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
} catch (Throwable $e) {
       app_log('ERROR', 'Attendance punch: staff_attendance runtime migration failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'attendance/punch.php',
    ]);
}

//  IST helpers 
$ist         = new DateTimeZone('Asia/Kolkata');
$nowIst      = new DateTime('now', $ist);
$nowMins     = (int)$nowIst->format('H') * 60 + (int)$nowIst->format('i');
$todayIst    = $nowIst->format('Y-m-d');
$nowDt       = $nowIst->format('Y-m-d H:i:s');
$displayTime = $nowIst->format('h:i A');

function parse_hhmm(string $val): int {
    $val = trim($val);
    if ($val === '') return -1;
    $dt = DateTime::createFromFormat('h:i A', strtoupper($val))
       ?: DateTime::createFromFormat('g:i A', strtoupper($val))
       ?: DateTime::createFromFormat('H:i', $val);
    return $dt ? (int)$dt->format('H') * 60 + (int)$dt->format('i') : -1;
}

//  Today record + open break 
$stmt = $pdo->prepare('SELECT * FROM staff_attendance WHERE user_id=? AND date=? LIMIT 1');
$stmt->execute([$user['id'], $todayIst]);
$rec = $stmt->fetch();

$openBreak = null;
if ($rec) {
    $bq = $pdo->prepare('SELECT * FROM attendance_breaks WHERE attendance_id=? AND break_end IS NULL ORDER BY break_start DESC LIMIT 1');
    $bq->execute([$rec['id']]);
    $openBreak = $bq->fetch() ?: null;
}

// state: not_in | punched_in | on_break | punched_out
if (!$rec || !$rec['punch_in'])   $state = 'not_in';
elseif ($rec['punch_out'])        $state = 'punched_out';
elseif ($openBreak)               $state = 'on_break';
else                              $state = 'punched_in';

//  PUNCH IN 
if ($action === 'punch_in') {
    if ($state !== 'not_in') {
        $msgs = ['punched_in'=>'Already punched in.','on_break'=>'You are on a break.','punched_out'=>'Shift complete for today.'];
        echo json_encode(['ok'=>false,'message'=>$msgs[$state] ?? 'Already checked in.','state'=>$state]);
        exit;
    }
    $winStart = parse_hhmm(settings_get($pdo,'att_punchin_start',''));
    $winEnd   = parse_hhmm(settings_get($pdo,'att_punchin_end',''));
    $isLate   = ($winEnd   >= 0 && $nowMins > $winEnd);
    $warning  = $winStart >= 0 && $winEnd >= 0 && ($nowMins < $winStart || $isLate);
    $lateReason = null;
    if ($isLate) {
        $lateReason = trim($_POST['late_reason'] ?? '');
        if ($lateReason === '') {
            echo json_encode(['ok'=>false,'needs_late_reason'=>true,'message'=>'You are late. Please provide a reason.','state'=>$state]);
            exit;
        }
    }
    try {
        $pdo->prepare('INSERT INTO staff_attendance
            (user_id,date,punch_in,pin_warning,late_reason,punch_in_lat,punch_in_lng,punch_in_address)
            VALUES(?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            punch_in=VALUES(punch_in),pin_warning=VALUES(pin_warning),
            late_reason=VALUES(late_reason),punch_in_lat=VALUES(punch_in_lat),
            punch_in_lng=VALUES(punch_in_lng),punch_in_address=VALUES(punch_in_address)')
            ->execute([$user['id'],$todayIst,$nowDt,$warning?1:0,$lateReason,$lat,$lng,$address]);
        $msg = "Punched in at $displayTime" . ($warning ? ($isLate ? '  Late' : '  Early') : '');
        echo json_encode(['ok'=>true,'message'=>$msg,'warning'=>$warning,'state'=>'punched_in']);
    } catch (Throwable $e) {
           app_log('ERROR', 'Attendance punch: punch_in write failed - ' . $e->getMessage(), [
        'user_id' => (int)($user['id'] ?? 0),
        'action' => 'punch_in',
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);
        echo json_encode(['ok'=>false,'message'=>'DB error: '.$e->getMessage()]);
        
    }
    exit;
}

// All other actions need an existing punch-in
if ($state === 'not_in') {
    echo json_encode(['ok'=>false,'message'=>'You have not punched in today.','state'=>'not_in']);
    exit;
}
if ($state === 'punched_out' && $action !== 'break_resume') {
    echo json_encode(['ok'=>false,'message'=>'Shift already completed for today.','state'=>'punched_out']);
    exit;
}

//  BREAK START 
if ($action === 'break_start') {
    if ($state === 'on_break') {
        echo json_encode(['ok'=>false,'message'=>'Already on a break.','state'=>'on_break']);
        exit;
    }
    $breakReason = trim($_POST['break_reason'] ?? '');
    if ($breakReason === '') {
        echo json_encode(['ok'=>false,'message'=>'Please enter a reason for your break.','state'=>$state]);
        exit;
    }
    try {
        $pdo->prepare('INSERT INTO attendance_breaks (attendance_id,break_start,reason,start_lat,start_lng,start_address) VALUES(?,?,?,?,?,?)')
            ->execute([$rec['id'],$nowDt,$breakReason,$lat,$lng,$address]);
        echo json_encode(['ok'=>true,'message'=>"Break started at $displayTime",'state'=>'on_break']);
    } catch (Throwable $e) {
       app_log('ERROR', 'Attendance punch: break_resume write failed - ' . $e->getMessage(), [
        'user_id' => (int)($user['id'] ?? 0),
        'action' => 'break_resume',
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);
        echo json_encode(['ok'=>false,'message'=>'DB error: '.$e->getMessage()]);
    }
    exit;
}

//  BREAK RESUME 
if ($action === 'break_resume') {
    if (!$openBreak) {
        echo json_encode(['ok'=>false,'message'=>'No active break to resume from.','state'=>$state]);
        exit;
    }
    try {
        $pdo->prepare('UPDATE attendance_breaks SET break_end=?,end_lat=?,end_lng=?,end_address=? WHERE id=?')
            ->execute([$nowDt,$lat,$lng,$address,$openBreak['id']]);
        echo json_encode(['ok'=>true,'message'=>"Resumed at $displayTime",'state'=>'punched_in']);
    } catch (Throwable $e) {
         app_log('ERROR', 'Attendance punch: auto-close break failed during punch_out - ' . $e->getMessage(), [
        'user_id' => (int)($user['id'] ?? 0),
        'action' => 'punch_out',
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);
        echo json_encode(['ok'=>false,'message'=>'DB error: '.$e->getMessage()]);
    }
    exit;
}

//  PUNCH OUT 
if ($action === 'punch_out') {
    // Auto-close any open break
    if ($openBreak) {
        try { $pdo->prepare('UPDATE attendance_breaks SET break_end=?,end_lat=?,end_lng=?,end_address=? WHERE id=?')
            ->execute([$nowDt,$lat,$lng,$address,$openBreak['id']]); } catch(Throwable $e) {
                 app_log('ERROR', 'Attendance punch: punch_out write failed - ' . $e->getMessage(), [
        'user_id' => (int)($user['id'] ?? 0),
        'action' => 'punch_out',
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);
            }
    }
    $winStart  = parse_hhmm(settings_get($pdo,'att_punchout_start',''));
    $winEnd    = parse_hhmm(settings_get($pdo,'att_punchout_end',''));
    $isEarly   = ($winStart >= 0 && $nowMins < $winStart);
    $warning   = $winStart >= 0 && $winEnd >= 0 && ($isEarly || $nowMins > $winEnd);
    $earlyReason = null;
    if ($isEarly) {
        $earlyReason = trim($_POST['early_punchout_reason'] ?? '');
        if ($earlyReason === '') {
            echo json_encode(['ok'=>false,'needs_early_reason'=>true,'message'=>'You are punching out early. Please provide a reason.','state'=>$state]);
            exit;
        }
    }
    try {
        $pdo->prepare('UPDATE staff_attendance SET punch_out=?,pout_warning=?,early_punchout_reason=?,
            punch_out_lat=?,punch_out_lng=?,punch_out_address=? WHERE user_id=? AND date=?')
            ->execute([$nowDt,$warning?1:0,$earlyReason,$lat,$lng,$address,$user['id'],$todayIst]);
        $msg = "Punched out at $displayTime";
        if ($openBreak) $msg .= ' (break auto-closed)';
        if ($isEarly)   $msg .= '  Early';
        elseif ($warning) $msg .= '  Outside window';
        echo json_encode(['ok'=>true,'message'=>$msg,'warning'=>$warning,'state'=>'punched_out']);
    } catch (Throwable $e) {
         app_log('ERROR', ' failed - ' . $e->getMessage(), [
        'user_id' => (int)($user['id'] ?? 0),
        'action' => 'punch_out',
        'file' => $e->getFile() . ':' . $e->getLine(),
    ]);
        echo json_encode(['ok'=>false,'message'=>'DB error: '.$e->getMessage()]);
    }
    exit;
}
