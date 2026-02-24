<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();

$id = (int) ($_REQUEST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM leads WHERE id=?');
$stmt->execute([$id]);
$lead = $stmt->fetch();
if (!$lead) {
    flash('error', 'Lead not found.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Quick AJAX status-only update from pipeline
    if (($_POST['quick_update'] ?? '') === '1') {
        header('Content-Type: application/json');

        $allowedStatuses = ['new', 'contacted', 'interested', 'negotiation', 'closed_won', 'closed_lost'];
        $newStatus = $_POST['status'] ?? $lead['status'];
        $lostReason = trim($_POST['lost_reason'] ?? '');

        if (!in_array($newStatus, $allowedStatuses, true)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Invalid status selected.']);
            exit;
        }

        if ($newStatus === 'closed_lost' && $lostReason === '') {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => 'Lost reason is required when closing a lead as lost.']);
            exit;
        }

        $lostReasonValue = $newStatus === 'closed_lost' ? $lostReason : null;
        $pdo->prepare('UPDATE leads SET status=?, lost_reason=?, updated_at=NOW() WHERE id=?')
            ->execute([$newStatus, $lostReasonValue, $id]);

        $activityNote = "Status updated to: " . str_replace('_', ' ', $newStatus) . ".";
        if ($newStatus === 'closed_lost') {
            $activityNote .= " Lost reason: " . $lostReasonValue;
        }
        $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')->execute([$id, $activityNote]);
        echo json_encode(['ok' => true]);
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $inquiry = $_POST['inquiry_type'] ?? 'daily';
    $vehicle = trim($_POST['vehicle_interest'] ?? '');
    $source = $_POST['source'] ?? 'phone';
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
    if ($status === 'closed_lost' && !$lostReason)
        $errors['lost_reason'] = 'Please document the reason for closing as lost.';

    if (empty($errors)) {
        $pdo->prepare('UPDATE leads SET name=?,phone=?,email=?,inquiry_type=?,vehicle_interest=?,source=?,assigned_to=?,notes=?,status=?,lost_reason=?,updated_at=NOW() WHERE id=?')
            ->execute([$name, $phone, $email ?: null, $inquiry, $vehicle ?: null, $source, $assigned ?: null, $notes ?: null, $status, $lostReason ?: null, $id]);

        if ($status !== $lead['status']) {
            $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')->execute([$id, "Status changed to: " . str_replace('_', ' ', $status) . "."]);
        } else {
            $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)')->execute([$id, "Lead details updated."]);
        }

        flash('success', 'Lead updated.');
        redirect("show.php?id=$id");
    }

    // Re-populate for re-render
    $lead = array_merge($lead, ['name' => $name, 'phone' => $phone, 'email' => $email, 'inquiry_type' => $inquiry, 'vehicle_interest' => $vehicle, 'source' => $source, 'assigned_to' => $assigned, 'notes' => $notes, 'status' => $status, 'lost_reason' => $lostReason]);
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
                    ['email', 'Email', 'email', false, 'ahmed@example.com'],
                    ['vehicle_interest', 'Vehicle Interest', 'text', false, 'e.g. SUV, Camry'],
                    ['assigned_to', 'Assigned To', 'text', false, 'Staff name'],
                ];
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
                        <?php foreach (['walk_in' => 'Walk-in', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'referral' => 'Referral', 'website' => 'Website', 'other' => 'Other'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $lead['source'] === $v ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Status</label>
                    <select name="status" id="status-select"
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-white focus:outline-none focus:border-mb-accent text-sm">
                        <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'interested' => 'Interested', 'negotiation' => 'Negotiation', 'closed_won' => 'Closed Won', 'closed_lost' => 'Closed Lost'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $lead['status'] === $v ? 'selected' : '' ?>>
                                <?= $l ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
