-- ============================================================
-- O Rent CRM — Live Server Migration Script (Safe to run multiple times)
-- Run this in phpMyAdmin on your live server database
-- ============================================================

-- 1.1 Add missing columns to reservations (Fix for 500 error)
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS overdue_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE reservations MODIFY COLUMN rental_type VARCHAR(50) NOT NULL DEFAULT 'daily';

-- 1.2 Add missing rate columns to vehicles
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS monthly_rate DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS rate_1day DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS rate_7day DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS rate_15day DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE vehicles ADD COLUMN IF NOT EXISTS rate_30day DECIMAL(10,2) DEFAULT NULL;

-- 1.3 Add missing columns to clients
ALTER TABLE clients ADD COLUMN IF NOT EXISTS is_blacklisted TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS blacklist_reason TEXT DEFAULT NULL;

-- 2. Ensure start/end dates support time (DATETIME)
ALTER TABLE reservations MODIFY COLUMN start_date DATETIME NOT NULL;
ALTER TABLE reservations MODIFY COLUMN end_date DATETIME NOT NULL;
ALTER TABLE reservations MODIFY COLUMN actual_end_date DATETIME DEFAULT NULL;

-- 3. Add missing pricing columns (safe — IF NOT EXISTS)
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS km_limit INT DEFAULT NULL;
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS extra_km_price DECIMAL(10,2) DEFAULT NULL;
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS km_driven INT DEFAULT NULL;
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS km_overage_charge DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS damage_charge DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS discount_type ENUM('percent','amount') DEFAULT NULL;
ALTER TABLE reservations ADD COLUMN IF NOT EXISTS discount_value DECIMAL(10,2) DEFAULT 0.00;

-- 4. Create inspection_photos table (photo proofs for delivery & return)
CREATE TABLE IF NOT EXISTS inspection_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    view_name VARCHAR(50) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES vehicle_inspections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5. Create system_settings table (for late return hourly rate etc.)
CREATE TABLE IF NOT EXISTS system_settings (
    `key` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value` TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('late_return_rate_per_hour', '0');

-- 6. Create damage_costs table (for predefined damage charge items)
CREATE TABLE IF NOT EXISTS damage_costs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- All done! ✅
