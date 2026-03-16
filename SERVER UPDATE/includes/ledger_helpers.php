<?php
/**
 * includes/ledger_helpers.php
 * Bank accounts + ledger core service.
 *
 * Public API:
 *   ledger_ensure_schema($pdo)
 *   ledger_post_reservation_event($pdo, $reservationId, $event, $amount, $paymentMethod, $userId, $bankAccountId = null)
 *   ledger_post_manual($pdo, $type, $category, $amount, $paymentMode, $description, $userId, $postedAt = null, $bankAccountId = null)
 *   ledger_get_accounts($pdo)
 *   ledger_get_entries($pdo, array $filters)
 *   ledger_void_entry($pdo, $entryId, $userId, $reason)
 *   ledger_delete_manual_entry($pdo, $entryId, $userId)
 */

//  ”  ”  Schema  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 

function ledger_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done)
        return;
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS bank_accounts (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100) NOT NULL,
        bank_name     VARCHAR(100) DEFAULT NULL,
        account_number VARCHAR(50) DEFAULT NULL,
        balance       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        is_active     TINYINT(1)  NOT NULL DEFAULT 1,
        created_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ledger_entries (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        txn_type         ENUM('income','expense','adjustment') NOT NULL DEFAULT 'income',
        category         VARCHAR(100)  NOT NULL,
        description      TEXT          DEFAULT NULL,
        amount           DECIMAL(12,2) NOT NULL,
        payment_mode     VARCHAR(20)   DEFAULT NULL,
        bank_account_id  INT           DEFAULT NULL,
        source_type      VARCHAR(50)   NOT NULL DEFAULT 'manual',
        source_id        INT           DEFAULT NULL,
        source_event     VARCHAR(50)   DEFAULT NULL,
        idempotency_key  VARCHAR(120)  DEFAULT NULL,
        voided_at        DATETIME      DEFAULT NULL,
        voided_by        INT           DEFAULT NULL,
        void_reason      VARCHAR(255)  DEFAULT NULL,
        posted_at        DATETIME      DEFAULT CURRENT_TIMESTAMP,
        created_by       INT           DEFAULT NULL,
        created_at       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_idempotency (idempotency_key),
        INDEX idx_txn_type (txn_type),
        INDEX idx_posted_at (posted_at),
        INDEX idx_voided_at (voided_at),
        INDEX idx_bank_account (bank_account_id),
        INDEX idx_source (source_type, source_id),
        FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    // Seed default account if none exist
    $count = (int) $pdo->query("SELECT COUNT(*) FROM bank_accounts")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO bank_accounts (name, bank_name) VALUES ('Bank Account', NULL)");
    }
}

//  ”  ”  Payment Method   ’ Bank Account Mapping  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 

function ledger_bank_account_for_method(PDO $pdo, ?string $method): ?int
{
    if ($method === null)
        return null;
    $method = strtolower(trim($method));

    // Only 'account' (bank transfer/cheque) actually moves money in the bank account.
    // 'cash' = physical cash received  ” tracked in ledger but NOT in the bank balance.
    // 'credit' = receivable  ” no money received yet, ledger note only.
    if ($method !== 'account')
        return null;

    $row = $pdo->query("SELECT id FROM bank_accounts WHERE is_active=1 ORDER BY id ASC LIMIT 1")->fetch();
    return $row ? (int) $row['id'] : null;
}

/**
 * Resolve the bank account to be used for a posting.
 * - account mode: use selected active account if provided, else fallback to first active account
 * - cash/credit: always null (ledger-only, no bank mutation)
 */
function ledger_resolve_bank_account_id(PDO $pdo, ?string $paymentMode, ?int $selectedBankAccountId = null): ?int
{
    $mode = strtolower(trim((string) $paymentMode));
    if ($mode !== 'account') {
        return null;
    }

    if ($selectedBankAccountId !== null && $selectedBankAccountId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$selectedBankAccountId]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }
    }

    // Backward-safe fallback for older flows that don't pass account selection.
    return ledger_bank_account_for_method($pdo, 'account');
}

