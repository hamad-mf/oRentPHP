-- ============================================================
-- oRentPHP -- Full Database Wipe + Fresh Start
--   THIS DELETES ALL DATA. Run the Excel export first!
-- Steps:
--   1. (LOCAL)      phpMyAdmin > select DB > SQL tab > paste > Go
--   2. (PRODUCTION) Hostinger phpMyAdmin > select DB > SQL tab > paste > Go
-- After running this, log in with: admin / admin123
-- Then change the admin password immediately.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Add DROP for missing tables
DROP TABLE IF EXISTS emi_schedules;
DROP TABLE IF EXISTS emi_investments;
DROP TABLE IF EXISTS hope_daily_predictions;
DROP TABLE IF EXISTS hope_daily_targets;
DROP TABLE IF EXISTS monthly_targets;
DROP TABLE IF EXISTS payroll;
-- ============================================================
-- oRentPHP  ” Full Database Wipe + Fresh Start
--  š ï¸  THIS DELETES ALL DATA. Run the Excel export first!
--
-- Steps:
--   1. (LOCAL)      phpMyAdmin   ’ select 'orent' DB   ’ SQL tab   ’ paste   ’ Go
--   2. (PRODUCTION) Hostinger phpMyAdmin   ’ select your DB   ’ SQL tab   ’ paste   ’ Go
--
-- After running this, log in with: admin / admin123
-- Then change the admin password immediately.
-- ============================================================

--  ”  ”  Drop all tables  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
DROP TABLE IF EXISTS ledger_entries;
DROP TABLE IF EXISTS bank_accounts;
DROP TABLE IF EXISTS inspection_photos;
DROP TABLE IF EXISTS vehicle_inspections;
DROP TABLE IF EXISTS lead_followups;
DROP TABLE IF EXISTS lead_activities;
DROP TABLE IF EXISTS client_voucher_transactions;
DROP TABLE IF EXISTS staff_activity_log;
DROP TABLE IF EXISTS staff_permissions;
DROP TABLE IF EXISTS staff_tasks;
DROP TABLE IF EXISTS attendance_breaks;
DROP TABLE IF EXISTS staff_attendance;
DROP TABLE IF EXISTS challans;
DROP TABLE IF EXISTS reservation_extensions;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS vehicle_images;
DROP TABLE IF EXISTS documents;
DROP TABLE IF EXISTS papers;
DROP TABLE IF EXISTS hope_daily_predictions;
DROP TABLE IF EXISTS hope_daily_targets;
DROP TABLE IF EXISTS gps_daily_checks;
DROP TABLE IF EXISTS gps_tracking;
DROP TABLE IF EXISTS vehicle_requests;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS client_reviews;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS emi_schedules;
DROP TABLE IF EXISTS emi_investments;
DROP TABLE IF EXISTS damage_costs;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS staff;

--  ”  ”  Recreate schema  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 

