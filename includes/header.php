<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
$_currentUser = current_user();
require_once __DIR__ . '/notifications.php';
$_notifCount = notif_count($pdo);
$_notifs = notif_all($pdo);
?>
<!DOCTYPE html>
<html lang="en" id="html-root">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        <?= e($pageTitle ?? 'Orentincars') ?> | Orentincars
    </title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet">

    <!-- Theme: apply before paint to avoid flash -->
    <script>
        (function () {
            if (localStorage.getItem('theme') === 'light') {
                document.getElementById('html-root').classList.add('light-mode');
            }
        })();
    </script>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('notif', { open: false });
        });
        function toggleNotif(forceOpen) {
            setTimeout(function () {
                var d = document.getElementById('notif-dropdown');
                if (!d) return;
                var isOpen = d.style.display === 'block';
                d.style.display = (forceOpen === true ? true : !isOpen) ? 'block' : 'none';
            }, 0);
        }
        document.addEventListener('click', function (e) {
            var d = document.getElementById('notif-dropdown');
            var wrap = document.getElementById('notif-bell-wrap');
            if (d && d.style.display === 'block' && wrap && !wrap.contains(e.target)) {
                d.style.display = 'none';
            }
        });
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        }
    </script>

    <style>
        /* ── Light Mode ── */
        .light-mode body {
            background-color: #f0f4f8 !important;
            color: #0f172a !important;
        }

        .light-mode .text-white {
            color: #0f172a !important;
        }

        .light-mode .text-mb-silver {
            color: #334155 !important;
        }

        .light-mode .text-mb-subtle {
            color: #64748b !important;
        }

        .light-mode .bg-mb-black {
            background-color: #f0f4f8 !important;
        }

        .light-mode .bg-mb-surface {
            background-color: #ffffff !important;
        }

        .light-mode .border-mb-subtle\/20 {
            border-color: rgba(15, 23, 42, 0.12) !important;
        }

        .light-mode aside {
            background-color: #ffffff !important;
            border-color: rgba(15, 23, 42, 0.08) !important;
        }

        .light-mode aside a {
            color: #475569;
        }

        .light-mode aside a:hover {
            color: #0f172a !important;
        }

        .light-mode header {
            background-color: rgba(248, 250, 252, 0.85) !important;
            border-color: rgba(15, 23, 42, 0.08) !important;
        }

        .light-mode main>div {
            background: linear-gradient(135deg, #e8eef5, #f8fafc) !important;
        }

        .light-mode input,
        .light-mode textarea,
        .light-mode select {
            background-color: #f8fafc !important;
            color: #0f172a !important;
            border-color: rgba(15, 23, 42, 0.15) !important;
        }

        .light-mode .bg-mb-black\/50 {
            background-color: rgba(240, 244, 248, 0.9) !important;
        }

        .light-mode .bg-mb-black\/40 {
            background-color: rgba(240, 244, 248, 0.8) !important;
        }

        /* Select2 theming */
        .select2-container--default .select2-selection--single {
            background-color: #1a1a1a !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            border-radius: 0.5rem !important;
            height: 46px !important;
            display: flex !important;
            align-items: center !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #ffffff !important;
            padding-left: 1rem !important;
            line-height: normal !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px !important;
            right: 10px !important;
        }

        .select2-dropdown {
            background-color: #1a1a1a !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: #ffffff !important;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            background-color: #000 !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            color: #fff !important;
            border-radius: 0.25rem !important;
        }

        .select2-container--default .select2-results__option--highlighted {
            background-color: #00adef !important;
            color: white !important;
        }

        .select2-container--default .select2-results__option--selected {
            background-color: rgba(0, 173, 239, 0.15) !important;
        }

        .select2-results__option {
            color: #ffffff !important;
        }

        /* Light mode select2 */
        .light-mode .select2-container--default .select2-selection--single {
            background-color: #f8fafc !important;
            border-color: rgba(15, 23, 42, 0.15) !important;
        }

        .light-mode .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #0f172a !important;
        }

        .light-mode .select2-dropdown {
            background-color: #ffffff !important;
            border-color: rgba(15, 23, 42, 0.15) !important;
            color: #0f172a !important;
        }

        .light-mode .select2-results__option {
            color: #0f172a !important;
        }

        .light-mode .select2-container--default .select2-search--dropdown .select2-search__field {
            background-color: #f1f5f9 !important;
            border-color: rgba(15, 23, 42, 0.15) !important;
            color: #0f172a !important;
        }

        /* Animate fade in */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        main {
            animation: fadeIn 0.5s ease-out forwards;
        }

        *,
        *::before,
        *::after {
            transition: background-color 0.25s ease, border-color 0.25s ease, color 0.2s ease;
        }
    </style>
