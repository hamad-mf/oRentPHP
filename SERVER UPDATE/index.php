<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/reservation_payment_helpers.php';
require_once __DIR__ . '/includes/ledger_helpers.php';
require_once __DIR__ . '/includes/settings_helpers.php';
require_once __DIR__ . '/includes/gps_helpers.php';
$pdo = db();
reservation_payment_ensure_schema($pdo);
ledger_ensure_schema($pdo);
settings_ensure_table($pdo);
gps_tracking_ensure_schema($pdo);

auth_check();
$_cuMe   = current_user();
$isAdmin = ($_cuMe['role'] ?? '') === 'admin';
$canSeeBothDashboards = false;
if (!$isAdmin && !empty($_cuMe['staff_id'])) {
    $dashCheck = $pdo->prepare("SELECT enable_admin_dashboard FROM staff WHERE id = ?");
    $dashCheck->execute([(int)$_cuMe['staff_id']]);
    $dashRow = $dashCheck->fetch();
    if (!empty($dashRow['enable_admin_dashboard'])) {
        $canSeeBothDashboards = true;
        if (isset($_SESSION['force_staff_dashboard'])) {
            $isAdmin = !$_SESSION['force_staff_dashboard'];
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_dashboard'])) {
    $_SESSION['force_staff_dashboard'] = ($_POST['toggle_dashboard'] === 'staff');
    header('Location: index.php');
    exit;
}
$cuPerms = $_cuMe['permissions'] ?? [];
$istNow  = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$istToday = $istNow->format('Y-m-d');

// Fleet stats (all roles may need these)
$totalCars       = $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$availableCars   = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='available'")->fetchColumn();
$rentedCars      = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='rented'")->fetchColumn();
$maintenanceCars = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='maintenance'")->fetchColumn();
$overdueVehicles = (int) $pdo->query("SELECT COUNT(*)
    FROM reservations r1
    INNER JOIN (
        SELECT vehicle_id, MAX(id) AS max_id
        FROM reservations
        WHERE status='active'
        GROUP BY vehicle_id
    ) latest ON latest.max_id = r1.id
    WHERE r1.end_date IS NOT NULL
      AND r1.end_date < NOW()")->fetchColumn();
$tr = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE status='active' AND DATE(end_date)=?");
$tr->execute([$istToday]);
$todayReturns = (int) $tr->fetchColumn();
$gpsWarnings     = gps_active_warning_count($pdo);


$eq = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE DATE(created_at)=?"); $eq->execute([$istToday]); $enquiries = (int)$eq->fetchColumn();
$cd = $pdo->prepare("SELECT COUNT(*) FROM reservations r JOIN vehicle_inspections vi ON vi.reservation_id=r.id AND vi.type='return' WHERE r.status='completed' AND DATE(vi.created_at)=?"); $cd->execute([$istToday]); $closedDeals = (int)$cd->fetchColumn();
$nc = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE DATE(created_at)=?"); $nc->execute([$istToday]); $newClients = (int)$nc->fetchColumn();
$totalClients = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

$overdueFollowups = 0; $activeLeads = 0; $totalLeads = 0;
try {
    $of = $pdo->prepare("SELECT COUNT(*) FROM lead_followups WHERE scheduled_at < ? AND is_done=0");
    $of->execute([app_now_sql()]);
    $overdueFollowups = (int) $of->fetchColumn();
    $activeLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads WHERE status NOT IN('closed_won','closed_lost')")->fetchColumn();
    $totalLeads = (int)$pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
} catch(Throwable $e) {
     app_log('ERROR', 'Dashboard: lead metrics query failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'index.php',
        'date' => $istToday,
    ]);
}

// Admin-only financial data
if ($isAdmin) {
    $dailyTarget = (float)($pdo->query("SELECT value FROM system_settings WHERE `key`='daily_target' LIMIT 1")->fetchColumn() ?: 0);
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['daily_target'])) {
        $newTarget = max(0,(float)$_POST['daily_target']);
        $pdo->prepare("INSERT INTO system_settings (`key`,`value`) VALUES('daily_target',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$newTarget]);
        app_log('ACTION',"Updated daily target to $newTarget");
        $dailyTarget = $newTarget;
    }
    $todayRevenue = 0.0;
    $todayRevenueResolved = false;
    try {
        $rs=$pdo->prepare("SELECT COALESCE(SUM(CASE WHEN txn_type='income' THEN amount WHEN txn_type='expense' THEN -amount ELSE 0 END),0) FROM ledger_entries WHERE source_type='reservation' AND source_event IN('advance','delivery','delivery_prepaid','return','cancellation','extension') AND voided_at IS NULL AND DATE(posted_at)=?");
        $rs->execute([$istToday]); $todayRevenue=(float)$rs->fetchColumn();
        $todayRevenueResolved = true;
    } catch(Throwable $e){
       app_log('ERROR', 'Dashboard: primary today revenue query failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'index.php',
        'source' => 'ledger_entries',
        'date' => $istToday,
    ]);
    }
    if (!$todayRevenueResolved) {
        try{$ls=$pdo->prepare("SELECT COALESCE(SUM(
            CASE WHEN d.delivery_today=1 THEN r.delivery_paid_amount ELSE 0 END
          + CASE WHEN rt.return_today=1 THEN r.return_paid_amount ELSE 0 END
          + CASE WHEN DATE(r.created_at)=? THEN r.advance_paid ELSE 0 END
          + CASE WHEN DATE(r.created_at)=? THEN r.delivery_charge_prepaid ELSE 0 END
        ),0)
        FROM reservations r
        LEFT JOIN(SELECT reservation_id,1 AS delivery_today FROM vehicle_inspections WHERE type='delivery' AND DATE(created_at)=? GROUP BY reservation_id)d ON d.reservation_id=r.id
        LEFT JOIN(SELECT reservation_id,1 AS return_today FROM vehicle_inspections WHERE type='return' AND DATE(created_at)=? GROUP BY reservation_id)rt ON rt.reservation_id=r.id
        WHERE d.delivery_today=1 OR rt.return_today=1 OR DATE(r.created_at)=?");
        $ls->execute([$istToday,$istToday,$istToday,$istToday,$istToday]);$todayRevenue=(float)$ls->fetchColumn();}catch(Throwable $e){
           app_log('ERROR', 'Dashboard: fallback today revenue query failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'index.php',
        'source' => 'reservations + vehicle_inspections',
        'date' => $istToday,
    ]);
        }
    }
    $accounts=['total'=>0.0,'cash'=>0.0,'ac'=>0.0,'credit'=>0.0];
    try{
        $accounts['ac']=(float)$pdo->query("SELECT COALESCE(SUM(balance),0) FROM bank_accounts WHERE is_active=1")->fetchColumn();
        $ci=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='income' AND voided_at IS NULL")->fetchColumn();
        $ce=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='expense' AND voided_at IS NULL")->fetchColumn();
        $accounts['cash']=$ci-$ce;
        $cri=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='income' AND voided_at IS NULL")->fetchColumn();
        $cre=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='expense' AND voided_at IS NULL")->fetchColumn();
        $accounts['credit']=$cri-$cre;
        $accounts['total']=$accounts['cash']+$accounts['ac']+$accounts['credit'];
    }catch(Throwable $e){
      app_log('ERROR', 'Dashboard: accounts summary query failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'index.php',
    ]);
    }
    // Monthly accounts (current 15th-to-15th billing period)
    $accMonth=['total'=>0.0,'cash'=>0.0,'ac'=>0.0,'credit'=>0.0];
    $accPeriodLabel='';
    try{
        $istD=(int)$istNow->format('d');$istMn=(int)$istNow->format('n');$istYr=(int)$istNow->format('Y');
        if($istD>=15){
            $mPS=sprintf('%04d-%02d-15',$istYr,$istMn);
            $nMn=$istMn===12?1:$istMn+1;$nYr=$istMn===12?$istYr+1:$istYr;
            $mPE=sprintf('%04d-%02d-15',$nYr,$nMn);
        }else{
            $pm=$istMn===1?12:$istMn-1;$py=$istMn===1?$istYr-1:$istYr;
            $mPS=sprintf('%04d-%02d-15',$py,$pm);
            $mPE=sprintf('%04d-%02d-15',$istYr,$istMn);
        }
        $accPeriodLabel=date('d M',strtotime($mPS)).' – '.date('d M',strtotime($mPE));
        // Bank (AC) monthly net = income - expense via payment_mode='account'
        $mbi=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='account' AND txn_type='income' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
        $mbi->execute([$mPS,$mPE]);$mbiV=(float)$mbi->fetchColumn();
        $mbe=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='account' AND txn_type='expense' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
        $mbe->execute([$mPS,$mPE]);$mbeV=(float)$mbe->fetchColumn();
        $accMonth['ac']=$mbiV-$mbeV;
        $mci=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='income' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
        $mci->execute([$mPS,$mPE]);$mciV=(float)$mci->fetchColumn();
        $mce=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='expense' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
        $mce->execute([$mPS,$mPE]);$mceV=(float)$mce->fetchColumn();
        $accMonth['cash']=$mciV-$mceV;
        $mcri=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='income' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
        $mcri->execute([$mPS,$mPE]);$mcriV=(float)$mcri->fetchColumn();
        $mcre=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='credit' AND txn_type='expense' AND voided_at IS NULL AND DATE(posted_at) BETWEEN ? AND ?");
        $mcre->execute([$mPS,$mPE]);$mcreV=(float)$mcre->fetchColumn();
        $accMonth['credit']=$mcriV-$mcreV;
        $accMonth['total']=$accMonth['cash']+$accMonth['ac']+$accMonth['credit'];
    }catch(Throwable $e){
        $accMonth=$accounts;$accPeriodLabel='';
        app_log('ERROR','Dashboard: monthly accounts query failed - '.$e->getMessage(),['file'=>$e->getFile().':'.$e->getLine(),'screen'=>'index.php']);
    }
}