CREATE TABLE vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(255) NOT NULL,
    model VARCHAR(255) NOT NULL,
    year INT NOT NULL,
    license_plate VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(50) DEFAULT NULL,
    vin VARCHAR(50) DEFAULT NULL,
    status ENUM('available','rented','maintenance') NOT NULL DEFAULT 'available',
    maintenance_started_at DATETIME DEFAULT NULL,
    maintenance_expected_return DATE DEFAULT NULL,
    maintenance_workshop_name VARCHAR(255) DEFAULT NULL,
    parts_due_notes TEXT DEFAULT NULL,
    second_key_location VARCHAR(255) DEFAULT NULL,
    original_documents_location VARCHAR(255) DEFAULT NULL,
    daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monthly_rate DECIMAL(10,2) DEFAULT NULL,
    rate_1day DECIMAL(10,2) DEFAULT NULL,
    rate_7day DECIMAL(10,2) DEFAULT NULL,
    rate_15day DECIMAL(10,2) DEFAULT NULL,
    rate_30day DECIMAL(10,2) DEFAULT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE vehicle_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    -- Cancellation fields
    cancellation_reason TEXT DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    cancellation_by INT DEFAULT NULL,
    refund_amount DECIMAL(10,2) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    -- Cancellation fields
    cancellation_reason TEXT DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    cancellation_by INT DEFAULT NULL,
    refund_amount DECIMAL(10,2) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    expiry_date DATE DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    -- Cancellation fields
    cancellation_reason TEXT DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    cancellation_by INT DEFAULT NULL,
    refund_amount DECIMAL(10,2) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE gps_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT DEFAULT NULL,
    vehicle_id INT NOT NULL,
    tracker_id VARCHAR(100) DEFAULT NULL,
    last_location VARCHAR(255) DEFAULT NULL,
    tracking_active TINYINT(1) NOT NULL DEFAULT 1,
    last_seen TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gps_reservation_id (reservation_id),
    INDEX idx_gps_vehicle_id (vehicle_id),
    INDEX idx_gps_tracking_active (tracking_active),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    -- Cancellation fields
    cancellation_reason TEXT DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    cancellation_by INT DEFAULT NULL,
    refund_amount DECIMAL(10,2) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE gps_daily_checks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    check_date DATE NOT NULL,
    check_slot TINYINT NOT NULL,
    tracking_active TINYINT(1) NOT NULL DEFAULT 1,
    last_location VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_gps_daily_slot (reservation_id, check_date, check_slot),
    INDEX idx_gps_daily_vehicle (vehicle_id),
    INDEX idx_gps_daily_date (check_date),
    INDEX idx_gps_daily_reservation (reservation_id)
) ENGINE=InnoDB;

CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) DEFAULT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    rating TINYINT DEFAULT NULL,
    is_blacklisted TINYINT(1) NOT NULL DEFAULT 0,
    blacklist_reason TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    proof_file VARCHAR(500) DEFAULT NULL,
    voucher_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE client_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    reservation_id INT NOT NULL,
    rating TINYINT NOT NULL,
    review TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    UNIQUE KEY uq_reservation_review (reservation_id)
) ENGINE=InnoDB;

