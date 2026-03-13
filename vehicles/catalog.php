<?php
/**
 * Public Vehicle Catalog — No authentication required.
 * Shareable link: /vehicles/catalog.php
 */
require_once __DIR__ . '/../config/db.php';

// Skip auth_check — this is a public page
$pdo = db();

$vehicleId = max(0, (int)($_GET['vehicle_id'] ?? 0));
$singleVehicleMode = $vehicleId > 0;
$filter = $_GET['type'] ?? 'available'; // available | all
$search = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if ($singleVehicleMode) {
    // Strictly lock this catalog page to a single vehicle when shared with vehicle_id.
    $where[] = "v.id = ?";
    $params[] = $vehicleId;
    $filter = 'all';
    $search = '';
} else {
    if ($filter === 'available') {
        $where[] = "v.status = 'available'";
    }
    if ($search !== '') {
        $where[] = "(v.brand LIKE ? OR v.model LIKE ? OR v.license_plate LIKE ? OR v.color LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
}

$sql = "SELECT v.* FROM vehicles v WHERE " . implode(' AND ', $where) . " ORDER BY v.brand, v.model";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vehicles = $stmt->fetchAll();
$selectedVehicle = $singleVehicleMode ? ($vehicles[0] ?? null) : null;

$totalAvailable = $pdo->query("SELECT COUNT(*) FROM vehicles WHERE status='available'")->fetchColumn();

// Load all vehicle images for the modal
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_images (id INT AUTO_INCREMENT PRIMARY KEY, vehicle_id INT NOT NULL, file_path VARCHAR(255) NOT NULL, sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
     app_log('ERROR', 'Vehicle catalog: vehicle_images table ensure failed - ' . $e->getMessage(), [
        'file' => $e->getFile() . ':' . $e->getLine(),
        'screen' => 'vehicles/catalog.php',
        'vehicle_id' => $vehicleId > 0 ? $vehicleId : null,
    ]);
}
$allImgsRaw = $pdo->query("SELECT * FROM vehicle_images ORDER BY vehicle_id, sort_order, id")->fetchAll();
$vehicleImgMap = [];
foreach ($allImgsRaw as $img) { $vehicleImgMap[$img['vehicle_id']][] = $img['file_path']; }

// Build canonical URL for sharing
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = trim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/vehicles/catalog.php')), '/');
$catalogBaseUrl = $scheme . '://' . $host . ($basePath !== '' ? '/' . $basePath : '') . '/vehicles/catalog.php';
$shareUrl = $singleVehicleMode
    ? ($catalogBaseUrl . '?vehicle_id=' . $vehicleId)
    : $catalogBaseUrl;

$pageTitle = 'Vehicle Catalog — Available Fleet';
$metaDescription = "Browse our premium rental fleet. {$totalAvailable} vehicles available now.";
$ogTitle = 'Vehicle Catalog — Available Fleet';
$ogDescription = "Browse {$totalAvailable} available vehicles from our premium rental fleet.";
if ($singleVehicleMode && $selectedVehicle) {
    $name = trim((string)($selectedVehicle['brand'] ?? '') . ' ' . (string)($selectedVehicle['model'] ?? ''));
    $pageTitle = $name . ' — Vehicle Catalog';
    $metaDescription = "View catalog details for {$name}.";
    $ogTitle = $name . ' — Vehicle Catalog';
    $ogDescription = "View catalog details and pricing for {$name}.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta property="og:title" content="<?= e($ogTitle) ?>">
    <meta property="og:description" content="<?= e($ogDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= e($shareUrl) ?>">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0f;
            --surface: #111118;
            --card: #161622;
            --border: rgba(255, 255, 255, 0.07);
            --border-hover: rgba(255, 255, 255, 0.16);
            --accent: #6366f1;
            --accent2: #818cf8;
            --gold: #f59e0b;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --subtle: #475569;
            --green: #22c55e;
            --red: #ef4444;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ── Background ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 102, 241, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(99, 102, 241, 0.06) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .wrap {
            position: relative;
            z-index: 1;
        }

        /* ── Header ── */
        header {
            background: rgba(10, 10, 15, 0.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-inner {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            color: white;
            flex-shrink: 0;
        }

        .brand-name {
            font-size: 1.2rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            color: var(--text);
        }

        .brand-tagline {
            font-size: 0.72rem;
            color: var(--muted);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .header-badges {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .badge {
            padding: 0.3rem 0.9rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid;
        }

        .badge-green {
            background: rgba(34, 197, 94, 0.1);
            color: var(--green);
            border-color: rgba(34, 197, 94, 0.25);
        }

        .badge-purple {
            background: rgba(99, 102, 241, 0.1);
            color: var(--accent2);
            border-color: rgba(99, 102, 241, 0.25);
        }

        /* ── Hero ── */
        .hero {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3.5rem 2rem 2rem;
            text-align: center;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.2);
            color: var(--accent2);
            font-size: 0.75rem;
            font-weight: 500;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.35rem 1rem;
            border-radius: 999px;
            margin-bottom: 1.25rem;
        }

        .hero-eyebrow::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green);
            display: inline-block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1)
            }

            50% {
                opacity: 0.5;
                transform: scale(1.3)
            }
        }

        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.1;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.75rem;
        }

        .hero p {
            font-size: 1.05rem;
            color: var(--muted);
            max-width: 520px;
            margin: 0 auto 2rem;
        }

        /* ── Toolbar ── */
        .toolbar {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 240px;
            max-width: 400px;
        }

        .search-box svg {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: var(--subtle);
            pointer-events: none;
        }

        .search-box input {
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 0.65rem 1rem 0.65rem 2.75rem;
            color: var(--text);
            font-size: 0.875rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s;
        }

        .search-box input:focus {
            border-color: var(--accent);
        }

        .search-box input::placeholder {
            color: var(--subtle);
        }

        .filter-tabs {
            display: flex;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 3px;
            gap: 2px;
        }

        .filter-tab {
            padding: 0.45rem 1.1rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            color: var(--muted);
            transition: all 0.18s;
            white-space: nowrap;
        }

        .filter-tab:hover {
            color: var(--text);
        }

        .filter-tab.active {
            background: var(--accent);
            color: white;
        }

        .result-count {
            margin-left: auto;
            font-size: 0.8rem;
            color: var(--subtle);
            white-space: nowrap;
        }

        /* ── Grid ── */
        .grid-section {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem 5rem;
        }

        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .vehicle-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        .vehicle-card:hover {
            border-color: var(--border-hover);
            transform: translateY(-4px);
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(99, 102, 241, 0.1);
        }

        .card-image {
            height: 200px;
            background: linear-gradient(135deg, #0d0d18, #1a1a2e);
            position: relative;
            overflow: hidden;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .vehicle-card:hover .card-image img {
            transform: scale(1.05);
        }

        .card-image-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-image-placeholder svg {
            width: 56px;
            height: 56px;
            opacity: 0.12;
        }

        /* Gradient overlay on images */
        .card-image::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(22, 22, 34, 0.7) 0%, transparent 50%);
            pointer-events: none;
        }

        .status-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 2;
            padding: 0.25rem 0.7rem;
            border-radius: 999px;
            font-size: 0.7rem;
            font-weight: 600;
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            gap: 0.35rem;
            letter-spacing: 0.02em;
        }

        .status-badge .dot {
            width: 5px;
            height: 5px;
            border-radius: 50%;
        }

        .status-available {
            background: rgba(34, 197, 94, 0.18);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .status-rented {
            background: rgba(99, 102, 241, 0.18);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }

        .status-maintenance {
            background: rgba(239, 68, 68, 0.18);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .dot-available {
            background: #22c55e;
            animation: pulse 2s infinite;
        }

        .dot-rented {
            background: #818cf8;
        }

        .dot-maintenance {
            background: #ef4444;
        }

        .card-body {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .car-make {
            font-size: 0.7rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.25rem;
        }

        .car-name {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--text);
            letter-spacing: -0.01em;
            line-height: 1.2;
        }

        .car-sub {
            font-size: 0.8rem;
            color: var(--subtle);
            margin-top: 0.2rem;
        }

        .card-specs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 0.875rem 0;
        }

        .spec-pill {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 0.2rem 0.65rem;
            font-size: 0.72rem;
            color: var(--muted);
            white-space: nowrap;
        }

        .card-pricing {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .price-main {
            display: flex;
            align-items: baseline;
            gap: 0.2rem;
        }

        .price-amount {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.04em;
        }

        .price-currency {
            font-size: 0.9rem;
            color: var(--muted);
            font-weight: 400;
        }

        .price-period {
            font-size: 0.75rem;
            color: var(--subtle);
        }

        .price-monthly {
            font-size: 0.75rem;
            color: var(--muted);
        }

        /* ── Empty State ── */
        .empty {
            text-align: center;
            padding: 5rem 2rem;
        }

        .empty svg {
            width: 64px;
            height: 64px;
            opacity: 0.12;
            margin: 0 auto 1rem;
            display: block;
        }

        .empty h3 {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--muted);
            margin-bottom: 0.5rem;
        }

        .empty p {
            font-size: 0.875rem;
            color: var(--subtle);
        }

        /* ── Footer / CTA ── */
        footer {
            background: var(--surface);
            border-top: 1px solid var(--border);
            padding: 3rem 2rem;
            text-align: center;
        }

        .footer-inner {
            max-width: 600px;
            margin: 0 auto;
        }

        .cta-heading {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .cta-sub {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .cta-meta {
            font-size: 0.75rem;
            color: var(--subtle);
            margin-top: 2rem;
        }

        /* ── Animations ── */
        .vehicle-card {
            animation: fadeUp 0.4s both;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        <?php foreach ($vehicles as $i => $v):
            echo ".vehicle-card:nth-child({$i}) { animation-delay: " . min($i * 0.05, 0.5) . "s }"; endforeach; ?>
        /* ── Responsive ── */
        @media (max-width: 640px) {
            .header-inner {
                padding: 0.875rem 1rem;
            }

            .hero {
                padding: 2rem 1rem 1.5rem;
            }

            .toolbar {
                padding: 0 1rem 1.5rem;
            }

            .grid-section {
                padding: 0 1rem 3rem;
            }

            .hero h1 {
                font-size: 1.9rem;
            }
        }
    </style>
</head>

<body>
    <div class="wrap">

        <!-- ── Header ── -->
        <header>
            <div class="header-inner">
                <div class="brand">
                    <div class="brand-logo">R</div>
                    <div>
                        <div class="brand-name">orentincars</div>
                    </div>
                </div>
                <div class="header-badges">
                    <span class="badge badge-green">
                        <span
                            style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#22c55e;margin-right:4px;animation:pulse 2s infinite"></span>
                        <?= $totalAvailable ?> Available Now
                    </span>
                </div>
            </div>
        </header>

        <!-- ── Hero ── -->
        <div class="hero">
            <div class="hero-eyebrow"><?= $singleVehicleMode ? 'Vehicle Showcase' : 'Live Catalog' ?></div>
            <h1><?= $singleVehicleMode && $selectedVehicle ? e(trim((string)$selectedVehicle['brand'] . ' ' . (string)$selectedVehicle['model'])) : 'Premium Vehicle Rentals' ?></h1>
            <p><?= $singleVehicleMode ? 'Details and pricing for this selected vehicle.' : 'Browse our carefully curated fleet. Transparent pricing, instant availability.' ?></p>
        </div>

        <!-- ── Toolbar ── -->
        <div class="toolbar">
            <?php if (!$singleVehicleMode): ?>
                <div class="search-box">
                    <form method="GET">
                        <input type="hidden" name="type" value="<?= e($filter) ?>">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search brand, model or plate…"
                            autocomplete="off">
                    </form>
                </div>

                <div class="filter-tabs">
                    <a href="?type=available<?= $search ? '&q=' . urlencode($search) : '' ?>"
                        class="filter-tab <?= $filter !== 'all' ? 'active' : '' ?>">Available</a>
                    <a href="?type=all<?= $search ? '&q=' . urlencode($search) : '' ?>"
                        class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All Vehicles</a>
                </div>
            <?php endif; ?>

            <span class="result-count">
                <?= count($vehicles) ?> vehicle
                <?= count($vehicles) !== 1 ? 's' : '' ?>
            </span>
        </div>

        <!-- ── Vehicle Grid ── -->
        <div class="grid-section">
            <?php if (empty($vehicles)): ?>
                <div class="empty">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <h3>No vehicles found</h3>
                    <p>Try a different search or check back later.</p>
                </div>
            <?php else: ?>
                <div class="vehicle-grid">
                    <?php foreach ($vehicles as $v):
                        $statusClass = match ($v['status']) {
                            'available' => 'status-available',
                            'rented' => 'status-rented',
                            'maintenance' => 'status-maintenance',
                            default => 'status-available',
                        };
                        $dotClass = match ($v['status']) {
                            'available' => 'dot-available',
                            'rented' => 'dot-rented',
                            'maintenance' => 'dot-maintenance',
                            default => 'dot-available',
                        };
                        $statusLabel = ucfirst($v['status']);
                        ?>
                        <div class="vehicle-card" onclick="openModal(this.dataset.vid)" data-vid="<?= (int)$v['id'] ?>" style="cursor:pointer">
                            <div class="card-image">
                                <?php
                                $cardImg = !empty($vehicleImgMap[$v['id']]) ? '../' . $vehicleImgMap[$v['id']][0] : ($v['image_url'] ?? '');
                            ?>
                            <?php if ($cardImg): ?>
                                    <img src="<?= e($cardImg) ?>" alt="<?= e($v['brand']) ?> <?= e($v['model']) ?>"
                                        loading="lazy">
                                <?php else: ?>
                                    <div class="card-image-placeholder">
                                        <svg fill="none" stroke="#fff" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                <span class="status-badge <?= $statusClass ?>">
                                    <span class="dot <?= $dotClass ?>"></span>
                                    <?= $statusLabel ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="car-make">
                                    <?= e($v['brand']) ?>
                                </div>
                                <div class="car-name">
                                    <?= e($v['model']) ?>
                                </div>
                                <div class="car-sub">
                                    <?= e($v['year']) ?>
                                    <?php if ($v['color']): ?> ·
                                        <?= e($v['color']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="card-specs">
                                    <?php if ($v['license_plate']): ?>
                                        <span class="spec-pill">🪪
                                            <?= e($v['license_plate']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($v['vin']): ?>
                                        <span class="spec-pill">VIN:
                                            <?= e(substr($v['vin'], -6)) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($v['monthly_rate']): ?>
                                        <span class="spec-pill">$
                                            <?= number_format($v['monthly_rate'], 0) ?>/mo
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-pricing">
                                    <div>
                                        <div class="price-main">
                                            <span class="price-currency">$</span>
                                            <span class="price-amount">
                                                <?= number_format($v['daily_rate'], 0) ?>
                                            </span>
                                        </div>
                                        <div class="price-period">per day</div>
                                    </div>
                                    <?php if ($v['monthly_rate']): ?>
                                        <div class="price-monthly">$
                                            <?= number_format($v['monthly_rate'], 0) ?>/mo
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Footer CTA ── -->
        <footer>
            <div class="footer-inner">
                <div class="cta-heading">Ready to rent?</div>
                <div class="cta-sub">Contact us to check availability and reserve your vehicle today.</div>
                <p class="cta-meta">
                    This catalog is updated in real-time ·
                    <?= count($vehicles) ?> vehicle
                    <?= count($vehicles) !== 1 ? 's' : '' ?> shown ·
                    <?= date('F j, Y') ?>
                </p>
            </div>
        </footer>

    </div>

<!-- Vehicle Detail Modal -->
<div id="veh-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.88);z-index:9999;align-items:center;justify-content:center;padding:1rem" onclick="if(event.target===this)closeModal()">
    <div style="background:#161622;border:1px solid rgba(255,255,255,0.1);border-radius:1.25rem;width:100%;max-width:680px;max-height:90vh;overflow-y:auto;position:relative">
        <button onclick="closeModal()" style="position:absolute;top:1rem;right:1rem;z-index:10;background:rgba(255,255,255,0.1);border:none;color:white;border-radius:50%;width:2rem;height:2rem;font-size:1rem;cursor:pointer">✕</button>
        <div id="modal-carousel" style="height:300px;background:#0a0a0f;position:relative;border-radius:1.25rem 1.25rem 0 0;overflow:hidden">
            <div id="modal-slides" style="position:relative;width:100%;height:100%"></div>
            <button id="mc-prev" onclick="mCarMove(-1)" style="display:none;position:absolute;left:0.5rem;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.6);border:none;color:white;border-radius:50%;width:2rem;height:2rem;cursor:pointer;font-size:1.2rem;line-height:1">‹</button>
            <button id="mc-next" onclick="mCarMove(1)"  style="display:none;position:absolute;right:0.5rem;top:50%;transform:translateY(-50%);background:rgba(0,0,0,0.6);border:none;color:white;border-radius:50%;width:2rem;height:2rem;cursor:pointer;font-size:1.2rem;line-height:1">›</button>
            <div id="mc-dots" style="position:absolute;bottom:0.5rem;left:50%;transform:translateX(-50%);display:flex;gap:0.4rem"></div>
        </div>
        <div id="mc-thumbs" style="display:flex;gap:0.5rem;padding:0.75rem 1rem 0;overflow-x:auto"></div>
        <div id="modal-info" style="padding:1.5rem"></div>
    </div>
</div>

<script>
<?php
$vData = [];
foreach ($vehicles as $veh) {
    $slides = !empty($vehicleImgMap[$veh["id"]]) ? array_map(function($p){ return "../" . $p; }, $vehicleImgMap[$veh["id"]]) : [];
    if (empty($slides) && !empty($veh["image_url"])) $slides[] = $veh["image_url"];
    $vData[$veh["id"]] = [
        "id"      => (int)$veh["id"],
        "brand"   => $veh["brand"],
        "model"   => $veh["model"],
        "year"    => $veh["year"],
        "color"   => $veh["color"] ?? "",
        "plate"   => $veh["license_plate"],
        "daily"   => (float)$veh["daily_rate"],
        "monthly" => $veh["monthly_rate"] ? (float)$veh["monthly_rate"] : null,
        "status"  => $veh["status"],
        "slides"  => $slides,
    ];
}
echo "var VDATA=" . json_encode($vData) . ";";
?>
var mCur=0,mSlides=[];
function openModal(vid){
    var v=VDATA[vid];if(!v)return;
    var modal=document.getElementById("veh-modal");
    modal.style.display="flex";document.body.style.overflow="hidden";
    mSlides=v.slides;mCur=0;
    var sc=document.getElementById("modal-slides");sc.innerHTML="";
    mSlides.forEach(function(src,i){
        var img=document.createElement("img");img.src=src;
        img.style.cssText="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:opacity 0.3s;opacity:"+(i===0?"1":"0");
        sc.appendChild(img);
    });
    if(!mSlides.length){sc.innerHTML="<div style=\"display:flex;align-items:center;justify-content:center;height:100%;color:rgba(255,255,255,0.1);font-size:4rem\">🚗</div>";}
    var showArr=mSlides.length>1;
    document.getElementById("mc-prev").style.display=showArr?"flex":"none";
    document.getElementById("mc-next").style.display=showArr?"flex":"none";
    var dots=document.getElementById("mc-dots");dots.innerHTML="";
    mSlides.forEach(function(_,i){var d=document.createElement("div");d.style.cssText="width:8px;height:8px;border-radius:50%;cursor:pointer;background:"+(i===0?"white":"rgba(255,255,255,0.4)");d.onclick=function(){mCarGo(i);};dots.appendChild(d);});
    var th=document.getElementById("mc-thumbs");th.innerHTML="";
    if(mSlides.length>1){mSlides.forEach(function(src,i){var img=document.createElement("img");img.src=src;img.style.cssText="height:52px;width:72px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid "+(i===0?"white":"transparent")+";opacity:"+(i===0?"1":"0.5");img.onclick=function(){mCarGo(i);};th.appendChild(img);});}
    var sColor={available:"#4ade80",rented:"#a5b4fc",maintenance:"#fca5a5"}[v.status]||"#aaa";
    document.getElementById("modal-info").innerHTML=
        "<div style=\"display:flex;align-items:start;justify-content:space-between;margin-bottom:1rem\">" +
        "<div><div style=\"font-size:0.7rem;text-transform:uppercase;color:#94a3b8;letter-spacing:0.1em;margin-bottom:0.2rem\">"+v.brand+"</div>" +
        "<div style=\"font-size:1.6rem;font-weight:700;color:white;letter-spacing:-0.02em\">"+v.model+"</div>" +
        "<div style=\"color:#64748b;font-size:0.85rem;margin-top:0.25rem\">"+v.year+(v.color?" · "+v.color:"")+"</div></div>" +
        "<span style=\"padding:0.3rem 0.75rem;border-radius:999px;font-size:0.72rem;font-weight:600;background:"+sColor+"22;color:"+sColor+";border:1px solid "+sColor+"44\">"+v.status.charAt(0).toUpperCase()+v.status.slice(1)+"</span></div>" +
        "<div style=\"display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem\">" +
        "<div style=\"background:rgba(255,255,255,0.04);border-radius:0.75rem;padding:1rem;text-align:center\"><div style=\"font-size:0.7rem;text-transform:uppercase;color:#64748b;margin-bottom:0.25rem\">Daily Rate</div><div style=\"font-size:1.5rem;font-weight:700;color:#6366f1\">$"+Number(v.daily).toLocaleString()+"</div></div>" +
        "<div style=\"background:rgba(255,255,255,0.04);border-radius:0.75rem;padding:1rem;text-align:center\"><div style=\"font-size:0.7rem;text-transform:uppercase;color:#64748b;margin-bottom:0.25rem\">Plate</div><div style=\"font-size:1.2rem;font-weight:600;color:white\">"+v.plate+"</div></div></div>" +
        (v.monthly?"<div style=\"background:rgba(99,102,241,0.08);border:1px solid rgba(99,102,241,0.2);border-radius:0.75rem;padding:0.75rem 1rem;margin-bottom:1rem;font-size:0.85rem;color:#a5b4fc\">Monthly: <strong>$"+Number(v.monthly).toLocaleString()+"</strong></div>":"") +
        "<div style=\"font-size:0.75rem;color:#475569;text-align:center\">Contact us to reserve this vehicle</div>";
}
function mCarMove(d){mCarGo((mCur+d+mSlides.length)%mSlides.length);}
function mCarGo(n){
    var imgs=document.querySelectorAll("#modal-slides img"),dots=document.querySelectorAll("#mc-dots div"),ths=document.querySelectorAll("#mc-thumbs img");
    if(imgs[mCur])imgs[mCur].style.opacity="0";
    if(dots[mCur])dots[mCur].style.background="rgba(255,255,255,0.4)";
    if(ths[mCur]){ths[mCur].style.borderColor="transparent";ths[mCur].style.opacity="0.5";}
    mCur=n;
    if(imgs[mCur])imgs[mCur].style.opacity="1";
    if(dots[mCur])dots[mCur].style.background="white";
    if(ths[mCur]){ths[mCur].style.borderColor="white";ths[mCur].style.opacity="1";}
}
function closeModal(){document.getElementById("veh-modal").style.display="none";document.body.style.overflow="";}
document.addEventListener("keydown",function(e){if(e.key==="Escape")closeModal();});
<?php if ($singleVehicleMode && $selectedVehicle): ?>
window.addEventListener("load", function () {
    openModal(<?= (int) $selectedVehicle['id'] ?>);
});
<?php endif; ?>
</script>

</body>
</html>
