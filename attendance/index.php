<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();
auth_require_admin();
$perPage = get_per_page($pdo);
$page = max(1, (int) ($_GET['page'] ?? 1));

// Runtime migration
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
} catch (Throwable $e) {
  app_log('ERROR', 'Attendance index: staff_attendance runtime migration failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'attendance/index.php',
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
   app_log('ERROR', 'Attendance index: attendance_breaks table ensure failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'attendance/index.php',
    ]);
}

$ist      = new DateTimeZone('Asia/Kolkata');
$todayIst = (new DateTime('now', $ist))->format('Y-m-d');
$filterDate = trim($_GET['date'] ?? $todayIst);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) $filterDate = $todayIst;

$staff = $pdo->query("SELECT id, name FROM users WHERE role != 'admin' AND is_active = 1 ORDER BY name ASC")->fetchAll();

$attStmt = $pdo->prepare('SELECT * FROM staff_attendance WHERE date = ?');
$attStmt->execute([$filterDate]);
$attMap = [];
foreach ($attStmt->fetchAll() as $row) $attMap[$row['user_id']] = $row;

$breaksMap = [];
$attIds = array_column(array_values($attMap), 'id');
if ($attIds) {
    $in  = implode(',', array_map('intval', $attIds));
    $brs = $pdo->query("SELECT * FROM attendance_breaks WHERE attendance_id IN ($in) ORDER BY break_start ASC")->fetchAll();
    foreach ($brs as $b) $breaksMap[$b['attendance_id']][] = $b;
}

function fmt_t(?string $dt): string {
    if (!$dt) return '—';
    return (new DateTime($dt, new DateTimeZone('Asia/Kolkata')))->format('h:i A');
}
function maps_link(?float $lat, ?float $lng, ?string $addr): string {
    if (!$lat || !$lng || !$addr) return '<span class="text-mb-subtle/40">—</span>';
    $url   = "https://www.google.com/maps?q={$lat},{$lng}";
    $label = mb_strimwidth($addr, 0, 50, '');
    return "<a href=\"{$url}\" target=\"_blank\" class=\"text-mb-accent text-xs hover:underline inline-flex items-center gap-1\">"
         . "<svg class='w-3 h-3 flex-shrink-0' fill='none' stroke='currentColor' viewBox='0 0 24 24'>"
         . "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z'/>"
         . "<path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 11a3 3 0 11-6 0 3 3 0 016 0z'/></svg>"
         . htmlspecialchars($label) . "</a>";
}

$present = $inProgress = $absent = $onBreakCount = 0;
foreach ($staff as $s) {
    $att = $attMap[$s['id']] ?? null;
    if (!$att) { $absent++; continue; }
    if ($att['punch_in'] && $att['punch_out']) { $present++; continue; }
    $inProgress++;
    foreach ($breaksMap[$att['id']] ?? [] as $b) { if (!$b['break_end']) { $onBreakCount++; break; } }
}
$staffTotal = count($staff);
$staffTotalPages = max(1, (int) ceil($staffTotal / $perPage));
$page = min($page, $staffTotalPages);
$staffOffset = ($page - 1) * $perPage;
$staffPageRows = array_slice($staff, $staffOffset, $perPage);
$pgStaff = [
    'rows' => $staffPageRows,
    'total' => $staffTotal,
    'page' => $page,
    'per_page' => $perPage,
    'total_pages' => $staffTotalPages,
];

