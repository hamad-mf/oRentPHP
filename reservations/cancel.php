<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
$pdo = db();
auth_require_admin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$id) { flash('error','Invalid reservation.'); redirect('index.php'); }

// Load reservation with joined info
$rq = $pdo->prepare("SELECT r.*, c.name AS client_name, v.brand, v.model, v.license_plate, v.id AS vid FROM reservations r JOIN clients c ON c.id=r.client_id JOIN vehicles v ON v.id=r.vehicle_id WHERE r.id=?");
$rq->execute([$id]);
$r = $rq->fetch();
if (!$r) { flash('error','Reservation not found.'); redirect('index.php'); }
if ($r['status'] !== 'active') { flash('error','Only active reservations can be cancelled.'); redirect("show.php?id=$id"); }

// Runtime migration: add cancellation columns if missing
try {
    $cols = $pdo->query("SHOW COLUMNS FROM reservations")->fetchAll(PDO::FETCH_COLUMN);
    // Extend ENUM to include 'cancelled'
    $pdo->exec("ALTER TABLE reservations MODIFY COLUMN status ENUM('pending','confirmed','active','completed','cancelled') NOT NULL DEFAULT 'confirmed'");
    if (!in_array('cancellation_reason', $cols)) $pdo->exec("ALTER TABLE reservations ADD COLUMN cancellation_reason TEXT DEFAULT NULL");
    if (!in_array('cancelled_at', $cols))        $pdo->exec("ALTER TABLE reservations ADD COLUMN cancelled_at DATETIME DEFAULT NULL");
    if (!in_array('cancellation_by', $cols))      $pdo->exec("ALTER TABLE reservations ADD COLUMN cancellation_by INT DEFAULT NULL");
    if (!in_array('refund_amount', $cols))        $pdo->exec("ALTER TABLE reservations ADD COLUMN refund_amount DECIMAL(10,2) DEFAULT NULL");
} catch(Throwable $e) {
        app_log('ERROR', 'Reservation cancel: runtime migration failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'reservations/cancel.php',
        'reservation_id' => $id,
    ]);
}