CREATE TABLE reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    rental_type VARCHAR(50) NOT NULL DEFAULT 'daily',
    status ENUM('pending','confirmed','active','completed','cancelled') NOT NULL DEFAULT 'confirmed',
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    actual_end_date DATETIME DEFAULT NULL,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    extension_paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_manual_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_discount_type ENUM('percent','amount') DEFAULT NULL,
    delivery_discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_payment_method ENUM('cash','account','credit') DEFAULT NULL,
    delivery_paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_deposit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deposit_returned DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    overdue_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    chellan_amount DECIMAL(10,2) DEFAULT 0.00,
    km_limit INT DEFAULT NULL,
    extra_km_price DECIMAL(10,2) DEFAULT NULL,
    km_driven INT DEFAULT NULL,
    km_overage_charge DECIMAL(10,2) DEFAULT 0.00,
    damage_charge DECIMAL(10,2) DEFAULT 0.00,
    additional_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_type ENUM('percent','amount') DEFAULT NULL,
    discount_value DECIMAL(10,2) DEFAULT 0.00,
    voucher_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    return_voucher_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    return_payment_method ENUM('cash','account','credit') DEFAULT NULL,
    return_paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    early_return_credit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    voucher_credit_issued DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
    -- Cancellation fields
    cancellation_reason TEXT DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    cancellation_by INT DEFAULT NULL,
    refund_amount DECIMAL(10,2) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE reservation_extensions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    old_end_date DATETIME NOT NULL,
    base_start_date DATETIME NOT NULL,
    new_end_date DATETIME NOT NULL,
    rental_type ENUM('daily','1day','7day','15day','30day','monthly') NOT NULL DEFAULT 'daily',
    days INT NOT NULL,
    rate_per_day DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash','account','credit') DEFAULT NULL,
    bank_account_id INT DEFAULT NULL,
    ledger_entry_id INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reservation (reservation_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE client_voucher_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    reservation_id INT DEFAULT NULL,
    type ENUM('credit','debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_client_created (client_id, created_at),
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE vehicle_inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    type ENUM('delivery','return') NOT NULL,
    fuel_level INT NOT NULL DEFAULT 100,
    mileage INT NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inspection_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    view_name VARCHAR(50) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES vehicle_inspections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vehicle_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT DEFAULT NULL,
    client_name_free VARCHAR(120) DEFAULT NULL,
    vehicle_brand VARCHAR(80) NOT NULL,
    vehicle_model VARCHAR(80) NOT NULL,
    people_count INT NOT NULL DEFAULT 1,
    notes TEXT DEFAULT NULL,
    status ENUM('pending','contacted','acquired','cancelled') NOT NULL DEFAULT 'pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE challans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT DEFAULT NULL,
    client_id INT DEFAULT NULL,
    challan_no VARCHAR(100) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    issue_date DATE DEFAULT NULL,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE damage_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) DEFAULT NULL,
    expense_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE emi_investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    lender VARCHAR(255) DEFAULT NULL,
    total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    down_payment DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    emi_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tenure_months INT NOT NULL DEFAULT 1,
    start_date DATE NOT NULL,
    notes TEXT DEFAULT NULL,
    down_payment_account_id INT DEFAULT NULL,
    down_payment_ledger_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE emi_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investment_id INT NOT NULL,
    installment_no INT NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    paid_date DATE DEFAULT NULL,
    bank_account_id INT DEFAULT NULL,
    ledger_entry_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (investment_id) REFERENCES emi_investments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    salary DECIMAL(10,2) DEFAULT NULL,
    joined_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    id_proof_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    staff_id INT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE staff_permissions (
    user_id INT NOT NULL,
    permission VARCHAR(100) NOT NULL,
    PRIMARY KEY (user_id, permission)
) ENGINE=InnoDB;

CREATE TABLE staff_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE staff_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date DATE NOT NULL,
    punch_in DATETIME DEFAULT NULL,
    pin_warning TINYINT(1) NOT NULL DEFAULT 0,
    late_reason TEXT DEFAULT NULL,
    punch_in_lat DECIMAL(10,7) DEFAULT NULL,
    punch_in_lng DECIMAL(10,7) DEFAULT NULL,
    punch_in_address VARCHAR(500) DEFAULT NULL,
    punch_out DATETIME DEFAULT NULL,
    pout_warning TINYINT(1) NOT NULL DEFAULT 0,
    early_punchout_reason TEXT DEFAULT NULL,
    punch_out_lat DECIMAL(10,7) DEFAULT NULL,
    punch_out_lng DECIMAL(10,7) DEFAULT NULL,
    punch_out_address VARCHAR(500) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    UNIQUE KEY unique_user_date (user_id, date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE attendance_breaks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attendance_id INT NOT NULL,
    break_start DATETIME NOT NULL,
    break_end DATETIME DEFAULT NULL,
    reason TEXT DEFAULT NULL,
    start_lat DECIMAL(10,7) DEFAULT NULL,
    start_lng DECIMAL(10,7) DEFAULT NULL,
    start_address VARCHAR(500) DEFAULT NULL,
    end_lat DECIMAL(10,7) DEFAULT NULL,
    end_lng DECIMAL(10,7) DEFAULT NULL,
    end_address VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attendance_id) REFERENCES staff_attendance(id) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE staff_tasks (
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
) ENGINE=InnoDB;

CREATE TABLE leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    source VARCHAR(100) DEFAULT NULL,
    inquiry_type VARCHAR(100) DEFAULT NULL,
    vehicle_interest VARCHAR(255) DEFAULT NULL,
    status ENUM('new','contacted','interested','future','closed_won','closed_lost') NOT NULL DEFAULT 'new',
    assigned_to VARCHAR(255) DEFAULT NULL,
    assigned_staff_id INT DEFAULT NULL,
    converted_client_id INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    lost_reason TEXT DEFAULT NULL,
    closed_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE lead_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    type VARCHAR(50) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    done_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE lead_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    type VARCHAR(50) DEFAULT NULL,
    note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    reservation_id INT DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    related_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE system_settings (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

--  ”  ”  Ledger tables  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
CREATE TABLE bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100) DEFAULT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ledger_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    txn_type ENUM('income','expense','adjustment') NOT NULL DEFAULT 'income',
    category VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_mode VARCHAR(20) DEFAULT NULL,
    bank_account_id INT DEFAULT NULL,
    source_type VARCHAR(50) NOT NULL DEFAULT 'manual',
    source_id INT DEFAULT NULL,
    source_event VARCHAR(50) DEFAULT NULL,
    idempotency_key VARCHAR(120) DEFAULT NULL,
    posted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_idempotency (idempotency_key),
    INDEX idx_txn_type (txn_type),
    INDEX idx_posted_at (posted_at),
    INDEX idx_bank_account (bank_account_id),
    INDEX idx_source (source_type, source_id),
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB;

--  ”  ”  Default system settings  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
INSERT INTO system_settings (`key`, `value`) VALUES ('late_return_rate_per_hour', '0')
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO system_settings (`key`, `value`) VALUES ('daily_target', '0')
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO system_settings (`key`, `value`) VALUES ('deposit_percentage', '0')
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);
INSERT INTO system_settings (`key`, `value`) VALUES ('company_name', 'Orentincars')
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