//  ”  ”  Core Post  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 

/**
 * Post a ledger entry and atomically update bank account balance.
 *
 * @param  string|null $idempotencyKey  Unique key  ” duplicate calls silently skip.
 * @return int|null  Ledger entry ID, or null if skipped (duplicate).
 */
function ledger_post(
    PDO $pdo,
    string $txnType,        // income | expense | adjustment
    string $category,
    float $amount,
    ?string $paymentMode,
    ?int $bankAccountId,
    string $sourceType,
    ?int $sourceId,
    ?string $sourceEvent,
    ?string $description,
    ?int $userId,
    ?string $idempotencyKey = null,
    ?string $postedAt = null
): ?int {
    if ($amount <= 0)
        return null;

    $postedAt = $postedAt ?? (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        // Idempotency check
        if ($idempotencyKey !== null) {
            $exists = $pdo->prepare("SELECT id FROM ledger_entries WHERE idempotency_key = ?");
            $exists->execute([$idempotencyKey]);
            if ($existing = $exists->fetch()) {
                $pdo->rollBack();
                return (int) $existing['id'];
            }
        }

        // Insert ledger entry
        $stmt = $pdo->prepare("INSERT INTO ledger_entries
            (txn_type, category, description, amount, payment_mode, bank_account_id,
             source_type, source_id, source_event, idempotency_key, posted_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $txnType,
            $category,
            $description,
            $amount,
            $paymentMode,
            $bankAccountId,
            $sourceType,
            $sourceId,
            $sourceEvent,
            $idempotencyKey,
            $postedAt,
            $userId
        ]);
        $entryId = (int) $pdo->lastInsertId();

        // Update bank account balance
        if ($bankAccountId !== null) {
            $delta = ($txnType === 'expense') ? -abs($amount) : abs($amount);
            $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id = ?")
                ->execute([$delta, $bankAccountId]);
        }

        $pdo->commit();
        app_log('ACTION', "Ledger: posted $txnType $category amount=$amount (entry ID: $entryId)");
        return $entryId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        app_log('ERROR', "Ledger post failed: " . $e->getMessage());
        return null;
    }
}

//  ”  ”  Reservation Auto-Posting  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 

/**
 * Auto-post income from a reservation advance, delivery, or return payment.
 * Idempotent  ” safe to call twice for the same event.
 */
function ledger_post_reservation_event(
    PDO $pdo,
    int $reservationId,
    string $event,          // 'advance' | 'delivery' | 'return'
    float $amount,
    ?string $paymentMethod, // 'cash' | 'account' | 'credit' | null
    int $userId,
    ?int $bankAccountId = null
): ?int {
    if ($amount <= 0)
        return null;

    ledger_ensure_schema($pdo);

    $event = strtolower(trim($event));
    switch ($event) {
        case 'delivery_prepaid':
            $category = 'Reservation Delivery (Prepaid)';
            $label = 'Delivery (Prepaid)';
            break;
        case 'delivery':
            $category = 'Reservation Delivery';
            $label = 'Delivery';
            break;
        case 'return':
            $category = 'Reservation Return';
            $label = 'Return';
            break;
        case 'advance':
            $category = 'Reservation Advance';
            $label = 'Advance';
            break;
        default:
            $category = 'Reservation Payment';
            $label = ucfirst($event);
            break;
    }
    $description = "Reservation #$reservationId - {$label} payment";
    $idKey = "reservation:{$event}:{$reservationId}";
    $bankId = ledger_resolve_bank_account_id($pdo, $paymentMethod, $bankAccountId);

    return ledger_post(
        $pdo,
        'income',
        $category,
        $amount,
        $paymentMethod,
        $bankId,
        'reservation',
        $reservationId,
        $event,
        $description,
        $userId,
        $idKey
    );
}

