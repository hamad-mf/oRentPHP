-- ============================================================
-- O Rent CRM — MySQL Database Schema
-- Import this file into phpMyAdmin (u230826074_orentin)
-- NOTE: Do NOT include CREATE DATABASE — Hostinger creates it for you.
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── Vehicles ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehicles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(255) NOT NULL,
    model VARCHAR(255) NOT NULL,
    year INT NOT NULL,
    license_plate VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(50) DEFAULT NULL,
    vin VARCHAR(50) DEFAULT NULL,
    status ENUM('available','rented','maintenance') NOT NULL DEFAULT 'available',
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

-- ── Documents (vehicle files) ────────────────────────────────
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Clients ──────────────────────────────────────────────────
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

-- ── Reservations ─────────────────────────────────────────────
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
    overdue_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    km_limit INT DEFAULT NULL,
    extra_km_price DECIMAL(10,2) DEFAULT NULL,
    km_driven INT DEFAULT NULL,
    km_overage_charge DECIMAL(10,2) DEFAULT 0.00,
    damage_charge DECIMAL(10,2) DEFAULT 0.00,
    discount_type ENUM('percent','amount') DEFAULT NULL,
    discount_value DECIMAL(10,2) DEFAULT 0.00,
    voucher_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    return_voucher_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    early_return_credit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    voucher_credit_issued DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€ Client Voucher Transactions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

-- ── Vehicle Inspections ───────────────────────────────────────
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

-- ── Inspection Photos ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS inspection_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    view_name VARCHAR(50) NOT NULL COMMENT 'front, back, left, right, interior',
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES vehicle_inspections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Staff ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    role VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    salary DECIMAL(10,2) DEFAULT NULL,
    joined_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Investments ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    type ENUM('income','expense') NOT NULL DEFAULT 'expense',
    description TEXT DEFAULT NULL,
    investment_date DATE DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── GPS Tracking ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS gps_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT NOT NULL,
    tracker_id VARCHAR(100) DEFAULT NULL,
    last_location VARCHAR(255) DEFAULT NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT DEFAULT NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Papers (Vehicle Documents/Registrations) ──────────────────
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

-- ── Expenses ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    category VARCHAR(100) DEFAULT NULL,
    expense_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Challans (Traffic Fines) ──────────────────────────────────
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

-- ── Damage Costs (Predefined settings) ────────────────────────
CREATE TABLE IF NOT EXISTS damage_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── System Settings (Key-Value config) ─────────────────────────
CREATE TABLE IF NOT EXISTS system_settings (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Default late return hourly charge
INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('late_return_rate_per_hour', '0');
INSERT IGNORE INTO system_settings (`key`, `value`) VALUES
('lead_sources', '[{"value":"walk_in","label":"Walk-in"},{"value":"phone","label":"Phone Call"},{"value":"whatsapp","label":"WhatsApp"},{"value":"instagram","label":"Instagram"},{"value":"referral","label":"Referral"},{"value":"website","label":"Website"},{"value":"other","label":"Other"}]');

SET FOREIGN_KEY_CHECKS = 1;
