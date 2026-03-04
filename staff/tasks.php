<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();
auth_require_admin();
$perPage = get_per_page($pdo);
$page = max(1, (int) ($_GET['page'] ?? 1));

//  Ensure table 
$pdo->exec("CREATE TABLE IF NOT EXISTS staff_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    assigned_to INT NOT NULL,
    assigned_by INT NOT NULL,
    status ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    completion_note TEXT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB");

$pageAdmin = current_user();
$flash = '';

//  Handle Actions 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    if ($act === 'create') {
        $title  = trim($_POST['title'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $toId   = (int)($_POST['assigned_to'] ?? 0);
        $due    = trim($_POST['due_date'] ?? '') ?: null;
        if ($title && $toId) {
            $pdo->prepare('INSERT INTO staff_tasks (title,description,assigned_to,assigned_by,due_date) VALUES(?,?,?,?,?)')
                ->execute([$title, $desc ?: null, $toId, $pageAdmin['id'], $due]);
            header('Location: tasks.php?ok=1'); exit;
        }
        header('Location: tasks.php'); exit;
    }

    if ($act === 'delete') {
        $tid = (int)($_POST['task_id'] ?? 0);
        $pdo->prepare('DELETE FROM staff_tasks WHERE id=?')->execute([$tid]);
        header('Location: tasks.php'); exit;
    }

    if ($act === 'update_status') {
        $tid = (int)($_POST['task_id'] ?? 0);
        $st  = $_POST['status'] ?? 'pending';
        $pdo->prepare('UPDATE staff_tasks SET status=? WHERE id=?')->execute([$st, $tid]);
        header('Location: tasks.php'); exit;
    }
}

//  Load data 
$staff = $pdo->query("SELECT id,name FROM users WHERE role!='admin' AND is_active=1 ORDER BY name ASC")->fetchAll();
$filterUser = (int)($_GET['user'] ?? 0);
$filterStatus = $_GET['status'] ?? '';
$allowedStatuses = ['', 'pending', 'completed'];
if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = '';
}

$where = '1=1';
$params = [];
if ($filterUser) { $where .= ' AND t.assigned_to=?'; $params[] = $filterUser; }
if ($filterStatus !== '') { $where .= ' AND t.status=?'; $params[] = $filterStatus; }

$countSql = "SELECT COUNT(*) FROM staff_tasks t WHERE $where";
$taskSql = "SELECT t.*, u.name AS staff_name, ab.name AS created_by_name
    FROM staff_tasks t
    JOIN users u ON u.id=t.assigned_to
    JOIN users ab ON ab.id=t.assigned_by
    WHERE $where ORDER BY t.created_at DESC";
$pgResult = paginate_query($pdo, $taskSql, $countSql, $params, $page, $perPage);
$tasks = $pgResult['rows'];
$taskTotal = (int) ($pgResult['total'] ?? 0);

