<?php
require_once __DIR__ . '/../config/db.php';
auth_check();
$_currentUser = current_user();
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/settings_helpers.php';
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

        /* Shared mobile UI patterns (Batch 2)
           Keeps logic untouched; only visual/touch usability improvements. */
        @media (max-width: 767px) {
            main form input:not([type="checkbox"]):not([type="radio"]),
            main form select,
            main form textarea {
                min-height: 44px;
                font-size: 16px !important;
            }

            main form button,
            main .btn,
            main a[class*="px-"][class*="py-"] {
                min-height: 40px;
            }

            main .bg-mb-surface[class*="rounded-xl"] {
                border-radius: 0.75rem;
            }

            /* Mobile table behavior (Batch 3) */
            main .overflow-x-auto {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            main .overflow-x-auto > table,
            main .overflow-x-auto table {
                min-width: 680px;
            }

            main .overflow-x-auto table th,
            main .overflow-x-auto table td {
                white-space: nowrap;
            }

            main .overflow-x-auto table td.whitespace-normal,
            main .overflow-x-auto table td.whitespace-pre-wrap,
            main .overflow-x-auto table th.whitespace-normal,
            main .overflow-x-auto table th.whitespace-pre-wrap {
                white-space: normal;
            }

            /* Common modal panels on mobile */
            .fixed.inset-0.z-50 > .w-full,
            .fixed.inset-0.z-\[9999\] > .w-full,
            .fixed.inset-0.z-\[100\] > .w-full {
                width: calc(100vw - 1rem);
                max-width: calc(100vw - 1rem);
                max-height: 90vh;
                overflow-y: auto;
            }

            .select2-container {
                width: 100% !important;
            }
        }

        /* Expandable sidebar menu */
        .sidebar-chevron { transition: transform 0.25s ease; }
        .sidebar-chevron.expanded { transform: rotate(90deg); }
        .sidebar-submenu { display: none !important; }
        .sidebar-submenu.open { display: block !important; }

        /* Mobile bottom navigation */
        .mobile-bottom-nav {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .light-mode .mobile-bottom-nav {
            background-color: rgba(255, 255, 255, 0.95) !important;
            border-top-color: rgba(15, 23, 42, 0.12) !important;
        }
        .light-mode .mobile-bottom-nav a {
            color: #64748b;
        }
        .light-mode .mobile-bottom-nav a.mobile-bottom-nav-active {
            color: #0284c7;
        }
    </style>
</head>

<body
    class="bg-mb-black text-white font-sans antialiased h-screen flex selection:bg-mb-accent selection:text-white overflow-x-hidden">

    <!-- Sidebar -->
    <aside id="app-sidebar"
        class="w-64 max-w-[85vw] bg-mb-surface flex flex-col border-r border-mb-subtle/20 fixed inset-y-0 left-0 z-[70] transform -translate-x-full transition-transform duration-300 ease-in-out shadow-2xl md:static md:z-auto md:translate-x-0 md:shadow-none">
        <div class="h-20 flex items-center justify-center border-b border-mb-subtle/20">
            <div class="flex items-center gap-2">
                <!-- <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" />
                </svg> -->
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
                'profile' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4a4 4 0 100 8 4 4 0 000-8zM4 20a8 8 0 0116 0v1H4v-1z"/></svg>',
                'settings' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                'leads' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                'pipeline' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0v10m0-10a2 2 0 012 2h2a2 2 0 012-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2z"/></svg>',
                'reports' => '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
            ];

            $scriptPath = trim(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/');
            $segments = $scriptPath === '' ? [] : explode('/', $scriptPath);
            $moduleDirs = [
                'vehicles',
                'clients',
                'reservations',
                'investments',
                'gps',
                'papers',
                'expenses',
                'challans',
                'staff',
                'settings',
                'leads',
                'accounts',
                'notifications',
                'attendance',
                'auth',
                'payroll',
                'reports',
                'dashboard',
            ];
            $moduleIdx = null;
            foreach ($segments as $i => $seg) {
                if (in_array($seg, $moduleDirs, true)) {
                    $moduleIdx = $i;
                    break;
                }
            }
            if ($moduleIdx !== null) {
                $prefixParts = array_slice($segments, 0, $moduleIdx);
            } else {
                $prefixParts = count($segments) > 0 ? array_slice($segments, 0, -1) : [];
            }
            $root = '/' . (empty($prefixParts) ? '' : implode('/', $prefixParts) . '/');

            // Auth-based nav rendering
            $isAdmin = ($_currentUser['role'] ?? '') === 'admin';
            if (!$isAdmin && !empty($_currentUser['staff_id'])) {
                try {
                    $dashChk = $pdo->prepare("SELECT enable_admin_dashboard FROM staff WHERE id = ?");
                    $dashChk->execute([(int)$_currentUser['staff_id']]);
                    $dashRow = $dashChk->fetch();
                    if (!empty($dashRow['enable_admin_dashboard'])) {
                        if (isset($_SESSION['force_staff_dashboard'])) {
                            $isAdmin = !$_SESSION['force_staff_dashboard'];
                        } else {
                            $isAdmin = true;
                        }
                    }
                } catch (Throwable $e) {}
            }
            $cuPerms = $_currentUser['permissions'] ?? [];
            $canVehiclesFull = $isAdmin || in_array('add_vehicles', $cuPerms, true) || in_array('view_all_vehicles', $cuPerms, true);
            $canVehiclesAvailability = $isAdmin || in_array('add_vehicles', $cuPerms, true) || in_array('view_vehicle_availability', $cuPerms, true);
            $canVehiclesRequests = $isAdmin || in_array('add_vehicles', $cuPerms, true) || in_array('view_vehicle_requests', $cuPerms, true);
            $canVehiclesMenu = $canVehiclesFull || $canVehiclesAvailability || $canVehiclesRequests || !empty($_currentUser['staff_id']);

            $isDash = $currentPage === 'index.php' && $moduleIdx === null;
            echo navLink("{$root}index.php", 'Dashboard', $icons['dashboard'], $isDash);

            if ($canVehiclesMenu) {
                $vActive = $currentDir === 'vehicles';
                $vCls = $vActive
                    ? 'bg-mb-black text-white border-l-2 border-mb-accent'
                    : 'text-mb-silver hover:bg-mb-black hover:text-white';
                echo '<div onclick="toggleSubmenu(\'vehicles\')" class="flex items-center gap-4 px-4 py-3 transition-all rounded-md group cursor-pointer ' . $vCls . '">
                    ' . $icons['vehicles'] . '
                    <span class="font-light flex-1">Vehicles</span>
                    <svg class="w-4 h-4 opacity-50 sidebar-chevron ' . ($vActive ? 'expanded' : '') . '" id="chevron-vehicles" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>';
                echo '<div id="submenu-vehicles" class="ml-11 mt-1 pl-3 border-l border-mb-subtle/30 space-y-1 sidebar-submenu ' . ($vActive ? 'open' : '') . '">';
                echo '<a href="' . $root . 'vehicles/index.php" class="block text-xs px-3 py-1.5 rounded-lg ' . ($currentDir === 'vehicles' && !in_array($currentPage, ['availability.php', 'requests.php', 'challans.php', 'create_challan.php', 'edit_challan.php']) ? 'text-mb-accent bg-mb-accent/10' : 'text-white/75 hover:text-white hover:bg-mb-accent/10') . ' transition-colors">Vehicle List</a>';
                if ($canVehiclesAvailability) {
                    echo '<a href="' . $root . 'vehicles/availability.php" class="block text-xs px-3 py-1.5 rounded-lg ' . ($currentPage === 'availability.php' ? 'text-mb-accent bg-mb-accent/10' : 'text-white/75 hover:text-white hover:bg-mb-accent/10') . ' transition-colors">Vehicle Availability</a>';
                }
                if ($canVehiclesRequests) {
                    echo '<a href="' . $root . 'vehicles/requests.php" class="block text-xs px-3 py-1.5 rounded-lg ' . ($currentPage === 'requests.php' ? 'text-mb-accent bg-mb-accent/10' : 'text-white/75 hover:text-white hover:bg-mb-accent/10') . ' transition-colors">Vehicle Requests</a>';
                }
                if ($isAdmin || in_array('add_vehicles', $cuPerms, true)) {
                    echo '<a href="' . $root . 'vehicles/challans.php" class="block text-xs px-3 py-1.5 rounded-lg ' . ($currentPage === 'challans.php' ? 'text-mb-accent bg-mb-accent/10' : 'text-white/75 hover:text-white hover:bg-mb-accent/10') . ' transition-colors">Challans</a>';
                }
                echo '</div>';
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
            // if ($isAdmin || in_array('add_vehicles', $cuPerms, true)) {
            //     echo navLink("{$root}vehicles/requests.php", 'Vehicle Requests', '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>', $currentPage === 'requests.php' && $currentDir === 'vehicles');
            // }
            if ($isAdmin || array_intersect(['add_reservations', 'do_delivery', 'do_return'], $cuPerms)) {
                echo navLink("{$root}gps/index.php", 'GPS Tracking', $icons['gps'], $currentDir === 'gps');
            }
            if ($isAdmin || in_array('view_finances', $cuPerms, true)) {
                $accountIcon = '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>';
                $targetIcon  = '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>';
                $hopeIcon = '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';
                $reportsIcon = '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>';
                echo navLink("{$root}accounts/index.php", 'Accounts', $accountIcon, $currentDir === 'accounts' && !in_array($currentPage, ['targets.php', 'hope_window.php'], true));
                echo navLink("{$root}accounts/targets.php", 'Targets', $targetIcon, $currentDir === 'accounts' && $currentPage === 'targets.php');
                echo navLink("{$root}accounts/hope_window.php", 'Hope Window', $hopeIcon, $currentDir === 'accounts' && $currentPage === 'hope_window.php');
                echo navLink("{$root}reports/index.php", 'Reports', $reportsIcon, $currentDir === 'reports');
            }
            $hasStaffProfile = !empty($_currentUser['staff_id']);
            if ($hasStaffProfile) {
                echo navLink("{$root}staff/my_profile.php", 'My Profile', $icons['profile'], $currentDir === 'staff' && $currentPage === 'my_profile.php');
            }
            if ($isAdmin || in_array('manage_staff', $cuPerms, true)) {
                if ($isAdmin) {
                    $sActive = $currentDir === 'staff';
                    $sCls = $sActive
                        ? 'bg-mb-black text-white border-l-2 border-mb-accent'
                        : 'text-mb-silver hover:bg-mb-black hover:text-white';
                    echo '<div onclick="toggleSubmenu(\'staff\')" class="flex items-center gap-4 px-4 py-3 transition-all rounded-md group cursor-pointer ' . $sCls . '">
                        ' . $icons['staff'] . '
                        <span class="font-light flex-1">Staff</span>
                        <svg class="w-4 h-4 opacity-50 sidebar-chevron ' . ($sActive ? 'expanded' : '') . '" id="chevron-staff" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>';
                    echo '<div id="submenu-staff" class="ml-11 mt-1 pl-3 border-l border-mb-subtle/30 space-y-1 sidebar-submenu ' . ($sActive ? 'open' : '') . '">';
                    echo '<a href="' . $root . 'staff/index.php" class="block text-xs px-3 py-1.5 rounded-lg ' . ($currentDir === 'staff' && $currentPage !== 'tasks.php' ? 'text-mb-accent bg-mb-accent/10' : 'text-white/75 hover:text-white hover:bg-mb-accent/10') . ' transition-colors">Staff List</a>';
                    echo '<a href="' . $root . 'staff/tasks.php" class="block text-xs px-3 py-1.5 rounded-lg ' . ($currentPage === 'tasks.php' ? 'text-mb-accent bg-mb-accent/10' : 'text-white/75 hover:text-white hover:bg-mb-accent/10') . ' transition-colors">Staff Tasks</a>';
                    echo '</div>';
                } else {
                    echo navLink("{$root}staff/index.php", 'Staff', $icons['staff'], $currentDir === 'staff');
                }
            }
            if ($isAdmin) {
                $attendanceIcon = '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>';
                echo navLink("{$root}attendance/index.php", 'Attendance', $attendanceIcon, $currentDir === 'attendance');
                $payrollIcon = '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>';
                echo navLink("{$root}payroll/index.php", 'Payroll', $payrollIcon, $currentDir === 'payroll');
                $investIcon = '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                echo navLink("{$root}investments/index.php", 'EMI Management', $investIcon, $currentDir === 'investments');
            }
            echo navLink("{$root}settings/general.php", 'Settings', $icons['settings'], $currentDir === 'settings');

            $canVehicles = $canVehiclesMenu;
            $canClients = $isAdmin || in_array('manage_clients', $cuPerms, true);
            $canPipeline = $isAdmin || in_array('add_leads', $cuPerms, true);
            $canReservations = $isAdmin || !empty(array_intersect(['add_reservations', 'do_delivery', 'do_return'], $cuPerms));
            $canGps = $canReservations;
            $canAccounts = $isAdmin || in_array('view_finances', $cuPerms, true);
            $hasStaffProfile = !empty($_currentUser['staff_id']);
            $mobileAccountIcon = '<svg class="w-5 h-5 opacity-70 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>';
            $mobileMenuCatalog = [
                'reports' => ['href' => "{$root}reports/index.php", 'label' => 'Reports', 'icon' => $icons['reports'], 'active' => $currentDir === 'reports', 'allowed' => $canAccounts],
                'vehicles' => ['href' => "{$root}vehicles/index.php", 'label' => 'Vehicles', 'icon' => $icons['vehicles'], 'active' => $currentDir === 'vehicles', 'allowed' => $canVehicles],
                'pipeline' => ['href' => "{$root}leads/pipeline.php", 'label' => 'Pipeline', 'icon' => $icons['pipeline'], 'active' => $currentDir === 'leads', 'allowed' => $canPipeline],
                'reservations' => ['href' => "{$root}reservations/index.php", 'label' => 'Bookings', 'icon' => $icons['reservations'], 'active' => $currentDir === 'reservations', 'allowed' => $canReservations],
                'accounts' => ['href' => "{$root}accounts/index.php", 'label' => 'Accounts', 'icon' => $mobileAccountIcon, 'active' => $currentDir === 'accounts', 'allowed' => $canAccounts],
                'hope_window' => ['href' => "{$root}accounts/hope_window.php", 'label' => 'Hope Window', 'icon' => $mobileAccountIcon, 'active' => $currentDir === 'accounts' && $currentPage === 'hope_window.php', 'allowed' => $canAccounts],
                'clients' => ['href' => "{$root}clients/index.php", 'label' => 'Clients', 'icon' => $icons['clients'], 'active' => $currentDir === 'clients', 'allowed' => $canClients],
                'gps' => ['href' => "{$root}gps/index.php", 'label' => 'GPS', 'icon' => $icons['gps'], 'active' => $currentDir === 'gps', 'allowed' => $canGps],
                'my_profile' => ['href' => "{$root}staff/my_profile.php", 'label' => 'My Profile', 'icon' => $icons['profile'], 'active' => $currentDir === 'staff' && $currentPage === 'my_profile.php', 'allowed' => $hasStaffProfile],
                'settings' => ['href' => "{$root}settings/general.php", 'label' => 'Settings', 'icon' => $icons['settings'], 'active' => $currentDir === 'settings', 'allowed' => true],
            ];

            $mobileNavItems = [];
            $mobileAddedKeys = [];
            $mobileSelectedKeys = mobile_bottom_nav_get_keys($pdo, 5);
            foreach ($mobileSelectedKeys as $menuKey) {
                if (!isset($mobileMenuCatalog[$menuKey])) {
                    continue;
                }
                $menu = $mobileMenuCatalog[$menuKey];
                if (empty($menu['allowed']) || isset($mobileAddedKeys[$menuKey])) {
                    continue;
                }
                unset($menu['allowed']);
                $mobileAddedKeys[$menuKey] = true;
                $mobileNavItems[] = $menu;
            }

            $mobileFallbackOrder = array_merge(mobile_bottom_nav_default_keys(), array_keys($mobileMenuCatalog));
            foreach ($mobileFallbackOrder as $menuKey) {
                if (count($mobileNavItems) >= 5) {
                    break;
                }
                if (!isset($mobileMenuCatalog[$menuKey]) || isset($mobileAddedKeys[$menuKey])) {
                    continue;
                }
                $menu = $mobileMenuCatalog[$menuKey];
                if (empty($menu['allowed'])) {
                    continue;
                }
                unset($menu['allowed']);
                $mobileAddedKeys[$menuKey] = true;
                $mobileNavItems[] = $menu;
            }
            $mobileNavItems = array_slice($mobileNavItems, 0, 5);
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

    <div id="app-sidebar-backdrop" class="fixed inset-0 z-[60] bg-black/60 hidden md:hidden"></div>
    <?php if (!empty($mobileNavItems)): ?>
    <nav id="mobile-bottom-nav"
        class="mobile-bottom-nav md:hidden fixed inset-x-0 bottom-0 z-[55] border-t border-mb-subtle/30 bg-mb-surface/95">
        <div class="flex items-center justify-between gap-1 px-2 pt-1.5"
            style="padding-bottom: calc(env(safe-area-inset-bottom) + 0.375rem);">
            <?php foreach ($mobileNavItems as $item): ?>
                <?php
                    $isActiveMobile = !empty($item['active']);
                    $itemIcon = (string) ($item['icon'] ?? '');
                    if ($isActiveMobile) {
                        $itemIcon = str_replace('opacity-70 group-hover:opacity-100', 'opacity-100', $itemIcon);
                    }
                ?>
                <a href="<?= e((string) ($item['href'] ?? '#')) ?>"
                    class="group flex-1 min-w-0 flex flex-col items-center justify-center gap-1 py-1.5 rounded-md transition-colors <?= $isActiveMobile ? 'mobile-bottom-nav-active text-mb-accent' : 'text-mb-silver hover:text-white' ?>">
                    <?= $itemIcon ?>
                    <span class="text-[11px] leading-none truncate w-full text-center px-1">
                        <?= e((string) ($item['label'] ?? '')) ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Main Content -->
    <main class="flex-1 min-w-0 min-h-0 flex flex-col overflow-hidden relative">
        <!-- Header -->
        <header
            class="h-16 md:h-20 flex items-center justify-between px-4 sm:px-6 md:px-8 bg-mb-black/90 sticky top-0 z-50 border-b border-mb-subtle/10">
            <div class="flex items-center gap-4">
                <button id="sidebar-toggle" type="button"
                    class="md:hidden inline-flex items-center justify-center w-9 h-9 rounded-lg border border-mb-subtle/30 text-mb-silver hover:text-white hover:border-mb-accent/50 transition-colors"
                    aria-label="Open menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <h1 class="text-lg md:text-xl font-light text-white tracking-wide truncate">
                    <?= e($pageTitle ?? 'Dashboard') ?>
                </h1>
            </div>
            <div class="flex items-center gap-2 sm:gap-4">
                <?php
                // Punch In/Out Widget (non-admin staff only)
                if (($_currentUser['role'] ?? '') !== 'admin'):
                    $ist = new DateTimeZone('Asia/Kolkata');
                    $todayIst2 = (new DateTime('now', $ist))->format('Y-m-d');
                    $attRec2   = null;
                    $onBreak2  = false;
                    try {
                        $punchStmt2 = $pdo->prepare('SELECT id, punch_in, punch_out FROM staff_attendance WHERE user_id = ? AND date = ? LIMIT 1');
                        $punchStmt2->execute([$_currentUser['id'], $todayIst2]);
                        $attRec2 = $punchStmt2->fetch();
                        if ($attRec2 && $attRec2['punch_in'] && !$attRec2['punch_out']) {
                            $brkStmt = $pdo->prepare('SELECT id FROM attendance_breaks WHERE attendance_id=? AND break_end IS NULL LIMIT 1');
                            $brkStmt->execute([$attRec2['id']]);
                            $onBreak2 = (bool)$brkStmt->fetch();
                        }
                    } catch (Throwable $e2) {
                        app_log('ERROR', 'Header punch widget: attendance lookup failed - ' . $e2->getMessage(), [
                            'file' => $e2->getFile() . ':' . $e2->getLine(),
                            'screen' => 'includes/header.php',
                            'user_id' => (int) ($_currentUser['id'] ?? 0),
                        ]);
                    }
                    $hasPunchIn  = $attRec2 && $attRec2['punch_in'];
                    $hasPunchOut = $attRec2 && $attRec2['punch_out'];
                    ?>
                    <div class="flex items-center gap-1 sm:gap-2 bg-mb-surface border border-mb-subtle/20 rounded-full px-2 sm:px-3 py-1.5" id="punch-widget">
                        <span id="ist-clock" class="hidden sm:inline text-xs text-mb-silver font-mono tabular-nums"></span>
                        <?php if ($hasPunchIn && $hasPunchOut): ?>
                            <span class="text-[10px] text-green-400">&#10003; Done</span>
                        <?php elseif ($onBreak2): ?>
                            <button onclick="doPunch('break_resume')" class="text-[11px] bg-yellow-500/20 text-yellow-300 border border-yellow-500/30 px-3 py-0.5 rounded-full hover:bg-yellow-500/30 transition-colors font-medium">&#9654; Resume</button>
                            <button onclick="doPunch('punch_out')" class="text-[11px] bg-red-500/20 text-red-400 border border-red-500/30 px-2 py-0.5 rounded-full hover:bg-red-500/30 transition-colors">Out</button>
                        <?php elseif ($hasPunchIn): ?>
                            <button onclick="openBreakModal()" class="text-[11px] bg-amber-500/20 text-amber-300 border border-amber-500/30 px-2 py-0.5 rounded-full hover:bg-amber-500/30 transition-colors">&#9749; Break</button>
                            <button onclick="doPunch('punch_out')" class="text-[11px] bg-red-500/20 text-red-400 border border-red-500/30 px-3 py-0.5 rounded-full hover:bg-red-500/30 transition-colors font-medium">Punch Out</button>
                        <?php else: ?>
                            <button onclick="doPunch('punch_in')" class="text-[11px] bg-green-500/20 text-green-400 border border-green-500/30 px-3 py-0.5 rounded-full hover:bg-green-500/30 transition-colors font-medium">Punch In</button>
                        <?php endif; ?>
                    </div>
                    <div id="late-reason-modal" class="hidden fixed inset-0 z-[9999] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
                        <div class="w-full max-w-sm bg-mb-surface border border-amber-500/30 rounded-xl shadow-2xl p-6 space-y-4">
                            <h3 class="text-white font-medium border-l-2 border-amber-400 pl-3">Late Punch-In</h3>
                            <p class="text-mb-subtle text-sm">You are outside the allowed punch-in window. Please provide a reason.</p>
                            <textarea id="late-reason-input" rows="3" placeholder="e.g. Traffic, emergency..." class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent resize-none"></textarea>
                            <div class="flex justify-end gap-3">
                                <button onclick="closeLateModal()" class="text-mb-silver text-sm px-4 py-2 hover:text-white">Cancel</button>
                                <button onclick="submitLateReason()" class="bg-mb-accent text-white px-5 py-2 rounded-full text-sm hover:bg-mb-accent/80">Submit</button>
                            </div>
                        </div>
                    </div>
                    <div id="break-reason-modal" class="hidden fixed inset-0 z-[9999] bg-black/70 backdrop-blur-sm flex items-center justify-center p-4">
                        <div class="w-full max-w-sm bg-mb-surface border border-amber-500/30 rounded-xl shadow-2xl p-6 space-y-4">
                            <h3 class="text-white font-medium border-l-2 border-amber-400 pl-3">Start Break</h3>
                            <p class="text-mb-subtle text-sm">Enter a reason for your break.</p>
                            <textarea id="break-reason-input" rows="2" placeholder="e.g. Lunch, prayer..." class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-mb-accent resize-none"></textarea>
                            <div class="flex justify-end gap-3">
                                <button onclick="closeBreakModal()" class="text-mb-silver text-sm px-4 py-2 hover:text-white">Cancel</button>
                                <button onclick="submitBreak()" class="bg-amber-500 text-white px-5 py-2 rounded-full text-sm hover:bg-amber-400">Start Break</button>
                            </div>
                        </div>
                    </div>
                    <script>
                    (function tick(){var el=document.getElementById('ist-clock');if(el)el.textContent=new Intl.DateTimeFormat('en-IN',{timeZone:'Asia/Kolkata',hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true}).format(new Date());setTimeout(tick,1000);})();
                    var PUNCH_ROOT='<?= $root ?>';
                    var _cachedLoc=null;
                    function getGPS(){return new Promise(function(res,rej){if(!navigator.geolocation){rej('Geolocation not supported.');return;}navigator.geolocation.getCurrentPosition(function(p){res({lat:p.coords.latitude,lng:p.coords.longitude});},function(e){var m={1:'Location denied. Please allow location access and try again.',2:'Location unavailable. Please retry.',3:'Location timed out. Please retry.'};rej(m[e.code]||'Location error.');},{enableHighAccuracy:true,timeout:12000,maximumAge:0});});}
                    async function reverseGeocode(lat,lng){try{var r=await fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='+lat+'&lon='+lng+'&addressdetails=1&accept-language=en',{headers:{'User-Agent':'OrentincarsCRM/1.0'},signal:AbortSignal.timeout(5000)});var d=await r.json();if(d&&d.address){var a=d.address;return[a.road,a.suburb||a.neighbourhood,a.village||a.town||a.city,a.state_district,a.state,a.country].filter(Boolean).join(', ');}}catch(x){}return lat.toFixed(5)+', '+lng.toFixed(5);}
                    function toast(msg,type){var c={error:'#ef4444',warn:'#f59e0b',ok:'#22c55e',info:'#00adef'};var t=document.createElement('div');t.textContent=msg;Object.assign(t.style,{position:'fixed',bottom:'24px',left:'50%',transform:'translateX(-50%)',background:c[type]||c.info,color:'#fff',padding:'10px 22px',borderRadius:'999px',fontSize:'13px',zIndex:99999,boxShadow:'0 4px 20px rgba(0,0,0,.4)',maxWidth:'90vw',textAlign:'center'});document.body.appendChild(t);setTimeout(function(){t.remove();},4000);}
                    async function doPunch(action,extra){extra=extra||{};var btn=(typeof event!=='undefined'&&event&&event.currentTarget)?event.currentTarget:null;if(btn){btn.disabled=true;btn.style.opacity='0.5';}var lat,lng,address;if(_cachedLoc){lat=_cachedLoc.lat;lng=_cachedLoc.lng;address=_cachedLoc.address;_cachedLoc=null;}else{toast('Getting your location...','info');try{var pos=await getGPS();lat=pos.lat;lng=pos.lng;}catch(err){toast(err,'error');if(btn){btn.disabled=false;btn.style.opacity='';}return;}address=await reverseGeocode(lat,lng);}var fd=new FormData();fd.append('action',action);fd.append('lat',lat);fd.append('lng',lng);fd.append('address',address);for(var k in extra){if(extra.hasOwnProperty(k))fd.append(k,extra[k]);}try{var res=await fetch(PUNCH_ROOT+'attendance/punch.php',{method:'POST',body:fd});var data=await res.json();if(!data.ok&&data.needs_late_reason){_cachedLoc={lat:lat,lng:lng,address:address};openLateModal();if(btn){btn.disabled=false;btn.style.opacity='';}return;}if(!data.ok&&data.needs_early_reason){_cachedLoc={lat:lat,lng:lng,address:address};openEarlyModal();if(btn){btn.disabled=false;btn.style.opacity='';}return;}if(!data.ok){toast(data.message,'error');if(btn){btn.disabled=false;btn.style.opacity='';}return;}toast((data.warning?'Warning: ':'')+data.message,data.warning?'warn':'ok');setTimeout(function(){location.reload();},1300);}catch(e){toast('Network error. Please try again.','error');if(btn){btn.disabled=false;btn.style.opacity='';}}}
                    function openLateModal(){document.getElementById('late-reason-modal').classList.remove('hidden');setTimeout(function(){document.getElementById('late-reason-input').focus();},100);}
                    function closeLateModal(){document.getElementById('late-reason-modal').classList.add('hidden');_cachedLoc=null;}
                    function submitLateReason(){var r=document.getElementById('late-reason-input').value.trim();if(!r){toast('Please enter a reason.','warn');return;}closeLateModal();doPunch('punch_in',{late_reason:r});}
                    function openBreakModal(){document.getElementById('break-reason-modal').classList.remove('hidden');setTimeout(function(){document.getElementById('break-reason-input').focus();},100);}
                    function closeBreakModal(){document.getElementById('break-reason-modal').classList.add('hidden');}
                    function submitBreak(){var r=document.getElementById('break-reason-input').value.trim();if(!r){toast('Please enter a break reason.','warn');return;}closeBreakModal();doPunch('break_start',{break_reason:r});}
                    
                    function openEarlyModal(){document.getElementById('early-punchout-modal').classList.remove('hidden');setTimeout(function(){document.getElementById('early-punchout-input').focus();},100);}
                    function closeEarlyModal(){document.getElementById('early-punchout-modal').classList.add('hidden');_cachedLoc=null;}
                    function submitEarlyReason(){var r=document.getElementById('early-punchout-input').value.trim();if(!r){toast('Please enter a reason.','warn');return;}closeEarlyModal();doPunch('punch_out',{early_punchout_reason:r});}
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
                        class="absolute right-0 top-12 w-[92vw] max-w-sm sm:w-80 bg-mb-surface border border-mb-subtle/20 rounded-xl shadow-2xl z-[200] overflow-hidden">

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
                                    $notifTarget = !empty($n['reservation_id'])
                                        ? '../reservations/show.php?id=' . (int) $n['reservation_id']
                                        : '';
                                    $notifClickHref = $notifTarget !== ''
                                        ? $root . 'notifications/clear.php?action=mark_read&id=' . (int) $n['id'] . '&go=' . rawurlencode($notifTarget)
                                        : '';
                                    ?>
                                    <div
                                        class="flex items-start gap-3 px-4 py-3 <?= $bg ?> border-b border-mb-subtle/10 hover:bg-mb-black/20 transition-colors group">
                                        <?php if ($notifClickHref !== ''): ?>
                                            <a href="<?= e($notifClickHref) ?>" class="flex items-start gap-3 flex-1 min-w-0">
                                                <span
                                                    class="w-2 h-2 mt-1.5 rounded-full <?= $dot ?> flex-shrink-0 <?= $n['is_read'] ? 'opacity-30' : '' ?>"></span>
                                                <div class="flex-1 min-w-0">
                                                    <p
                                                        class="text-sm <?= $n['is_read'] ? 'text-mb-subtle' : 'text-white' ?> leading-snug hover:text-mb-accent transition-colors">
                                                        <?= htmlspecialchars($n['message']) ?>
                                                    </p>
                                                    <p class="text-xs text-mb-subtle mt-0.5">
                                                        <?= date('d M, h:i A', strtotime($n['created_at'])) ?>
                                                    </p>
                                                </div>
                                            </a>
                                        <?php else: ?>
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
                                        <?php endif; ?>
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
            <script>
                (function () {
                    const sidebar = document.getElementById('app-sidebar');
                    const backdrop = document.getElementById('app-sidebar-backdrop');
                    const toggle = document.getElementById('sidebar-toggle');
                    if (!sidebar || !backdrop || !toggle) return;

                    const desktopMq = window.matchMedia('(min-width: 768px)');

                    const openSidebar = () => {
                        sidebar.classList.remove('-translate-x-full');
                        backdrop.classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    };

                    const closeSidebar = () => {
                        sidebar.classList.add('-translate-x-full');
                        backdrop.classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                    };

                    toggle.addEventListener('click', function () {
                        if (sidebar.classList.contains('-translate-x-full')) {
                            openSidebar();
                        } else {
                            closeSidebar();
                        }
                    });

                    backdrop.addEventListener('click', closeSidebar);

                    sidebar.querySelectorAll('a').forEach(function (link) {
                        link.addEventListener('click', function () {
                            if (!desktopMq.matches) {
                                closeSidebar();
                            }
                        });
                    });

                    const onViewportChange = function (e) {
                        if (e.matches) {
                            backdrop.classList.add('hidden');
                            document.body.classList.remove('overflow-hidden');
                        } else {
                            closeSidebar();
                        }
                    };

                    if (desktopMq.addEventListener) {
                        desktopMq.addEventListener('change', onViewportChange);
                    } else if (desktopMq.addListener) {
                        desktopMq.addListener(onViewportChange);
                    }
                })();
            </script>
            <script>
                function toggleSubmenu(key) {
                    var sub = document.getElementById('submenu-' + key);
                    var chev = document.getElementById('chevron-' + key);
                    if (!sub) return;
                    var isOpen = sub.classList.contains('open');
                    if (isOpen) {
                        sub.classList.remove('open');
                        if (chev) chev.classList.remove('expanded');
                    } else {
                        sub.classList.add('open');
                        if (chev) chev.classList.add('expanded');
                    }
                }
            </script>        </header>

        <!-- Page Content -->
        <div class="flex-1 min-h-0 overflow-y-auto bg-gradient-to-br from-mb-black to-mb-surface p-4 sm:p-6 md:p-8 pb-24 sm:pb-24 md:pb-8">