// Staff-only data
$staffAttRec=null; $staffOpenBreak=null; $staffBreakCount=0;
$staffReservations=[]; $staffTasks=[];
$staffAdvanceSchemaReady=false;
$staffAdvanceMonthCount=0; $staffAdvanceMonthTotal=0.0; $staffAdvanceMonthOutstanding=0.0;
$staffAdvancePastCount=0; $staffAdvanceMonthLabel=$istNow->format('F Y');
if (!$isAdmin) {
    // Attendance
    try{
        $sa=$pdo->prepare("SELECT * FROM staff_attendance WHERE user_id=? AND date=? LIMIT 1");
        $sa->execute([$_cuMe['id'],$istToday]); $staffAttRec=$sa->fetch();
        if($staffAttRec){
            $sb=$pdo->prepare("SELECT * FROM attendance_breaks WHERE attendance_id=? ORDER BY break_start ASC");
            $sb->execute([$staffAttRec['id']]); $allBrks=$sb->fetchAll(); $staffBreakCount=count($allBrks);
            foreach($allBrks as $b){if(!$b['break_end']){$staffOpenBreak=$b;break;}}
        }
    }catch(Throwable $e){
         app_log('ERROR', 'Dashboard (staff): attendance widget query failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'index.php',
        'user_id' => (int)($_cuMe['id'] ?? 0),
        'date' => $istToday,
    ]);
    }
    // Reservations
    if(array_intersect(['add_reservations','do_delivery','do_return'],$cuPerms)){
        try{$sr=$pdo->prepare("SELECT r.id,r.status,r.start_date,r.end_date,c.name AS client_name,v.plate_number FROM reservations r LEFT JOIN clients c ON c.id=r.client_id LEFT JOIN vehicles v ON v.id=r.vehicle_id WHERE r.status IN('pending','confirmed','active') ORDER BY r.start_date ASC LIMIT 5");$sr->execute();$staffReservations=$sr->fetchAll();}catch(Throwable $e){
            app_log('ERROR', 'Dashboard (staff): reservations widget query failed - ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'screen' => 'index.php',
                'user_id' => (int)($_cuMe['id'] ?? 0),
            ]);
        }
    }
    // Tasks — ensure table exists first
    try{
        $pdo->exec("CREATE TABLE IF NOT EXISTS staff_tasks (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, assigned_to INT NOT NULL, assigned_by INT NOT NULL, status ENUM('pending','completed') NOT NULL DEFAULT 'pending', completion_note TEXT DEFAULT NULL, due_date DATE DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, completed_at DATETIME DEFAULT NULL, FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB");
        $tq=$pdo->prepare("SELECT t.*,u.name AS assigned_by_name FROM staff_tasks t JOIN users u ON u.id=t.assigned_by WHERE t.assigned_to=? AND t.status='pending' ORDER BY t.due_date ASC, t.created_at DESC");
        $tq->execute([$_cuMe['id']]); $staffTasks=$tq->fetchAll();
    }catch(Throwable $e){
        app_log('ERROR', 'Dashboard (staff): tasks widget query failed - ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'screen' => 'index.php',
            'user_id' => (int)($_cuMe['id'] ?? 0),
        ]);
    }
    // Payroll advance snapshot (current month + past count)
    try {
        $staffAdvanceSchemaReady = (bool) $pdo->query("SHOW TABLES LIKE 'payroll_advances'")->fetchColumn();
        if ($staffAdvanceSchemaReady) {
            $advMonth = (int) $istNow->format('n');
            $advYear = (int) $istNow->format('Y');

            $advNowStmt = $pdo->prepare("SELECT COUNT(*) AS item_count, COALESCE(SUM(amount),0) AS total_amount, COALESCE(SUM(remaining_amount),0) AS total_remaining FROM payroll_advances WHERE user_id = ? AND month = ? AND year = ?");
            $advNowStmt->execute([$_cuMe['id'], $advMonth, $advYear]);
            $advNow = $advNowStmt->fetch() ?: [];
            $staffAdvanceMonthCount = (int) ($advNow['item_count'] ?? 0);
            $staffAdvanceMonthTotal = (float) ($advNow['total_amount'] ?? 0);
            $staffAdvanceMonthOutstanding = (float) ($advNow['total_remaining'] ?? 0);

            $advPastStmt = $pdo->prepare("SELECT COUNT(*) FROM payroll_advances WHERE user_id = ? AND (year < ? OR (year = ? AND month < ?))");
            $advPastStmt->execute([$_cuMe['id'], $advYear, $advYear, $advMonth]);
            $staffAdvancePastCount = (int) $advPastStmt->fetchColumn();
        }
    } catch (Throwable $e) {
        app_log('ERROR', 'Dashboard (staff): payroll advance widget query failed - ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'screen' => 'index.php',
            'user_id' => (int)($_cuMe['id'] ?? 0),
        ]);
    }
    // Payroll incentive snapshot (current month + past count)
    $staffIncentiveSchemaReady=false;
    $staffIncentiveMonthCount=0; $staffIncentiveMonthTotal=0.0;
    $staffIncentivePastCount=0; $staffIncentiveMonthLabel=$istNow->format('F Y');
    try {
        $staffIncentiveSchemaReady = (bool) $pdo->query("SHOW TABLES LIKE 'staff_incentives'")->fetchColumn();
        if ($staffIncentiveSchemaReady) {
            $incMonth = (int) $istNow->format('n');
            $incYear = (int) $istNow->format('Y');

            $incNowStmt = $pdo->prepare("SELECT COUNT(*) AS item_count, COALESCE(SUM(amount),0) AS total_amount FROM staff_incentives WHERE user_id = ? AND month = ? AND year = ?");
            $incNowStmt->execute([$_cuMe['id'], $incMonth, $incYear]);
            $incNow = $incNowStmt->fetch() ?: [];
            $staffIncentiveMonthCount = (int) ($incNow['item_count'] ?? 0);
            $staffIncentiveMonthTotal = (float) ($incNow['total_amount'] ?? 0);

            $incPastStmt = $pdo->prepare("SELECT COUNT(*) FROM staff_incentives WHERE user_id = ? AND (year < ? OR (year = ? AND month < ?))");
            $incPastStmt->execute([$_cuMe['id'], $incYear, $incYear, $incMonth]);
            $staffIncentivePastCount = (int) $incPastStmt->fetchColumn();
        }
    } catch (Throwable $e) {
        app_log('ERROR', 'Dashboard (staff): payroll incentive widget query failed - ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'screen' => 'index.php',
            'user_id' => (int)($_cuMe['id'] ?? 0),
        ]);
    }
}