--  ”  ”  Seed default bank account  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
INSERT INTO bank_accounts (name) VALUES ('Bank Account');

--  ”  ”  Admin account  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ”  ” 
-- Password for the seed admin below is: admin123
INSERT INTO users (name, username, password_hash, role, is_active)
VALUES (
    'Admin',
    'admin',
    '$2y$10$fD/xMKFUGHUlAeW0R9seXeOamoHNmCk.IdnOm1PmckjwP5uABlCnK',
    'admin',
    1
);
-- After login, go to Settings   ’ Change Password to set your real password.
-- ── payroll ──────────────
CREATE TABLE IF NOT EXISTS payroll (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  month TINYINT NOT NULL,
  year SMALLINT NOT NULL,
  basic_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
  incentive DECIMAL(10,2) NOT NULL DEFAULT 0,
  allowances DECIMAL(10,2) NOT NULL DEFAULT 0,
  deductions DECIMAL(10,2) NOT NULL DEFAULT 0,
  net_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes TEXT DEFAULT NULL,
  status ENUM('Pending','Paid') NOT NULL DEFAULT 'Pending',
  payment_date DATETIME DEFAULT NULL,
  paid_from_account_id INT DEFAULT NULL,
  ledger_entry_id INT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY user_id (user_id),
  KEY paid_from (paid_from_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  monthly_targets 
CREATE TABLE IF NOT EXISTS monthly_targets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  target_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY period_start (period_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  hope_daily_targets
CREATE TABLE IF NOT EXISTS hope_daily_targets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target_date DATE NOT NULL,
  target_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_by INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_target_date (target_date),
  KEY idx_target_date (target_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  hope_daily_predictions
CREATE TABLE IF NOT EXISTS hope_daily_predictions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target_date DATE NOT NULL,
  label VARCHAR(255) NOT NULL,
  amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_by INT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_target_date (target_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  emi_investments 
CREATE TABLE IF NOT EXISTS emi_investments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  lender VARCHAR(255) DEFAULT NULL,
  total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  down_payment DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  loan_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  emi_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  tenure_months INT NOT NULL DEFAULT 1,
  start_date DATE NOT NULL,
  notes TEXT DEFAULT NULL,
  down_payment_account_id INT DEFAULT NULL,
  down_payment_ledger_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  emi_schedules 
CREATE TABLE IF NOT EXISTS emi_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  investment_id INT NOT NULL,
  installment_no INT NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
  paid_date DATE DEFAULT NULL,
  bank_account_id INT DEFAULT NULL,
  ledger_entry_id INT DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY investment_id (investment_id),
  CONSTRAINT emi_sch_fk FOREIGN KEY (investment_id) REFERENCES emi_investments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--  Seed: updated settings (includes all 2026 keys) 
INSERT INTO system_settings (`key`, `value`) VALUES
  ('late_return_rate_per_hour', '0'),
  ('deposit_percentage', '0'),
  ('delivery_charge_default', '0'),
  ('lead_incentive_per_lead', '0'),
  ('delivery_incentive_per_delivery', '0'),
  ('per_page', '25')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

SET FOREIGN_KEY_CHECKS = 1;
