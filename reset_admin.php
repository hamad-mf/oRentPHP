<?php
/**
 * reset_admin.php — Creates or resets the admin account.
 * Run ONCE after a fresh database wipe, then delete this file (or leave it — it's protected).
 * URL: http://localhost:8000/reset_admin.php
 */
require_once __DIR__ . '/config/db.php';
$pdo = db();

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm'] ?? '');

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        // Create or reset the admin user
        $exists = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username='admin'")->fetchColumn();
        if ($exists) {
            // Get admin user ID before updating
            $adminUserId = (int) $pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
            
            $pdo->prepare("UPDATE users SET password_hash=?, role='admin', is_active=1 WHERE username='admin'")->execute([$hash]);
            
            // Invalidate all remember tokens when password changes (security requirement)
            if ($adminUserId) {
                delete_all_user_tokens($adminUserId);
                app_log('AUTH', "All remember tokens invalidated due to admin password reset for user {$adminUserId}");
            }
            
            $msg = '✅ Admin password reset.';
        } else {
            $pdo->prepare("INSERT INTO users (name, username, password_hash, role, is_active) VALUES ('Admin','admin',?,'admin',1)")->execute([$hash]);
            $msg = '✅ Admin account created.';
        }

        // Reset ALL system settings to defaults
        $defaults = [
            'daily_target' => '0',
            'late_return_rate_per_hour' => '0',
            'deposit_percentage' => '0',
            'delivery_charge_default' => '0',
        ];
        foreach ($defaults as $key => $val) {
            $pdo->prepare("INSERT INTO system_settings (`key`, `value`) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([$key, $val]);
        }
        $msg .= ' All settings reset to defaults (daily target = $0).';
        $msg .= ' <a href="auth/login.php" style="color:#00adef">Login here</a>.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Reset Admin — oRentPHP</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: sans-serif;
            background: #0f0f0f;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .card {
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 12px;
            padding: 2rem;
            width: 360px;
        }

        h1 {
            font-size: 1.2rem;
            margin-bottom: 0.3rem;
            color: #00adef;
        }

        p.sub {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            font-size: 0.8rem;
            color: #aaa;
            margin-bottom: 0.3rem;
        }

        input {
            width: 100%;
            background: #111;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 0.6rem 0.8rem;
            color: #fff;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        button {
            width: 100%;
            background: #00adef;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 0.7rem;
            font-size: 0.95rem;
            cursor: pointer;
        }

        .msg {
            background: #00adef15;
            border: 1px solid #00adef40;
            color: #00adef;
            border-radius: 6px;
            padding: 0.7rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }

        .err {
            background: #ef444415;
            border: 1px solid #ef444440;
            color: #f87171;
            border-radius: 6px;
            padding: 0.7rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
    </style>
</head>

<body>
    <div class="card">
        <h1>🔑 Reset Admin Account</h1>
        <p class="sub">Creates or resets the admin user. Use after a database wipe.</p>

        <?php if ($msg): ?>
            <div class="msg"><?= $msg ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="err"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!$msg): ?>
            <form method="POST">
                <label>New Admin Password</label>
                <input type="password" name="password" placeholder="Min 6 characters" required>
                <label>Confirm Password</label>
                <input type="password" name="confirm" placeholder="Repeat password" required>
                <button type="submit">Create / Reset Admin</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>