$pageTitle='Dashboard';
require_once __DIR__ . '/includes/header.php';

function statCard(string $label,$val,string $href='',string $color='text-white',string $sub=''):string{
    $t=$href?"a href=\"$href\"":'div'; $e=$href?'a':'div'; $h=$href?'hover:border-white/20 cursor-pointer':'';
    return "<$t class=\"bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg transition-all $h group\"><p class=\"text-mb-silver text-sm uppercase mb-1\">$label</p><span class=\"text-3xl font-light $color\">$val</span>".($sub?"<p class=\"text-xs text-mb-subtle mt-1\">$sub</p>":"")."</$e>";
}
?>
<div class="space-y-8">

<?php if ($isAdmin): ?>
<!--  ADMIN DASHBOARD  -->

<?php if ($canSeeBothDashboards): ?>
<div class="flex justify-end mb-4">
    <form method="POST" class="flex items-center gap-2 bg-mb-surface border border-mb-subtle/20 rounded-lg px-3 py-2">
        <span class="text-xs text-mb-subtle">View:</span>
        <button type="submit" name="toggle_dashboard" value="admin" class="text-xs px-2 py-1 rounded <?= $isAdmin ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white' ?>">Admin</button>
        <button type="submit" name="toggle_dashboard" value="staff" class="text-xs px-2 py-1 rounded <?= !$isAdmin ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white' ?>">Staff</button>
    </form>