</head>

<body
    class="bg-mb-black text-white font-sans antialiased h-screen flex selection:bg-mb-accent selection:text-white overflow-hidden">

    <!-- Sidebar -->
    <aside class="w-64 bg-mb-surface hidden md:flex flex-col border-r border-mb-subtle/20">
        <div class="h-20 flex items-center justify-center border-b border-mb-subtle/20">
            <div class="flex items-center gap-2">
                <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" />
                </svg>
                <span class="text-xl font-light tracking-widest text-white uppercase">Orentincars</span>
            </div>
        </div>

        <nav class="flex-1 py-8 space-y-1 px-4 overflow-y-auto">
            <?php
            $currentPage = basename($_SERVER['PHP_SELF']);
            $currentDir = basename(dirname($_SERVER['PHP_SELF']));

            function navLink(string $href, string $label, string $icon, bool $active): string
            {
                $cls = $active
                    ? 'bg-mb-black text-white border-l-2 border-mb-accent'
                    : 'text-mb-silver hover:bg-mb-black hover:text-white';
                return "<a href=\"$href\" class=\"flex items-center gap-4 px-4 py-3 transition-all rounded-md group $cls\">
                    $icon
                    <span class=\"font-light\">$label</span>
                </a>";
            }

            $icons = [
                'dashboard' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
                'vehicles' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 012-2 2 2 0 002 2 2 2 0 11-2-2 2 2 0 00-2 2z"/></svg>',
                'clients' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
                'reservations' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
                'investments' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                'gps' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                'papers' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                'expenses' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                'challans' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
                'staff' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                'settings' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                'leads' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                'pipeline' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0v10m0-10a2 2 0 012 2h2a2 2 0 012-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2z"/></svg>',
            ];

            $root = str_repeat('../', max(0, substr_count($_SERVER['PHP_SELF'], '/') - 1));

            // Auth-based nav rendering
            $isAdmin = ($_currentUser['role'] ?? '') === 'admin';
            $cuPerms = $_currentUser['permissions'] ?? [];

            $isDash = $currentPage === 'index.php' && !in_array($currentDir, ['vehicles', 'clients', 'reservations', 'investments', 'gps', 'papers', 'expenses', 'challans', 'staff', 'settings', 'leads'], true);
            echo navLink("{$root}index.php", 'Dashboard', $icons['dashboard'], $isDash);

            if ($isAdmin || in_array('add_vehicles', $cuPerms, true)) {
                echo navLink("{$root}vehicles/index.php", 'Vehicles', $icons['vehicles'], $currentDir === 'vehicles');
                echo navLink("{$root}vehicles/requests.php", 'Vehicle Requests', '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>', $currentPage === 'requests.php' && $currentDir === 'vehicles');
            }
            if ($isAdmin || array_intersect(['add_reservations', 'do_delivery', 'do_return'], $cuPerms)) {
                echo navLink("{$root}reservations/index.php", 'Reservations', $icons['reservations'], $currentDir === 'reservations');
            }
            if ($isAdmin || in_array('manage_clients', $cuPerms, true)) {
                echo navLink("{$root}clients/index.php", 'Clients', $icons['clients'], $currentDir === 'clients');
            }
            if ($isAdmin || in_array('add_leads', $cuPerms, true)) {
                echo navLink("{$root}leads/pipeline.php", 'Pipeline', $icons['pipeline'], $currentDir === 'leads');
            }
            if ($isAdmin || in_array('manage_staff', $cuPerms, true)) {
                echo navLink("{$root}staff/index.php", 'Staff', $icons['staff'], $currentDir === 'staff');
            }
            if ($isAdmin) {
                $attendanceIcon = '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>';
                echo navLink("{$root}attendance/index.php", 'Attendance', $attendanceIcon, $currentDir === 'attendance');
            }
            echo navLink("{$root}settings/general.php", 'Settings', $icons['settings'], $currentDir === 'settings');
            ?>
        </nav>

        <!-- Logged-in user info -->
        <div class="p-4 border-t border-mb-subtle/20">
            <div class="flex items-center gap-3 px-2 py-2 text-mb-silver">
                <div
                    class="w-8 h-8 rounded-full bg-mb-accent/10 border border-mb-accent/30 flex items-center justify-center text-xs font-semibold text-mb-accent flex-shrink-0">
                    <?= strtoupper(substr($_currentUser['name'] ?? 'U', 0, 2)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate"><?= e($_currentUser['name'] ?? 'User') ?></p>
                    <p class="text-xs text-mb-subtle capitalize"><?= e($_currentUser['role'] ?? '') ?></p>
                </div>
                <a href="<?= $root ?>auth/logout.php" title="Sign out"
                    class="flex-shrink-0 w-7 h-7 flex items-center justify-center rounded-full border border-mb-subtle/20 hover:border-red-500/40 hover:text-red-400 transition-all text-mb-subtle">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col overflow-hidden relative">
        <!-- Header -->
        <header
            class="h-20 flex items-center justify-between px-8 bg-mb-black/90 sticky top-0 z-50 border-b border-mb-subtle/10">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-light text-white tracking-wide">
                    <?= e($pageTitle ?? 'Dashboard') ?>
                </h1>
            </div>
            <div class="flex items-center gap-4">
                <?php
                // ── Punch In/Out Widget (non-admin staff only) ─────────────
                if (($_currentUser['role'] ?? '') !== 'admin'):
                    $ist = new DateTimeZone('Asia/Kolkata');
                    $todayIst2 = (new DateTime('now', $ist))->format('Y-m-d');
                    $attRec2 = null;
                    try {
                        $punchStmt2 = $pdo->prepare('SELECT punch_in, punch_out FROM staff_attendance WHERE user_id = ? AND date = ? LIMIT 1');
                        $punchStmt2->execute([$_currentUser['id'], $todayIst2]);
                        $attRec2 = $punchStmt2->fetch();
                    } catch (Throwable $e2) {
                    }
                    $hasPunchIn = $attRec2 && $attRec2['punch_in'];
                    $hasPunchOut = $attRec2 && $attRec2['punch_out'];
                    ?>
                    <div class="flex items-center gap-2 bg-mb-surface border border-mb-subtle/20 rounded-full px-3 py-1.5"
                        id="punch-widget">
                        <!-- Live IST Clock -->
                        <span id="ist-clock" class="text-xs text-mb-silver font-mono tabular-nums"></span>
                        <?php if ($hasPunchIn && $hasPunchOut): ?>
                            <span class="text-[10px] text-green-400 flex items-center gap-1">✓ Done</span>
                        <?php elseif ($hasPunchIn): ?>
                            <button onclick="doPunch('punch_out')"
                                class="text-[11px] bg-red-500/20 text-red-400 border border-red-500/30 px-3 py-0.5 rounded-full hover:bg-red-500/30 transition-colors font-medium">
                                Punch Out
                            </button>
                        <?php else: ?>
                            <button onclick="doPunch('punch_in')"
                                class="text-[11px] bg-green-500/20 text-green-400 border border-green-500/30 px-3 py-0.5 rounded-full hover:bg-green-500/30 transition-colors font-medium">
                                Punch In
                            </button>
                        <?php endif; ?>
                    </div>
                    <script>
                        // Live IST clock
                        function updateIstClock() {
                            const now = new Date();
                            const ist = new Intl.DateTimeFormat('en-IN', {
                                timeZone: 'Asia/Kolkata',
                                hour: '2-digit', minute: '2-digit', second: '2-digit',
                                hour12: true
                            }).format(now);
                            const el = document.getElementById('ist-clock');
                            if (el) el.textContent = ist;
                        }
                        updateIstClock();
                        setInterval(updateIstClock, 1000);

                        async function doPunch(action) {
                            const btn = event.currentTarget;
                            btn.disabled = true;
                            btn.style.opacity = '0.6';
                            try {
                                const root = '<?= $root ?>';
                                const fd = new FormData();
                                fd.append('action', action);
                                const res = await fetch(root + 'attendance/punch.php', { method: 'POST', body: fd });
                                const data = await res.json();
                                if (data.warning) {
                                    alert('⚠️ ' + data.message);
                                } else if (!data.ok) {
                                    alert('❌ ' + data.message);
                                    btn.disabled = false;
                                    btn.style.opacity = '';
                                    return;
                                }
                                location.reload();
                            } catch (e) {
                                alert('Network error. Please try again.');
                                btn.disabled = false;
                                btn.style.opacity = '';
                            }
                        }
                    </script>
                <?php endif; ?>
                <!-- Theme Toggle -->
                <button id="theme-toggle" onclick="toggleTheme()" title="Switch theme"
                    class="relative w-9 h-9 rounded-full flex items-center justify-center border border-mb-subtle/20 hover:border-mb-accent/50 transition-all hover:bg-mb-accent/5 group">
                    <svg id="icon-moon" style="width:18px;height:18px"
                        class="text-mb-silver group-hover:text-mb-accent transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                    </svg>
                    <svg id="icon-sun" style="width:18px;height:18px;display:none"
                        class="text-mb-silver group-hover:text-mb-accent transition-colors" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-6.07-.71.71M6.34 17.66l-.71.71m12.73 0-.71-.71M6.34 6.34l-.71-.71M12 8a4 4 0 100 8 4 4 0 000-8z" />
                    </svg>
                </button>
                <!-- Notification Bell with Popup -->
                <div class="relative" id="notif-bell-wrap">
                    <button onclick="toggleNotif()" title="Notifications"
                        class="relative w-9 h-9 rounded-full flex items-center justify-center border border-mb-subtle/20 hover:border-mb-accent/50 transition-all hover:bg-mb-accent/5 group">
                        <svg style="width:18px;height:18px"
                            class="text-mb-silver group-hover:text-mb-accent transition-colors" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <?php if ($_notifCount > 0): ?>
                            <span
                                class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                                <?= min(9, $_notifCount) ?>     <?= $_notifCount > 9 ? '+' : '' ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- Dropdown -->
                    <div id="notif-dropdown" style="display:none"
                        class="absolute right-0 top-12 w-80 bg-mb-surface border border-mb-subtle/20 rounded-xl shadow-2xl z-[200] overflow-hidden">

                        <!-- Header -->
                        <div class="flex items-center justify-between px-4 py-3 border-b border-mb-subtle/20">
                            <span class="text-white text-sm font-medium">Notifications
                                <?php if ($_notifCount > 0): ?>
                                    <span
                                        class="ml-1 text-xs bg-red-500/20 text-red-400 px-1.5 py-0.5 rounded-full"><?= $_notifCount ?>
                                        new</span>
                                <?php endif; ?>
                            </span>
                            <div class="flex items-center gap-2">
                                <?php if ($_notifs): ?>
                                    <form method="POST" action="<?= $root ?>notifications/clear.php" class="inline">
                                        <input type="hidden" name="action" value="mark_all_read">
                                        <button type="submit"
                                            class="text-xs text-mb-subtle hover:text-mb-accent transition-colors">Mark all
                                            read</button>
                                    </form>
                                    <span class="text-mb-subtle/40 text-xs">·</span>
                                    <form method="POST" action="<?= $root ?>notifications/clear.php" class="inline"
                                        onsubmit="return confirm('Clear all notifications?')">
                                        <input type="hidden" name="action" value="clear_all">
                                        <button type="submit"
                                            class="text-xs text-red-400 hover:text-red-300 transition-colors">Clear
                                            all</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Notification List -->
                        <div class="max-h-80 overflow-y-auto">
                            <?php if (empty($_notifs)): ?>
                                <div class="px-4 py-8 text-center">
                                    <p class="text-3xl mb-2">🔔</p>
                                    <p class="text-mb-subtle text-sm">No notifications</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($_notifs as $n): ?>
                                    <?php
                                    $bg = $n['is_read'] ? '' : 'bg-mb-black/30';
                                    $dot = match ($n['type']) {
                                        'overdue' => 'bg-red-500',
                                        'due_today' => 'bg-orange-500',
                                        'due_soon' => 'bg-yellow-500',
                                        default => 'bg-blue-500'
                                    };
                                    ?>
                                    <div
                                        class="flex items-start gap-3 px-4 py-3 <?= $bg ?> border-b border-mb-subtle/10 hover:bg-mb-black/20 transition-colors group">
                                        <span
                                            class="w-2 h-2 mt-1.5 rounded-full <?= $dot ?> flex-shrink-0 <?= $n['is_read'] ? 'opacity-30' : '' ?>"></span>
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-sm <?= $n['is_read'] ? 'text-mb-subtle' : 'text-white' ?> leading-snug">
                                                <?= htmlspecialchars($n['message']) ?>
                                            </p>
                                            <p class="text-xs text-mb-subtle mt-0.5">
                                                <?= date('d M, h:i A', strtotime($n['created_at'])) ?>
                                            </p>
                                        </div>
                                        <?php if (!$n['is_read']): ?>
                                            <form method="POST" action="<?= $root ?>notifications/clear.php"
                                                class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                                <button type="submit" title="Mark as read"
                                                    class="text-mb-subtle hover:text-white text-xs">✓</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>



                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="flex-1 overflow-y-auto bg-gradient-to-br from-mb-black to-mb-surface p-8">
