<?php
require_once __DIR__ . '/../config/db.php';

$id = (int) ($_GET['id'] ?? 0);
$pdo = db();

$vStmt = $pdo->prepare('SELECT * FROM vehicles WHERE id = ?');
$vStmt->execute([$id]);
$v = $vStmt->fetch();
if (!$v) {
    flash('error', 'Vehicle not found.');
    redirect('index.php');
}

$docs = $pdo->prepare('SELECT * FROM documents WHERE vehicle_id = ? ORDER BY created_at DESC');
$docs->execute([$id]);
$documents = $docs->fetchAll();

// Load uploaded photos
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS vehicle_images (id INT AUTO_INCREMENT PRIMARY KEY, vehicle_id INT NOT NULL, file_path VARCHAR(255) NOT NULL, sort_order INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}
$imgStmt = $pdo->prepare('SELECT * FROM vehicle_images WHERE vehicle_id=? ORDER BY sort_order, id');
$imgStmt->execute([$id]);
$vehiclePhotos = $imgStmt->fetchAll();
// Build slides: uploaded first, then URL fallback
$carouselSlides = [];
foreach ($vehiclePhotos as $p) { $carouselSlides[] = '../' . $p['file_path']; }
if (empty($carouselSlides) && !empty($v['image_url'])) { $carouselSlides[] = $v['image_url']; }

$resStmt = $pdo->prepare('SELECT r.*, c.name AS client_name FROM reservations r JOIN clients c ON r.client_id = c.id WHERE r.vehicle_id = ? ORDER BY r.created_at DESC');
$resStmt->execute([$id]);
$reservations = $resStmt->fetchAll();

$totalRevenue = array_sum(array_column($reservations, 'total_price'));
$activeReservation = array_filter($reservations, fn($r) => $r['status'] === 'active');
$activeReservation = reset($activeReservation) ?: null;

$success = getFlash('success');
$error = getFlash('error');

$pageTitle = e($v['brand']) . ' ' . e($v['model']);
require_once __DIR__ . '/../includes/header.php';

$badge = ['available' => 'bg-green-500/10 text-green-400 border-green-500/30', 'rented' => 'bg-sky-500/10 text-sky-400 border-sky-500/30', 'maintenance' => 'bg-red-500/10 text-red-400 border-red-500/30'];
$badgeCls = $badge[$v['status']] ?? 'bg-gray-500/10 text-gray-400';
$maintenanceSinceLabel = '';
if (($v['status'] ?? '') === 'maintenance') {
    $maintenanceStartRaw = (string) ($v['maintenance_started_at'] ?? '');
    if ($maintenanceStartRaw === '') {
        $maintenanceStartRaw = (string) ($v['updated_at'] ?? $v['created_at'] ?? '');
    }
    $startTs = $maintenanceStartRaw !== '' ? strtotime($maintenanceStartRaw) : false;
    if ($startTs !== false) {
        $maintenanceSinceLabel = date('d M Y, h:i A', $startTs);
    }
}
?>