//  ”  ”  Manual Entry  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 

/**
 * Post multiple split-payment ledger entries for a reservation event.
 * Each split = ['mode' => 'cash'|'account'|'credit', 'amount' => float, 'bank_id' => int|null]
 * Idempotent - each split gets its own unique key: reservation:{event}:{id}:{mode}
 * @return array  List of ledger entry IDs created.
 */
function ledger_post_reservation_event_multi(
    PDO $pdo,
    int $reservationId,
    string $event,
    array $splits,
    int $userId
): array {
    ledger_ensure_schema($pdo);
    $entryIds = [];
    $category = $event === 'delivery' ? 'Reservation Delivery' : 'Reservation Return';

    foreach ($splits as $split) {
        $mode = $split['mode'] ?? null;
        $amount = (float) ($split['amount'] ?? 0);
        $bankId = !empty($split['bank_id']) ? (int) $split['bank_id'] : null;

        if ($amount <= 0) continue;

        $description = "Reservation #$reservationId - " . ucfirst($event) . " payment (" . ucfirst($mode ?? 'unknown') . ")";
        $idKey = "reservation:{$event}:{$reservationId}:{$mode}";
        $resolvedBankId = ledger_resolve_bank_account_id($pdo, $mode, $bankId);

        $entryId = ledger_post(
            $pdo,
            'income',
            $category,
            $amount,
            $mode,
            $resolvedBankId,
            'reservation',
            $reservationId,
            $event,
            $description,
            $userId,
            $idKey
        );
        if ($entryId) {
            $entryIds[] = $entryId;
        }
    }
    return $entryIds;
}

/**
 * Return an active bank account id when valid; otherwise null.
 */
