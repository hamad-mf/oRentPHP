-- ============================================================
--  oRent — Full Server Migration Script
--  Run once on the production server via phpMyAdmin.
--  SAFE: Uses IF NOT EXISTS / IF NOT EXISTS column checks.
--  Last generated: 2026-02-27
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ──────────────────────────────────────────────────────────────
-- 1. SYSTEM SETTINGS TABLE
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `system_settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 2. USERS TABLE (login accounts)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `staff_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `fk_users_staff` (`staff_id`),
  CONSTRAINT `fk_users_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin123) — skip if already exists
INSERT IGNORE INTO `users` (`name`, `username`, `password_hash`, `role`, `is_active`)
VALUES ('Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

-- ──────────────────────────────────────────────────────────────
-- 3. STAFF PERMISSIONS TABLE
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `staff_permissions` (
  `user_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL,
  PRIMARY KEY (`user_id`,`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 4. STAFF ACTIVITY LOG TABLE
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `staff_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_created` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 5. LEADS TABLE — create if missing, then add any missing columns
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `inquiry_type` enum('daily','weekly','monthly','other') DEFAULT 'daily',
  `vehicle_interest` varchar(255) DEFAULT NULL,
  `source` varchar(100) NOT NULL DEFAULT 'phone',
  `status` enum('new','contacted','interested','future','closed_won','closed_lost') DEFAULT 'new',
  `lost_reason` text DEFAULT NULL,
  `assigned_to` varchar(100) DEFAULT NULL,
  `assigned_staff_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `converted_client_id` int(11) DEFAULT NULL,
  `converted_reservation_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_leads_client` FOREIGN KEY (`converted_client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add missing columns to leads (safe — only adds if column doesn't exist)
ALTER TABLE `leads`
  MODIFY COLUMN `source` VARCHAR(100) NOT NULL DEFAULT 'phone',
  MODIFY COLUMN `status` ENUM('new','contacted','interested','future','closed_won','closed_lost') DEFAULT 'new';

-- Add assigned_staff_id column if missing
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'assigned_staff_id'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE `leads` ADD COLUMN `assigned_staff_id` int(11) DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add lost_reason column if missing
SET @col_exists2 = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'lost_reason'
);
SET @sql2 = IF(@col_exists2 = 0,
  'ALTER TABLE `leads` ADD COLUMN `lost_reason` TEXT DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- ──────────────────────────────────────────────────────────────
-- 6. LEAD FOLLOWUPS TABLE
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lead_followups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `type` enum('call','meeting','email','whatsapp') NOT NULL DEFAULT 'call',
  `scheduled_at` datetime NOT NULL,
  `notes` text DEFAULT NULL,
  `is_done` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  CONSTRAINT `lead_followups_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 7. LEAD ACTIVITIES TABLE
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `lead_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `note` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  CONSTRAINT `lead_activities_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 8. RESERVATIONS — add all missing columns
-- ──────────────────────────────────────────────────────────────
-- Add deposit_amount if missing
SET @c1 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='deposit_amount');
SET @s1 = IF(@c1=0, 'ALTER TABLE `reservations` ADD COLUMN `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p1 FROM @s1; EXECUTE p1; DEALLOCATE PREPARE p1;

-- Add deposit_returned if missing
SET @c2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='deposit_returned');
SET @s2 = IF(@c2=0, 'ALTER TABLE `reservations` ADD COLUMN `deposit_returned` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p2 FROM @s2; EXECUTE p2; DEALLOCATE PREPARE p2;

-- Add delivery_charge if missing
SET @c3 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='delivery_charge');
SET @s3 = IF(@c3=0, 'ALTER TABLE `reservations` ADD COLUMN `delivery_charge` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p3 FROM @s3; EXECUTE p3; DEALLOCATE PREPARE p3;

-- Add additional_charge if missing
SET @c4 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='additional_charge');
SET @s4 = IF(@c4=0, 'ALTER TABLE `reservations` ADD COLUMN `additional_charge` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p4 FROM @s4; EXECUTE p4; DEALLOCATE PREPARE p4;

-- Add voucher_applied if missing
SET @c5 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='voucher_applied');
SET @s5 = IF(@c5=0, 'ALTER TABLE `reservations` ADD COLUMN `voucher_applied` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p5 FROM @s5; EXECUTE p5; DEALLOCATE PREPARE p5;

-- Add early_return_credit if missing
SET @c6 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='early_return_credit');
SET @s6 = IF(@c6=0, 'ALTER TABLE `reservations` ADD COLUMN `early_return_credit` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p6 FROM @s6; EXECUTE p6; DEALLOCATE PREPARE p6;

-- Add voucher_credit_issued if missing
SET @c7 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='voucher_credit_issued');
SET @s7 = IF(@c7=0, 'ALTER TABLE `reservations` ADD COLUMN `voucher_credit_issued` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p7 FROM @s7; EXECUTE p7; DEALLOCATE PREPARE p7;

-- Add return_voucher_applied if missing
SET @c8 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='return_voucher_applied');
SET @s8 = IF(@c8=0, 'ALTER TABLE `reservations` ADD COLUMN `return_voucher_applied` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p8 FROM @s8; EXECUTE p8; DEALLOCATE PREPARE p8;

-- Add delivery_payment_method if missing
SET @c9 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='delivery_payment_method');
SET @s9 = IF(@c9=0, "ALTER TABLE `reservations` ADD COLUMN `delivery_payment_method` enum('cash','account','credit') DEFAULT NULL", 'SELECT 1');
PREPARE p9 FROM @s9; EXECUTE p9; DEALLOCATE PREPARE p9;

-- Add delivery_paid_amount if missing
SET @c10 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='delivery_paid_amount');
SET @s10 = IF(@c10=0, 'ALTER TABLE `reservations` ADD COLUMN `delivery_paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p10 FROM @s10; EXECUTE p10; DEALLOCATE PREPARE p10;

-- Add return_payment_method if missing
SET @c11 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='return_payment_method');
SET @s11 = IF(@c11=0, "ALTER TABLE `reservations` ADD COLUMN `return_payment_method` enum('cash','account','credit') DEFAULT NULL", 'SELECT 1');
PREPARE p11 FROM @s11; EXECUTE p11; DEALLOCATE PREPARE p11;

