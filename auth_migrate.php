<?php
/**
 * oRentPHP — Auth & Staff Migration Script
 * Run ONCE via browser, then delete this file.
 * URL: http://localhost/oRentPHP/auth_migrate.php
 */
require_once __DIR__ . '/config/db.php';

// Skip session/auth check — this is the bootstrapper
$pdo = db();
$errors = [];
$steps = [];

function runSql(PDO $pdo, string $label, string $sql, array &$steps, array &$errors): void
{
    try {
        $pdo->exec($sql);
        $steps[] = "✅ $label";
    } catch (PDOException $e) {
        // Ignore "already exists" errors
        if (
            str_contains($e->getMessage(), 'already exists') ||
            str_contains($e->getMessage(), 'Duplicate column') ||
            str_contains($e->getMessage(), 'Duplicate key')
        ) {
            $steps[] = "⚠️  $label — already exists, skipped";
        } else {
            $errors[] = "❌ $label — " . $e->getMessage();
        }
    }
}

// Disable foreign key checks for migration
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

// ── 0. Staff table (core project table if missing) ──────────────────────────
runSql($pdo, 'Create staff table', "
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
", $steps, $errors);

// ── 1. Users table ──────────────────────────────────────────────────────────
runSql($pdo, 'Create users table', "
    CREATE TABLE IF NOT EXISTS users (
        id             INT AUTO_INCREMENT PRIMARY KEY,
        name           VARCHAR(255) NOT NULL,
        username       VARCHAR(100) NOT NULL UNIQUE,
        password_hash  VARCHAR(255) NOT NULL,
        role           ENUM('admin','staff') NOT NULL DEFAULT 'staff',
        staff_id       INT DEFAULT NULL,
        is_active      TINYINT(1) NOT NULL DEFAULT 1,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username)
    ) ENGINE=InnoDB;
", $steps, $errors);

// ── 2. Staff activity log ────────────────────────────────────────────────────
runSql($pdo, 'Create staff_activity_log table', "
    CREATE TABLE IF NOT EXISTS staff_activity_log (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        user_id      INT NOT NULL,
        action       VARCHAR(100) NOT NULL,
        entity_type  VARCHAR(50) DEFAULT NULL,
        entity_id    INT DEFAULT NULL,
        description  TEXT DEFAULT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB;
", $steps, $errors);

// ── 3. Staff permissions table ───────────────────────────────────────────────
runSql($pdo, 'Create staff_permissions table', "
    CREATE TABLE IF NOT EXISTS staff_permissions (
        user_id     INT NOT NULL,
        permission  VARCHAR(100) NOT NULL,
        PRIMARY KEY (user_id, permission)
    ) ENGINE=InnoDB;
", $steps, $errors);

// ── 4. Add staff_id FK to users (after staff table confirmed exists) ────────
// First add the column if not present
runSql($pdo, 'Add staff_id FK to users', "
    ALTER TABLE users
        ADD CONSTRAINT fk_users_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL;
", $steps, $errors);

// ── 5. Add assigned_staff_id to leads ───────────────────────────────────────
runSql($pdo, 'Add assigned_staff_id column to leads', "
    ALTER TABLE leads ADD COLUMN assigned_staff_id INT DEFAULT NULL;
", $steps, $errors);

runSql($pdo, 'Add FK for leads.assigned_staff_id', "
    ALTER TABLE leads
        ADD CONSTRAINT fk_leads_assigned_staff FOREIGN KEY (assigned_staff_id) REFERENCES users(id) ON DELETE SET NULL;
", $steps, $errors);

// ── 6. Default admin user ────────────────────────────────────────────────────
$adminHash = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
$stmt->execute();
if ((int) $stmt->fetchColumn() === 0) {
    $ins = $pdo->prepare("INSERT INTO users (name, username, password_hash, role) VALUES ('Admin', 'admin', ?, 'admin')");
    $ins->execute([$adminHash]);
    $steps[] = "✅ Default admin user created (username: admin, password: admin123)";
} else {
    $steps[] = "⚠️  Admin user already exists, skipped";
}

// Re-enable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Migration — oRentPHP</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #0f0f0f;
            color: #fff;
            padding: 2rem;
            max-width: 800px;
            margin: auto;
        }

        h1 {
            color: #00adef;
            margin-bottom: 1.5rem;
        }

        .step {
            padding: 0.4rem 0;
            border-bottom: 1px solid #222;
            font-size: 0.9rem;
        }

        .error {
            color: #f87171;
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #f87171;
            border-radius: 8px;
        }

        .success {
            color: #4ade80;
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid #4ade80;
            border-radius: 8px;
        }

        a {
            color: #00adef;
        }
    </style>
</head>

<body>
    <h1>🚀 oRentPHP — Auth Migration</h1>

    <?php foreach ($steps as $s): ?>
        <div class="step">
            <?= htmlspecialchars($s) ?>
        </div>
    <?php endforeach; ?>

    <?php if ($errors): ?>
        <div class="error">
            <strong>Errors encountered:</strong><br>
            <?php foreach ($errors as $e): ?>
                <?= htmlspecialchars($e) ?><br>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="success">
            <strong>✅ Migration completed successfully!</strong><br><br>
            Default credentials:<br>
            Username: <strong>admin</strong><br>
            Password: <strong>admin123</strong><br><br>
            <strong>⚠️ Please change the admin password after first login and DELETE this file.</strong><br><br>
            <a href="auth/login.php">→ Go to Login Page</a>
        </div>
    <?php endif; ?>
</body>

</html>