// Collect what was taken from the customer at delivery
$deliveryPaid   = (float)($r['delivery_paid_amount'] ?? 0);
$deliveryMethod = $r['delivery_payment_method'] ?? null;
// Fetch bank_account_id from ledger_entries (not stored directly on reservation)
$ledBankRow = $pdo->prepare("SELECT bank_account_id FROM ledger_entries WHERE source_type='reservation' AND source_id=? AND source_event='delivery' AND bank_account_id IS NOT NULL ORDER BY id DESC LIMIT 1");
$ledBankRow->execute([$id]);
$deliveryBankId = $ledBankRow->fetchColumn() ?: null;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason     = trim($_POST['reason'] ?? '');
    $refund     = max(0, (float)str_replace(',', '', $_POST['refund_amount'] ?? '0'));
    $admin      = current_user();

    if (!$reason) $errors[] = 'Cancellation reason is required.';
    if ($refund > $deliveryPaid) {
        $errors[] = 'Refund cannot exceed amount collected at delivery ($' . number_format($deliveryPaid, 2) . ').';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Mark reservation cancelled
            $nowSql = app_now_sql();
            $pdo->prepare("UPDATE reservations SET status='cancelled', cancellation_reason=?, cancelled_at=?, cancellation_by=?, refund_amount=? WHERE id=?")
                ->execute([$reason, $nowSql, $admin['id'], $refund, $id]);

            // 2. Free up the vehicle
            $pdo->prepare("UPDATE vehicles SET status='available' WHERE id=?")->execute([$r['vid']]);

            // 3. Post refund as expense in ledger (reverses the income) — only if refund > 0
            if ($refund > 0) {
                // Resolve original bank account
                $bankId = null;
                if ($deliveryMethod === 'account' && $deliveryBankId) {
                    $bankId = (int)$deliveryBankId;
                }
                // If cash, no bank account to adjust; for account payments reduce bank balance
                $now = app_now_sql();
                $pdo->prepare("INSERT INTO ledger_entries (txn_type,category,description,amount,payment_mode,bank_account_id,source_type,source_id,source_event,posted_at,created_by) VALUES ('expense','Reservation Cancellation Refund',?,?,?,?,'reservation',?,'cancellation',?,?)")
                    ->execute(["Refund — Reservation #$id cancelled. Reason: $reason", $refund, $deliveryMethod, $bankId, $id, $now, $admin['id']]);
                // Adjust bank balance if payment was account-based
                if ($bankId) {
                    $pdo->prepare("UPDATE bank_accounts SET balance = balance - ? WHERE id=?")->execute([$refund, $bankId]);
                }
            }

            // 4. Log activity
            require_once __DIR__ . '/../includes/activity_log.php';
            log_activity($pdo, 'cancellation', 'reservation', $id, "Cancelled reservation #{$id} — {$r['client_name']}  {$r['brand']} {$r['model']} ({$r['license_plate']}). Refund: \$$refund. Reason: $reason.");

            $pdo->commit();
            app_log('ACTION', "Cancelled reservation #$id. Refund: $refund.");
            flash('success', "Reservation #{$id} cancelled. Refund amount: \$" . number_format($refund, 2) . '.');
            redirect("show.php?id=$id");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            app_log('ERROR', 'Reservation cancel failed: ' . $e->getMessage());
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Cancel Reservation #' . $id;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Reservations</a>
        <span></span>
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors">Reservation #<?= $id ?></a>
        <span></span>
        <span class="text-red-400">Cancel</span>
    </div>

    <!-- Warning Banner -->
    <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-5 flex gap-4">
        <div class="text-red-400 mt-0.5"></div>
        <div>
            <p class="text-red-300 font-medium">Cancel Active Reservation</p>
            <p class="text-red-400/70 text-sm mt-1">This will mark the reservation as cancelled, free the vehicle, and post a refund entry to the ledger. This action cannot be undone.</p>
        </div>
    </div>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
    <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-5 py-3 text-red-400 text-sm space-y-1">
        <?php foreach ($errors as $e): ?><p> <?= e($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Reservation Summary -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-4">
        <h2 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Reservation Summary</h2>
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div><p class="text-mb-subtle text-xs uppercase mb-1">Client</p><p class="text-white"><?= e($r['client_name']) ?></p></div>
            <div><p class="text-mb-subtle text-xs uppercase mb-1">Vehicle</p><p class="text-white"><?= e($r['brand'].' '.$r['model']) ?></p><p class="text-mb-subtle text-xs"><?= e($r['license_plate']) ?></p></div>
            <div><p class="text-mb-subtle text-xs uppercase mb-1">Period</p><p class="text-white"><?= date('d M Y', strtotime($r['start_date'])) ?>  <?= date('d M Y', strtotime($r['end_date'])) ?></p></div>
            <div><p class="text-mb-subtle text-xs uppercase mb-1">Status</p><span class="text-green-400 bg-green-500/10 border border-green-500/30 px-2 py-0.5 rounded-full text-xs">Active</span></div>
        </div>

        <!-- Amount collected so far -->
        <div class="border-t border-mb-subtle/10 pt-4 space-y-2">
            <h3 class="text-mb-subtle text-xs uppercase tracking-wider mb-3">Amount Collected from Customer</h3>
            <?php
            $basePrice = (float)$r['total_price'];
            $voucherAmt = (float)($r['voucher_applied'] ?? 0);
            $delCharge = (float)($r['delivery_charge'] ?? 0);
            $delManual = (float)($r['delivery_manual_amount'] ?? 0);
            $delDiscType = $r['delivery_discount_type'] ?? null;
            $delDiscVal = (float)($r['delivery_discount_value'] ?? 0);
            $delBase = max(0, $basePrice - $voucherAmt) + $delCharge + $delManual;
            $delDisc = $delDiscType === 'percent' ? round($delBase * min($delDiscVal,100)/100,2) : ($delDiscType==='amount'?min($delDiscVal,$delBase):0);
            $collectedAtDelivery = max(0, $delBase - $delDisc);
            ?>
            <?php if ($voucherAmt > 0): ?><div class="flex justify-between text-sm"><span class="text-mb-subtle">Voucher Used</span><span class="text-green-400">-$<?= number_format($voucherAmt,2) ?></span></div><?php endif; ?>
            <?php if ($delCharge > 0): ?><div class="flex justify-between text-sm"><span class="text-mb-subtle">Delivery Charge</span><span class="text-white">+$<?= number_format($delCharge,2) ?></span></div><?php endif; ?>
            <?php if ($delDisc > 0): ?><div class="flex justify-between text-sm"><span class="text-mb-subtle">Delivery Discount</span><span class="text-green-400">-$<?= number_format($delDisc,2) ?></span></div><?php endif; ?>
            <div class="flex justify-between items-center bg-mb-black/40 rounded-lg px-4 py-3 border border-mb-subtle/20">
                <span class="text-white font-medium text-sm"> Total Collected at Delivery</span>
                <span class="text-mb-accent font-bold text-xl">$<?= number_format($deliveryPaid, 2) ?></span>
            </div>
            <?php if ($deliveryMethod): ?>
            <p class="text-mb-subtle text-xs">Payment method: <span class="text-white"><?= ucfirst($deliveryMethod) ?></span></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancellation Form -->
    <form method="POST" class="bg-mb-surface border border-red-500/20 rounded-xl p-6 space-y-5">
        <input type="hidden" name="id" value="<?= $id ?>">
        <h2 class="text-white font-light text-lg border-l-2 border-red-400 pl-3">Cancellation Details</h2>

        <div>
            <label class="text-mb-subtle text-xs uppercase tracking-wider block mb-2">Refund Amount <span class="text-mb-subtle/50 normal-case">(0 = no refund)</span></label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-mb-subtle">$</span>
                <input type="number" name="refund_amount" id="refund_amount" step="0.01" min="0" max="<?= $deliveryPaid ?>"
                    value="<?= number_format($deliveryPaid, 2, '.', '') ?>"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg pl-8 pr-4 py-3 text-white text-sm focus:outline-none focus:border-red-400 focus:ring-1 focus:ring-red-400/20">
            </div>
            <div class="flex gap-2 mt-2">
                <button type="button" onclick="document.getElementById('refund_amount').value='<?= number_format($deliveryPaid,2,'.','') ?>'" class="text-[11px] bg-mb-black border border-mb-subtle/20 text-mb-subtle hover:text-white px-3 py-1 rounded-full transition-colors">Full Refund ($<?= number_format($deliveryPaid,2) ?>)</button>
                <button type="button" onclick="document.getElementById('refund_amount').value='0'" class="text-[11px] bg-mb-black border border-mb-subtle/20 text-mb-subtle hover:text-white px-3 py-1 rounded-full transition-colors">No Refund ($0)</button>
            </div>
        </div>

        <div>
            <label class="text-mb-subtle text-xs uppercase tracking-wider block mb-2">Cancellation Reason <span class="text-red-400">*</span></label>
            <textarea name="reason" rows="3" required placeholder="Why is this reservation being cancelled?" class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white text-sm focus:outline-none focus:border-red-400 focus:ring-1 focus:ring-red-400/20 resize-none"></textarea>
        </div>

        <div class="flex justify-end gap-3 pt-2 border-t border-mb-subtle/10">
            <a href="show.php?id=<?= $id ?>" class="text-mb-silver text-sm px-5 py-2.5 hover:text-white transition-colors">Go Back</a>
            <button type="submit" onclick="return confirm('Confirm cancellation of Reservation #<?= $id ?>? This cannot be undone.')"
                class="bg-red-600 text-white px-6 py-2.5 rounded-full text-sm hover:bg-red-500 font-medium transition-colors">
                 Confirm Cancellation
            </button>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
