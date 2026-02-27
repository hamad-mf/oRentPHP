<?php
/**
 * ONE-TIME Admin Password Reset Script
 * ---------------------------------------------------
 * 1. Upload this file to your server root
 * 2. Visit: https://orentin.abrarfuturetech.com/reset_admin.php
 * 3. DELETE the file immediately after use!
 */

require_once __DIR__ . '/config/db.php';

$newPassword = 'admin123';
$hash = password_hash($newPassword, PASSWORD_BCRYPT);

$pdo = db();

// Upsert admin user with fresh hash from THIS server's PHP
$stmt = $pdo->prepare("
    INSERT INTO users (name, username, password_hash, role, is_active)
    VALUES ('Administrator', 'admin', ?, 'admin', 1)
    ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1
");
$stmt->execute([$hash]);

// Verify it works
$check = $pdo->prepare("SELECT password_hash FROM users WHERE username = 'admin'");
$check->execute();
$stored = $check->fetchColumn();
$ok = password_verify($newPassword, $stored);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Admin Reset</title>
    <style>
        body {
            font-family: monospace;
            background: #111;
            color: #eee;
            padding: 2rem;
        }

        .ok {
            color: #4ade80;
        }

        .err {
            color: #f87171;
        }

        code {
            background: #222;
            padding: 2px 8px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <h2>Admin Password Reset</h2>
    <p>PHP version on this server: <code><?= phpversion() ?></code></p>
    <p>Hash generated: <code><?= htmlspecialchars(substr($hash, 0, 30)) ?>…</code> (
        <?= strlen($hash) ?> chars)
    </p>
    <p>Stored & verified: <strong class="<?= $ok ? 'ok' : 'err' ?>">
            <?= $ok ? '✅ SUCCESS' : '❌ FAILED' ?>
        </strong></p>

    <?php if ($ok): ?>
        <hr style="border-color:#333;margin:1.5rem 0">
        <h3 class="ok">✅ Login is now fixed!</h3>
        <p>Go to: <a href="/auth/login.php" style="color:#60a5fa">/auth/login.php</a></p>
        <p>Username: <code>admin</code> &nbsp; Password: <code>admin123</code></p>
        <p style="color:#f87171;margin-top:1rem">⚠️ <strong>DELETE this file immediately after logging in!</strong></p>
    <?php else: ?>
        <h3 class="err">❌ Something went wrong — please contact support.</h3>
    <?php endif; ?>
</body>

</html>