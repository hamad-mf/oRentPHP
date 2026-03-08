<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
require_once __DIR__ . '/../includes/activity_log.php';
auth_check();
if (!auth_has_perm('add_leads')) {
    flash('error', 'You are not allowed to edit leads.');
    redirect('index.php');
}
$pdo = db();

function lead_status_ensure_pipeline(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    try {
        $stmt = $pdo->query("SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'leads'
              AND COLUMN_NAME = 'status'
            LIMIT 1");
        $columnType = strtolower((string) $stmt->fetchColumn());
        if ($columnType !== '') {
            if (strpos($columnType, "'negotiation'") !== false) {
                $pdo->exec("UPDATE leads SET status='interested' WHERE status='negotiation'");
            }
            if (strpos($columnType, "'future'") === false || strpos($columnType, "'negotiation'") !== false) {
                $pdo->exec("ALTER TABLE leads MODIFY COLUMN status ENUM('new','contacted','interested','future','closed_won','closed_lost') DEFAULT 'new'");
            }
        }
    } catch (Throwable $e) {
        app_log('ERROR', 'Lead edit: status pipeline ensure failed - ' . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

    }
}

function lead_has_column(PDO $pdo, string $column): bool
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
        app_log('ERROR', "Lead edit: column check failed for leads.{$column} - " . $e->getMessage(), [
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

        $cache[$key] = false;
    }

    return $cache[$key];
}

lead_status_ensure_pipeline($pdo);
$supportsAlternativeNumber = lead_has_column($pdo, 'alternative_number');

// Ensure closed_at column exists (added in later migration)
try {
    $pdo->query("SELECT closed_at FROM leads LIMIT 0");
} catch (Throwable $_) {
    app_log('ERROR', 'Lead edit: closed_at probe failed - ' . $_->getMessage(), [
    'file' => $_->getFile() . ':' . $_->getLine(),
]);

    $pdo->exec("ALTER TABLE leads ADD COLUMN closed_at DATETIME NULL DEFAULT NULL AFTER updated_at");
}

$id = (int) ($_REQUEST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM leads WHERE id=?');
$stmt->execute([$id]);
$lead = $stmt->fetch();
if (!$lead) {
    flash('error', 'Lead not found.');
    redirect('index.php');
}

$errors = [];
$leadSourcesMap = lead_sources_get_map($pdo);
if (!empty($lead['source']) && !array_key_exists($lead['source'], $leadSourcesMap)) {
    $leadSourcesMap[$lead['source']] = lead_source_guess_label($lead['source']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Quick AJAX status-only update from pipeline
    if (($_POST['quick_update'] ?? '') === '1') {
        header('Content-Type: application/json');

        $allowedStatuses = ['new', 'contacted', 'interested', 'future', 'closed_won', 'closed_lost'];
        $newStatus = $_POST['status'] ?? $lead['status'];
        $lostReason = trim($_POST['lost_reason'] ?? '');

        if (!in_array($newStatus, $allowedStatuses, true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Invalid status selected.']);
            exit;
        }

        $autoCloseAfter = (int) settings_get($pdo, 'auto_close_lost_after_followups', '0');
        if ($newStatus === 'closed_lost' && $autoCloseAfter > 0 && $lead['status'] !== 'closed_lost') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Cannot manually close to Lost. Leads auto-close after ' . $autoCloseAfter . ' follow-ups when this setting is enabled.']);
            exit;
        }

        if ($newStatus === 'closed_lost' && $lostReason === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Lost reason is required when closing a lead as lost.']);
            exit;
        }

        $lostReasonValue = $newStatus === 'closed_lost' ? $lostReason : null;
        $nowSql = app_now_sql();
        $pdo->prepare('UPDATE leads SET status=?, lost_reason=?, updated_at=?,
            closed_at = IF(? = ? AND closed_at IS NULL, ?, closed_at) WHERE id=?')
            ->execute([$newStatus, $lostReasonValue, $nowSql, 'closed_won', $newStatus, $nowSql, $id]);

        $activityNote = "Status updated to: " . str_replace('_', ' ', $newStatus) . ".";
        if ($newStatus === 'closed_lost') {
            $activityNote .= " Lost reason: " . $lostReasonValue;
        }
        $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')->execute([$id, $activityNote]);

        $oldStatusLabel = str_replace('_', ' ', (string) ($lead['status'] ?? ''));
        $newStatusLabel = str_replace('_', ' ', (string) $newStatus);
        $statusLogDescription = "Updated lead #$id status from $oldStatusLabel to $newStatusLabel.";
        if ($newStatus === 'closed_lost' && $lostReasonValue) {
            $statusLogDescription .= " Lost reason: $lostReasonValue.";
        }
        log_activity($pdo, 'updated_lead_status', 'lead', $id, $statusLogDescription);

        echo json_encode(['ok' => true]);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $alternativeNumber = trim($_POST['alternative_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $inquiry = $_POST['inquiry_type'] ?? 'daily';
    $vehicle = trim($_POST['vehicle_interest'] ?? '');
    $source = $_POST['source'] ?? $lead['source'];
    $assigned = trim($_POST['assigned_to'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? $lead['status'];
    $lostReason = trim($_POST['lost_reason'] ?? '');

    if (!$name)
        $errors['name'] = 'Name is required.';
    if (!$phone)
        $errors['phone'] = 'Phone is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Invalid email format.';
    if (!array_key_exists($source, $leadSourcesMap))
        $errors['source'] = 'Please select a valid lead source.';
    if (!in_array($status, ['new', 'contacted', 'interested', 'future', 'closed_won', 'closed_lost'], true))
        $errors['status'] = 'Please select a valid lead status.';
    if ($status === 'closed_lost' && !$lostReason)
        $errors['lost_reason'] = 'Please document the reason for closing as lost.';

    $autoCloseAfter = (int) settings_get($pdo, 'auto_close_lost_after_followups', '0');
    if ($status === 'closed_lost' && $autoCloseAfter > 0 && $lead['status'] !== 'closed_lost') {
        $errors['status'] = 'Cannot manually close to Lost. Leads auto-close after ' . $autoCloseAfter . ' follow-ups when this setting is enabled.';
    }

    if (empty($errors)) {
        $nowSql = app_now_sql();
        if ($supportsAlternativeNumber) {
            $pdo->prepare('UPDATE leads SET name=?,phone=?,alternative_number=?,email=?,inquiry_type=?,vehicle_interest=?,source=?,assigned_to=?,notes=?,status=?,lost_reason=?,updated_at=?,
                closed_at = IF(? = ? AND closed_at IS NULL, ?, closed_at) WHERE id=?')
                ->execute([$name, $phone, $alternativeNumber ?: null, $email ?: null, $inquiry, $vehicle ?: null, $source, $assigned ?: null, $notes ?: null, $status, $lostReason ?: null, $nowSql, 'closed_won', $status, $nowSql, $id]);
        } else {
            $pdo->prepare('UPDATE leads SET name=?,phone=?,email=?,inquiry_type=?,vehicle_interest=?,source=?,assigned_to=?,notes=?,status=?,lost_reason=?,updated_at=?,
                closed_at = IF(? = ? AND closed_at IS NULL, ?, closed_at) WHERE id=?')
                ->execute([$name, $phone, $email ?: null, $inquiry, $vehicle ?: null, $source, $assigned ?: null, $notes ?: null, $status, $lostReason ?: null, $nowSql, 'closed_won', $status, $nowSql, $id]);
        }

        if ($status !== $lead['status']) {
            $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')->execute([$id, "Status changed to: " . str_replace('_', ' ', $status) . "."]);
            $oldStatusLabel = str_replace('_', ' ', (string) ($lead['status'] ?? ''));
            $newStatusLabel = str_replace('_', ' ', (string) $status);
            log_activity($pdo, 'updated_lead_status', 'lead', $id, "Updated lead #$id status from $oldStatusLabel to $newStatusLabel.");
        } else {
            $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')->execute([$id, "Lead details updated."]);
            log_activity($pdo, 'updated_lead', 'lead', $id, "Updated lead #$id details.");
        }

        app_log('ACTION', "Updated lead (ID: $id)");
        flash('success', 'Lead updated.');
        redirect("show.php?id=$id");
    }

    // Re-populate for re-render
    $lead = array_merge($lead, ['name' => $name, 'phone' => $phone, 'alternative_number' => $alternativeNumber, 'email' => $email, 'inquiry_type' => $inquiry, 'vehicle_interest' => $vehicle, 'source' => $source, 'assigned_to' => $assigned, 'notes' => $notes, 'status' => $status, 'lost_reason' => $lostReason]);
}

$pageTitle = 'Edit Lead';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Leads</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <a href="show.php?id=<?= $id ?>" class="hover:text-white transition-colors">
            <?= e($lead['name']) ?>
        </a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">Edit</span>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 text-sm text-red-400 space-y-1">
            <?php foreach ($errors as $e): ?>
                <p>&bull;
                    <?= e($e) ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Lead Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <?php
                $fields = [
                    ['name', 'Full Name', 'text', true, 'Ahmed Al Rashid'],
                    ['phone', 'Phone / WhatsApp', 'text', true, '+971 50 123 4567'],
                    ['alternative_number', 'Alternative Number', 'text', false, '+971 52 123 4567'],
                    ['email', 'Email', 'email', false, 'ahmed@example.com'],
                    ['vehicle_interest', 'Vehicle Interest', 'text', false, 'e.g. SUV, Camry'],
                    ['assigned_to', 'Assigned To', 'text', false, 'Staff name'],
                ];
                if (!$supportsAlternativeNumber) {
                    $fields = array_values(array_filter($fields, static fn($f) => $f[0] !== 'alternative_number'));
                }
                foreach ($fields as [$fname, $label, $type, $req, $ph]):
                    $val = e($lead[$fname] ?? '');
                    $err = $errors[$fname] ?? '';
                    ?>
                    <div>
                        <label class="block text-sm text-mb-silver mb-2">
                            <?= $label ?>
                            <?= $req ? ' <span class="text-red-400">*</span>' : ' <span class="text-mb-subtle text-xs">(optional)</span>' ?>
                        </label>
                        <input type="<?= $type ?>" name="<?= $fname ?>" value="<?= $val ?>" <?= $req ? 'required' : '' ?>
                            placeholder="
                    <?= $ph ?>" class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white
                    focus:outline-none focus:border-mb-accent transition-colors text-sm">
                        <?php if ($err): ?>
                            <p class="text-red-400 text-xs mt-1">
                                <?= e($err) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div>
                    <label class="block text-sm text-mb-silver mb-2">Inquiry Type</label>
                    <select name="inquiry_type"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                        <?php foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'other' => 'Other'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $lead['inquiry_type'] === $v ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Lead Source</label>
                    <select name="source"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                        <?php foreach ($leadSourcesMap as $v => $l): ?>
                            <option value="<?= e($v) ?>" <?= $lead['source'] === $v ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errors['source'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['source']) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Status</label>
                    <select name="status" id="status-select"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                        <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'interested' => 'Interested', 'future' => 'Book Later', 'closed_won' => 'Closed Won', 'closed_lost' => 'Closed Lost'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $lead['status'] === $v ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($errors['status'] ?? ''): ?>
                        <p class="text-red-400 text-xs mt-1">
                            <?= e($errors['status']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lost Reason (shown only for closed_lost) -->
            <div id="lost-reason-wrap" style="display:<?= $lead['status'] === 'closed_lost' ? 'block' : 'none' ?>">
                <label class="block text-sm text-mb-silver mb-2">Lost Reason <span class="text-red-400">*</span></label>
                <textarea name="lost_reason" rows="2"
                    placeholder="Why did you lose this lead? (Price, competitor, not interested…)"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= e($lead['lost_reason'] ?? '') ?></textarea>
                <?php if ($errors['lost_reason'] ?? ''): ?>
                    <p class="text-red-400 text-xs mt-1">
                        <?= e($errors['lost_reason']) ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Closed Won notice -->
            <div id="won-notice" style="display:<?= $lead['status'] === 'closed_won' ? 'block' : 'none' ?>"
                class="bg-green-500/10 border border-green-500/30 rounded-lg p-3 text-green-400 text-sm">
                🎉 This lead is <strong>Closed Won!</strong> Go to the lead detail page to convert them into a Client.
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-sm text-mb-silver mb-2">Notes</label>
                <textarea name="notes" rows="3"
                    class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent transition-colors text-sm resize-none"><?= e($lead['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="flex items-center justify-end gap-4">
            <a href="show.php?id=<?= $id ?>"
                class="text-mb-silver hover:text-white transition-colors text-sm">Cancel</a>
            <button type="submit"
                class="bg-mb-accent text-white px-8 py-3 rounded-full hover:bg-mb-accent/80 transition-colors font-medium shadow-lg shadow-mb-accent/20">Save
                Changes</button>
        </div>
    </form>
</div>

<script>
    document.getElementById('status-select').addEventListener('change', function () {
        document.getElementById('lost-reason-wrap').style.display = this.value === 'closed_lost' ? 'block' : 'none';
        document.getElementById('won-notice').style.display = this.value === 'closed_won' ? 'block' : 'none';
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
