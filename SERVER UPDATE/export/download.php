<?php
/**
 * export/download.php — Full database export to .xlsx
 * Super-admin only. Blocked by kill-switch in config/export_enabled.php.
 *
 * Uses a self-contained pure-PHP ZIP builder — no php_zip extension required.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/export_enabled.php';
require_once __DIR__ . '/../includes/reservation_payment_helpers.php';
require_once __DIR__ . '/../includes/vehicle_helpers.php';

// ── Kill-switch ────────────────────────────────────────────────────────────
if (!defined('EXPORT_ENABLED') || !EXPORT_ENABLED) {
    $depth = max(0, substr_count($_SERVER['PHP_SELF'], '/') - 1);
    header('Location: ' . str_repeat('../', $depth) . 'index.php');
    exit;
}

auth_require_admin();
$pdo = db();
reservation_payment_ensure_schema($pdo);
vehicle_ensure_schema($pdo);

// ══════════════════════════════════════════════════════════════════════════════
// Pure-PHP ZIP builder (no ZipArchive / php_zip extension needed)
// Uses "stored" (uncompressed) method — keeps code simple & dependency-free.
// ══════════════════════════════════════════════════════════════════════════════
class PureZip
{
    private array $files = [];

    public function addFromString(string $name, string $data): void
    {
        $this->files[] = ['name' => $name, 'data' => $data];
    }

    public function getBytes(): string
    {
        $localPart = '';
        $centralDir = '';
        $offset = 0;

        // DOS time: 1980-01-01 00:00:00
        $dosTime = 0;
        $dosDate = (1 << 5) | 1;   // Jan 1, 1980

        foreach ($this->files as $file) {
            $name = $file['name'];
            $data = $file['data'];
            $crc = crc32($data);
            $size = strlen($data);
            $nameLen = strlen($name);

            // Local file header (30 bytes) + name + data
            $local = pack(
                'VvvvvvVVVvv',
                0x04034b50,  // local file header signature
                20,          // version needed to extract (2.0)
                0x0000,      // general purpose bit flag
                0,           // compression method: STORED
                $dosTime,    // last mod file time
                $dosDate,    // last mod file date
                $crc,        // crc-32
                $size,       // compressed size
                $size,       // uncompressed size
                $nameLen,    // file name length
                0            // extra field length
            ) . $name . $data;

            // Central directory file header (46 bytes) + name
            $central = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,  // central file header signature
                20,          // version made by (DOS, 2.0)
                20,          // version needed to extract
                0x0000,      // general purpose bit flag
                0,           // compression method: STORED
                $dosTime,    // last mod file time
                $dosDate,    // last mod file date
                $crc,        // crc-32
                $size,       // compressed size
                $size,       // uncompressed size
                $nameLen,    // file name length
                0,           // extra field length
                0,           // file comment length
                0,           // disk number start
                0,           // internal file attributes
                0,           // external file attributes
                $offset      // relative offset of local header
            ) . $name;

            $localPart .= $local;
            $centralDir .= $central;
            $offset += strlen($local);
        }

        $cdSize = strlen($centralDir);
        $cdOffset = strlen($localPart);
        $numFiles = count($this->files);

        // End of central directory record (22 bytes)
        $eocd = pack(
            'VvvvvVVv',
            0x06054b50,  // end of central dir signature
            0,           // number of this disk
            0,           // disk where central directory starts
            $numFiles,   // number of entries on this disk
            $numFiles,   // total number of entries
            $cdSize,     // size of central directory
            $cdOffset,   // offset of central directory
            0            // ZIP file comment length
        );

        return $localPart . $centralDir . $eocd;
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// OOXML helpers
// ══════════════════════════════════════════════════════════════════════════════

/** Escape a value for XML text content */
function xEsc(mixed $v): string
{
    if ($v === null || $v === '')
        return '';
    return htmlspecialchars((string) $v, ENT_XML1, 'UTF-8');
}

/** Convert 0-based column index → Excel column letters (A, B, … Z, AA, …) */
function colLetter(int $n): string
{
    $letter = '';
    do {
        $letter = chr(65 + ($n % 26)) . $letter;
        $n = intdiv($n, 26) - 1;
    } while ($n >= 0);
    return $letter;
}