<div class="space-y-6">
    <?php if ($success): ?>
        <div
            class="flex items-center gap-3 bg-green-500/10 border border-green-500/30 text-green-400 rounded-lg px-5 py-3 text-sm">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="flex items-center gap-3 text-sm text-mb-subtle">
        <a href="index.php" class="hover:text-white transition-colors">Vehicles</a>
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-white">
            <?= e($v['brand']) ?>
            <?= e($v['model']) ?>
        </span>
    </div>

    <!-- Hero -->
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="grid md:grid-cols-5">
            <!-- Photo Carousel -->
            <div class="md:col-span-2 h-64 md:h-auto bg-mb-black relative overflow-hidden" id="carousel-wrap">
                <?php if (empty($carouselSlides)): ?>
                    <div class="w-full h-full min-h-64 flex items-center justify-center">
                        <svg class="w-20 h-20 text-mb-subtle/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                <?php else: ?>
                    <!-- Slides -->
                    <?php foreach ($carouselSlides as $si => $slide): ?>
                        <img src="<?= e($slide) ?>"
                            class="carousel-slide absolute inset-0 w-full h-full object-cover transition-opacity duration-300 <?= $si === 0 ? 'opacity-100' : 'opacity-0 pointer-events-none' ?>"
                            data-index="<?= $si ?>">
                    <?php endforeach; ?>
                    <?php if (count($carouselSlides) > 1): ?>
                        <!-- Arrows -->
                        <button onclick="carouselMove(-1)" class="absolute left-2 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-black/50 hover:bg-black/80 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <button onclick="carouselMove(1)" class="absolute right-2 top-1/2 -translate-y-1/2 z-10 w-8 h-8 bg-black/50 hover:bg-black/80 rounded-full flex items-center justify-center text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <!-- Dots -->
                        <div class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5 z-10">
                            <?php foreach ($carouselSlides as $di => $s): ?>
                                <button onclick="carouselGo(<?= $di ?>)" class="carousel-dot w-2 h-2 rounded-full transition-colors <?= $di===0 ? 'bg-white' : 'bg-white/40' ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <!-- Thumbnail strip -->
                    <?php if (count($carouselSlides) > 1): ?>
                    <div class="absolute bottom-8 left-0 right-0 flex justify-center gap-2 px-3 z-10">
                        <?php foreach ($carouselSlides as $ti => $slide): ?>
                            <img src="<?= e($slide) ?>" onclick="carouselGo(<?= $ti ?>)"
                                class="carousel-thumb h-10 w-14 object-cover rounded cursor-pointer border-2 transition-all <?= $ti===0 ? 'border-white' : 'border-transparent opacity-60' ?>">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <!-- Info -->
            <div class="md:col-span-3 p-7 space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-white text-2xl font-light">
                            <?= e($v['brand']) ?>
                            <?= e($v['model']) ?>
                        </h2>
                        <p class="text-mb-silver text-sm mt-1">
                            <?= e($v['year']) ?> &bull;
                            <?= e($v['license_plate']) ?>
                            <?= $v['color'] ? ' &bull; ' . e($v['color']) : '' ?>
                        </p>
                    </div>
                    <span class="px-3 py-1.5 rounded-full text-sm border <?= $badgeCls ?>">
                        <?= ucfirst($v['status']) ?>
                    </span>
                </div>
                <?php if (($v['status'] ?? '') === 'maintenance'): ?>
                    <div class="rounded-xl bg-red-500/10 border border-red-500/30 p-4 space-y-2">
                        <?php if (!empty($v['maintenance_workshop_name'])): ?>
                            <p class="text-sm text-red-200">Workshop:
                                <span class="text-white"><?= e((string) $v['maintenance_workshop_name']) ?></span>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($v['maintenance_expected_return'])): ?>
                            <p class="text-sm text-yellow-300">Expected Return:
                                <span class="text-white"><?= e(date('d M Y', strtotime((string) $v['maintenance_expected_return']))) ?></span>
                            </p>
                        <?php endif; ?>
                        <?php if ($maintenanceSinceLabel !== ''): ?>
                            <p class="text-xs text-red-200/80">In maintenance since
                                <?= e($maintenanceSinceLabel) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div class="bg-mb-black/40 rounded-xl p-4">
                        <p class="text-mb-subtle text-xs uppercase">Daily Rate</p>
                        <p class="text-mb-accent text-2xl font-light mt-1">$
                            <?= number_format($v['daily_rate'], 0) ?>
                        </p>
                    </div>
                    <div class="bg-mb-black/40 rounded-xl p-4">
                        <p class="text-mb-subtle text-xs uppercase">Monthly</p>
                        <p class="text-white text-2xl font-light mt-1">
                            <?= $v['monthly_rate'] ? '$' . number_format($v['monthly_rate'], 0) : '—' ?>
                        </p>
                    </div>
                    <div class="bg-mb-black/40 rounded-xl p-4">
                        <p class="text-mb-subtle text-xs uppercase">Total Revenue</p>
                        <p class="text-green-400 text-2xl font-light mt-1">$
                            <?= number_format($totalRevenue, 0) ?>
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3 flex-wrap">
                    <a href="edit.php?id=<?= $v['id'] ?>"
                        class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium">Edit
                        Vehicle</a>
                    <?php if ($v['status'] !== 'rented'): ?>
                        <a href="delete.php?id=<?= $v['id'] ?>"
                            onclick="return confirm('Remove this vehicle from the fleet?')"
                            class="border border-red-500/30 text-red-400 px-5 py-2 rounded-full hover:bg-red-500/10 transition-colors text-sm">Delete</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Calendar -->
    <?php
    // Build a map of booked intervals: [['start' => 'Y-m-d', 'end' => 'Y-m-d', 'client' => '...', 'status' => '...'], ...]
    $bookedRanges = [];
    foreach ($reservations as $r) {
        if (in_array($r['status'], ['confirmed', 'active', 'pending'])) {
            $bookedRanges[] = [
                'start' => date('Y-m-d', strtotime($r['start_date'])),
                'end' => date('Y-m-d', strtotime($r['end_date'])),
                'client' => $r['client_name'],
                'status' => $r['status'],
            ];
        }
    }
    $bookedJson = json_encode($bookedRanges);
    ?>
    <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
            <h3 class="text-white font-light">Booking Calendar
                <span class="text-mb-subtle text-sm ml-2">Booked dates for this vehicle</span>
            </h3>
            <div class="flex items-center gap-4 text-xs text-mb-subtle">
                <span class="flex items-center gap-1.5"><span
                        class="w-3 h-3 rounded-sm bg-red-500/60 inline-block"></span> Booked</span>
                <span class="flex items-center gap-1.5"><span
                        class="w-3 h-3 rounded-sm bg-mb-accent/70 inline-block"></span> Today</span>
                <span class="flex items-center gap-1.5"><span
                        class="w-3 h-3 rounded-sm bg-mb-black/40 inline-block border border-mb-subtle/20"></span>
                    Available</span>
            </div>
        </div>
        <div class="p-6">
            <!-- Month navigation -->
            <div class="flex items-center justify-between mb-6">
                <button id="cal-prev"
                    class="w-8 h-8 flex items-center justify-center rounded-full border border-mb-subtle/20 hover:border-mb-accent/50 hover:text-mb-accent transition-all text-mb-silver">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <span id="cal-title" class="text-white font-light text-sm tracking-wide"></span>
                <button id="cal-next"
                    class="w-8 h-8 flex items-center justify-center rounded-full border border-mb-subtle/20 hover:border-mb-accent/50 hover:text-mb-accent transition-all text-mb-silver">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>
            <!-- Calendar grid -->
            <div id="cal-grid" class="select-none"></div>
            <!-- Tooltip -->
            <div id="cal-tip"
                class="hidden mt-4 text-xs bg-mb-black/60 border border-mb-subtle/20 rounded-lg px-4 py-2.5 text-mb-silver">
            </div>
        </div>
    </div>

    
