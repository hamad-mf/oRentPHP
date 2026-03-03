-- =============================================================
-- O Rent CRM — Full Server Migration Script
-- Run this once on the production database (u230826074_orentin)
-- Generated: 2026-02-28
-- Safe to run multiple times (uses IF NOT EXISTS / IF NOT COLUMN)
-- =============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. VEHICLES ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehicles (
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
    daily_rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monthly_rate DECIMAL(10,2) DEFAULT NULL,
    rate_1day DECIMAL(10,2) DEFAULT NULL COMMENT 'Special 1-day package rate',
    rate_7day DECIMAL(10,2) DEFAULT NULL COMMENT 'Special 7-day package rate',
    rate_15day DECIMAL(10,2) DEFAULT NULL COMMENT 'Special 15-day package rate',
    rate_30day DECIMAL(10,2) DEFAULT NULL COMMENT 'Special 30-day package rate',
    image_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS maintenance_started_at DATETIME DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS maintenance_expected_return DATE DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS maintenance_workshop_name VARCHAR(255) DEFAULT NULL;

-- ── 2. DOCUMENTS (vehicle files) ──────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 2b. VEHICLE IMAGES (uploaded photos) ─────────────────────
CREATE TABLE IF NOT EXISTS vehicle_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 3. CLIENTS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    rating TINYINT DEFAULT NULL COMMENT '1-5 stars',
    is_blacklisted TINYINT(1) NOT NULL DEFAULT 0,
    blacklist_reason TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    voucher_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 4. RESERVATIONS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    vehicle_id INT NOT NULL,
    rental_type VARCHAR(50) NOT NULL DEFAULT 'daily',
    status ENUM('pending','confirmed','active','completed') NOT NULL DEFAULT 'confirmed',
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    actual_end_date DATETIME DEFAULT NULL,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_discount_type ENUM('percent','amount') DEFAULT NULL,
    delivery_discount_value DECIMAL(10,2) DEFAULT 0.00,
    delivery_payment_method ENUM('cash','account','credit') DEFAULT NULL,
    delivery_paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    overdue_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    km_limit INT DEFAULT NULL,
    extra_km_price DECIMAL(10,2) DEFAULT NULL,
    km_driven INT DEFAULT NULL,
    km_overage_charge DECIMAL(10,2) DEFAULT 0.00,
    damage_charge DECIMAL(10,2) DEFAULT 0.00,
    additional_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    chellan_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Traffic fine charged at return',
    discount_type ENUM('percent','amount') DEFAULT NULL,
    discount_value DECIMAL(10,2) DEFAULT 0.00,
    voucher_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    return_voucher_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    return_payment_method ENUM('cash','account','credit') DEFAULT NULL,
    return_paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    early_return_credit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    voucher_credit_issued DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deposit_collected DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    deposit_returned DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 5. CLIENT VOUCHER TRANSACTIONS ────────────────────────────
CREATE TABLE IF NOT EXISTS client_voucher_transactions (
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

-- ── 6. VEHICLE INSPECTIONS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehicle_inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    type ENUM('delivery','return') NOT NULL,
    fuel_level INT NOT NULL DEFAULT 100 COMMENT 'Percentage 0-100',
    mileage INT NOT NULL DEFAULT 0,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 7. INSPECTION PHOTOS ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS inspection_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    view_name VARCHAR(50) NOT NULL COMMENT 'front, back, left, right, interior',
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES vehicle_inspections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 8. STAFF ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    salary DECIMAL(10,2) DEFAULT NULL,
    joined_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    id_proof_path VARCHAR(500) DEFAULT NULL COMMENT 'Path to uploaded ID proof image',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 9. USERS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    staff_id INT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 10. STAFF PERMISSIONS ────────────────────────────────────
CREATE TABLE IF NOT EXISTS staff_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission VARCHAR(100) NOT NULL,
    UNIQUE KEY unique_user_perm (user_id, permission),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 11. STAFF ACTIVITY LOG ───────────────────────────────────
CREATE TABLE IF NOT EXISTS staff_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 12. INVESTMENTS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    type ENUM('income','expense') NOT NULL DEFAULT 'expense',
    description TEXT DEFAULT NULL,
    investment_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 13. GPS TRACKING ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS gps_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    tracker_id VARCHAR(100) DEFAULT NULL,
    last_location VARCHAR(255) DEFAULT NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 14. PAPERS (Vehicle Docs/Registrations) ───────────────────
CREATE TABLE IF NOT EXISTS papers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    expiry_date DATE DEFAULT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 15. EXPENSES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) DEFAULT NULL,
    expense_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 16. CHALLANS (Traffic Fines — standalone records) ─────────
CREATE TABLE IF NOT EXISTS challans (
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

-- ── 17. DAMAGE COSTS ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS damage_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 18. SYSTEM SETTINGS ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 19. LEADS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS leads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    inquiry_type ENUM('daily','weekly','monthly','other') NOT NULL DEFAULT 'daily',
    vehicle_interest VARCHAR(255) DEFAULT NULL,
    source VARCHAR(100) DEFAULT NULL,
    assigned_to VARCHAR(255) DEFAULT NULL,
    assigned_staff_id INT DEFAULT NULL,
    status ENUM('new','contacted','interested','future','closed_won','closed_lost') NOT NULL DEFAULT 'new',
    lost_reason TEXT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 20. LEAD ACTIVITIES ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS lead_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 21. LEAD FOLLOWUPS ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS lead_followups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lead_id INT NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    scheduled_at DATETIME DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_done TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 22. NOTIFICATIONS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT DEFAULT NULL,
    type VARCHAR(50) DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================================
-- ALTER TABLE MIGRATIONS (for existing production databases)
-- Each ALTER is wrapped in a stored procedure to skip if the
-- column already exists — safe to run multiple times.
-- =============================================================

-- chellan_amount on reservations
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS chellan_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00
    COMMENT 'Traffic fine charged at return'
    AFTER additional_charge;

-- delivery discount columns on reservations
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS delivery_discount_type ENUM('percent','amount') DEFAULT NULL
    AFTER delivery_charge;

ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS delivery_discount_value DECIMAL(10,2) DEFAULT 0.00
    AFTER delivery_discount_type;

-- deposit columns on reservations
ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS deposit_collected DECIMAL(10,2) NOT NULL DEFAULT 0.00
    AFTER voucher_credit_issued;

ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS deposit_returned DECIMAL(10,2) NOT NULL DEFAULT 0.00
    AFTER deposit_collected;

-- id_proof_path on staff
ALTER TABLE staff
    ADD COLUMN IF NOT EXISTS id_proof_path VARCHAR(500) DEFAULT NULL
    COMMENT 'Path to uploaded ID proof image'
    AFTER notes;

-- assigned_staff_id on leads (for CRM)
ALTER TABLE leads
    ADD COLUMN IF NOT EXISTS assigned_staff_id INT DEFAULT NULL
    AFTER assigned_to;

-- lost_reason on leads
ALTER TABLE leads
    ADD COLUMN IF NOT EXISTS lost_reason TEXT DEFAULT NULL
    AFTER status;

-- Ensure leads status ENUM includes all pipeline stages
ALTER TABLE leads MODIFY COLUMN status
    ENUM('new','contacted','interested','future','closed_won','closed_lost')
    NOT NULL DEFAULT 'new';

-- =============================================================
-- DEFAULT SYSTEM SETTINGS
-- =============================================================
INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('late_return_rate_per_hour', '0');
INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('daily_target', '0');
INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('deposit_percent', '0');
INSERT IGNORE INTO system_settings (`key`, `value`) VALUES
('lead_sources', '[{"value":"walk_in","label":"Walk-in"},{"value":"phone","label":"Phone Call"},{"value":"whatsapp","label":"WhatsApp"},{"value":"instagram","label":"Instagram"},{"value":"referral","label":"Referral"},{"value":"website","label":"Website"},{"value":"other","label":"Other"}]');

-- =============================================================
-- DIRECTORY NOTE
-- Create these folders on the server if they do not exist
-- and ensure they are writable by the web server (chmod 755):
--   /uploads/vehicles/      (vehicle photo uploads)
--   /uploads/documents/     (vehicle document uploads)
--   /uploads/staff_docs/    (staff ID proof uploads)
-- =============================================================

SET FOREIGN_KEY_CHECKS = 1;