/** Build the XML string for one worksheet from a 2-D array */
function buildSheetXml(array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetData>';

    $rowIdx = 1;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $rowIdx . '">';
        $colIdx = 0;
        foreach ($row as $cell) {
            $ref = colLetter($colIdx++) . $rowIdx;
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . xEsc($cell) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowIdx++;
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}

/** Safely fetch rows; returns [] if the table doesn't exist */
function safeFetch(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// Collect data for each sheet
// ══════════════════════════════════════════════════════════════════════════════
$sheetsData = [];

// 1. Vehicles
$rows = safeFetch($pdo, 'SELECT id, brand, model, year, license_plate, color, vin, status,
    maintenance_started_at, maintenance_expected_return, maintenance_workshop_name,
    daily_rate, monthly_rate, rate_1day, rate_7day, rate_15day, rate_30day, created_at
    FROM vehicles ORDER BY id');
$sheetsData[] = [
    'name' => 'Vehicles',
    'rows' => array_merge(
        [
            [
                'ID',
                'Brand',
                'Model',
                'Year',
                'Plate',
                'Color',
                'VIN',
                'Status',
                'Maintenance Started At',
                'Maintenance Expected Return',
                'Maintenance Workshop Name',
                'Daily Rate',
                'Monthly Rate',
                '1-Day Rate',
                '7-Day Rate',
                '15-Day Rate',
                '30-Day Rate',
                'Created At'
            ]
        ],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// 2. Clients
$rows = safeFetch($pdo, 'SELECT id, name, email, phone, address, rating, is_blacklisted,
    blacklist_reason, notes, voucher_balance, created_at FROM clients ORDER BY id');
$sheetsData[] = [
    'name' => 'Clients',
    'rows' => array_merge(
        [
            [
                'ID',
                'Name',
                'Email',
                'Phone',
                'Address',
                'Rating',
                'Blacklisted',
                'Blacklist Reason',
                'Notes',
                'Voucher Balance',
                'Created At'
            ]
        ],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// 3. Reservations (joined with client + vehicle)
$rows = safeFetch($pdo, '
    SELECT r.id, c.name AS client_name, v.license_plate,
           r.rental_type, r.status, r.start_date, r.end_date, r.actual_end_date,
           r.total_price, r.delivery_charge, r.delivery_manual_amount, r.delivery_payment_method, r.delivery_paid_amount,
           r.overdue_amount, r.km_limit, r.extra_km_price, r.km_driven, r.km_overage_charge,
           r.damage_charge, r.additional_charge, r.discount_type, r.discount_value,
           r.voucher_applied, r.return_voucher_applied, r.return_payment_method,
           r.return_paid_amount, r.early_return_credit, r.voucher_credit_issued, r.created_at
    FROM reservations r
    LEFT JOIN clients  c ON r.client_id  = c.id
    LEFT JOIN vehicles v ON r.vehicle_id = v.id
    ORDER BY r.id');
$sheetsData[] = [
    'name' => 'Reservations',
    'rows' => array_merge(
        [
            [
                'ID',
                'Client',
                'Vehicle Plate',
                'Rental Type',
                'Status',
                'Start Date',
                'End Date',
                'Actual End Date',
                'Total Price',
                'Delivery Charge',
                'Delivery Manual Amount',
                'Delivery Pay Method',
                'Delivery Paid',
                'Overdue',
                'KM Limit',
                'Extra KM Price',
                'KM Driven',
                'KM Overage Charge',
                'Damage Charge',
                'Additional Charge',
                'Discount Type',
                'Discount Value',
                'Voucher Applied',
                'Return Voucher',
                'Return Pay Method',
                'Return Paid',
                'Early Return Credit',
                'Voucher Credit Issued',
                'Created At'
            ]
        ],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// 4. Pipeline Leads
$rows = safeFetch($pdo, '
    SELECT l.id, l.name, l.phone, l.email, l.status, l.source, l.inquiry_type,
           l.vehicle_interest, l.notes, l.lost_reason, l.assigned_to,
           u.name AS assigned_staff, l.created_at
    FROM leads l
    LEFT JOIN users u ON l.assigned_staff_id = u.id
    ORDER BY l.id');
$sheetsData[] = [
    'name' => 'Pipeline Leads',
    'rows' => array_merge(
        [
            [
                'ID',
                'Name',
                'Phone',
                'Email',
                'Stage',
                'Source',
                'Inquiry Type',
                'Vehicle Interest',
                'Notes',
                'Lost Reason',
                'Assigned To (text)',
                'Assigned Staff (user)',
                'Created At'
            ]
        ],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// 5. Staff
$rows = safeFetch($pdo, 'SELECT id, name, role, phone, email, salary, joined_date, notes, created_at
    FROM staff ORDER BY id');
$sheetsData[] = [
    'name' => 'Staff',
    'rows' => array_merge(
        [['ID', 'Name', 'Role', 'Phone', 'Email', 'Salary', 'Joined Date', 'Notes', 'Created At']],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// 6. Expenses
$rows = safeFetch($pdo, 'SELECT id, title, amount, category, expense_date, notes, created_at
    FROM expenses ORDER BY id');
$sheetsData[] = [
    'name' => 'Expenses',
    'rows' => array_merge(
        [['ID', 'Title', 'Amount', 'Category', 'Expense Date', 'Notes', 'Created At']],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// 7. Investments
$rows = safeFetch($pdo, 'SELECT id, title, amount, type, description, investment_date, created_at
    FROM investments ORDER BY id');
$sheetsData[] = [
    'name' => 'Investments',
    'rows' => array_merge(
        [['ID', 'Title', 'Amount', 'Type', 'Description', 'Investment Date', 'Created At']],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// 8. Challans
$rows = safeFetch($pdo, '
    SELECT ch.id, v.license_plate, c.name AS client_name,
           ch.challan_no, ch.amount, ch.issue_date, ch.status, ch.notes, ch.created_at
    FROM challans ch
    LEFT JOIN vehicles v ON ch.vehicle_id = v.id
    LEFT JOIN clients  c ON ch.client_id  = c.id
    ORDER BY ch.id');
$sheetsData[] = [
    'name' => 'Challans',
    'rows' => array_merge(
        [['ID', 'Vehicle Plate', 'Client', 'Challan No', 'Amount', 'Issue Date', 'Status', 'Notes', 'Created At']],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// 9. Voucher Transactions
$rows = safeFetch($pdo, '
    SELECT vt.id, c.name AS client_name, vt.reservation_id, vt.type, vt.amount, vt.note, vt.created_at
    FROM client_voucher_transactions vt
    LEFT JOIN clients c ON vt.client_id = c.id
    ORDER BY vt.id');
$sheetsData[] = [
    'name' => 'Voucher Transactions',
    'rows' => array_merge(
        [['ID', 'Client', 'Reservation ID', 'Type', 'Amount', 'Note', 'Created At']],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// 10. Attendance
$rows = safeFetch($pdo, '
    SELECT sa.id, u.name AS staff_name, sa.date, sa.punch_in, sa.punch_out, sa.notes
    FROM staff_attendance sa
    LEFT JOIN users u ON sa.user_id = u.id
    ORDER BY sa.date DESC, sa.id DESC');
$sheetsData[] = [
    'name' => 'Attendance',
    'rows' => array_merge(
        [['ID', 'Staff Name', 'Date', 'Punch In', 'Punch Out', 'Notes']],
        array_map(fn($r) => array_values($r), $rows)
    )
];

// ══════════════════════════════════════════════════════════════════════════════
// Assemble .xlsx using PureZip
// ══════════════════════════════════════════════════════════════════════════════
$zip = new PureZip();

// [Content_Types].xml
$ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$ct .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
$ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
$ct .= '<Default Extension="xml"  ContentType="application/xml"/>';
$ct .= '<Override PartName="/xl/workbook.xml"
    ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
foreach ($sheetsData as $i => $s) {
    $n = $i + 1;
    $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml"
        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
}
$ct .= '</Types>';
$zip->addFromString('[Content_Types].xml', $ct);

// _rels/.rels
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
$rels .= '<Relationship Id="rId1"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
    Target="xl/workbook.xml"/>';
$rels .= '</Relationships>';
$zip->addFromString('_rels/.rels', $rels);

// xl/_rels/workbook.xml.rels
$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
foreach ($sheetsData as $i => $s) {
    $n = $i + 1;
    $wbRels .= '<Relationship Id="rId' . $n . '"
        Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
        Target="worksheets/sheet' . $n . '.xml"/>';
}
$wbRels .= '</Relationships>';
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

// xl/workbook.xml
$wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
    xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
$wb .= '<sheets>';
foreach ($sheetsData as $i => $s) {
    $n = $i + 1;
    $wb .= '<sheet name="' . xEsc($s['name']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
}
$wb .= '</sheets></workbook>';
$zip->addFromString('xl/workbook.xml', $wb);

// xl/worksheets/sheetN.xml
foreach ($sheetsData as $i => $s) {
    $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', buildSheetXml($s['rows']));
}

// ── Stream to browser ──────────────────────────────────────────────────────
$xlsxBytes = $zip->getBytes();
$filename = 'orent_export_' . date('Y-m-d_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xlsxBytes));
header('Cache-Control: max-age=0');

echo $xlsxBytes;
exit;