<script>
(function(){
    var slides = Array.from(document.querySelectorAll('.carousel-slide'));
    var dots   = Array.from(document.querySelectorAll('.carousel-dot'));
    var thumbs = Array.from(document.querySelectorAll('.carousel-thumb'));
    var cur = 0;
    function go(n) {
        if (!slides.length) return;
        slides[cur].classList.remove('opacity-100'); slides[cur].classList.add('opacity-0','pointer-events-none');
        dots[cur] && dots[cur].classList.replace('bg-white','bg-white/40');
        thumbs[cur] && thumbs[cur].classList.remove('border-white','opacity-100') && thumbs[cur].classList.add('border-transparent','opacity-60');
        cur = (n + slides.length) % slides.length;
        slides[cur].classList.remove('opacity-0','pointer-events-none'); slides[cur].classList.add('opacity-100');
        dots[cur] && dots[cur].classList.replace('bg-white/40','bg-white');
        thumbs[cur] && (thumbs[cur].classList.add('border-white'), thumbs[cur].classList.remove('border-transparent','opacity-60'));
    }
    window.carouselMove = function(d){ go(cur + d); };
    window.carouselGo   = function(n){ go(n); };
    document.addEventListener('keydown', function(e){ if(e.key==='ArrowLeft') go(cur-1); if(e.key==='ArrowRight') go(cur+1); });
})();
</script>
<script>
        (function () {
            const BOOKED = <?= $bookedJson ?>;
            const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            let viewYear, viewMonth;
            const today = new Date(); today.setHours(0, 0, 0, 0);
            viewYear = today.getFullYear();
            viewMonth = today.getMonth();

            function toDate(str) { const p = str.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }

            function getBookingForDate(d) {
                const ds = d.toISOString().slice(0, 10);
                for (const b of BOOKED) {
                    if (ds >= b.start && ds <= b.end) return b;
                }
                return null;
            }

            function isStart(d) {
                const ds = d.toISOString().slice(0, 10);
                return BOOKED.some(b => b.start === ds);
            }

            function isEnd(d) {
                const ds = d.toISOString().slice(0, 10);
                return BOOKED.some(b => b.end === ds);
            }

            function render() {
                const grid = document.getElementById('cal-grid');
                const title = document.getElementById('cal-title');
                title.textContent = MONTHS[viewMonth] + ' ' + viewYear;

                const firstDay = new Date(viewYear, viewMonth, 1).getDay();
                const daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

                let html = '<div class="grid grid-cols-7 gap-px">';
                // Day headers
                for (const d of DAYS) {
                    html += `<div class="text-center text-[10px] uppercase text-mb-subtle/60 py-1.5 font-medium tracking-wider">${d}</div>`;
                }
                // Empty cells before first
                for (let i = 0; i < firstDay; i++) {
                    html += '<div class="h-9"></div>';
                }
                // Day cells
                for (let day = 1; day <= daysInMonth; day++) {
                    const d = new Date(viewYear, viewMonth, day);
                    const booking = getBookingForDate(d);
                    const isToday = d.getTime() === today.getTime();

                    let cls = 'h-9 flex items-center justify-center text-xs rounded-md transition-all ';
                    let style = '';
                    let dataAttr = '';

                    if (booking) {
                        const s = isStart(d), e = isEnd(d);
                        cls += 'text-white font-medium cursor-pointer ';
                        if (s && e) cls += 'bg-red-500/70 rounded-md mx-1 ';
                        else if (s) cls += 'bg-red-500/70 rounded-l-md rounded-r-none ml-1 ';
                        else if (e) cls += 'bg-red-500/70 rounded-r-md rounded-l-none mr-1 ';
                        else cls += 'bg-red-500/40 rounded-none ';
                        dataAttr = `data-client="${booking.client}" data-status="${booking.status}" data-start="${booking.start}" data-end="${booking.end}"`;
                    } else if (isToday) {
                        cls += 'bg-mb-accent/70 text-white font-semibold ring-1 ring-mb-accent ';
                    } else {
                        cls += 'text-mb-silver hover:bg-mb-black/40 ';
                    }

                    html += `<div class="${cls}" ${dataAttr} onclick="calClick(this)">${day}</div>`;
                }
                html += '</div>';
                grid.innerHTML = html;
            }

            window.calClick = function (el) {
                const tip = document.getElementById('cal-tip');
                if (!el.dataset.client) { tip.classList.add('hidden'); return; }
                const s = new Date(el.dataset.start), e = new Date(el.dataset.end);
                const fmt = d => d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
                const days = Math.round((e - s) / 86400000) + 1;
                tip.classList.remove('hidden');
                tip.innerHTML = `<span class="text-white font-medium">${el.dataset.client}</span> &mdash; <span class="capitalize text-yellow-400">${el.dataset.status}</span><br>
                <span class="text-mb-subtle">${fmt(s)} → ${fmt(e)}</span> <span class="text-mb-accent ml-2">${days} day${days > 1 ? 's' : ''}</span>`;
            };

            document.getElementById('cal-prev').onclick = () => {
                viewMonth--;
                if (viewMonth < 0) { viewMonth = 11; viewYear--; }
                render();
            };
            document.getElementById('cal-next').onclick = () => {
                viewMonth++;
                if (viewMonth > 11) { viewMonth = 0; viewYear++; }
                render();
            };

            render();
        })();
    </script>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Documents -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10 flex items-center justify-between">
                <h3 class="text-white font-light">Documents <span class="text-mb-subtle text-sm ml-2">
                        <?= count($documents) ?> files
                    </span></h3>
            </div>
            <?php if (empty($documents)): ?>
                <p class="py-10 text-center text-mb-subtle text-sm italic">No documents uploaded yet.</p>
            <?php else: ?>
                <div class="p-4 space-y-2">
                    <?php foreach ($documents as $doc): ?>
                        <div class="flex items-center gap-3 p-3 bg-mb-black/30 rounded-lg">
                            <svg class="w-8 h-8 text-mb-accent flex-shrink-0" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-white text-sm truncate">
                                    <?= e($doc['title']) ?>
                                </p>
                                <p class="text-mb-subtle text-xs uppercase">
                                    <?= e($doc['type']) ?>
                                </p>
                            </div>
                            <a href="../<?= e($doc['file_path']) ?>" target="_blank"
                                class="text-mb-accent hover:text-white transition-colors text-xs">View</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rental History -->
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mb-subtle/10">
                <h3 class="text-white font-light">Rental History <span class="text-mb-subtle text-sm ml-2">
                        <?= count($reservations) ?> trips
                    </span></h3>
            </div>
            <?php if (empty($reservations)): ?>
                <p class="py-10 text-center text-mb-subtle text-sm italic">No rental history yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-mb-subtle/10">
                            <tr class="text-mb-subtle text-xs uppercase">
                                <th class="px-6 py-3 text-left">Client</th>
                                <th class="px-6 py-3 text-left">Period</th>
                                <th class="px-6 py-3 text-right">Total</th>
                                <th class="px-6 py-3 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-mb-subtle/10">
                            <?php foreach ($reservations as $r): ?>
                                <tr class="hover:bg-mb-black/30 transition-colors">
                                    <td class="px-6 py-3 text-white">
                                        <?= e($r['client_name']) ?>
                                    </td>
                                    <td class="px-6 py-3 text-mb-silver text-xs">
                                        <?= e($r['start_date']) ?> →
                                        <?= e($r['end_date']) ?>
                                    </td>
                                    <td class="px-6 py-3 text-right text-mb-accent">$
                                        <?= number_format($r['total_price'], 0) ?>
                                    </td>
                                    <td class="px-6 py-3"><span class="text-xs capitalize">
                                            <?= e($r['status']) ?>
                                        </span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
