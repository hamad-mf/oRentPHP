<?php
/**
 * leads/export.php — Export filtered pipeline leads to .xlsx
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';

auth_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pipeline.php');
    exit;
}

$pdo = db();
$leadSourcesMap = lead_sources_get_map($pdo);

// ── Inputs ─────────────────────────────────────────────────────────────────
$filterSearch  = trim($_POST['q'] ?? '');
$filterStaff   = (int) ($_POST['staff_filter'] ?? 0);
$filterDateFrom = trim($_POST['date_from'] ?? '');
$filterDateTo   = trim($_POST['date_to'] ?? '');
$filterSource   = trim($_POST['source_filter'] ?? '');
$filterStatus   = trim($_POST['status_filter'] ?? '');
$filterInquiry  = trim($_POST['inquiry_filter'] ?? '');
$fields         = (array) ($_POST['fields'] ?? []);

$currentUser = current_user();
$isAdmin = (($currentUser['role'] ?? '') === 'admin');
$currentUserId = (int) ($currentUser['id'] ?? 0);
// Staff can only export their own leads
if (!$isAdmin && $currentUserId > 0) {
    $filterStaff = $currentUserId;
}

// ── Optional columns ────────────────────────────────────────────────────────
$include = [
    'name'             => true,
    'phone'            => true,
    'alternative_number' => in_array('alternative_number', $fields),
    'email'            => in_array('email', $fields),
    'status'           => in_array('status', $fields),
    'source'           => in_array('source', $fields),
    'inquiry_type'     => in_array('inquiry_type', $fields),
    'vehicle_interest' => in_array('vehicle_interest', $fields),
    'assigned_to'      => in_array('assigned_to', $fields),
    'notes'            => in_array('notes', $fields),
    'created_at'       => in_array('created_at', $fields),
];

// ── Build WHERE (mirrors pipeline.php) ─────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($filterStaff > 0) {
    $where[] = 'l.assigned_staff_id = ?';
    $params[] = $filterStaff;
}
if ($filterDateFrom !== '') {
    $where[] = 'DATE(l.created_at) >= ?';
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $where[] = 'DATE(l.created_at) <= ?';
    $params[] = $filterDateTo;
}
if ($filterSource !== '') {
    $where[] = 'l.source = ?';
    $params[] = $filterSource;
}
if ($filterStatus !== '') {
    $where[] = 'l.status = ?';
    $params[] = $filterStatus;
}
if ($filterInquiry !== '') {
    $where[] = 'l.inquiry_type = ?';
    $params[] = $filterInquiry;
}
if ($filterSearch !== '') {
    $where[] = '(l.name LIKE ? OR l.phone LIKE ? OR l.alternative_number LIKE ? OR l.email LIKE ? OR l.vehicle_interest LIKE ?)';
    $term = '%' . $filterSearch . '%';
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$whereSql = implode(' AND ', $where);

// ── Query ───────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT l.name, l.phone, l.alternative_number, l.email,
            l.status, l.source, l.inquiry_type, l.vehicle_interest,
            l.notes, l.created_at,
            u.name AS assigned_user_name
     FROM leads l
     LEFT JOIN users u ON l.assigned_staff_id = u.id
     WHERE $whereSql
     ORDER BY l.created_at DESC"
);
$stmt->execute($params);
$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Column map ──────────────────────────────────────────────────────────────
$stageLabels = [
    'new' => 'New', 'contacted' => 'Contacted', 'interested' => 'Interested',
    'future' => 'Book Later', 'closed_won' => 'Closed Won', 'closed_lost' => 'Closed Lost',
];
$inquiryLabels = [
    'daily' => 'Daily Rental', 'weekly' => 'Weekly Rental',
    'monthly' => 'Monthly Rental', 'wedding_rental' => 'Wedding Rental', 'other' => 'Other',
];

$colMap = [];
if ($include['name'])               $colMap['name']               = 'Name';
if ($include['phone'])              $colMap['phone']              = 'Phone';
if ($include['alternative_number']) $colMap['alternative_number'] = 'Alt. Phone';
if ($include['email'])              $colMap['email']              = 'Email';
if ($include['status'])             $colMap['status']             = 'Status';
if ($include['source'])             $colMap['source']             = 'Source';
if ($include['inquiry_type'])       $colMap['inquiry_type']       = 'Inquiry Type';
if ($include['vehicle_interest'])   $colMap['vehicle_interest']   = 'Vehicle Interest';
if ($include['assigned_to'])        $colMap['assigned_to']        = 'Assigned To';
if ($include['notes'])              $colMap['notes']              = 'Notes';
if ($include['created_at'])         $colMap['created_at']         = 'Created At';

$headerRow = array_values($colMap);

$dataRows = [];
foreach ($leads as $l) {
    $row = [];
    foreach (array_keys($colMap) as $key) {
        $val = match ($key) {
            'status'       => $stageLabels[$l['status'] ?? ''] ?? ($l['status'] ?? ''),
            'source'       => $leadSourcesMap[$l['source'] ?? ''] ?? ($l['source'] ?? ''),
            'inquiry_type' => $inquiryLabels[$l['inquiry_type'] ?? ''] ?? ($l['inquiry_type'] ?? ''),
            'assigned_to'  => $l['assigned_user_name'] ?? '',
            'created_at'   => $l['created_at'] ? date('d M Y', strtotime($l['created_at'])) : '',
            default        => $l[$key] ?? '',
        };
        $row[] = $val;
    }
    $dataRows[] = $row;
}

$sheetRows = array_merge([$headerRow], $dataRows);

// ── Pure-PHP ZIP/XLSX builder (same as clients/export.php) ─────────────────
class LeadExportZip
{
    private array $files = [];
    public function addFromString(string $name, string $data): void { $this->files[] = ['name' => $name, 'data' => $data]; }
    public function getBytes(): string
    {
        $localPart = $centralDir = ''; $offset = 0; $dosTime = 0; $dosDate = (1 << 5) | 1;
        foreach ($this->files as $file) {
            $name = $file['name']; $data = $file['data'];
            $crc = crc32($data); $size = strlen($data); $nameLen = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0) . $name . $data;
            $central = pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset) . $name;
            $localPart .= $local; $centralDir .= $central; $offset += strlen($local);
        }
        $cdSize = strlen($centralDir); $cdOffset = strlen($localPart); $numFiles = count($this->files);
        return $localPart . $centralDir . pack('VvvvvVVv', 0x06054b50, 0, 0, $numFiles, $numFiles, $cdSize, $cdOffset, 0);
    }
}
function leXEsc(mixed $v): string { return $v === null || $v === '' ? '' : htmlspecialchars((string)$v, ENT_XML1, 'UTF-8'); }
function leColLetter(int $n): string { $l = ''; do { $l = chr(65 + ($n % 26)) . $l; $n = intdiv($n, 26) - 1; } while ($n >= 0); return $l; }
function leBuildSheetXml(array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    $ri = 1;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $ri . '">';
        $ci = 0;
        foreach ($row as $cell) { $ref = leColLetter($ci++) . $ri; $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . leXEsc($cell) . '</t></is></c>'; }
        $xml .= '</row>'; $ri++;
    }
    return $xml . '</sheetData></worksheet>';
}

$zip = new LeadExportZip();
$ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>';
$zip->addFromString('[Content_Types].xml', $ct);
$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
$zip->addFromString('_rels/.rels', $rels);
$wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>';
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
$wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Leads" sheetId="1" r:id="rId1"/></sheets></workbook>';
$zip->addFromString('xl/workbook.xml', $wb);
$zip->addFromString('xl/worksheets/sheet1.xml', leBuildSheetXml($sheetRows));

$bytes    = $zip->getBytes();
$filename = 'leads_export_' . date('Y-m-d_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: max-age=0');
echo $bytes;
exit;