$pageTitle = 'Attendance';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h2 class="text-white text-xl font-light">Attendance</h2>
      <p class="text-mb-subtle text-sm mt-0.5"><?= $staffTotal ?> active staff member<?= $staffTotal !== 1 ? 's' : '' ?></p>
    </div>
    <a href="../settings/attendance.php" class="text-sm border border-mb-subtle/20 text-mb-silver px-4 py-2 rounded-full hover:border-mb-accent/40 hover:text-white transition-colors">
      &#x2699;&#xFE0F; Punch Window Settings
    </a>
  </div>

  <form method="GET" class="flex items-center gap-3 bg-mb-surface border border-mb-subtle/20 rounded-xl px-4 py-3 w-fit">
    <svg class="w-4 h-4 text-mb-subtle" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
    </svg>
    <input type="date" name="date" value="<?= e($filterDate) ?>" onchange="this.form.submit()"
      class="bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-mb-accent cursor-pointer">
    <?php if ($filterDate !== $todayIst): ?>
    <a href="index.php" class="text-xs text-mb-subtle hover:text-white">&#x2715; Today</a>
    <?php endif; ?>
  </form>

  <div class="flex gap-3 flex-wrap">
    <div class="bg-green-500/10 border border-green-500/20 rounded-xl px-5 py-3 flex items-center gap-2"><span class="text-green-400 font-semibold text-lg"><?= $present ?></span><span class="text-green-400/70 text-sm">Present</span></div>
    <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl px-5 py-3 flex items-center gap-2"><span class="text-yellow-400 font-semibold text-lg"><?= $inProgress ?></span><span class="text-yellow-400/70 text-sm">In Progress</span></div>
    <?php if ($onBreakCount): ?>
    <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl px-5 py-3 flex items-center gap-2"><span class="text-amber-400 font-semibold text-lg"><?= $onBreakCount ?></span><span class="text-amber-400/70 text-sm">On Break</span></div>
    <?php endif; ?>
    <div class="bg-red-500/10 border border-red-500/20 rounded-xl px-5 py-3 flex items-center gap-2"><span class="text-red-400 font-semibold text-lg"><?= $absent ?></span><span class="text-red-400/70 text-sm">Absent</span></div>
  </div>

  <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
    <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center gap-2">
      <span class="text-white text-sm font-medium"><?= date('d M Y', strtotime($filterDate)) ?></span>
      <?php if ($filterDate === $todayIst): ?><span class="text-[10px] bg-mb-accent/20 text-mb-accent px-2 py-0.5 rounded-full">Today</span><?php endif; ?>
    </div>

    <div class="hidden md:grid grid-cols-12 gap-4 px-5 py-2 border-b border-mb-subtle/10 text-[10px] uppercase tracking-wider text-mb-subtle/60">
      <div class="col-span-2">Staff</div>
      <div class="col-span-3">Punch In</div>
      <div class="col-span-3">Punch Out</div>
      <div class="col-span-2 text-right">Duration</div>
      <div class="col-span-2 text-right px-2">Actions</div>
    </div>

    <?php if (empty($staffPageRows)): ?>
    <div class="px-6 py-10 text-center text-mb-subtle/50 text-xs">No active staff found.</div>
    <?php endif; ?>

    <?php foreach ($staffPageRows as $s):
      $att    = $attMap[$s['id']] ?? null;
      $breaks = $att ? ($breaksMap[$att['id']] ?? []) : [];
      $openBrk = null;
      foreach ($breaks as $b) { if (!$b['break_end']) { $openBrk = $b; break; } }
      $pinTime  = $att['punch_in']  ?? null;
      $poutTime = $att['punch_out'] ?? null;
      $pinWarn  = ($att['pin_warning']  ?? 0) == 1;
      $poutWarn = ($att['pout_warning'] ?? 0) == 1;
      $lateReason  = $att['late_reason']           ?? null;
      $earlyReason = $att['early_punchout_reason'] ?? null;

      if (!$att)                             $badge = '<span class="text-[10px] bg-red-500/10 text-red-400/80 px-2 py-0.5 rounded-full">Absent</span>';
      elseif ($pinTime && $poutTime)         $badge = '<span class="text-[10px] bg-green-500/15 text-green-400 px-2 py-0.5 rounded-full">&#10003; Present</span>';
      elseif ($openBrk)                      $badge = '<span class="text-[10px] bg-amber-500/15 text-amber-400 px-2 py-0.5 rounded-full">&#9749; On Break</span>';
      else                                   $badge = '<span class="text-[10px] bg-yellow-500/15 text-yellow-400 px-2 py-0.5 rounded-full">&#x23F0; In Progress</span>';

      $workedStr = '';
      if ($pinTime && $poutTime) {
          $secs = strtotime($poutTime) - strtotime($pinTime);
          foreach ($breaks as $b) { if ($b['break_end']) $secs -= strtotime($b['break_end']) - strtotime($b['break_start']); }
          $secs = max(0, $secs);
          $workedStr = floor($secs/3600).'h '.floor(($secs%3600)/60).'m';
      }
    ?>
    <div class="border-b border-mb-subtle/10 last:border-0">
      <div class="px-5 py-4 hover:bg-mb-black/20 transition-colors">
        <div class="grid grid-cols-12 gap-4 items-start">
          <!-- Name + Status -->
          <div class="col-span-2 flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-mb-accent/10 border border-mb-accent/20 flex items-center justify-center text-[11px] font-semibold text-mb-accent flex-shrink-0">
              <?= strtoupper(substr($s['name'], 0, 2)) ?>
            </div>
            <div>
              <div class="text-white text-sm leading-tight"><?= e($s['name']) ?></div>
              <div class="mt-0.5"><?= $badge ?></div>
            </div>
          </div>

          <!-- Punch In -->
          <div class="col-span-3 space-y-1">
            <?php if ($pinTime): ?>
              <div class="flex items-center gap-1.5">
                <span class="text-white text-sm font-medium"><?= fmt_t($pinTime) ?></span>
                <?php if ($pinWarn): ?><span class="text-[10px] text-yellow-400" title="Outside window">&#x26A0;&#xFE0F;</span><?php endif; ?>
              </div>
              <?= maps_link($att['punch_in_lat'] ?? null, $att['punch_in_lng'] ?? null, $att['punch_in_address'] ?? null) ?>
              <?php if ($lateReason): ?>
              <div class="text-[11px] text-orange-300 bg-orange-500/10 border border-orange-500/20 rounded px-2 py-0.5 mt-1">
                <span class="text-orange-400/70">Late:</span> <?= e($lateReason) ?>
              </div>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-mb-subtle/40 text-sm">—</span>
            <?php endif; ?>
          </div>

          <!-- Punch Out -->
          <div class="col-span-3 space-y-1">
            <?php if ($poutTime): ?>
              <div class="flex items-center gap-1.5">
                <span class="text-white text-sm font-medium"><?= fmt_t($poutTime) ?></span>
                <?php if ($poutWarn): ?><span class="text-[10px] text-yellow-400" title="Outside window">&#x26A0;&#xFE0F;</span><?php endif; ?>
              </div>
              <?= maps_link($att['punch_out_lat'] ?? null, $att['punch_out_lng'] ?? null, $att['punch_out_address'] ?? null) ?>
              <?php if ($earlyReason): ?>
              <div class="text-[11px] text-orange-300 bg-orange-500/10 border border-orange-500/20 rounded px-2 py-0.5 mt-1">
                <span class="text-orange-400/70">Left early:</span> <?= e($earlyReason) ?>
              </div>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-mb-subtle/40 text-sm">—</span>
            <?php endif; ?>
          </div>

          <!-- Duration -->
          <div class="col-span-2 text-right px-2">
            <?php if ($workedStr): ?>
              <span class="text-mb-accent text-sm font-medium"><?= $workedStr ?></span>
            <?php elseif ($openBrk): ?>
              <span class="text-amber-400 text-[11px]">On Break</span>
            <?php elseif ($pinTime): ?>
              <span class="text-mb-subtle text-[11px]">Ongoing</span>
            <?php endif; ?>
          </div>

          <!-- Actions -->
          <div class="col-span-2 text-right flex flex-col items-end gap-1.5 px-2">
            <?php if ($att): ?>
              <div class="flex items-center gap-2">
                <?php if (!$poutTime): ?>
                  <button type="button" onclick="adminForcePunchOut(<?= $att['id'] ?>)" 
                    class="text-[10px] bg-red-500/10 text-red-400 border border-red-500/20 px-2 py-0.5 rounded hover:bg-red-500/20 transition-colors" title="Force Punch Out">
                    Punch Out
                  </button>
                <?php endif; ?>
                <button type="button" onclick="openEditModal(<?= e(json_encode([
                  'id' => $att['id'],
                  'name' => $s['name'],
                  'punch_in' => $att['punch_in'],
                  'punch_out' => $att['punch_out'],
                  'late_reason' => $att['late_reason'],
                  'early_reason' => $att['early_punchout_reason'],
                  'admin_note' => $att['admin_note'] ?? '',
                ])) ?>)" 
                  class="text-[10px] bg-mb-accent/10 text-mb-accent border border-mb-accent/20 px-2 py-0.5 rounded hover:bg-mb-accent/20 transition-colors">
                  Edit
                </button>
              </div>
              <?php if ($att['admin_note'] ?? null): ?>
                <div class="text-[9px] text-mb-subtle/60 truncate max-w-[100px]" title="<?= e($att['admin_note']) ?>">
                  Note: <?= e($att['admin_note']) ?>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!empty($breaks)): ?>
        <div class="mt-3 ml-11 space-y-1">
          <div class="text-[9px] text-mb-subtle uppercase tracking-wider mb-1.5">Break History</div>
          <?php foreach ($breaks as $idx => $b):
            $bDur = '';
            if ($b['break_end']) {
              $bs = strtotime($b['break_end']) - strtotime($b['break_start']);
              $bDur = floor($bs/3600).'h '.floor(($bs%3600)/60).'m';
            }
          ?>
          <div class="bg-mb-black/40 rounded-lg px-3 py-2 text-[11px] flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="text-mb-subtle font-mono">#<?= $idx+1 ?></span>
            <span class="text-white"><?= fmt_t($b['break_start']) ?> &#8594; <?= $b['break_end'] ? fmt_t($b['break_end']) : '<span class="text-amber-400">ongoing</span>' ?></span>
            <?php if ($bDur): ?><span class="text-mb-subtle/60">(<?= $bDur ?>)</span><?php endif; ?>
            <?php if ($b['reason']): ?><span class="text-mb-subtle">Reason: <span class="text-white"><?= e($b['reason']) ?></span></span><?php endif; ?>
            <span class="text-mb-subtle/50">Start:</span><?= maps_link($b['start_lat'] ?? null, $b['start_lng'] ?? null, $b['start_address'] ?? null) ?>
            <?php if ($b['break_end']): ?><span class="text-mb-subtle/50">Resume:</span><?= maps_link($b['end_lat'] ?? null, $b['end_lng'] ?? null, $b['end_address'] ?? null) ?><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Edit Attendance Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-mb-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-200">
        <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between bg-mb-black/20">
            <h3 class="text-white font-medium">Edit Attendance: <span id="editStaffName" class="text-mb-accent"></span></h3>
            <button onclick="closeEditModal()" class="text-mb-subtle hover:text-white transition-colors">&times;</button>
        </div>
        <form id="editForm" class="p-6 space-y-4">
            <input type="hidden" name="id" id="editId">
            <input type="hidden" name="action" value="edit_attendance">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="space-y-1.5">
                    <label class="text-[11px] uppercase tracking-wider text-mb-subtle font-medium">Punch In</label>
                    <input type="datetime-local" name="punch_in" id="editPunchIn" step="1"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors">
                </div>
                <div class="space-y-1.5">
                    <label class="text-[11px] uppercase tracking-wider text-mb-subtle font-medium">Punch Out</label>
                    <input type="datetime-local" name="punch_out" id="editPunchOut" step="1"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-[11px] uppercase tracking-wider text-mb-subtle font-medium">Late Reason</label>
                <textarea name="late_reason" id="editLateReason" rows="2"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors resize-none"></textarea>
            </div>

            <div class="space-y-1.5">
                <label class="text-[11px] uppercase tracking-wider text-mb-subtle font-medium">Early Leave Reason</label>
                <textarea name="early_punchout_reason" id="editEarlyReason" rows="2"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors resize-none"></textarea>
            </div>

            <div class="space-y-1.5">
                <label class="text-[11px] uppercase tracking-wider text-mb-subtle font-medium">Admin Note</label>
                <input type="text" name="admin_note" id="editAdminNote" placeholder="Why are you editing this?"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-mb-subtle/10">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-sm text-mb-subtle hover:text-white transition-colors">Cancel</button>
                <button type="submit" class="bg-mb-accent text-white px-6 py-2 rounded-full text-sm font-medium hover:bg-mb-accent/80 shadow-lg shadow-mb-accent/20 transition-all">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function adminForcePunchOut(id) {
    if(!confirm('Force punch out this staff member? This will also close any open breaks.')) return;
    const body = new URLSearchParams();
    body.append('action', 'force_punch_out');
    body.append('id', id);

    fetch('admin_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok) location.reload();
        else alert(res.message);
    });
}

function openEditModal(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editStaffName').textContent = data.name;
    document.getElementById('editPunchIn').value = data.punch_in ? data.punch_in.replace(' ', 'T') : '';
    document.getElementById('editPunchOut').value = data.punch_out ? data.punch_out.replace(' ', 'T') : '';
    document.getElementById('editLateReason').value = data.late_reason || '';
    document.getElementById('editEarlyReason').value = data.early_reason || '';
    document.getElementById('editAdminNote').value = data.admin_note || '';
    
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    const body = new URLSearchParams(fd);

    fetch('admin_actions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(r => r.json())
    .then(res => {
        if(res.ok) location.reload();
        else alert(res.message);
    });
});
</script>

<?php
echo render_pagination(
    $pgStaff,
    array_filter(
        ['date' => $filterDate],
        static fn($v) => $v !== null && $v !== ''
    )
);
?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