function ledger_get_active_bank_account_id(PDO $pdo, ?int $bankAccountId): ?int
{
    if ($bankAccountId === null || $bankAccountId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$bankAccountId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

function ledger_is_security_deposit_event(?string $sourceEvent): bool
{
    return in_array((string) $sourceEvent, ['security_deposit_in', 'security_deposit_out'], true);
}

function ledger_non_voided_clause(string $alias = ''): string
{
    $prefix = trim($alias);
    if ($prefix !== '') {
        $prefix = rtrim($prefix, '.') . '.';
    }
    return $prefix . "voided_at IS NULL";
}

function ledger_kpi_exclusion_clause(string $alias = ''): string
{
    $prefix = trim($alias);
    if ($prefix !== '') {
        $prefix = rtrim($prefix, '.') . '.';
    }
    return ledger_non_voided_clause($alias)
        . " AND (" . $prefix . "source_event IS NULL OR " . $prefix . "source_event NOT IN ('security_deposit_in','security_deposit_out','transfer_out','transfer_in'))"
        . " AND (" . $prefix . "source_type IS NULL OR " . $prefix . "source_type <> 'transfer')";
}

/**
 * Post security deposit in/out as non-KPI ledger entries.
 * These entries affect the selected bank account balance, but are excluded from target/income KPI calculations.
 */
function ledger_post_security_deposit(
    PDO $pdo,
    int $reservationId,
    string $direction, // in | out
    float $amount,
    int $bankAccountId,
    int $userId
): ?int {
    if ($amount <= 0 || $bankAccountId <= 0) {
        return null;
    }

    ledger_ensure_schema($pdo);

    $dir = strtolower(trim($direction));
    if (!in_array($dir, ['in', 'out'], true)) {
        return null;
    }

    $sourceEvent = $dir === 'in' ? 'security_deposit_in' : 'security_deposit_out';
    $txnType = $dir === 'in' ? 'income' : 'expense';
    $category = $dir === 'in' ? 'Security Deposit Collected' : 'Security Deposit Returned';
    $description = $dir === 'in'
        ? "Reservation #$reservationId - Security deposit collected"
        : "Reservation #$reservationId - Security deposit returned";
    $idKey = "reservation:{$sourceEvent}:{$reservationId}";

    return ledger_post(
        $pdo,
        $txnType,
        $category,
        $amount,
        'account',
        $bankAccountId,
        'reservation',
        $reservationId,
        $sourceEvent,
        $description,
        $userId,
        $idKey
    );
}

function ledger_get_security_deposit_account_id(PDO $pdo, int $reservationId): ?int
{
    ledger_ensure_schema($pdo);
    $stmt = $pdo->prepare("SELECT bank_account_id
        FROM ledger_entries
        WHERE source_type = 'reservation'
          AND source_id = ?
          AND source_event = 'security_deposit_in'
          AND bank_account_id IS NOT NULL
        ORDER BY id DESC
        LIMIT 1");
    $stmt->execute([$reservationId]);
    $bankAccountId = $stmt->fetchColumn();
    if ($bankAccountId === false || $bankAccountId === null) {
        return null;
    }
    return (int) $bankAccountId;
}

function ledger_post_manual(
    PDO $pdo,
    string $txnType,      // 'income' | 'expense'
    string $category,
    float $amount,
    ?string $paymentMode,
    ?string $description,
    int $userId,
    ?string $postedAt = null,
    ?int $bankAccountId = null
): ?int {
    ledger_ensure_schema($pdo);

    $txnType = strtolower(trim($txnType));
    $category = trim($category);
    if ($category === '') {
        $category = $txnType === 'expense' ? 'Manual Expense' : 'Manual Income';
    }

    $bankId = ledger_resolve_bank_account_id($pdo, $paymentMode, $bankAccountId);

    return ledger_post(
        $pdo,
        $txnType,
        $category,
        $amount,
        $paymentMode,
        $bankId,
        'manual',
        null,
        'manual',
        $description,
        $userId,
        null, // no idempotency for manual
        $postedAt
    );
}

//  ”  ”  Delete Manual Entry  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 

/**
 * Void a ledger entry and reverse the bank balance impact.
 * Keeps the row for audit; voided entries are excluded from totals.
 */
function ledger_void_entry(PDO $pdo, int $entryId, int $userId, string $reason): bool
{
    ledger_ensure_schema($pdo);
    $reason = trim($reason);
    if ($reason === '') {
        return false;
    }

    $entry = $pdo->prepare("SELECT * FROM ledger_entries WHERE id = ? LIMIT 1");
    $entry->execute([$entryId]);
    $row = $entry->fetch();

    if (!$row || !empty($row['voided_at'])) {
        return false;
    }
    if (($row['source_type'] ?? '') === 'transfer' || in_array((string) ($row['source_event'] ?? ''), ['transfer_out', 'transfer_in'], true)) {
        return false;
    }

    try {
        $pdo->beginTransaction();

        // Reverse bank balance
        if (!empty($row['bank_account_id'])) {
            $delta = ($row['txn_type'] === 'expense') ? abs($row['amount']) : -abs($row['amount']);
            $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id = ?")
                ->execute([$delta, $row['bank_account_id']]);
        }

        $pdo->prepare("UPDATE ledger_entries SET voided_at = ?, voided_by = ?, void_reason = ? WHERE id = ?")
            ->execute([app_now_sql(), $userId > 0 ? $userId : null, $reason, $entryId]);

        $pdo->commit();
        app_log('ACTION', "Ledger: voided entry ID $entryId by user $userId");
        return true;

    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        app_log('ERROR', "Ledger void failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a manual ledger entry and reverse the bank balance.
 * System entries (source_type != 'manual') cannot be deleted.
 */
function ledger_delete_manual_entry(PDO $pdo, int $entryId, int $userId): bool
{
    ledger_ensure_schema($pdo);

    $entry = $pdo->prepare("SELECT * FROM ledger_entries WHERE id = ? AND source_type = 'manual' AND voided_at IS NULL");
    $entry->execute([$entryId]);
    $row = $entry->fetch();

    if (!$row)
        return false;

    try {
        $pdo->beginTransaction();

        // Reverse bank balance
        if ($row['bank_account_id']) {
            $delta = ($row['txn_type'] === 'expense') ? abs($row['amount']) : -abs($row['amount']);
            $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id = ?")
                ->execute([$delta, $row['bank_account_id']]);
        }

        $pdo->prepare("DELETE FROM ledger_entries WHERE id = ?")->execute([$entryId]);
        $pdo->commit();
        app_log('ACTION', "Ledger: deleted manual entry ID $entryId by user $userId");
        return true;

    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        app_log('ERROR', "Ledger delete failed: " . $e->getMessage());
        return false;
    }
}

//  ”  ”  Fund Transfer  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 

/**
 * Transfer funds between two bank accounts atomically.
 * - Creates an expense ledger entry on the from-account ("Transfer Out")
 * - Creates an income  ledger entry on the to-account   ("Transfer In")
 * - Updates both balances in a single DB transaction.
 *
 * @param  string $error  Populated with a reason on failure.
 * @return bool
 */
function ledger_transfer(
    PDO $pdo,
    int $fromId,
    int $toId,
    float $amount,
    ?string $description,
    int $userId,
    ?string $postedAt = null,
    string &$error = ''
): bool {
    ledger_ensure_schema($pdo);

    if ($fromId === $toId) {
        $error = 'Cannot transfer to the same account.';
        return false;
    }
    if ($amount <= 0) {
        $error = 'Transfer amount must be greater than zero.';
        return false;
    }

    $stmt = $pdo->prepare("SELECT id, name, balance, is_active FROM bank_accounts WHERE id IN (?, ?)");
    $stmt->execute([$fromId, $toId]);
    $accts = [];
    foreach ($stmt->fetchAll() as $row)
        $accts[(int) $row['id']] = $row;

    if (!isset($accts[$fromId]) || !$accts[$fromId]['is_active']) {
        $error = 'Source account not found or inactive.';
        return false;
    }
    if (!isset($accts[$toId]) || !$accts[$toId]['is_active']) {
        $error = 'Destination account not found or inactive.';
        return false;
    }
    if ((float) $accts[$fromId]['balance'] < $amount) {
        $error = 'Insufficient balance in source account (Balance: $' . number_format($accts[$fromId]['balance'], 2) . ').';
        return false;
    }

    $postedAt = $postedAt ?? (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    $desc = $description ?: ('Transfer: ' . $accts[$fromId]['name'] . '   ’ ' . $accts[$toId]['name']);

    try {
        $pdo->beginTransaction();

        $pdo->prepare("INSERT INTO ledger_entries
            (txn_type, category, description, amount, payment_mode, bank_account_id,
             source_type, source_event, posted_at, created_by)
            VALUES ('expense', 'Transfer Out', ?, ?, 'account', ?, 'transfer', 'transfer_out', ?, ?)")
            ->execute([$desc, $amount, $fromId, $postedAt, $userId]);

        $pdo->prepare("INSERT INTO ledger_entries
            (txn_type, category, description, amount, payment_mode, bank_account_id,
             source_type, source_event, posted_at, created_by)
            VALUES ('income', 'Transfer In', ?, ?, 'account', ?, 'transfer', 'transfer_in', ?, ?)")
            ->execute([$desc, $amount, $toId, $postedAt, $userId]);

        $pdo->prepare("UPDATE bank_accounts SET balance = balance - ? WHERE id = ?")->execute([$amount, $fromId]);
        $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id = ?")->execute([$amount, $toId]);

        $pdo->commit();
        app_log('ACTION', "Ledger: transfer $$amount from account#$fromId to account#$toId by user#$userId");
        return true;

    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        app_log('ERROR', "Ledger transfer failed: " . $e->getMessage());
        $error = 'Database error. Please try again.';
        return false;
    }
}

/**
 * Transfer funds from Cash to a bank account atomically.
 * - Creates a cash expense entry ("Transfer Out")
 * - Creates an account income entry ("Transfer In")
 * - Updates destination bank balance in the same transaction
 *
 * @param  string $error  Populated with a reason on failure.
 * @return bool
 */
function ledger_transfer_cash_to_bank(
    PDO $pdo,
    int $toId,
    float $amount,
    ?string $description,
    int $userId,
    ?string $postedAt = null,
    string &$error = ''
): bool {
    ledger_ensure_schema($pdo);

    if ($toId <= 0) {
        $error = 'Please select a destination bank account.';
        return false;
    }
    if ($amount <= 0) {
        $error = 'Transfer amount must be greater than zero.';
        return false;
    }

    $stmt = $pdo->prepare("SELECT id, name, is_active FROM bank_accounts WHERE id = ? LIMIT 1");
    $stmt->execute([$toId]);
    $acct = $stmt->fetch();

    if (!$acct || !(int) $acct['is_active']) {
        $error = 'Destination account not found or inactive.';
        return false;
    }

    $cashIncome  = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='income' AND voided_at IS NULL")->fetchColumn();
    $cashExpense = (float) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM ledger_entries WHERE payment_mode='cash' AND txn_type='expense' AND voided_at IS NULL")->fetchColumn();
    $cashBalance = $cashIncome - $cashExpense;

    if ($cashBalance < $amount) {
        $error = 'Insufficient cash balance (Balance: $' . number_format($cashBalance, 2) . ').';
        return false;
    }

    $postedAt = $postedAt ?? (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
    $desc = $description ?: ('Cash transfer to ' . $acct['name']);

    try {
        $pdo->beginTransaction();

        $pdo->prepare("INSERT INTO ledger_entries
            (txn_type, category, description, amount, payment_mode, bank_account_id,
             source_type, source_event, posted_at, created_by)
            VALUES ('expense', 'Transfer Out', ?, ?, 'cash', NULL, 'transfer', 'transfer_out', ?, ?)")
            ->execute([$desc, $amount, $postedAt, $userId]);

        $pdo->prepare("INSERT INTO ledger_entries
            (txn_type, category, description, amount, payment_mode, bank_account_id,
             source_type, source_event, posted_at, created_by)
            VALUES ('income', 'Transfer In', ?, ?, 'account', ?, 'transfer', 'transfer_in', ?, ?)")
            ->execute([$desc, $amount, $toId, $postedAt, $userId]);

        $pdo->prepare("UPDATE bank_accounts SET balance = balance + ? WHERE id = ?")
            ->execute([$amount, $toId]);

        $pdo->commit();
        app_log('ACTION', "Ledger: cash transfer $$amount to account#$toId by user#$userId");
        return true;

    } catch (Throwable $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        app_log('ERROR', "Ledger cash transfer failed: " . $e->getMessage());
        $error = 'Database error. Please try again.';
        return false;
    }
}

//  ”  ”  Query Helpers  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 

function ledger_get_accounts(PDO $pdo): array
{
    ledger_ensure_schema($pdo);
    return $pdo->query("SELECT * FROM bank_accounts ORDER BY id ASC")->fetchAll();
}

function ledger_get_entries(PDO $pdo, array $f = []): array
{
    ledger_ensure_schema($pdo);

    $where = ['1=1'];
    $params = [];

    if (!empty($f['type'])) {
        $where[] = "le.txn_type = ?";
        $params[] = $f['type'];
    }
    if (!empty($f['account_id'])) {
        $where[] = "le.bank_account_id = ?";
        $params[] = (int) $f['account_id'];
    } elseif (isset($f['account']) && $f['account'] !== null && $f['account'] !== '') {
        if (is_numeric($f['account'])) {
            $where[] = "le.bank_account_id = ?";
            $params[] = (int) $f['account'];
        } elseif (in_array($f['account'], ['cash', 'credit'], true)) {
            $where[] = "le.payment_mode = ?";
            $params[] = $f['account'];
        }
    }
    if (!empty($f['date_from'])) {
        $where[] = "DATE(le.posted_at) >= ?";
        $params[] = $f['date_from'];
    }
    if (!empty($f['date_to'])) {
        $where[] = "DATE(le.posted_at) <= ?";
        $params[] = $f['date_to'];
    }
    if (!empty($f['source'])) {
        $where[] = "le.source_type = ?";
        $params[] = $f['source'];
    }
    if (!empty($f['category'])) {
        $where[] = "le.category = ?";
        $params[] = $f['category'];
    }
    if (empty($f['include_voided'])) {
        $where[] = ledger_non_voided_clause('le');
    }

    $sql = "SELECT le.*, ba.name AS account_name, u.name AS posted_by_name
            FROM ledger_entries le
            LEFT JOIN bank_accounts ba ON ba.id = le.bank_account_id
            LEFT JOIN users u ON u.id = le.created_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY le.id DESC, le.posted_at DESC
            LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function ledger_build_query(array $f = []): array {
    $where = ['1=1'];
    $params = [];
    if (!empty($f['type']))       { $where[] = "le.txn_type = ?";         $params[] = $f['type']; }
    if (!empty($f['account_id'])) {
        $where[] = "le.bank_account_id = ?";
        $params[] = (int) $f['account_id'];
    } elseif (isset($f['account']) && $f['account'] !== null && $f['account'] !== '') {
        if (is_numeric($f['account'])) {
            $where[] = "le.bank_account_id = ?";
            $params[] = (int) $f['account'];
        } elseif (in_array($f['account'], ['cash', 'credit'], true)) {
            $where[] = "le.payment_mode = ?";
            $params[] = $f['account'];
        }
    }
    if (!empty($f['date_from']))  { $where[] = "DATE(le.posted_at) >= ?"; $params[] = $f['date_from']; }
    if (!empty($f['date_to']))    { $where[] = "DATE(le.posted_at) <= ?"; $params[] = $f['date_to']; }
    if (!empty($f['source']))     { $where[] = "le.source_type = ?";      $params[] = $f['source']; }
    if (!empty($f['category']))   { $where[] = "le.category = ?";         $params[] = $f['category']; }
    if (empty($f['include_voided'])) { $where[] = ledger_non_voided_clause('le'); }
    $base = "FROM ledger_entries le LEFT JOIN bank_accounts ba ON ba.id=le.bank_account_id LEFT JOIN users u ON u.id=le.created_by WHERE " . implode(' AND ', $where);
    return [
        'select' => "SELECT le.*, ba.name AS account_name, u.name AS posted_by_name ",
        'count'  => "SELECT COUNT(*) ",
        'base'   => $base,
        'order'  => " ORDER BY le.id DESC, le.posted_at DESC",
        'params' => $params,
    ];
}
function ledger_get_totals(PDO $pdo, string $dateFrom = '', string $dateTo = ''): array
{
    ledger_ensure_schema($pdo);

    $where = [ledger_kpi_exclusion_clause()];
    $params = [];
    if ($dateFrom) {
        $where[] = "DATE(posted_at) >= ?";
        $params[] = $dateFrom;
    }
    if ($dateTo) {
        $where[] = "DATE(posted_at) <= ?";
        $params[] = $dateTo;
    }

    $stmt = $pdo->prepare("SELECT
        COALESCE(SUM(CASE WHEN txn_type='income'  THEN amount ELSE 0 END),0) AS total_income,
        COALESCE(SUM(CASE WHEN txn_type='expense' THEN amount ELSE 0 END),0) AS total_expense
        FROM ledger_entries WHERE " . implode(' AND ', $where));
    $stmt->execute($params);
    return $stmt->fetch();
}
