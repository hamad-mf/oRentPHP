<?php
/**
 * schema_diff.php
 * Compares the Codex-provided schema against live INFORMATION_SCHEMA.
 * Outputs what's MISSING from live DB (need to ADD) and what's EXTRA in live DB (safe to ignore).
 * Run: php schema_diff.php
 */

$pdo = new PDO("mysql:host=127.0.0.1;dbname=orent;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// ── Codex schema (what the code actually needs) ────────────────
$codex = [
    'bank_accounts' => ['id', 'name', 'bank_name', 'account_number', 'balance', 'is_active', 'created_at', 'updated_at'],
    'challans' => ['id', 'vehicle_id', 'client_id', 'challan_no', 'amount', 'issue_date', 'status', 'notes', 'created_at'],
    'clients' => ['id', 'name', 'email', 'phone', 'address', 'rating', 'is_blacklisted', 'blacklist_reason', 'notes', 'proof_file', 'voucher_balance', 'created_at', 'updated_at'],
    'client_voucher_transactions' => ['id', 'client_id', 'reservation_id', 'type', 'amount', 'note', 'created_at'],
    'damage_costs' => ['id', 'item_name', 'cost', 'created_at', 'updated_at'],
    'documents' => ['id', 'vehicle_id', 'title', 'type', 'file_path', 'created_at'],
    'emi_investments' => ['id', 'title', 'lender', 'total_cost', 'down_payment', 'loan_amount', 'emi_amount', 'tenure_months', 'start_date', 'notes', 'down_payment_account_id', 'down_payment_ledger_id', 'created_at'],
    'emi_schedules' => ['id', 'investment_id', 'installment_no', 'due_date', 'amount', 'status', 'paid_date', 'bank_account_id', 'ledger_entry_id', 'notes'],
    'expenses' => ['id', 'title', 'amount', 'category', 'expense_date', 'notes', 'created_at'],
    'gps_daily_checks' => ['id', 'reservation_id', 'vehicle_id', 'check_date', 'check_slot', 'tracking_active', 'last_location', 'notes', 'updated_by', 'updated_at', 'created_at'],
    'gps_tracking' => ['id', 'reservation_id', 'vehicle_id', 'tracker_id', 'last_location', 'tracking_active', 'last_seen', 'notes', 'updated_by', 'updated_at'],
    'inspection_photos' => ['id', 'inspection_id', 'view_name', 'file_path', 'created_at'],
    'investments' => ['id', 'title', 'amount', 'type', 'description', 'investment_date', 'created_at'],
    'leads' => ['id', 'name', 'phone', 'email', 'source', 'inquiry_type', 'vehicle_interest', 'status', 'assigned_to', 'assigned_staff_id', 'converted_client_id', 'notes', 'lost_reason', 'created_at', 'updated_at', 'closed_at'],
    'lead_activities' => ['id', 'lead_id', 'user_id', 'type', 'note', 'created_at'],
    'lead_followups' => ['id', 'lead_id', 'user_id', 'type', 'note', 'notes', 'scheduled_at', 'is_done', 'done_at', 'created_at'],
    'ledger_entries' => ['id', 'txn_type', 'category', 'description', 'amount', 'payment_mode', 'bank_account_id', 'source_type', 'source_id', 'source_event', 'idempotency_key', 'voided_at', 'voided_by', 'void_reason', 'posted_at', 'created_by', 'created_at'],
    'notifications' => ['id', 'type', 'message', 'reservation_id', 'is_read', 'related_id', 'created_at'],
    'papers' => ['id', 'vehicle_id', 'title', 'expiry_date', 'file_path', 'notes', 'created_at'],
    'payroll' => ['id', 'user_id', 'month', 'year', 'basic_salary', 'incentive', 'allowances', 'deductions', 'net_salary', 'status', 'payment_date', 'paid_from_account_id', 'ledger_entry_id', 'notes', 'created_by', 'created_at'],
    'reservation_extensions' => ['id', 'reservation_id', 'old_end_date', 'base_start_date', 'new_end_date', 'rental_type', 'days', 'rate_per_day', 'amount', 'payment_method', 'bank_account_id', 'ledger_entry_id', 'created_by', 'created_at'],
    'reservations' => ['id', 'client_id', 'vehicle_id', 'rental_type', 'start_date', 'end_date', 'actual_end_date', 'overdue_amount', 'pickup_location', 'dropoff_location', 'status', 'total_amount', 'total_price', 'amount_paid', 'extension_paid_amount', 'delivery_charge', 'delivery_deposit', 'deposit_returned', 'voucher_code', 'voucher_discount', 'late_return_charge', 'damage_charge', 'notes', 'delivery_notes', 'return_notes', 'payment_mode', 'payment_reference', 'delivery_payment_mode', 'delivery_payment_ref', 'delivery_voucher_code', 'delivery_voucher_discount', 'return_payment_mode', 'return_payment_ref', 'return_voucher_code', 'return_voucher_discount', 'voucher_credit_issued', 'created_at', 'updated_at', 'delivery_manual_amount', 'delivery_payment_method', 'delivery_paid_amount', 'delivery_charge_prepaid', 'delivery_prepaid_payment_method', 'delivery_prepaid_bank_account_id', 'return_payment_method', 'return_paid_amount', 'delivery_discount_type', 'delivery_discount_value', 'voucher_applied', 'return_voucher_applied', 'early_return_credit', 'additional_charge', 'deposit_amount', 'km_limit', 'extra_km_price', 'km_driven', 'delivery_bank_account_id', 'return_bank_account_id', 'odometer_start', 'odometer_end', 'fuel_level_start', 'fuel_level_end', 'km_overage_charge', 'chellan_amount', 'discount_type', 'discount_value'],
    'staff' => ['id', 'name', 'role', 'phone', 'email', 'salary', 'joined_date', 'notes', 'id_proof_path', 'created_at', 'updated_at'],
    'staff_activity_log' => ['id', 'user_id', 'action', 'entity_type', 'entity_id', 'description', 'created_at'],
    'staff_attendance' => ['id', 'user_id', 'date', 'punch_in', 'pin_warning', 'punch_out', 'pout_warning', 'notes'],
    'staff_permissions' => ['user_id', 'permission'],
    'system_settings' => ['key', 'value', 'updated_at'],
    'users' => ['id', 'name', 'username', 'password_hash', 'role', 'staff_id', 'is_active', 'created_at'],
    'vehicles' => ['id', 'brand', 'model', 'year', 'license_plate', 'color', 'vin', 'status', 'maintenance_started_at', 'maintenance_expected_return', 'maintenance_workshop_name', 'parts_due_notes', 'second_key_location', 'original_documents_location', 'daily_rate', 'monthly_rate', 'rate_1day', 'rate_7day', 'rate_15day', 'rate_30day', 'image_url', 'created_at', 'updated_at'],
    'vehicle_images' => ['id', 'vehicle_id', 'file_path', 'sort_order', 'created_at'],
    'vehicle_inspections' => ['id', 'reservation_id', 'type', 'fuel_level', 'mileage', 'notes', 'created_at'],
    'vehicle_requests' => ['id', 'client_id', 'client_name_free', 'vehicle_brand', 'vehicle_model', 'people_count', 'notes', 'status', 'requested_at', 'updated_at'],
];

// ── Fetch live schema ──────────────────────────────────────────
$live = [];
foreach ($pdo->query("SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='orent' ORDER BY TABLE_NAME, ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $live[$r['TABLE_NAME']][] = $r['COLUMN_NAME'];
}

// ── Diff ───────────────────────────────────────────────────────
$missingInLive = []; // in Codex, not in DB → need ALTER
$missingInCodex = []; // in DB, not in Codex → just informational
$missingTables = []; // table in Codex, not in DB → need CREATE

foreach ($codex as $tbl => $cols) {
    if (!isset($live[$tbl])) {
        $missingTables[] = $tbl;
        continue;
    }
    $liveSet = array_flip($live[$tbl]);
    foreach ($cols as $col) {
        if (!isset($liveSet[$col])) {
            $missingInLive[$tbl][] = $col;
        }
    }
}

// ── Output ─────────────────────────────────────────────────────
echo "\n=== SCHEMA DIFF: Codex vs Live DB ===\n\n";

if ($missingTables) {
    echo "❌ TABLES MISSING FROM LIVE DB (need CREATE):\n";
    foreach ($missingTables as $t)
        echo "  - $t\n";
    echo "\n";
}

if ($missingInLive) {
    echo "⚠  COLUMNS IN CODEX BUT NOT IN LIVE DB (need ALTER TABLE):\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($missingInLive as $tbl => $cols) {
        echo "\nALTER TABLE `$tbl`\n";
        foreach ($cols as $col) {
            echo "  ADD COLUMN IF NOT EXISTS `$col` ???  -- needs correct type\n";
        }
        echo ";\n";
    }
} else {
    echo "✅ No missing columns — live DB matches Codex schema fully!\n";
}

echo "\n" . str_repeat('─', 60) . "\n";
echo "Live DB has " . array_sum(array_map('count', $live)) . " cols across " . count($live) . " tables.\n";
echo "Codex schema has " . array_sum(array_map('count', $codex)) . " cols across " . count($codex) . " tables.\n";