</div>
<?php endif; ?>

    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2">Fleet Status</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <a href="vehicles/index.php" class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-white/20 transition-all group cursor-pointer"><p class="text-mb-silver text-sm uppercase mb-1">Total Cars</p><div class="flex items-end justify-between"><span class="text-4xl font-light text-white"><?= $totalCars ?></span><svg class="w-6 h-6 text-mb-silver/30 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg></div></a>
            <a href="vehicles/index.php?status=available" class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-green-500/50 transition-all group cursor-pointer"><p class="text-mb-silver text-sm uppercase mb-1">Available</p><div class="flex items-end justify-between"><span class="text-4xl font-light text-green-400"><?= $availableCars ?></span><div class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></div></div></a>
            <a href="vehicles/index.php?status=rented" class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-mb-accent/50 transition-all group cursor-pointer"><p class="text-mb-silver text-sm uppercase mb-1">Running / Rented</p><div class="flex items-end justify-between"><span class="text-4xl font-light text-mb-accent"><?= $rentedCars ?></span><svg class="w-6 h-6 text-mb-accent/30 group-hover:text-mb-accent transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div></a>
            <a href="vehicles/index.php?status=maintenance" class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-red-500/50 transition-all group cursor-pointer"><p class="text-mb-silver text-sm uppercase mb-1">Workshop</p><div class="flex items-end justify-between"><span class="text-4xl font-light text-red-400"><?= $maintenanceCars ?></span><svg class="w-6 h-6 text-red-500/30 group-hover:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div></a>
            <a href="vehicles/index.php?overdue=1" class="bg-mb-surface border <?= $overdueVehicles>0?'border-red-500/40 bg-red-500/5':'border-mb-subtle/20' ?> p-5 rounded-lg hover:border-red-500/50 transition-all group cursor-pointer"><p class="text-mb-silver text-sm uppercase mb-1">Overdue Vehicles</p><div class="flex items-end justify-between"><span class="text-4xl font-light <?= $overdueVehicles>0?'text-red-400':'text-white' ?>"><?= $overdueVehicles ?></span><div class="w-10 h-10 rounded-full <?= $overdueVehicles>0?'bg-red-500/10 text-red-400':'bg-mb-subtle/10 text-mb-silver' ?> flex items-center justify-center"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M10.29 3.86l-8 14A1 1 0 003.14 19h16a1 1 0 00.85-1.5l-8-14a1 1 0 00-1.7 0z"/></svg></div></div><?php if($overdueVehicles>0):?><p class="text-xs text-red-400/70 mt-1 animate-pulse">Warning: delayed returns</p><?php else:?><p class="text-xs text-mb-subtle mt-1">No overdue rentals</p><?php endif;?></a>
        </div>
    </section>

    <?php
    // Held Deposits Alert (graceful degradation if column doesn't exist)
    try {
        $overdueHeldDeposits = reservation_get_overdue_held_deposits($pdo);
        $heldDepositCount = count($overdueHeldDeposits);
    } catch (Throwable $e) {
        // Column doesn't exist yet - skip this section
        $heldDepositCount = 0;
        $overdueHeldDeposits = [];
    }
    
    if ($heldDepositCount > 0):
    ?>
    <section>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">
            <div class="flex items-center gap-3 mb-2">
                <span class="text-red-400 text-xs font-semibold uppercase tracking-wider">⚠ Held Deposits Alert</span>
                <span class="bg-red-500/20 text-red-400 text-xs font-bold px-2 py-0.5 rounded-full"><?= $heldDepositCount ?></span>
                <span class="text-red-400/60 text-xs">exceeded alert threshold</span>
            </div>
            <div class="space-y-1.5 max-h-40 overflow-y-auto">
                <?php foreach ($overdueHeldDeposits as $res):
                    $heldStatus = $res['held_status'];
                    $timeUnit = $heldStatus['test_mode'] ? 'hrs' : 'days';
                ?>
                    <a href="reservations/show.php?id=<?= $res['id'] ?>"
                       class="flex items-center justify-between bg-mb-surface/40 border border-red-500/15 rounded px-3 py-1.5 hover:border-red-500/35 transition-colors">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-white text-xs font-medium whitespace-nowrap">Res #<?= $res['id'] ?></span>
                            <span class="text-mb-subtle text-xs truncate"><?= e($res['client_name']) ?> &bull; <?= e($res['brand']) ?> <?= e($res['model']) ?></span>
                            <?php if (!empty($res['deposit_hold_reason'])): ?>
                                <span class="text-mb-subtle/60 text-xs italic truncate hidden sm:inline">— <?= e($res['deposit_hold_reason']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0 ml-3">
                            <span class="text-red-400 text-xs font-medium">$<?= number_format((float) $res['deposit_held'], 2) ?></span>
                            <span class="text-red-400/60 text-xs"><?= $heldStatus['days_held'] ?> <?= $timeUnit ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php
    // EMI Due Alert
    $emiDueAlerts = [];
    try {
        $hasEmiTable = (bool) $pdo->query("SHOW TABLES LIKE 'emi_schedules'")->fetchColumn();
        if ($hasEmiTable) {
            $emiAlertStmt = $pdo->prepare("
                SELECT s.id, s.due_date, s.amount, s.installment_no,
                       i.title AS investment_title, i.lender
                FROM emi_schedules s
                JOIN emi_investments i ON i.id = s.investment_id
                WHERE s.status = 'pending'
                  AND s.due_date <= DATE_ADD(CURDATE(), INTERVAL 2 DAY)
                ORDER BY s.due_date ASC
            ");
            $emiAlertStmt->execute();
            $emiDueAlerts = $emiAlertStmt->fetchAll();
        }
    } catch (Throwable $e) {
        $emiDueAlerts = [];
    }
    if (!empty($emiDueAlerts)):
    ?>
    <section>
        <div class="bg-purple-500/10 border border-purple-500/30 rounded-lg px-4 py-3">
            <div class="flex items-center gap-3 mb-2">
                <span class="text-purple-400 text-xs font-semibold uppercase tracking-wider">💳 EMI Due Alert</span>
                <span class="bg-purple-500/20 text-purple-400 text-xs font-bold px-2 py-0.5 rounded-full"><?= count($emiDueAlerts) ?></span>
                <span class="text-purple-400/60 text-xs">overdue or due within 2 days</span>
            </div>
            <div class="space-y-1.5 max-h-40 overflow-y-auto">
                <?php foreach ($emiDueAlerts as $emi):
                    $daysLeft = (int) floor((strtotime($emi['due_date']) - strtotime($istToday)) / 86400);
                    $isDueToday = $daysLeft === 0;
                    $isOverdue = $daysLeft < 0;
                ?>
                    <a href="investments/index.php"
                       class="flex items-center justify-between bg-mb-surface/40 border <?= $isOverdue ? 'border-red-500/40' : ($isDueToday ? 'border-purple-500/40' : 'border-purple-500/15') ?> rounded px-3 py-1.5 hover:border-purple-500/40 transition-colors">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-white text-xs font-medium truncate"><?= e($emi['investment_title']) ?></span>
                            <?php if ($emi['lender']): ?>
                                <span class="text-mb-subtle text-xs truncate hidden sm:inline">(<?= e($emi['lender']) ?>)</span>
                            <?php endif; ?>
                            <span class="text-mb-subtle text-xs whitespace-nowrap">EMI #<?= (int) $emi['installment_no'] ?> &bull; <?= date('d M', strtotime($emi['due_date'])) ?></span>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0 ml-3">
                            <span class="<?= $isOverdue ? 'text-red-400' : 'text-purple-400' ?> text-xs font-medium">$<?= number_format((float) $emi['amount'], 2) ?></span>
                            <?php if ($isOverdue): ?>
                                <span class="text-red-400 text-xs font-semibold animate-pulse"><?= abs($daysLeft) ?>d overdue</span>
                            <?php elseif ($isDueToday): ?>
                                <span class="text-purple-300 text-xs font-semibold animate-pulse">Today!</span>
                            <?php else: ?>
                                <span class="text-purple-400/60 text-xs"><?= $daysLeft ?>d</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2">Daily Operations</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="reservations/index.php?due_today=1" class="bg-mb-surface border border-mb-subtle/20 p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer"><div><p class="text-mb-silver text-sm uppercase mb-1">Today Returns</p><span class="text-3xl font-light text-white"><?= $todayReturns ?> Vehicles</span></div><div class="w-12 h-12 rounded-full bg-mb-accent/10 flex items-center justify-center text-mb-accent"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div></a>
            <div onclick="toggleNotif(true)" class="bg-mb-surface border border-mb-subtle/20 p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer"><div><p class="text-mb-silver text-sm uppercase mb-1">Notifications</p><span class="text-3xl font-light <?= $_notifCount>0?'text-yellow-400':'text-white' ?>"><?= $_notifCount ?> New</span><p class="text-xs text-mb-subtle mt-1">Click to view details</p></div><div class="w-12 h-12 rounded-full bg-yellow-500/10 flex items-center justify-center text-yellow-500"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></div></div>
            <a href="gps/index.php?status=active&tracking=no" class="bg-mb-surface border <?= $gpsWarnings>0?'border-red-500/40 bg-red-500/5':'border-mb-subtle/20' ?> p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer"><div><p class="text-mb-silver text-sm uppercase mb-1">GPS Warnings</p><span class="text-3xl font-light <?= $gpsWarnings>0?'text-red-400':'text-white' ?>"><?= $gpsWarnings ?> Issue<?= $gpsWarnings===1?'':'s' ?></span><?php if($gpsWarnings>0):?><p class="text-xs text-red-400/70 mt-1 animate-pulse">Active vehicles with GPS inactive</p><?php else:?><p class="text-xs text-mb-subtle mt-1">All active vehicles tracking</p><?php endif;?></div><div class="w-12 h-12 rounded-full <?= $gpsWarnings>0?'bg-red-500/10 text-red-400':'bg-green-500/10 text-green-400' ?> flex items-center justify-center"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 2a7 7 0 00-7 7c0 4.5 7 13 7 13s7-8.5 7-13a7 7 0 00-7-7zm0 10a3 3 0 110-6 3 3 0 010 6z"/></svg></div></a>
        </div>
    </section>

    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2">Business Performance <span class="text-mb-subtle text-sm normal-case tracking-normal">(Today)</span></h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg" x-data="{editing:false}">
                <div class="flex items-center justify-between mb-2"><p class="text-mb-silver text-sm uppercase">Daily Target</p><button @click="editing=!editing" class="text-mb-subtle hover:text-mb-accent transition-colors p-1 rounded"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button></div>
                <form x-show="editing" method="POST" class="flex items-center gap-2 mb-2" x-cloak><span class="text-mb-silver text-sm">$</span><input type="number" name="daily_target" value="<?= (int)$dailyTarget ?>" min="0" step="100" class="w-28 bg-mb-black border border-mb-accent/40 rounded px-2 py-1 text-white text-sm focus:outline-none focus:border-mb-accent"><button type="submit" class="bg-mb-accent text-white px-3 py-1 rounded text-xs font-medium hover:bg-mb-accent/80 transition-colors">Save</button><button type="button" @click="editing=false" class="text-mb-subtle hover:text-white text-xs transition-colors">Cancel</button></form>
                <div x-show="!editing"><div class="flex items-baseline gap-2"><span class="text-2xl font-light text-white">$<?= number_format($dailyTarget) ?></span><span class="text-xs <?= $todayRevenue>=$dailyTarget?'text-green-400':'text-mb-silver' ?>">/ $<?= number_format($todayRevenue) ?> achieved</span></div><div class="w-full bg-mb-black h-1.5 mt-3 rounded-full overflow-hidden"><div class="<?= $todayRevenue>=$dailyTarget?'bg-green-500':'bg-mb-accent' ?> h-full transition-all" style="width:<?= max(0,min(100,$dailyTarget>0?($todayRevenue/$dailyTarget)*100:0)) ?>%"></div></div><?php if($todayRevenue>=$dailyTarget):?><p class="text-green-400 text-xs mt-1.5">&#x1F389; Target reached!</p><?php else:?><p class="mt-2 inline-flex items-center gap-1.5 bg-orange-500/15 border border-orange-500/30 text-orange-400 font-semibold text-sm px-3 py-1 rounded-full">&#x1F525; $<?= number_format($dailyTarget-$todayRevenue) ?> to go</p><?php endif;?></div>
            </div>
            <?= statCard('Enquiries',$enquiries) ?>
            <?= statCard('Closed Deals',$closedDeals) ?>
            <?= statCard('Total Clients',$totalClients,'clients/index.php','text-white',$newClients > 0 ? $newClients . ' new today' : '') ?>
            <?= statCard('Total Leads',$totalLeads,'leads/index.php') ?>
        </div>
    </section>

    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2">CRM <span class="text-mb-subtle text-sm normal-case tracking-normal">Lead Pipeline</span></h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <a href="leads/pipeline.php" class="bg-mb-surface border border-mb-subtle/20 p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer"><div><p class="text-mb-silver text-sm uppercase mb-1">Active Leads</p><span class="text-3xl font-light text-white"><?= $activeLeads ?></span><p class="text-xs text-mb-subtle mt-1">In pipeline now</p></div><div class="w-12 h-12 rounded-full bg-mb-accent/10 flex items-center justify-center text-mb-accent"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg></div></a>
            <a href="leads/pipeline.php" class="bg-mb-surface border <?= $overdueFollowups>0?'border-red-500/40 bg-red-500/5':'border-mb-subtle/20' ?> p-6 rounded-lg flex items-center justify-between hover:bg-mb-black/30 transition-colors cursor-pointer"><div><p class="text-mb-silver text-sm uppercase mb-1">Overdue Follow-ups</p><span class="text-3xl font-light <?= $overdueFollowups>0?'text-red-400':'text-white' ?>"><?= $overdueFollowups ?></span><?php if($overdueFollowups>0):?><p class="text-xs text-red-400/70 mt-1 animate-pulse">&#x26A0; Action required</p><?php else:?><p class="text-xs text-mb-subtle mt-1">All clear</p><?php endif;?></div><div class="w-12 h-12 rounded-full <?= $overdueFollowups>0?'bg-red-500/10 text-red-400':'bg-mb-subtle/10 text-mb-silver' ?> flex items-center justify-center"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div></a>
        </div>
    </section>

    <section>
        <h3 class="text-white text-lg font-light mb-4 uppercase tracking-wider border-l-2 border-mb-accent pl-2 flex items-center flex-wrap gap-3">
            <span>Accounts</span>
            <span class="inline-flex items-center bg-mb-black/60 border border-mb-subtle/20 rounded-lg p-0.5">
                <button id="acc-btn-monthly" onclick="switchAccView('monthly')" class="px-2.5 py-1 rounded-md bg-mb-accent text-white text-xs font-medium normal-case tracking-normal transition-all">Monthly</button>
                <button id="acc-btn-alltime" onclick="switchAccView('alltime')" class="px-2.5 py-1 rounded-md text-mb-subtle hover:text-white text-xs font-medium normal-case tracking-normal transition-all">All-time</button>
            </span>
            <span id="acc-period-lbl" class="text-mb-subtle text-xs font-normal normal-case tracking-normal"><?= e($accPeriodLabel) ?></span>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-mb-surface/50 border border-mb-subtle/20 p-4 rounded text-center">
                <p class="text-xs text-mb-silver uppercase mb-1">Total</p>
                <p class="text-2xl text-white font-light">
                    <span class="acc-m">$<?= number_format($accMonth['total']) ?></span>
                    <span class="acc-a" style="display:none">$<?= number_format($accounts['total']) ?></span>
                </p>
            </div>
            <div class="bg-mb-surface/50 border border-mb-subtle/20 p-4 rounded text-center">
                <p class="text-xs text-mb-silver uppercase mb-1">Cash</p>
                <p class="text-2xl text-green-400 font-light">
                    <span class="acc-m">$<?= number_format($accMonth['cash']) ?></span>
                    <span class="acc-a" style="display:none">$<?= number_format($accounts['cash']) ?></span>
                </p>
            </div>
            <div class="bg-mb-surface/50 border border-mb-subtle/20 p-4 rounded text-center">
                <p class="text-xs text-mb-silver uppercase mb-1">Bank (AC)</p>
                <p class="text-2xl text-blue-400 font-light">
                    <span class="acc-m">$<?= number_format($accMonth['ac']) ?></span>
                    <span class="acc-a" style="display:none">$<?= number_format($accounts['ac']) ?></span>
                </p>
                <p class="text-xs text-mb-subtle mt-1 acc-m">Net this period</p>
                <p class="text-xs text-mb-subtle mt-1 acc-a" style="display:none">Current balance</p>
            </div>
            <div class="bg-mb-surface/50 border border-mb-subtle/20 p-4 rounded text-center">
                <p class="text-xs text-mb-silver uppercase mb-1">Credit</p>
                <p class="text-2xl text-red-400 font-light">
                    <span class="acc-m">$<?= number_format($accMonth['credit']) ?></span>
                    <span class="acc-a" style="display:none">$<?= number_format($accounts['credit']) ?></span>
                </p>
            </div>
        </div>
    </section>
    <script>
    function switchAccView(v){
        var isM=v==='monthly';
        document.querySelectorAll('.acc-m').forEach(function(el){el.style.display=isM?'':'none';});
        document.querySelectorAll('.acc-a').forEach(function(el){el.style.display=isM?'none':'';});
        document.getElementById('acc-btn-monthly').className='px-2.5 py-1 rounded-md text-xs font-medium normal-case tracking-normal transition-all '+(isM?'bg-mb-accent text-white':'text-mb-subtle hover:text-white');
        document.getElementById('acc-btn-alltime').className='px-2.5 py-1 rounded-md text-xs font-medium normal-case tracking-normal transition-all '+(isM?'text-mb-subtle hover:text-white':'bg-mb-accent text-white');
        var lbl=document.getElementById('acc-period-lbl');
        if(lbl) lbl.style.display=isM?'':'none';
    }
    </script>

    <section class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 text-center">
        <?php foreach([['Investments','investments/index.php'],['GPS Tracking','gps/index.php'],['Reservations','reservations/index.php'],['Accounts','accounts/index.php'],['Clients','clients/index.php'],['Staff','staff/index.php']] as [$n,$h]):?>
        <a href="<?= $h ?>" class="bg-mb-surface border border-mb-subtle/20 p-4 rounded-lg hover:bg-mb-black hover:border-mb-accent/30 transition-all group duration-300 transform hover:-translate-y-1"><p class="text-mb-silver group-hover:text-white transition-colors text-sm uppercase tracking-wide"><?= $n ?></p></a>
        <?php endforeach;?>
    </section>

<?php else: ?>
<!--  STAFF DASHBOARD  -->

<?php if ($canSeeBothDashboards): ?>
<div class="flex justify-end mb-4">
    <form method="POST" class="flex items-center gap-2 bg-mb-surface border border-mb-subtle/20 rounded-lg px-3 py-2">
        <span class="text-xs text-mb-subtle">View:</span>
        <button type="submit" name="toggle_dashboard" value="admin" class="text-xs px-2 py-1 rounded <?= $isAdmin ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white' ?>">Admin</button>
        <button type="submit" name="toggle_dashboard" value="staff" class="text-xs px-2 py-1 rounded <?= !$isAdmin ? 'bg-mb-accent text-white' : 'text-mb-subtle hover:text-white' ?>">Staff</button>
    </form>
</div>
<?php endif; ?>

    <div class="flex items-center justify-between">
        <div>
            <p class="text-mb-subtle text-sm"><?= $istNow->format('l, d M Y') ?></p>
        </div>
    </div>

    <!-- Attendance Card -->
    <section>
        <h3 class="text-white text-base font-light mb-3 uppercase tracking-wider border-l-2 border-mb-accent pl-2">My Attendance Today</h3>
        <?php
        $pinTime=$staffAttRec['punch_in']??null; $poutTime=$staffAttRec['punch_out']??null;
        $pinFmt=$pinTime?(new DateTime($pinTime,new DateTimeZone('Asia/Kolkata')))->format('h:i A'):null;
        $poutFmt=$poutTime?(new DateTime($poutTime,new DateTimeZone('Asia/Kolkata')))->format('h:i A'):null;
        $workedStr='';
        if($pinTime&&$poutTime){
            $secs=strtotime($poutTime)-strtotime($pinTime);
            try{$bq=$pdo->prepare("SELECT break_start,break_end FROM attendance_breaks WHERE attendance_id=? AND break_end IS NOT NULL");$bq->execute([$staffAttRec['id']]);foreach($bq->fetchAll() as $b)$secs-=strtotime($b['break_end'])-strtotime($b['break_start']);}catch(Throwable $e){
                app_log('ERROR', 'Dashboard (staff): break duration calculation query failed - ' . $e->getMessage(), [
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'screen' => 'index.php',
                    'attendance_id' => (int)($staffAttRec['id'] ?? 0),
                ]);
            }
            $secs=max(0,$secs);$workedStr=floor($secs/3600).'h '.floor(($secs%3600)/60).'m';
        }
        ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-mb-surface border <?= !$pinTime?'border-mb-subtle/20':($poutTime?'border-green-500/30':($staffOpenBreak?'border-amber-500/30':'border-mb-accent/30')) ?> p-5 rounded-lg">
                <p class="text-mb-subtle text-xs uppercase tracking-wider mb-3">Status</p>
                <?php if(!$pinTime):?><div class="flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-red-500"></div><span class="text-white text-lg font-light">Not Punched In</span></div><p class="text-mb-subtle text-xs mt-2">Use the header button to punch in</p>
                <?php elseif($staffOpenBreak):?><div class="flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div><span class="text-amber-300 text-lg font-light">On Break</span></div><p class="text-mb-subtle text-xs mt-2">Since <?= (new DateTime($staffOpenBreak['break_start'],new DateTimeZone('Asia/Kolkata')))->format('h:i A') ?></p>
                <?php elseif($poutTime):?><div class="flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-green-500"></div><span class="text-green-400 text-lg font-light">Shift Complete</span></div><p class="text-mb-subtle text-xs mt-2">Net work: <span class="text-white"><?= $workedStr?:'—' ?></span></p>
                <?php else:?><div class="flex items-center gap-2"><div class="w-2 h-2 rounded-full bg-mb-accent animate-pulse"></div><span class="text-mb-accent text-lg font-light">Working</span></div><p class="text-mb-subtle text-xs mt-2">In since <?= $pinFmt ?></p>
                <?php endif;?>
            </div>
            <div class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg">
                <p class="text-mb-subtle text-xs uppercase tracking-wider mb-3">Punch In</p>
                <?php if($pinFmt):?><span class="text-white text-2xl font-light"><?= $pinFmt ?></span><?php if($staffAttRec['late_reason']??null):?><p class="text-orange-300 text-xs mt-2">Late: <?= e($staffAttRec['late_reason']) ?></p><?php endif;?>
                <?php else:?><span class="text-mb-subtle/40 text-2xl font-light">—</span><?php endif;?>
            </div>
            <div class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg">
                <p class="text-mb-subtle text-xs uppercase tracking-wider mb-3">Punch Out <?php if($staffBreakCount):?><span class="ml-2 text-[10px] bg-mb-black px-1.5 py-0.5 rounded-full text-mb-subtle"><?= $staffBreakCount ?> break<?= $staffBreakCount>1?'s':'' ?></span><?php endif;?></p>
                <?php if($poutFmt):?><span class="text-white text-2xl font-light"><?= $poutFmt ?></span>
                <?php elseif($pinTime):?><span class="text-mb-subtle/40 text-sm">Not yet</span>
                <?php else:?><span class="text-mb-subtle/40 text-2xl font-light">—</span><?php endif;?>
            </div>
        </div>
    </section>

    <!-- Payroll Advance -->
    <section>
        <h3 class="text-white text-base font-light mb-3 uppercase tracking-wider border-l-2 border-mb-accent pl-2">My Payroll Advance</h3>
        <div class="bg-mb-surface border <?= $staffAdvanceMonthCount>0?'border-orange-500/30':'border-mb-subtle/20' ?> rounded-xl p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2"><?= e($staffAdvanceMonthLabel) ?></p>
                    <?php if(!$staffAdvanceSchemaReady):?>
                        <p class="text-mb-subtle text-sm">Advance tracking is not enabled yet.</p>
                    <?php elseif($staffAdvanceMonthCount>0):?>
                        <p class="text-white text-2xl font-light">$<?= number_format($staffAdvanceMonthTotal,2) ?></p>
                        <p class="text-mb-subtle text-xs mt-1"><?= $staffAdvanceMonthCount ?> advance<?= $staffAdvanceMonthCount>1?'s':'' ?> this month</p>
                    <?php else:?>
                        <p class="text-white text-lg font-light">No advance this month</p>
                        <p class="text-mb-subtle text-xs mt-1">You can still check previous records.</p>
                    <?php endif;?>
                </div>
                <?php if($staffAdvanceSchemaReady):?>
                <a href="staff/advance_history.php" class="inline-flex items-center gap-2 bg-mb-black border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-mb-accent/40 transition-colors px-4 py-2 rounded-lg text-xs uppercase tracking-wider">
                    View History
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7"/></svg>
                </a>
                <?php endif;?>
            </div>
            <?php if($staffAdvanceSchemaReady && $staffAdvanceMonthCount>0):?>
                <div class="mt-4 pt-3 border-t border-mb-subtle/15 text-xs text-mb-subtle flex items-center justify-between flex-wrap gap-2">
                    <span>Outstanding</span>
                    <span class="<?= $staffAdvanceMonthOutstanding>0?'text-orange-300':'text-green-400' ?>">
                        $<?= number_format($staffAdvanceMonthOutstanding,2) ?>
                    </span>
                </div>
            <?php elseif($staffAdvanceSchemaReady && $staffAdvancePastCount>0):?>
                <p class="text-xs text-mb-subtle mt-4 pt-3 border-t border-mb-subtle/15">Past advances available: <?= $staffAdvancePastCount ?></p>
            <?php endif;?>
        </div>
    </section>

    <!-- Payroll Incentive -->
    <section>
        <h3 class="text-white text-base font-light mb-3 uppercase tracking-wider border-l-2 border-green-400 pl-2">My Payroll Incentive</h3>
        <div class="bg-mb-surface border <?= $staffIncentiveMonthCount>0?'border-green-500/30':'border-mb-subtle/20' ?> rounded-xl p-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <p class="text-mb-subtle text-xs uppercase tracking-wider mb-2"><?= e($staffIncentiveMonthLabel) ?></p>
                    <?php if(!$staffIncentiveSchemaReady):?>
                        <p class="text-mb-subtle text-sm">Incentive tracking is not enabled yet.</p>
                    <?php elseif($staffIncentiveMonthCount>0):?>
                        <p class="text-green-400 text-2xl font-light">$<?= number_format($staffIncentiveMonthTotal,2) ?></p>
                        <p class="text-mb-subtle text-xs mt-1"><?= $staffIncentiveMonthCount ?> incentive<?= $staffIncentiveMonthCount>1?'s':'' ?> this month</p>
                    <?php else:?>
                        <p class="text-white text-lg font-light">No incentive this month</p>
                        <p class="text-mb-subtle text-xs mt-1">You can still check previous records.</p>
                    <?php endif;?>
                </div>
                <?php if($staffIncentiveSchemaReady):?>
                <a href="staff/incentive_history.php" class="inline-flex items-center gap-2 bg-mb-black border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-green-400/40 transition-colors px-4 py-2 rounded-lg text-xs uppercase tracking-wider">
                    View History
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5l7 7-7 7"/></svg>
                </a>
                <?php endif;?>
            </div>
            <?php if($staffIncentiveSchemaReady && $staffIncentivePastCount>0):?>
                <p class="text-xs text-mb-subtle mt-4 pt-3 border-t border-mb-subtle/15">Past incentives available: <?= $staffIncentivePastCount ?></p>
            <?php endif;?>
        </div>
    </section>

    <!-- Assigned Tasks -->
    <?php if (!empty($staffTasks)): ?>
    <section id="tasks-section">
        <h3 class="text-white text-base font-light mb-3 uppercase tracking-wider border-l-2 border-red-400 pl-2">
            &#x1F4CB; My Tasks <span id="tasks-pending-badge" class="text-red-400 text-sm normal-case tracking-normal ml-1"><?= count($staffTasks) ?> pending</span>
        </h3>
        <div class="bg-mb-surface border border-red-500/30 rounded-xl overflow-hidden shadow-lg shadow-red-500/5">
            <?php foreach($staffTasks as $task):
                $isOvd = $task['due_date'] && $task['due_date'] < date('Y-m-d');
            ?>
            <div class="px-5 py-4 border-b border-mb-subtle/10 last:border-0 hover:bg-mb-black/20 transition-colors staff-task-row" id="task-<?= $task['id'] ?>">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <div class="mt-1.5 flex-shrink-0 w-2 h-2 rounded-full <?= $isOvd?'bg-red-500 animate-pulse':'bg-yellow-400' ?>"></div>
                        <div>
                            <p class="text-white text-sm font-medium"><?= e($task['title']) ?></p>
                            <?php if($task['description']):?><p class="text-mb-subtle text-xs mt-0.5"><?= e($task['description']) ?></p><?php endif;?>
                            <div class="flex flex-wrap gap-3 mt-1 text-[11px] text-mb-subtle">
                                <span>From: <span class="text-white"><?= e($task['assigned_by_name']) ?></span></span>
                                <?php if($task['due_date']):?><span class="<?= $isOvd?'text-red-400 font-medium':'' ?>">Due: <?= date('d M Y',strtotime($task['due_date'])) ?><?= $isOvd?' &#x26A0;':'' ?></span><?php endif;?>
                            </div>
                        </div>
                    </div>
                    <button onclick="openTaskComplete(<?= $task['id'] ?>)" class="flex-shrink-0 text-[11px] bg-green-500/15 text-green-400 border border-green-500/30 px-3 py-1.5 rounded-full hover:bg-green-500/25 transition-colors font-medium whitespace-nowrap">
                        &#x2713; Mark Done
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Work Overview -->
    <?php
    $showV=in_array('add_vehicles',$cuPerms,true);
    $showR=(bool)array_intersect(['add_reservations','do_delivery','do_return'],$cuPerms);
    $showC=in_array('manage_clients',$cuPerms,true);
    $showL=in_array('add_leads',$cuPerms,true);
    if($showV||$showR||$showC||$showL):?>
    <section>
        <h3 class="text-white text-base font-light mb-3 uppercase tracking-wider border-l-2 border-mb-accent pl-2">My Work Overview</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <?php if($showV):?><a href="vehicles/index.php" class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-mb-accent/30 transition-all"><p class="text-mb-subtle text-xs uppercase mb-2">Vehicles</p><p class="text-3xl font-light text-white"><?= $availableCars ?></p><p class="text-xs text-mb-subtle mt-1">available now</p></a><?php endif;?>
            <?php if($showR):?><a href="reservations/index.php?due_today=1" class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-mb-accent/30 transition-all"><p class="text-mb-subtle text-xs uppercase mb-2">Returns Today</p><p class="text-3xl font-light text-white"><?= $todayReturns ?></p><p class="text-xs text-mb-subtle mt-1">vehicles due back</p></a><a href="reservations/index.php" class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-mb-accent/30 transition-all"><p class="text-mb-subtle text-xs uppercase mb-2">Enquiries</p><p class="text-3xl font-light text-white"><?= $enquiries ?></p><p class="text-xs text-mb-subtle mt-1">today</p></a><?php endif;?>
            <?php if($showC):?><a href="clients/index.php" class="bg-mb-surface border border-mb-subtle/20 p-5 rounded-lg hover:border-mb-accent/30 transition-all"><p class="text-mb-subtle text-xs uppercase mb-2">Clients</p><p class="text-3xl font-light text-white"><?= $newClients ?></p><p class="text-xs text-mb-subtle mt-1">today</p></a><?php endif;?>
            <?php if($showL):?><a href="leads/pipeline.php" class="bg-mb-surface border <?= $overdueFollowups>0?'border-red-500/30 bg-red-500/5':'border-mb-subtle/20' ?> p-5 rounded-lg hover:border-mb-accent/30 transition-all"><p class="text-mb-subtle text-xs uppercase mb-2">Overdue Follow-ups</p><p class="text-3xl font-light <?= $overdueFollowups>0?'text-red-400':'text-white' ?>"><?= $overdueFollowups ?></p><p class="text-xs text-mb-subtle mt-1"><?= $overdueFollowups>0?'Action needed':'All clear' ?></p></a><?php endif;?>
        </div>
    </section>
    <?php endif;?>

    <!-- Active Reservations -->
    <?php if(!empty($staffReservations)):?>
    <section>
        <h3 class="text-white text-base font-light mb-3 uppercase tracking-wider border-l-2 border-mb-accent pl-2">Active Reservations</h3>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <?php foreach($staffReservations as $r):
                $badge=match($r['status']){'active'=>'bg-green-500/15 text-green-400','confirmed'=>'bg-blue-500/15 text-blue-400',default=>'bg-mb-subtle/15 text-mb-subtle'};
            ?><a href="reservations/show.php?id=<?= $r['id'] ?>" class="flex items-center justify-between px-5 py-3 border-b border-mb-subtle/10 last:border-0 hover:bg-mb-black/20 transition-colors"><div class="flex items-center gap-3"><span class="text-[10px] <?= $badge ?> px-2 py-0.5 rounded-full capitalize"><?= $r['status'] ?></span><span class="text-white text-sm"><?= e($r['client_name']??'—') ?></span><span class="text-mb-subtle text-xs"><?= e($r['plate_number']??'') ?></span></div><span class="text-mb-subtle text-xs"><?= $r['end_date']?date('d M',strtotime($r['end_date'])):'' ?></span></a><?php endforeach;?>
        </div>
    </section>
    <?php endif;?>

    <!-- Quick Links -->
    <section>
        <h3 class="text-white text-base font-light mb-3 uppercase tracking-wider border-l-2 border-mb-accent pl-2">Quick Links</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
            <?php
            $sl=[];
            if($showV)  $sl[]=["Vehicles","vehicles/index.php"];
            if($showR)  $sl[]=["Reservations","reservations/index.php"];
            if($showR)  $sl[]=["GPS Tracking","gps/index.php"];
            if($showC)  $sl[]=["Clients","clients/index.php"];
            if($showL)  $sl[]=["Pipeline","leads/pipeline.php"];
            if($staffAdvanceSchemaReady) $sl[]=["Advance History","staff/advance_history.php"];
            if($staffIncentiveSchemaReady) $sl[]=["Incentive History","staff/incentive_history.php"];
            $sl[]=["Settings","settings/general.php"];
            foreach($sl as [$n,$h]):?><a href="<?= $h ?>" class="bg-mb-surface border border-mb-subtle/20 p-4 rounded-lg hover:bg-mb-black hover:border-mb-accent/30 transition-all group duration-300 transform hover:-translate-y-1"><p class="text-mb-silver group-hover:text-white transition-colors text-sm uppercase tracking-wide"><?= $n ?></p></a><?php endforeach;?>
        </div>
    </section>

    <!-- Task Complete Modal -->
    <div id="task-complete-modal" class="hidden fixed inset-0 z-[9999] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="w-full max-w-sm bg-mb-surface border border-green-500/30 rounded-xl shadow-2xl p-6 space-y-4">
            <h3 class="text-white font-medium border-l-2 border-green-400 pl-3">Mark Task Complete</h3>
            <p class="text-mb-subtle text-sm">Add a note or remarks (optional)</p>
            <textarea id="task-note-input" rows="3" placeholder="e.g. Done, vehicle cleaned..." class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-green-400 resize-none"></textarea>
            <div class="flex justify-end gap-3">
                <button onclick="closeTaskModal()" class="text-mb-silver text-sm px-4 py-2 hover:text-white">Cancel</button>
                <button onclick="submitTaskComplete()" class="bg-green-500 text-white px-5 py-2 rounded-full text-sm hover:bg-green-400 font-medium">Confirm Done</button>
            </div>
        </div>
    </div>
    <script>
    var _taskId=null,_TROOT='<?= $root ?>';
    function openTaskComplete(id){_taskId=id;document.getElementById('task-complete-modal').classList.remove('hidden');setTimeout(function(){document.getElementById('task-note-input').focus();},100);}
    function closeTaskModal(){document.getElementById('task-complete-modal').classList.add('hidden');_taskId=null;document.getElementById('task-note-input').value='';}
    function submitTaskComplete(){
        var note=document.getElementById('task-note-input').value.trim();
        var fd=new FormData();fd.append('action','complete');fd.append('task_id',_taskId);fd.append('note',note);
        fetch(_TROOT+'staff/task_action.php',{method:'POST',body:fd})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.ok){
                var el=document.getElementById('task-'+_taskId);
                if(el){el.style.transition='opacity 0.4s';el.style.opacity='0';setTimeout(function(){
                    el.remove();
                    var remaining=document.querySelectorAll('.staff-task-row').length;
                    var badge=document.getElementById('tasks-pending-badge');
                    if(badge)badge.textContent=remaining+' pending';
                    var sec=document.getElementById('tasks-section');
                    if(sec&&remaining===0){sec.style.transition='opacity 0.4s';sec.style.opacity='0';setTimeout(function(){sec.remove();},400);}
                },400);}
                closeTaskModal();
            } else { alert(d.message||'Error completing task.'); }
        }).catch(function(){alert('Network error. Please try again.');});
    }
    </script>

<?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
