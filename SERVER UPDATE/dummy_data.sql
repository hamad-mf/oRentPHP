-- ============================================================
-- oRentPHP — Dummy Staff Seed Data
-- Run this AFTER wipe_and_reset.sql
-- Username = Password for each staff member
-- ============================================================

-- Ahmed Ali (username/password: ahmed_ali)
INSERT INTO staff (name, role, phone, salary, joined_date) VALUES ('Ahmed Ali', 'Driver', '+971501111001', 2500, '2025-01-01');
SET @sid = LAST_INSERT_ID();
INSERT INTO users (name, username, password_hash, role, staff_id) VALUES ('Ahmed Ali', 'ahmed_ali', '$2y$10$rrtJcUADuXxEUHYw5ZCc3ue0NImMHzPSlMS/mr3GNh2USpMGL3kEu', 'staff', @sid);
SET @uid = LAST_INSERT_ID();
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_vehicles');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_reservations');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_delivery');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_return');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_leads');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_clients');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'view_finances');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_staff');

-- Sara Khan (username/password: sara_khan)
INSERT INTO staff (name, role, phone, salary, joined_date) VALUES ('Sara Khan', 'Coordinator', '+971501111002', 3000, '2025-01-01');
SET @sid = LAST_INSERT_ID();
INSERT INTO users (name, username, password_hash, role, staff_id) VALUES ('Sara Khan', 'sara_khan', '$2y$10$KTAC.vU2g4Fj3VQVm9WcDuIk2M8cBhJhFWSxOe9F8.afB8/kT1.76', 'staff', @sid);
SET @uid = LAST_INSERT_ID();
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_vehicles');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_reservations');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_delivery');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_return');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_leads');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_clients');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'view_finances');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_staff');

-- Omar Hassan (username/password: omar_hassan)
INSERT INTO staff (name, role, phone, salary, joined_date) VALUES ('Omar Hassan', 'Sales', '+971501111003', 2800, '2025-01-01');
SET @sid = LAST_INSERT_ID();
INSERT INTO users (name, username, password_hash, role, staff_id) VALUES ('Omar Hassan', 'omar_hassan', '$2y$10$ZeSc4.PKoXnLp2YmI27m/.DQOmi1N8fAIwAO3flJMijKz2VRLmvHi', 'staff', @sid);
SET @uid = LAST_INSERT_ID();
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_vehicles');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_reservations');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_delivery');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_return');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_leads');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_clients');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'view_finances');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_staff');

-- Fatima Noor (username/password: fatima_noor)
INSERT INTO staff (name, role, phone, salary, joined_date) VALUES ('Fatima Noor', 'Operations', '+971501111004', 3200, '2025-01-01');
SET @sid = LAST_INSERT_ID();
INSERT INTO users (name, username, password_hash, role, staff_id) VALUES ('Fatima Noor', 'fatima_noor', '$2y$10$zImVun.OymO/dlPeeTgVt.fizl2.KGw6ONCc/QrbdACPmUT.fZwwa', 'staff', @sid);
SET @uid = LAST_INSERT_ID();
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_vehicles');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_reservations');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_delivery');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_return');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_leads');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_clients');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'view_finances');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_staff');

-- Khalid Rashid (username/password: khalid_rashid)
INSERT INTO staff (name, role, phone, salary, joined_date) VALUES ('Khalid Rashid', 'Driver', '+971501111005', 2600, '2025-01-01');
SET @sid = LAST_INSERT_ID();
INSERT INTO users (name, username, password_hash, role, staff_id) VALUES ('Khalid Rashid', 'khalid_rashid', '$2y$10$D36Y4oTiIXRjwo8GBYC7Me7avXmznX0fs1mA8BmHiFZniK3544.hK', 'staff', @sid);
SET @uid = LAST_INSERT_ID();
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_vehicles');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_reservations');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_delivery');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'do_return');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'add_leads');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_clients');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'view_finances');
INSERT INTO staff_permissions (user_id, permission) VALUES (@uid, 'manage_staff');

