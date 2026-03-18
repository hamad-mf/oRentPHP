<?php
require_once __DIR__ . '/../config/db.php';

// Already logged in → go to dashboard
if (isset($_SESSION['user'])) {
    redirect('../index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Load permissions
            $permStmt = $pdo->prepare("SELECT permission FROM staff_permissions WHERE user_id = ?");
            $permStmt->execute([$user['id']]);
            $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);

            // Admin gets all permissions
            if ($user['role'] === 'admin') {
                $permissions = [
                    'add_vehicles',
                    'view_all_vehicles',
                    'view_vehicle_availability',
                    'view_vehicle_requests',
                    'add_reservations',
                    'add_leads',
                    'do_delivery',
                    'do_return',
                    'view_finances',
                    'manage_clients',
                    'manage_staff'
                ];
            }

            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'username' => $user['username'],
                'role' => $user['role'],
                'staff_id' => $user['staff_id'],
                'permissions' => $permissions,
            ];

            app_log('ACTION', "Login successful: $username (role: {$user['role']})");
            redirect('../index.php');
        } else {
            app_log('ACTION', "Login failed for username: $username");
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" id="html-root">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Orentincars</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        'mb-black': '#000000',
                        'mb-surface': '#1f1f1f',
                        'mb-silver': '#e5e5e5',
                        'mb-accent': '#00adef',
                        'mb-subtle': '#4a4a4a',
                    }
                }
            }
        };
    </script>
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(24px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 30px rgba(0, 173, 239, 0.15);
            }

            50% {
                box-shadow: 0 0 60px rgba(0, 173, 239, 0.30);
            }
        }

        .glow-card {
            animation: pulse-glow 4s ease-in-out infinite;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-text-fill-color: #ffffff;
            -webkit-box-shadow: 0 0 0px 1000px #111111 inset;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>

<body
    class="bg-mb-black text-white font-sans antialiased min-h-screen flex items-center justify-center selection:bg-mb-accent selection:text-white"
    style="background: radial-gradient(ellipse at 50% 0%, rgba(0,173,239,0.07) 0%, #000 60%)">

    <div class="w-full max-w-sm px-4">
        <!-- Logo -->
        <div class="text-center mb-10 login-card">
            <div class="flex items-center justify-center gap-3 mb-3">
                <div
                    class="w-10 h-10 rounded-xl bg-mb-accent/10 border border-mb-accent/30 flex items-center justify-center">
                    <svg class="w-6 h-6 text-mb-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 012-2 2 2 0 002 2 2 2 0 11-2-2 2 2 0 00-2 2z" />
                    </svg>
                </div>
            </div>
            <h1 class="text-2xl font-light tracking-widest uppercase text-white">Orentincars</h1>
            <p class="text-mb-subtle text-sm mt-1 tracking-wide">Management System</p>
        </div>

        <!-- Card -->
        <div class="login-card glow-card bg-mb-surface border border-mb-subtle/20 rounded-2xl p-8 space-y-6">
            <div>
                <h2 class="text-white text-lg font-light">Welcome back</h2>
                <p class="text-mb-subtle text-sm mt-0.5">Sign in to your account</p>
            </div>

            <?php if ($error): ?>
                <div
                    class="flex items-center gap-2 bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 text-sm">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Username</label>
                    <input type="text" name="username" id="username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="username" autofocus
                        required
                        class="w-full bg-mb-black border border-mb-subtle/30 rounded-xl px-4 py-3 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                        placeholder="Enter your username">
                </div>
                <div>
                    <label class="block text-sm text-mb-silver mb-1.5">Password</label>
                    <div class="relative">
                        <input type="password" name="password" id="password" autocomplete="current-password" required
                            class="w-full bg-mb-black border border-mb-subtle/30 rounded-xl px-4 py-3 pr-12 text-white placeholder:text-mb-subtle focus:outline-none focus:border-mb-accent transition-colors text-sm"
                            placeholder="Enter your password">
                        <button type="button" onclick="togglePwd()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-mb-subtle hover:text-white transition-colors">
                            <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <button type="submit"
                    class="w-full bg-mb-accent text-white py-3 rounded-xl font-medium hover:bg-mb-accent/80 transition-all shadow-lg shadow-mb-accent/20 hover:shadow-mb-accent/40 mt-2">
                    Sign In
                </button>
            </form>
        </div>

        <p class="text-center text-mb-subtle/40 text-xs mt-6">©
            <?= date('Y') ?> Orentincars
        </p>
    </div>

    <script>
        function togglePwd() {
            const inp = document.getElementById('password');
            inp.type = inp.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>

</html>
