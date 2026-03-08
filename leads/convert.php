<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_log.php';
require_once __DIR__ . '/../includes/client_helpers.php';
auth_check();
if (!auth_has_perm('add_leads')) {
    flash('error', 'You are not allowed to convert leads.');
    redirect('index.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../leads/index.php');
}
$pdo = db();
clients_ensure_schema($pdo);
$id = (int) ($_POST['id'] ?? 0);
if (!$id) {
    flash('error', 'Invalid lead selected.');
    redirect('index.php');
}

function leads_has_column(PDO $pdo, string $column): bool
{
    static $cache = [];
    $key = $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'leads'
              AND COLUMN_NAME = ?");
        $stmt->execute([$column]);
        $cache[$key] = ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable $e) {
        app_log('ERROR', "Lead convert: column check failed for leads.{$column} - " . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

        $cache[$key] = false;
    }

    return $cache[$key];
}

try {
    $pdo->beginTransaction();

    $leadStmt = $pdo->prepare('SELECT * FROM leads WHERE id=? FOR UPDATE');
    $leadStmt->execute([$id]);
    $lead = $leadStmt->fetch();

    if (!$lead) {
        $pdo->rollBack();
        flash('error', 'Lead not found.');
        redirect('index.php');
    }
    if ($lead['status'] !== 'closed_won') {
        $pdo->rollBack();
        flash('error', 'Only Closed Won leads can be converted.');
        redirect("show.php?id=$id");
    }
    if ($lead['converted_client_id']) {
        $pdo->rollBack();
        flash('error', 'This lead has already been converted.');
        redirect("show.php?id=$id");
    }

    $clientId = 0;
    $linkedExistingClient = false;
    $leadEmail = trim((string) ($lead['email'] ?? ''));
    $leadPhone = trim((string) ($lead['phone'] ?? ''));
    $supportsLeadAlternative = leads_has_column($pdo, 'alternative_number');
    $supportsClientAlternative = clients_has_column($pdo, 'alternative_number');
    $leadAlternative = $supportsLeadAlternative ? trim((string) ($lead['alternative_number'] ?? '')) : '';

    // Avoid duplicate clients: first try by email, then by phone.
    if ($leadEmail !== '') {
        $clientLookup = $pdo->prepare('SELECT id FROM clients WHERE email=? LIMIT 1');
        $clientLookup->execute([$leadEmail]);
        $clientId = (int) ($clientLookup->fetchColumn() ?: 0);
        $linkedExistingClient = $clientId > 0;
    }
    if (!$clientId && $leadPhone !== '') {
        if ($supportsClientAlternative) {
            $clientLookup = $pdo->prepare('SELECT id FROM clients WHERE phone=? OR alternative_number=? ORDER BY id DESC LIMIT 1');
            $clientLookup->execute([$leadPhone, $leadPhone]);
        } else {
            $clientLookup = $pdo->prepare('SELECT id FROM clients WHERE phone=? ORDER BY id DESC LIMIT 1');
            $clientLookup->execute([$leadPhone]);
        }
        $clientId = (int) ($clientLookup->fetchColumn() ?: 0);
        $linkedExistingClient = $clientId > 0;
    }

    if (!$clientId) {
        if ($supportsClientAlternative) {
            $pdo->prepare('INSERT INTO clients (name, phone, alternative_number, email, notes) VALUES (?,?,?,?,?)')
                ->execute([
                    $lead['name'],
                    $lead['phone'],
                    ($leadAlternative !== '' ? $leadAlternative : null),
                    ($leadEmail !== '' ? $leadEmail : null),
                    'Converted from lead #' . $id
                ]);
        } else {
            $pdo->prepare('INSERT INTO clients (name, phone, email, notes) VALUES (?,?,?,?)')
                ->execute([
                    $lead['name'],
                    $lead['phone'],
                    ($leadEmail !== '' ? $leadEmail : null),
                    'Converted from lead #' . $id
                ]);
        }
        $clientId = (int) $pdo->lastInsertId();
    }

    $nowSql = app_now_sql();
    $pdo->prepare("UPDATE leads SET converted_client_id=?, status='closed_won', updated_at=?,
        closed_at = IF(closed_at IS NULL, ?, closed_at) WHERE id=?")
        ->execute([$clientId, $nowSql, $nowSql, $id]);

    $activityNote = $linkedExistingClient
        ? "Lead linked to existing client #$clientId."
        : "Lead converted to client #$clientId.";
    $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')->execute([$id, $activityNote]);

    $pdo->commit();

    $message = $linkedExistingClient
        ? 'Lead linked to existing client successfully! You can now create a reservation for them.'
        : 'Lead converted to client successfully! You can now create a reservation for them.';

    $staffLogDescription = $linkedExistingClient
        ? "Linked lead #$id ({$lead['name']}) to existing client #$clientId."
        : "Converted lead #$id ({$lead['name']}) to new client #$clientId.";
    log_activity($pdo, 'converted_lead', 'lead', $id, $staffLogDescription);

    app_log('ACTION', "Converted lead (ID: $id) to client (ID: $clientId)");
    flash('success', $message);
    redirect("../clients/show.php?id=$clientId");
} catch (Throwable $e) {
    app_log('ERROR', 'Lead conversion failed for lead #' . $id . ' - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Lead conversion failed for lead #' . $id . ': ' . $e->getMessage());
    flash('error', 'Could not convert this lead right now. Please try again.');
    redirect("show.php?id=$id");
}
