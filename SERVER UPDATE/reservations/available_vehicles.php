<?php
require_once __DIR__ . '/../config/db.php';
$pdo = db();

header('Content-Type: application/json');

$start = trim((string) ($_GET['start'] ?? ''));
$end = trim((string) ($_GET['end'] ?? ''));
$excludeReservationId = (int) ($_GET['exclude_reservation_id'] ?? 0);

if ($start === '' || $end === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Start and end date are required.']);
    exit;
}

$startDt = DateTime::createFromFormat('Y-m-d H:i:s', $start);
$endDt = DateTime::createFromFormat('Y-m-d H:i:s', $end);
if (!$startDt || !$endDt || $endDt <= $startDt) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Invalid date range.']);
    exit;
}

$startDate = $startDt->format('Y-m-d H:i:s');
$endDate = $endDt->format('Y-m-d H:i:s');

$sql = "SELECT v.id, v.brand, v.model, v.license_plate, v.daily_rate, v.monthly_rate,
               v.rate_1day, v.rate_7day, v.rate_15day, v.rate_30day
        FROM vehicles v
        WHERE v.status <> 'maintenance'
          AND NOT EXISTS (
              SELECT 1
              FROM reservations r
              WHERE r.vehicle_id = v.id
                AND r.id <> ?
                AND r.status IN ('pending','confirmed','active')
                AND r.start_date < ?
                AND r.end_date > ?
          )
        ORDER BY v.brand, v.model, v.license_plate";

$stmt = $pdo->prepare($sql);
$stmt->execute([$excludeReservationId, $endDate, $startDate]);

echo json_encode([
    'ok' => true,
    'vehicles' => $stmt->fetchAll(),
]);
