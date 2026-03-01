-- ============================================================
-- O Rent CRM — Dummy Data: 5 Vehicles + 4 Clients
-- Run this in phpMyAdmin or MySQL CLI against the `orent` database
-- Does NOT touch reservations or any other tables
-- ============================================================

-- ── 5 Dummy Vehicles ─────────────────────────────────────────
INSERT INTO vehicles (brand, model, year, license_plate, color, vin, status, daily_rate, monthly_rate, image_url) VALUES
('Toyota',   'Camry',        2022, 'ABC-1234', 'White',      'JT2BG22K1W0123456', 'available',   120.00, 2800.00, NULL),
('Honda',    'Civic',        2021, 'DEF-5678', 'Silver',     '2HGFG3B55CH123456', 'available',   100.00, 2400.00, NULL),
('Nissan',   'Patrol',       2023, 'GHI-9012', 'Black',      '5N1AA0NC5DN123456', 'maintenance', 200.00, 4500.00, NULL),
('Hyundai',  'Tucson',       2022, 'JKL-3456', 'Blue',       'KM8J33A42NU123456', 'available',   130.00, 3000.00, NULL),
('Mercedes', 'C-Class',      2023, 'MNO-7890', 'Midnight Grey','WDD2050421R123456','available',  250.00, 5500.00, NULL);

-- ── 4 Dummy Clients ──────────────────────────────────────────
INSERT INTO clients (name, email, phone, address, rating, is_blacklisted, blacklist_reason, notes) VALUES
('Ahmed Al-Rashidi',  'ahmed.rashidi@email.com',  '+966501234567', '12 King Fahd Rd, Riyadh, SA',       4, 0, NULL, 'Regular customer, always returns on time.'),
('Sara Johnson',      'sara.johnson@email.com',   '+971501234568', 'Flat 5B, Marina Towers, Dubai, UAE', 5, 0, NULL, 'VIP client, prefers luxury vehicles.'),
('Omar Khalid',       'omar.khalid@email.com',    '+966551234569', '8 Al Olaya St, Riyadh, SA',         3, 0, NULL, 'Occasional late returns, monitor closely.'),
('Fatima Al-Mansoori','fatima.mansoori@email.com','+971551234570', 'Villa 22, Jumeirah, Dubai, UAE',     5, 0, NULL, 'Long-term client, monthly rental preferred.');
