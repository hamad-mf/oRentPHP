<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';
auth_require_admin();
$pdo = db();

$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
    redirect('index.php');
}

$stmt = $pdo->prepare("SELECT * FROM emi_investments WHERE id=?");
$stmt->execute([$id]);
$inv = $stmt->fetch();
if (!$inv) {
    flash('error', 'Investment not found.');
    redirect('index.php');
}

// Check paid EMIs
$paidCount = (int) $pdo->query("SELECT COUNT(*) FROM emi_schedules WHERE investment_id=$id AND status='paid'")->fetchColumn();

if ($paidCount > 0) {
    flash('error', "Cannot delete — $paidCount EMI payment(s) already recorded. Unmark all payments first.");
    redirect("show.php?id=$id");
}

try {
    // Reverse down payment ledger if exists
    if ($inv['down_payment_ledger_id']) {
        $le = $pdo->prepare("SELECT * FROM ledger_entries WHERE id=?");
        $le->execute([$inv['down_payment_ledger_id']]);
        $le = $le->fetch();
        if ($le && $le['bank_account_id']) {
            $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id=?")->execute([$le['amount'], $le['bank_account_id']]);
        }
        $pdo->prepare("DELETE FROM ledger_entries WHERE id=?")->execute([$inv['down_payment_ledger_id']]);
    }
    // Cascade delete (emi_schedules FK)
    $pdo->prepare("DELETE FROM emi_investments WHERE id=?")->execute([$id]);
    flash('success', "Investment \"{$inv['title']}\" deleted.");
} catch (Throwable $e) {
    flash('error', 'Delete failed: ' . $e->getMessage());
}
redirect('index.php');