$pageTitle = 'Staff Tasks';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="space-y-6">
  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-4">
    <div>
      <h2 class="text-white text-xl font-light">Staff Tasks</h2>
      <p class="text-mb-subtle text-sm mt-0.5"><?= $taskTotal ?> task<?= $taskTotal!==1?'s':'' ?> found</p>
    </div>
    <button onclick="document.getElementById('create-modal').classList.remove('hidden')"
      class="bg-mb-accent text-white px-5 py-2 rounded-full text-sm hover:bg-mb-accent/80 transition-colors">
      + New Task
    </button>
  </div>

  <!-- Filters -->
  <form method="GET" class="flex flex-wrap items-center gap-3">
    <select name="user" onchange="this.form.submit()" class="bg-mb-surface border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
      <option value="">All Staff</option>
      <?php foreach($staff as $s): ?>
      <option value="<?= $s['id'] ?>" <?= $filterUser==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" onchange="this.form.submit()" class="bg-mb-surface border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-mb-accent">
      <option value="">All Status</option>
      <option value="pending" <?= $filterStatus==='pending'?'selected':'' ?>>Pending</option>
      <option value="completed" <?= $filterStatus==='completed'?'selected':'' ?>>Completed</option>
    </select>
    <?php if($filterUser||$filterStatus): ?>
    <a href="tasks.php" class="text-xs text-mb-subtle hover:text-white">&#x2715; Clear</a>
    <?php endif; ?>
  </form>

  <!-- Tasks List -->
  <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
    <?php if (empty($tasks)): ?>
    <div class="px-6 py-12 text-center text-mb-subtle/50 text-sm">No tasks found. Create one above.</div>
    <?php else: ?>
    <div class="divide-y divide-mb-subtle/10">
      <?php foreach($tasks as $t):
        $isPending = $t['status'] === 'pending';
        $isOverdue = $isPending && $t['due_date'] && $t['due_date'] < date('Y-m-d');
      ?>
      <div class="px-5 py-4 hover:bg-mb-black/20 transition-colors flex items-start gap-4">
        <!-- Status dot -->
        <div class="mt-1 flex-shrink-0">
          <?php if($isPending): ?>
          <div class="w-2.5 h-2.5 rounded-full <?= $isOverdue?'bg-red-500 animate-pulse':'bg-yellow-400' ?>"></div>
          <?php else: ?>
          <div class="w-2.5 h-2.5 rounded-full bg-green-500"></div>
          <?php endif; ?>
        </div>
        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <p class="text-white text-sm font-medium <?= !$isPending?'line-through text-mb-subtle':'' ?>"><?= e($t['title']) ?></p>
              <?php if($t['description']): ?>
              <p class="text-mb-subtle text-xs mt-0.5"><?= e($t['description']) ?></p>
              <?php endif; ?>
              <div class="flex flex-wrap items-center gap-2 mt-1.5 text-[11px] text-mb-subtle">
                <span>&#x1F464; <span class="text-white"><?= e($t['staff_name']) ?></span></span>
                <span>&#x2022;</span>
                <span>By <?= e($t['created_by_name']) ?></span>
                <span>&#x2022;</span>
                <span><?= date('d M Y', strtotime($t['created_at'])) ?></span>
                <?php if($t['due_date']): ?>
                <span>&#x2022;</span>
                <span class="<?= $isOverdue?'text-red-400':'text-mb-subtle' ?>">Due <?= date('d M Y', strtotime($t['due_date'])) ?></span>
                <?php endif; ?>
                <?php if($t['completion_note']): ?>
                <span>&#x2022;</span>
                <span class="text-green-400/70">Note: <?= e($t['completion_note']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <!-- Actions -->
            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="text-[10px] <?= $isPending?($isOverdue?'bg-red-500/15 text-red-400':'bg-yellow-500/15 text-yellow-400'):'bg-green-500/15 text-green-400' ?> px-2 py-0.5 rounded-full capitalize">
                <?= $isOverdue?'Overdue':$t['status'] ?>
              </span>
              <!-- Toggle status -->
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <input type="hidden" name="status" value="<?= $isPending?'completed':'pending' ?>">
                <button type="submit" title="<?= $isPending?'Mark complete':'Mark pending' ?>"
                  class="text-[11px] border border-mb-subtle/20 text-mb-subtle hover:text-white hover:border-white/30 px-2 py-0.5 rounded transition-colors">
                  <?= $isPending ? '&#x2713; Done' : '&#x21BA; Reopen' ?>
                </button>
              </form>
              <!-- Delete -->
              <form method="POST" class="inline" onsubmit="return confirm('Delete this task?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                <button type="submit" class="text-[11px] text-red-400/60 hover:text-red-400 transition-colors px-1" title="Delete">&#x1F5D1;</button>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php
echo render_pagination(
    $pgResult,
    array_filter(
        ['user' => $filterUser ?: null, 'status' => $filterStatus],
        static fn($v) => $v !== null && $v !== ''
    )
);
?>

<!-- Create Task Modal -->
<div id="create-modal" class="hidden fixed inset-0 z-[9999] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="w-full max-w-md bg-mb-surface border border-mb-subtle/20 rounded-xl shadow-2xl p-6 space-y-4">
    <h3 class="text-white font-medium border-l-2 border-mb-accent pl-3 text-lg">New Task</h3>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create">
      <div>
        <label class="text-mb-subtle text-xs uppercase tracking-wider block mb-1.5">Title <span class="text-red-400">*</span></label>
        <input type="text" name="title" required class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent" placeholder="e.g. Clean the vehicles">
      </div>
      <div>
        <label class="text-mb-subtle text-xs uppercase tracking-wider block mb-1.5">Description</label>
        <textarea name="description" rows="2" class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent resize-none" placeholder="Optional details..."></textarea>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="text-mb-subtle text-xs uppercase tracking-wider block mb-1.5">Assign To <span class="text-red-400">*</span></label>
          <select name="assigned_to" required class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
            <option value="">Select staff...</option>
            <?php foreach($staff as $s): ?>
            <option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-mb-subtle text-xs uppercase tracking-wider block mb-1.5">Due Date</label>
          <input type="date" name="due_date" class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent">
        </div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="document.getElementById('create-modal').classList.add('hidden')" class="text-mb-silver text-sm px-4 py-2 hover:text-white">Cancel</button>
        <button type="submit" class="bg-mb-accent text-white px-6 py-2 rounded-full text-sm hover:bg-mb-accent/80">Create Task</button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
