<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/ledger_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$pdo = db();
ledger_ensure_schema($pdo);

$vehicleId     = (int)   ($_POST['vehicle_id'] ?? 0);
$amount        = (float) ($_POST['amount'] ?? 0);
$category      = trim($_POST['category'] ?? '');
$description   = trim($_POST['description'] ?? '');
$paymentMode   = trim($_POST['payment_mode'] ?? '');
$bankAccountId = !empty($_POST['bank_account_id']) ? (int)$_POST['bank_account_id'] : null;
$expenseDate   = trim($_POST['expense_date'] ?? '');
$kmReading     = trim($_POST['km_reading'] ?? '');
$currentUser   = current_user();

// KM reading required for Service, Tyre, Spare Parts
$kmRequiredCategories = ['Service', 'Tyre', 'Spare Parts'];
if (in_array($category, $kmRequiredCategories, true) && $kmReading === '') {
    flash('error', 'KM reading is required for ' . $category . ' expenses.');
    redirect('show.php?id=' . $vehicleId);
}
$kmReadingInt = ($kmReading !== '') ? (int) $kmReading : null;

// Validate vehicle exists
$vCheck = $pdo->prepare('SELECT id, brand, model, status FROM vehicles WHERE id=?');
$vCheck->execute([$vehicleId]);
$vehicle = $vCheck->fetch();
if (!$vehicle) {
    flash('error', 'Vehicle not found.');
    redirect('index.php');
}

// Block expense on sold vehicles
if (($vehicle['status'] ?? '') === 'sold') {
    flash('error', 'Cannot add expenses to a sold vehicle.');
    redirect('show.php?id=' . $vehicleId);
}

// Validate amount
if ($amount <= 0) {
    flash('error', 'Amount must be greater than zero.');
    redirect('show.php?id=' . $vehicleId);
}

// Validate category
if ($category === '') {
    flash('error', 'Expense category is required.');
    redirect('show.php?id=' . $vehicleId);
}

// Validate payment mode
$validModes = ['cash', 'credit', 'account'];
if (!in_array($paymentMode, $validModes, true)) {
    flash('error', 'Invalid payment mode.');
    redirect('show.php?id=' . $vehicleId);
}

// Resolve bank account for 'account' mode
$resolvedBankId = null;
if ($paymentMode === 'account') {
    if ($bankAccountId) {
        $resolvedBankId = $bankAccountId;
    } else {
        $resolvedBankId = ledger_resolve_bank_account_id($pdo, 'account', null);
    }
    if (!$resolvedBankId) {
        flash('error', 'No active bank account found.');
        redirect('show.php?id=' . $vehicleId);
    }
}

// Validate expense date
$postedAt = null;
if ($expenseDate !== '') {
    $dateCheck = DateTime::createFromFormat('Y-m-d', $expenseDate);
    if ($dateCheck && $dateCheck->format('Y-m-d') === $expenseDate) {
        $postedAt = $expenseDate . ' ' . (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('H:i:s');
    }
}

// Build description
$fullDesc = $vehicle['brand'] . ' ' . $vehicle['model'] . ' - ' . $category;
if ($description !== '') {
    $fullDesc .= ': ' . $description;
}
if ($kmReadingInt !== null) {
    $fullDesc .= ' [KM: ' . $kmReadingInt . ']';
}

// Post to ledger
$entryId = ledger_post(
    $pdo,
    'expense',
    'Vehicle Expense',
    $amount,
    $paymentMode,
    $resolvedBankId,
    'vehicle_expense',
    $vehicleId,
    $category,
    $fullDesc,
    (int)($currentUser['id'] ?? 0),
    null,
    $postedAt
);

if ($entryId) {
    flash('success', 'Expense of $' . number_format($amount, 2) . ' added successfully.');
} else {
    flash('error', 'Failed to add expense. Please try again.');
}

redirect('show.php?id=' . $vehicleId);