-- Add return_paid_amount if missing
SET @c12 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='return_paid_amount');
SET @s12 = IF(@c12=0, 'ALTER TABLE `reservations` ADD COLUMN `return_paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p12 FROM @s12; EXECUTE p12; DEALLOCATE PREPARE p12;

-- Add km_limit, extra_km_price, km_driven, km_overage_charge if missing
SET @c13 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='km_limit');
SET @s13 = IF(@c13=0, 'ALTER TABLE `reservations` ADD COLUMN `km_limit` int(11) DEFAULT NULL, ADD COLUMN `extra_km_price` decimal(10,2) DEFAULT NULL, ADD COLUMN `km_driven` int(11) DEFAULT NULL, ADD COLUMN `km_overage_charge` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p13 FROM @s13; EXECUTE p13; DEALLOCATE PREPARE p13;

-- Add damage_charge if missing
SET @c14 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='damage_charge');
SET @s14 = IF(@c14=0, 'ALTER TABLE `reservations` ADD COLUMN `damage_charge` decimal(10,2) NOT NULL DEFAULT 0.00', 'SELECT 1');
PREPARE p14 FROM @s14; EXECUTE p14; DEALLOCATE PREPARE p14;

-- Add discount_type, discount_value if missing
SET @c15 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reservations' AND COLUMN_NAME='discount_type');
SET @s15 = IF(@c15=0, "ALTER TABLE `reservations` ADD COLUMN `discount_type` enum('percent','amount') DEFAULT NULL, ADD COLUMN `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00", 'SELECT 1');
PREPARE p15 FROM @s15; EXECUTE p15; DEALLOCATE PREPARE p15;

-- ──────────────────────────────────────────────────────────────
-- 9. VEHICLES — add rate columns and image_url if missing
-- ──────────────────────────────────────────────────────────────
SET @vc1 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicles' AND COLUMN_NAME='rate_1day');
SET @vs1 = IF(@vc1=0, 'ALTER TABLE `vehicles` ADD COLUMN `rate_1day` decimal(10,2) DEFAULT NULL, ADD COLUMN `rate_7day` decimal(10,2) DEFAULT NULL, ADD COLUMN `rate_15day` decimal(10,2) DEFAULT NULL, ADD COLUMN `rate_30day` decimal(10,2) DEFAULT NULL', 'SELECT 1');
PREPARE vp1 FROM @vs1; EXECUTE vp1; DEALLOCATE PREPARE vp1;

SET @vc2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicles' AND COLUMN_NAME='monthly_rate');
SET @vs2 = IF(@vc2=0, 'ALTER TABLE `vehicles` ADD COLUMN `monthly_rate` decimal(10,2) DEFAULT NULL', 'SELECT 1');
PREPARE vp2 FROM @vs2; EXECUTE vp2; DEALLOCATE PREPARE vp2;

SET @vc3 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicles' AND COLUMN_NAME='image_url');
SET @vs3 = IF(@vc3=0, 'ALTER TABLE `vehicles` ADD COLUMN `image_url` varchar(500) DEFAULT NULL', 'SELECT 1');
PREPARE vp3 FROM @vs3; EXECUTE vp3; DEALLOCATE PREPARE vp3;

SET @vc4 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='vehicles' AND COLUMN_NAME='vin');
SET @vs4 = IF(@vc4=0, 'ALTER TABLE `vehicles` ADD COLUMN `vin` varchar(50) DEFAULT NULL', 'SELECT 1');
PREPARE vp4 FROM @vs4; EXECUTE vp4; DEALLOCATE PREPARE vp4;

-- ──────────────────────────────────────────────────────────────
-- 10. CLIENTS — add proof_file column if missing
-- ──────────────────────────────────────────────────────────────
SET @cc1 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clients' AND COLUMN_NAME='proof_file');
SET @cs1 = IF(@cc1=0, 'ALTER TABLE `clients` ADD COLUMN `proof_file` varchar(500) DEFAULT NULL', 'SELECT 1');
PREPARE cp1 FROM @cs1; EXECUTE cp1; DEALLOCATE PREPARE cp1;

-- ──────────────────────────────────────────────────────────────
-- 11. VOUCHERS TABLE (if not exists)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `reservation_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ──────────────────────────────────────────────────────────────
-- 12. DOCUMENTS TABLE (if not exists)
-- ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `vehicle_id` (`vehicle_id`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  END OF MIGRATION — all done! 
--  Now upload the PHP files from SERVER UPDATE to your server.
-- ============================================================
