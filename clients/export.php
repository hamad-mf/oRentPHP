<?php
/**
 * clients/export.php — Export filtered client list to .xlsx
 * Reuses the same pure-PHP ZIP/OOXML approach as export/download.php.
 * No Composer / php_zip extension required.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/client_helpers.php';

auth_check(); // must be logged in

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$pdo = db();
clients_ensure_schema($pdo);
$supportsAlt = clients_has_column($pdo, 'alternative_number');

// ── Inputs ─────────────────────────────────────────────────────────────────
$search  = trim($_POST['search'] ?? '');
$filter  = $_POST['filter'] ?? '';
$fields  = (array) ($_POST['fields'] ?? []);

// Mandatory fields always included
$include = [
    'name'  => true,
    'phone' => true,
];
// Optional fields
foreach (['email', 'alternative_number', 'rating', 'rentals_count'] as $f) {
    $include[$f] = in_array($f, $fields, true);
}
// alternative_number only if column exists
if (!$supportsAlt) {
    $include['alternative_number'] = false;
}

// ── Build WHERE (mirrors clients/index.php) ────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($search !== '') {
    if ($supportsAlt) {
        $where[]  = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.alternative_number LIKE ?)';
        $params   = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
    } else {
        $where[]  = '(c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
        $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
    }
}
switch ($filter) {
    case 'blacklisted':
        $where[] = 'c.is_blacklisted = 1';
        break;
    case 'rated':
        $where[] = 'c.rating IS NOT NULL';
        break;
    case 'unrated':
        $where[] = 'c.rating IS NULL';
        break;
    case 'completed':
        $where[] = "EXISTS (SELECT 1 FROM reservations r WHERE r.client_id = c.id AND r.status = 'completed')";
        break;
}

$whereClause = implode(' AND ', $where);

// ── Query ──────────────────────────────────────────────────────────────────
$sql = 'SELECT c.name, c.phone, c.email'
     . ($supportsAlt ? ', c.alternative_number' : '')
     . ', c.rating'
     . ', (SELECT COUNT(*) FROM reservations r WHERE r.client_id = c.id) AS rentals_count'
     . ' FROM clients c WHERE ' . $whereClause
     . ' ORDER BY c.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Build header row ───────────────────────────────────────────────────────
$headers = [];
$colMap  = []; // field key → label

if ($include['name'])               $colMap['name']               = 'Name';
if ($include['phone'])              $colMap['phone']              = 'Phone';
if ($include['email'])              $colMap['email']              = 'Email';
if ($include['alternative_number']) $colMap['alternative_number'] = 'Alt. Phone';
if ($include['rating'])             $colMap['rating']             = 'Rating';
if ($include['rentals_count'])      $colMap['rentals_count']      = 'No. of Rentals';

$headerRow = array_values($colMap);

// ── Build data rows ────────────────────────────────────────────────────────
$dataRows = [];
foreach ($clients as $c) {
    $row = [];
    foreach (array_keys($colMap) as $key) {
        $row[] = $c[$key] ?? '';
    }
    $dataRows[] = $row;
}

$sheetRows = array_merge([$headerRow], $dataRows);

// ══════════════════════════════════════════════════════════════════════════════
// Pure-PHP ZIP builder (no ZipArchive / php_zip extension needed)
// ══════════════════════════════════════════════════════════════════════════════
class ClientExportZip
{
    private array $files = [];

    public function addFromString(string $name, string $data): void
    {
        $this->files[] = ['name' => $name, 'data' => $data];
    }

    public function getBytes(): string
    {
        $localPart  = '';
        $centralDir = '';
        $offset     = 0;
        $dosTime    = 0;
        $dosDate    = (1 << 5) | 1;

        foreach ($this->files as $file) {
            $name    = $file['name'];
            $data    = $file['data'];
            $crc     = crc32($data);
            $size    = strlen($data);
            $nameLen = strlen($name);

            $local = pack(
                'VvvvvvVVVvv',
                0x04034b50, 20, 0x0000, 0, $dosTime, $dosDate,
                $crc, $size, $size, $nameLen, 0
            ) . $name . $data;

            $central = pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50, 20, 20, 0x0000, 0, $dosTime, $dosDate,
                $crc, $size, $size, $nameLen, 0, 0, 0, 0, 0, $offset
            ) . $name;

            $localPart  .= $local;
            $centralDir .= $central;
            $offset     += strlen($local);
        }

        $cdSize   = strlen($centralDir);
        $cdOffset = strlen($localPart);
        $numFiles = count($this->files);

        $eocd = pack(
            'VvvvvVVv',
            0x06054b50, 0, 0, $numFiles, $numFiles, $cdSize, $cdOffset, 0
        );

        return $localPart . $centralDir . $eocd;
    }
}

function ceXEsc(mixed $v): string
{
    if ($v === null || $v === '') return '';
    return htmlspecialchars((string) $v, ENT_XML1, 'UTF-8');
}

function ceColLetter(int $n): string
{
    $letter = '';
    do {
        $letter = chr(65 + ($n % 26)) . $letter;
        $n = intdiv($n, 26) - 1;
    } while ($n >= 0);
    return $letter;
}

function ceBuildSheetXml(array $rows): string
{
    $xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml .= '<sheetData>';
    $rowIdx = 1;
    foreach ($rows as $row) {
        $xml .= '<row r="' . $rowIdx . '">';
        $colIdx = 0;
        foreach ($row as $cell) {
            $ref  = ceColLetter($colIdx++) . $rowIdx;
            $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . ceXEsc($cell) . '</t></is></c>';
        }
        $xml .= '</row>';
        $rowIdx++;
    }
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

// ── Assemble xlsx ──────────────────────────────────────────────────────────
$zip = new ClientExportZip();

$ct  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$ct .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
$ct .= '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
$ct .= '<Default Extension="xml"  ContentType="application/xml"/>';
$ct .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
$ct .= '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
$ct .= '</Types>';
$zip->addFromString('[Content_Types].xml', $ct);

$rels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$rels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
$rels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
$rels .= '</Relationships>';
$zip->addFromString('_rels/.rels', $rels);

$wbRels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wbRels .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
$wbRels .= '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
$wbRels .= '</Relationships>';
$zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

$wb  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
$wb .= '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
$wb .= '<sheets><sheet name="Clients" sheetId="1" r:id="rId1"/></sheets>';
$wb .= '</workbook>';
$zip->addFromString('xl/workbook.xml', $wb);

$zip->addFromString('xl/worksheets/sheet1.xml', ceBuildSheetXml($sheetRows));

// ── Stream ─────────────────────────────────────────────────────────────────
$bytes    = $zip->getBytes();
$filename = 'clients_export_' . date('Y-m-d_His') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($bytes));
header('Cache-Control: max-age=0');

echo $bytes;
exit;
