<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/settings_helpers.php';
require_once __DIR__ . '/../includes/activity_log.php';

auth_check();
$currentUser = current_user();
if (($currentUser['role'] ?? '') !== 'admin') {
    flash('error', 'Only admin can import leads.');
    redirect('pipeline.php');
}

$pdo = db();
$leadSourcesMap = lead_sources_get_map($pdo);
$defaultSource = array_key_exists('phone', $leadSourcesMap) ? 'phone' : (array_key_first($leadSourcesMap) ?? 'other');
$assignableStaff = $pdo->query("SELECT id, name FROM users WHERE is_active = 1 AND role = 'staff' ORDER BY name ASC")->fetchAll();
$assignableStaffById = [];
foreach ($assignableStaff as $staffRow) {
    $assignableStaffById[(int) $staffRow['id']] = $staffRow;
}

const LEAD_IMPORT_DRAFT_KEY = 'lead_import_draft';
const LEAD_IMPORT_MAX_ROWS = 5000;
const LEAD_IMPORT_MAX_FAILED_PREVIEW = 500;

function lead_import_header_norm(string $value): string
{
    return preg_replace('/[^a-z0-9]+/', '', strtolower(trim($value)));
}

function lead_import_phone_norm(string $value): string
{
    return preg_replace('/\D+/', '', $value);
}

function lead_import_col_name_from_index(int $index): string
{
    $name = '';
    $n = $index;
    do {
        $name = chr(($n % 26) + 65) . $name;
        $n = intdiv($n, 26) - 1;
    } while ($n >= 0);
    return $name;
}

function lead_import_col_index_from_name(string $name): int
{
    $name = strtoupper(trim($name));
    $len = strlen($name);
    $index = 0;
    for ($i = 0; $i < $len; $i++) {
        $index = ($index * 26) + (ord($name[$i]) - 64);
    }
    return max(0, $index - 1);
}

function lead_import_xml_text(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function lead_import_extract_shared_strings(string $xml): array
{
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
        return [];
    }

    $xp = new DOMXPath($dom);
    $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $shared = [];
    foreach ($xp->query('//a:si') ?: [] as $siNode) {
        $text = '';
        foreach ($xp->query('.//a:t', $siNode) ?: [] as $part) {
            $text .= $part->textContent;
        }
        $shared[] = $text;
    }
    return $shared;
}

function lead_import_parse_xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive extension is required to read XLSX files.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Could not open XLSX file.');
    }

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if (is_string($sharedXml)) {
        $sharedStrings = lead_import_extract_shared_strings($sharedXml);
    }

    $worksheetNames = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);
        if (is_string($entryName) && preg_match('#^xl/worksheets/.+\.xml$#i', $entryName)) {
            $worksheetNames[] = $entryName;
        }
    }
    natcasesort($worksheetNames);
    $worksheetNames = array_values($worksheetNames);
    if (empty($worksheetNames)) {
        $zip->close();
        throw new RuntimeException('No worksheet found in XLSX file.');
    }

    $sheetXml = $zip->getFromName($worksheetNames[0]);
    $zip->close();
    if (!is_string($sheetXml) || $sheetXml === '') {
        throw new RuntimeException('Could not read worksheet data.');
    }

    $sheetDom = new DOMDocument();
    if (!@$sheetDom->loadXML($sheetXml)) {
        throw new RuntimeException('Invalid worksheet XML.');
    }
    $xp = new DOMXPath($sheetDom);
    $xp->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $rows = [];
    foreach (($xp->query('//a:sheetData/a:row') ?: []) as $rowNode) {
        $rowNumber = (int) ($rowNode->getAttribute('r') ?: 0);
        if ($rowNumber <= 0) {
            $rowNumber = count($rows) + 1;
        }

        $cells = [];
        foreach (($xp->query('a:c', $rowNode) ?: []) as $cell) {
            $ref = (string) $cell->getAttribute('r');
            $colName = '';
            if ($ref !== '' && preg_match('/^([A-Z]+)\d+$/i', $ref, $m)) {
                $colName = strtoupper($m[1]);
            }
            $colIndex = $colName !== '' ? lead_import_col_index_from_name($colName) : count($cells);

            $cellType = (string) $cell->getAttribute('t');
            $value = '';
            if ($cellType === 's') {
                $vNode = $xp->query('a:v', $cell)->item(0);
                $sharedIndex = (int) ($vNode ? $vNode->textContent : 0);
                $value = (string) ($sharedStrings[$sharedIndex] ?? '');
            } elseif ($cellType === 'inlineStr') {
                foreach (($xp->query('a:is//a:t', $cell) ?: []) as $textNode) {
                    $value .= $textNode->textContent;
                }
            } else {
                $vNode = $xp->query('a:v', $cell)->item(0);
                $value = $vNode ? $vNode->textContent : '';
            }
            $cells[$colIndex] = trim($value);
        }

        if (empty($cells)) {
            continue;
        }
        ksort($cells);
        $maxCol = (int) max(array_keys($cells));
        $normalized = array_fill(0, $maxCol + 1, '');
        foreach ($cells as $idx => $val) {
            $normalized[(int) $idx] = (string) $val;
        }
        $rows[] = ['row_number' => $rowNumber, 'cells' => $normalized];
    }

    return $rows;
}

function lead_import_parse_csv_rows(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Could not open CSV file.');
    }

    $rows = [];
    $lineNo = 0;
    while (($data = fgetcsv($handle)) !== false) {
        $lineNo++;
        $data = array_map(static fn($v) => trim((string) $v), $data);
        if ($lineNo === 1 && isset($data[0])) {
            $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
        }
        $rows[] = ['row_number' => $lineNo, 'cells' => array_values($data)];
    }
    fclose($handle);
    return $rows;
}

function lead_import_parse_file_rows(string $tmpPath, string $extension): array
{
    if ($extension === 'xlsx') {
        return lead_import_parse_xlsx_rows($tmpPath);
    }
    if ($extension === 'csv') {
        return lead_import_parse_csv_rows($tmpPath);
    }
    throw new RuntimeException('Unsupported file type.');
}

function lead_import_guess_map(array $headers): array
{
    $map = [
        'name' => null,
        'phone' => null,
        'email' => null,
        'source' => null,
        'inquiry_type' => null,
        'vehicle_interest' => null,
        'status' => null,
        'notes' => null,
    ];

    $aliases = [
        'name' => ['name', 'fullname', 'leadname', 'clientname', 'customername'],
        'phone' => ['phone', 'phonenumber', 'mobile', 'mobilenumber', 'whatsapp', 'whatsappnumber'],
        'email' => ['email', 'emailaddress', 'mail'],
        'source' => ['source', 'leadsource', 'channel'],
        'inquiry_type' => ['inquirytype', 'inquiry', 'rentaltype', 'durationtype'],
        'vehicle_interest' => ['vehicleinterest', 'vehicle', 'carinterest', 'interestedvehicle', 'car'],
        'status' => ['status', 'leadstatus', 'stage'],
        'notes' => ['notes', 'note', 'remark', 'remarks', 'comment', 'comments'],
    ];

    foreach ($headers as $idx => $headerLabel) {
        $norm = lead_import_header_norm((string) $headerLabel);
        if ($norm === '') {
            continue;
        }
        foreach ($aliases as $targetField => $terms) {
            if ($map[$targetField] !== null) {
                continue;
            }
            foreach ($terms as $term) {
                if ($norm === $term || str_contains($norm, $term)) {
                    $map[$targetField] = (int) $idx;
                    break 2;
                }
            }
        }
    }

    return $map;
}

function lead_import_map_index(array $map, string $field): ?int
{
    if (!array_key_exists($field, $map)) {
        return null;
    }
    $raw = $map[$field];
    if ($raw === '' || $raw === null || $raw === '__none__') {
        return null;
    }
    if (!is_numeric($raw)) {
        return null;
    }
    return (int) $raw;
}

function lead_import_cell(array $cells, ?int $idx): string
{
    if ($idx === null) {
        return '';
    }
    return trim((string) ($cells[$idx] ?? ''));
}
function lead_import_source_slug(string $rawSource, array $leadSourcesMap, string $defaultSource): string
{
    $rawSource = trim($rawSource);
    if ($rawSource === '') {
        return $defaultSource;
    }
    if (array_key_exists($rawSource, $leadSourcesMap)) {
        return $rawSource;
    }

    $norm = lead_import_header_norm($rawSource);
    foreach ($leadSourcesMap as $slug => $label) {
        if ($norm === lead_import_header_norm((string) $slug) || $norm === lead_import_header_norm((string) $label)) {
            return (string) $slug;
        }
    }
    return $defaultSource;
}

function lead_import_inquiry_type(string $rawValue, string $default): string
{
    $norm = lead_import_header_norm($rawValue);
    if ($norm === '') {
        return $default;
    }
    if (str_contains($norm, 'wedding')) {
        return 'wedding_rental';
    }
    if (str_contains($norm, 'week')) {
        return 'weekly';
    }
    if (str_contains($norm, 'month')) {
        return 'monthly';
    }
    if (str_contains($norm, 'other')) {
        return 'other';
    }
    return 'daily';
}

function lead_import_status(string $rawValue, string $default): string
{
    $norm = lead_import_header_norm($rawValue);
    if ($norm === '') {
        return $default;
    }
    if ($norm === 'new' || $norm === 'uncontacted') {
        return 'new';
    }
    if ($norm === 'contacted') {
        return 'contacted';
    }
    if ($norm === 'future' || $norm === 'booklater' || $norm === 'later') {
        return 'future';
    }
    return $default;
}

function lead_import_table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function lead_import_template_sheet_xml(array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $rowIndex => $rowCells) {
        $rowNum = $rowIndex + 1;
        $xml .= '<row r="' . $rowNum . '">';
        foreach ($rowCells as $colIndex => $value) {
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $cellRef = lead_import_col_name_from_index((int) $colIndex) . $rowNum;
            $needsPreserve = ($value !== trim($value));
            $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t' . ($needsPreserve ? ' xml:space="preserve"' : '') . '>' . lead_import_xml_text($value) . '</t></is></c>';
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function lead_import_download_template(array $leadSourcesMap): never
{
    $rows = [
        ['Name', 'Phone', 'Email', 'Source', 'Inquiry Type', 'Vehicle Interest', 'Status', 'Notes'],
        ['Ahmed Al Rashid', '+971501234567', 'ahmed@example.com', 'phone', 'daily', 'Mercedes GLE', 'new', 'Follow up tomorrow'],
        ['Sara Khan', '+971551112233', 'sara@example.com', 'instagram', 'weekly', 'BMW 5 Series', 'contacted', 'Interested in weekend pickup'],
    ];

    if (!class_exists('ZipArchive')) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="lead_import_template.csv"');
        $out = fopen('php://output', 'wb');
        foreach ($rows as $csvRow) {
            fputcsv($out, $csvRow);
        }
        fclose($out);
        exit;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'lead_tpl_');
    if ($tmpPath === false) {
        http_response_code(500);
        echo 'Could not prepare template file.';
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpPath);
        http_response_code(500);
        echo 'Could not create template file.';
        exit;
    }

    $sheetXml = lead_import_template_sheet_xml($rows);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Leads" sheetId="1" r:id="rId1"/>
    </sheets>
</workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="lead_import_template.xlsx"');
    header('Content-Length: ' . filesize($tmpPath));
    readfile($tmpPath);
    @unlink($tmpPath);
    exit;
}

if (($_GET['action'] ?? '') === 'template') {
    lead_import_download_template($leadSourcesMap);
}

$allowedInquiryTypes = ['daily', 'weekly', 'monthly', 'wedding_rental', 'other'];
$allowedInitialStatuses = ['new', 'contacted', 'future'];
$stage = $_POST['stage'] ?? $_GET['stage'] ?? 'upload';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'prepare') {
    if (empty($assignableStaffById)) {
        flash('error', 'No active staff accounts found for round robin assignment.');
        redirect('pipeline.php');
    }

    $selectedStaffIds = array_values(array_unique(array_map('intval', $_POST['staff_ids'] ?? [])));
    $selectedStaffIds = array_values(array_filter($selectedStaffIds, static fn($id) => $id > 0));
    $selectedStaffIds = array_values(array_filter($selectedStaffIds, static fn($id) => isset($assignableStaffById[$id])));
    if (empty($selectedStaffIds)) {
        flash('error', 'Select at least one staff member for round robin assignment.');
        redirect('pipeline.php');
    }

    if (!isset($_FILES['import_file']) || !is_array($_FILES['import_file']) || (int) ($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        flash('error', 'Upload a valid import file.');
        redirect('pipeline.php');
    }

    $originalName = trim((string) ($_FILES['import_file']['name'] ?? ''));
    $tmpPath = (string) ($_FILES['import_file']['tmp_name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, ['xlsx', 'csv'], true)) {
        flash('error', 'Only .xlsx and .csv files are supported.');
        redirect('pipeline.php');
    }

    try {
        $parsedRows = lead_import_parse_file_rows($tmpPath, $ext);
    } catch (Throwable $e) {
        app_log('ERROR', 'Lead import: file parse failed - ' . $e->getMessage(), [
    'uploaded_file' => $originalName,
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

        flash('error', 'Could not read file: ' . $e->getMessage());
        redirect('pipeline.php');
    }

    if (count($parsedRows) < 2) {
        flash('error', 'The file must include a header row and at least one data row.');
        redirect('pipeline.php');
    }

    $headerCells = array_values((array) ($parsedRows[0]['cells'] ?? []));
    $headerCount = max(1, count($headerCells));
    $headers = [];
    $usedHeaderNames = [];
    for ($i = 0; $i < $headerCount; $i++) {
        $base = trim((string) ($headerCells[$i] ?? ''));
        if ($base === '') {
            $base = 'Column ' . ($i + 1);
        }
        $candidate = $base;
        $suffix = 2;
        while (isset($usedHeaderNames[strtolower($candidate)])) {
            $candidate = $base . ' (' . $suffix . ')';
            $suffix++;
        }
        $usedHeaderNames[strtolower($candidate)] = true;
        $headers[] = $candidate;
    }

    $dataRows = [];
    for ($i = 1; $i < count($parsedRows); $i++) {
        $entry = $parsedRows[$i];
        $rowCells = array_values((array) ($entry['cells'] ?? []));
        $normalizedCells = [];
        for ($c = 0; $c < $headerCount; $c++) {
            $normalizedCells[] = trim((string) ($rowCells[$c] ?? ''));
        }
        $hasData = false;
        foreach ($normalizedCells as $cellValue) {
            if ($cellValue !== '') {
                $hasData = true;
                break;
            }
        }
        if (!$hasData) {
            continue;
        }
        $dataRows[] = [
            'row_number' => (int) ($entry['row_number'] ?? ($i + 1)),
            'cells' => $normalizedCells,
        ];
        if (count($dataRows) > LEAD_IMPORT_MAX_ROWS) {
            flash('error', 'Too many rows. Maximum allowed rows per import is ' . LEAD_IMPORT_MAX_ROWS . '.');
            redirect('pipeline.php');
        }
    }

    if (empty($dataRows)) {
        flash('error', 'No usable data rows found in file.');
        redirect('pipeline.php');
    }

    $_SESSION[LEAD_IMPORT_DRAFT_KEY] = [
        'filename' => ($originalName !== '' ? $originalName : 'import.' . $ext),
        'headers' => $headers,
        'rows' => $dataRows,
        'staff_ids' => $selectedStaffIds,
        'staff_names' => array_values(array_map(static fn($id) => $assignableStaffById[$id]['name'] ?? ('Staff #' . $id), $selectedStaffIds)),
        'created_at' => time(),
    ];

    redirect('import.php?stage=map');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $stage === 'import') {
    $draft = $_SESSION[LEAD_IMPORT_DRAFT_KEY] ?? null;
    if (!is_array($draft) || empty($draft['rows']) || empty($draft['headers']) || empty($draft['staff_ids'])) {
        flash('error', 'Import draft expired. Please upload the file again.');
        redirect('pipeline.php');
    }

    $headers = (array) $draft['headers'];
    $rows = (array) $draft['rows'];
    $selectedStaffIds = array_values(array_filter(array_map('intval', (array) $draft['staff_ids']), static fn($id) => $id > 0));
    $selectedStaffIds = array_values(array_filter($selectedStaffIds, static fn($id) => isset($assignableStaffById[$id])));
    if (empty($selectedStaffIds)) {
        unset($_SESSION[LEAD_IMPORT_DRAFT_KEY]);
        flash('error', 'Assigned staff list is invalid. Please re-upload and select staff again.');
        redirect('pipeline.php');
    }

    $map = (array) ($_POST['map'] ?? []);
    $mapName = lead_import_map_index($map, 'name');
    $mapPhone = lead_import_map_index($map, 'phone');
    if ($mapName === null || $mapPhone === null) {
        flash('error', 'Mapping for Name and Phone is required.');
        redirect('import.php?stage=map');
    }

    $mapEmail = lead_import_map_index($map, 'email');
    $mapSource = lead_import_map_index($map, 'source');
    $mapInquiry = lead_import_map_index($map, 'inquiry_type');
    $mapVehicle = lead_import_map_index($map, 'vehicle_interest');
    $mapStatus = lead_import_map_index($map, 'status');
    $mapNotes = lead_import_map_index($map, 'notes');

    $defaultImportSource = (string) ($_POST['default_source'] ?? $defaultSource);
    if (!array_key_exists($defaultImportSource, $leadSourcesMap)) {
        $defaultImportSource = $defaultSource;
    }
    $defaultInquiry = (string) ($_POST['default_inquiry_type'] ?? 'daily');
    if (!in_array($defaultInquiry, $allowedInquiryTypes, true)) {
        $defaultInquiry = 'daily';
    }
    $defaultStatus = (string) ($_POST['default_status'] ?? 'new');
    if (!in_array($defaultStatus, $allowedInitialStatuses, true)) {
        $defaultStatus = 'new';
    }

    $supportsAssignedStaff = lead_import_table_has_column($pdo, 'leads', 'assigned_staff_id');
    $insertLeadWithStaff = $pdo->prepare('INSERT INTO leads (name, phone, email, inquiry_type, vehicle_interest, source, assigned_to, assigned_staff_id, status, notes) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $insertLeadWithoutStaff = $pdo->prepare('INSERT INTO leads (name, phone, email, inquiry_type, vehicle_interest, source, assigned_to, status, notes) VALUES (?,?,?,?,?,?,?,?,?)');
    $insertActivityStmt = $pdo->prepare('INSERT INTO lead_activities (lead_id, note) VALUES (?,?)');

    $existingPhones = [];
    $existingEmails = [];
    foreach ($pdo->query('SELECT phone, email FROM leads') as $existingRow) {
        $phoneKey = lead_import_phone_norm((string) ($existingRow['phone'] ?? ''));
        if ($phoneKey !== '') {
            $existingPhones[$phoneKey] = true;
        }
        $emailKey = strtolower(trim((string) ($existingRow['email'] ?? '')));
        if ($emailKey !== '') {
            $existingEmails[$emailKey] = true;
        }
    }

    $seenPhones = [];
    $seenEmails = [];
    $successCount = 0;
    $failedCount = 0;
    $failedEntries = [];
    $roundRobinIdx = 0;

    foreach ($rows as $row) {
        $rowNumber = (int) ($row['row_number'] ?? 0);
        $cells = (array) ($row['cells'] ?? []);

        $name = lead_import_cell($cells, $mapName);
        $phone = lead_import_cell($cells, $mapPhone);
        $email = strtolower(lead_import_cell($cells, $mapEmail));
        $sourceRaw = lead_import_cell($cells, $mapSource);
        $inquiryRaw = lead_import_cell($cells, $mapInquiry);
        $vehicleInterest = lead_import_cell($cells, $mapVehicle);
        $statusRaw = lead_import_cell($cells, $mapStatus);
        $notes = lead_import_cell($cells, $mapNotes);

        $errorReason = '';
        if ($name === '') {
            $errorReason = 'Name is required.';
        } elseif ($phone === '') {
            $errorReason = 'Phone is required.';
        }

        $phoneKey = lead_import_phone_norm($phone);
        if ($errorReason === '' && $phoneKey === '') {
            $errorReason = 'Phone is invalid.';
        }
        if ($errorReason === '' && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorReason = 'Invalid email format.';
        }

        if ($errorReason === '' && $phoneKey !== '' && (isset($existingPhones[$phoneKey]) || isset($seenPhones[$phoneKey]))) {
            $errorReason = 'Duplicate phone.';
        }
        if ($errorReason === '' && $email !== '' && (isset($existingEmails[$email]) || isset($seenEmails[$email]))) {
            $errorReason = 'Duplicate email.';
        }

        if ($errorReason !== '') {
            $failedCount++;
            if (count($failedEntries) < LEAD_IMPORT_MAX_FAILED_PREVIEW) {
                $failedEntries[] = [
                    'row_number' => $rowNumber,
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'reason' => $errorReason,
                ];
            }
            continue;
        }

        $source = lead_import_source_slug($sourceRaw, $leadSourcesMap, $defaultImportSource);
        $inquiryType = lead_import_inquiry_type($inquiryRaw, $defaultInquiry);
        $status = lead_import_status($statusRaw, $defaultStatus);
        if (!in_array($status, $allowedInitialStatuses, true)) {
            $status = $defaultStatus;
        }

        $assignedStaffId = $selectedStaffIds[$roundRobinIdx % count($selectedStaffIds)];
        $roundRobinIdx++;
        $assignedStaffName = (string) ($assignableStaffById[$assignedStaffId]['name'] ?? ('Staff #' . $assignedStaffId));

        try {
            if ($supportsAssignedStaff) {
                $insertLeadWithStaff->execute([
                    $name,
                    $phone,
                    ($email !== '' ? $email : null),
                    $inquiryType,
                    ($vehicleInterest !== '' ? $vehicleInterest : null),
                    $source,
                    $assignedStaffName,
                    $assignedStaffId,
                    $status,
                    ($notes !== '' ? $notes : null),
                ]);
            } else {
                $insertLeadWithoutStaff->execute([
                    $name,
                    $phone,
                    ($email !== '' ? $email : null),
                    $inquiryType,
                    ($vehicleInterest !== '' ? $vehicleInterest : null),
                    $source,
                    $assignedStaffName,
                    $status,
                    ($notes !== '' ? $notes : null),
                ]);
            }

            $newLeadId = (int) $pdo->lastInsertId();
            $insertActivityStmt->execute([$newLeadId, 'Lead imported via Excel/CSV with status: ' . str_replace('_', ' ', $status) . '.']);
            $successCount++;
            $seenPhones[$phoneKey] = true;
            if ($email !== '') {
                $seenEmails[$email] = true;
            }
            $existingPhones[$phoneKey] = true;
            if ($email !== '') {
                $existingEmails[$email] = true;
            }
        } catch (Throwable $e) {
            app_log('ERROR', 'Lead import: row insert failed - ' . $e->getMessage(), [
    'row_number' => $rowNumber,
    'name' => $name,
    'phone' => $phone,
    'file' => $e->getFile() . ':' . $e->getLine(),
]);

            $failedCount++;
            if (count($failedEntries) < LEAD_IMPORT_MAX_FAILED_PREVIEW) {
                $failedEntries[] = [
                    'row_number' => $rowNumber,
                    'name' => $name,
                    'phone' => $phone,
                    'email' => $email,
                    'reason' => 'Database error: ' . $e->getMessage(),
                ];
            }
        }
    }

    $totalRows = count($rows);
    $failedPreviewCount = count($failedEntries);
    $failedHiddenCount = max(0, $failedCount - $failedPreviewCount);
    $report = [
        'filename' => (string) ($draft['filename'] ?? 'import'),
        'total_rows' => $totalRows,
        'success_count' => $successCount,
        'failed_count' => $failedCount,
        'failed_entries' => $failedEntries,
        'failed_hidden_count' => $failedHiddenCount,
        'staff_names' => array_values((array) ($draft['staff_names'] ?? [])),
        'completed_at' => date('Y-m-d H:i:s'),
    ];

    $_SESSION['lead_import_report'] = $report;
    unset($_SESSION[LEAD_IMPORT_DRAFT_KEY]);

    log_activity(
        $pdo,
        'imported_leads_batch',
        'lead',
        0,
        'Imported leads from file "' . $report['filename'] . '". Success: ' . $successCount . ', Failed: ' . $failedCount . '.'
    );
    app_log('ACTION', 'Lead import completed. File: ' . $report['filename'] . ', Success: ' . $successCount . ', Failed: ' . $failedCount);

    flash('success', 'Lead import finished. Success: ' . $successCount . ', Failed: ' . $failedCount . '.');
    redirect('pipeline.php');
}

$draft = $_SESSION[LEAD_IMPORT_DRAFT_KEY] ?? null;
$stage = ($stage === 'map' && is_array($draft) && !empty($draft['rows'])) ? 'map' : 'upload';
$headers = (array) ($draft['headers'] ?? []);
$rows = (array) ($draft['rows'] ?? []);
$staffNames = (array) ($draft['staff_names'] ?? []);
$mappingFields = [
    'name' => ['label' => 'Lead Name', 'required' => true],
    'phone' => ['label' => 'Phone', 'required' => true],
    'email' => ['label' => 'Email', 'required' => false],
    'source' => ['label' => 'Source', 'required' => false],
    'inquiry_type' => ['label' => 'Inquiry Type', 'required' => false],
    'vehicle_interest' => ['label' => 'Vehicle Interest', 'required' => false],
    'status' => ['label' => 'Status', 'required' => false],
    'notes' => ['label' => 'Notes', 'required' => false],
];
$suggestedMap = $stage === 'map' ? lead_import_guess_map($headers) : [];

$pageTitle = 'Import Leads';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h2 class="text-white text-xl font-light">Import Leads</h2>
            <p class="text-mb-subtle text-sm mt-0.5">Upload file, map columns, and import with round robin staff assignment.</p>
        </div>
        <a href="pipeline.php"
            class="bg-mb-black border border-mb-subtle/20 text-mb-silver px-4 py-2 rounded-full hover:border-mb-accent/40 hover:text-white transition-colors text-sm">
            Back to Pipeline
        </a>
    </div>

    <?php if ($stage === 'upload'): ?>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Step 1: Upload File</h3>
                <a href="import.php?action=template"
                    class="text-sm bg-mb-accent/15 border border-mb-accent/40 text-mb-accent px-4 py-2 rounded-full hover:bg-mb-accent/25 transition-colors">
                    Download Template (.xlsx)
                </a>
            </div>

            <form method="POST" enctype="multipart/form-data" id="leadImportPrepareForm" class="space-y-5">
                <input type="hidden" name="stage" value="prepare">
                <div>
                    <label class="block text-sm text-mb-silver mb-2">Import File</label>
                    <input type="file" name="import_file" accept=".xlsx,.csv" required
                        class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-4 py-3 text-sm text-white file:mr-4 file:rounded-md file:border-0 file:bg-mb-accent/20 file:px-3 file:py-1.5 file:text-xs file:text-mb-accent hover:file:bg-mb-accent/30">
                    <p class="text-xs text-mb-subtle mt-1">Supported formats: .xlsx and .csv (first row must be header).</p>
                </div>

                <div>
                    <label class="block text-sm text-mb-silver mb-2">Round Robin Staff Selection <span
                            class="text-red-400">*</span></label>
                    <?php if (empty($assignableStaff)): ?>
                        <p class="text-sm text-red-400">No active staff accounts found. Create active staff first.</p>
                    <?php else: ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <?php foreach ($assignableStaff as $staff): ?>
                                <label
                                    class="flex items-center gap-2 bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2 text-sm text-mb-silver hover:border-mb-accent/35 transition-colors cursor-pointer">
                                    <input type="checkbox" name="staff_ids[]" value="<?= (int) $staff['id'] ?>"
                                        class="accent-[#00adef]">
                                    <span><?= e($staff['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-mb-subtle mt-1">Imported leads will be assigned in sequence across selected staff.</p>
                    <?php endif; ?>
                </div>

                <div id="leadImportPrepareProgress"
                    class="hidden bg-mb-black/60 border border-mb-subtle/20 rounded-lg px-4 py-3">
                    <p class="text-xs text-mb-silver mb-2">Preparing file and loading column mapping...</p>
                    <div class="h-2 bg-mb-surface rounded-full overflow-hidden">
                        <div class="h-full bg-mb-accent animate-pulse w-2/3"></div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" id="leadImportPrepareSubmit"
                        class="bg-mb-accent text-white px-5 py-2 rounded-full hover:bg-mb-accent/80 transition-colors text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                        Upload and Continue
                    </button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="bg-mb-surface border border-mb-subtle/20 rounded-xl p-6 space-y-5">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="text-white font-light text-lg border-l-2 border-mb-accent pl-3">Step 2: Map Columns and Import</h3>
                <a href="import.php?action=template"
                    class="text-xs bg-mb-black border border-mb-subtle/20 text-mb-silver px-3 py-1.5 rounded-full hover:border-mb-accent/35 hover:text-white transition-colors">
                    Download Template Again
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg p-4 space-y-1">
                    <p class="text-xs text-mb-subtle uppercase">File</p>
                    <p class="text-sm text-white"><?= e((string) ($draft['filename'] ?? '')) ?></p>
                </div>
                <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg p-4 space-y-1">
                    <p class="text-xs text-mb-subtle uppercase">Rows Ready</p>
                    <p class="text-sm text-white"><?= count($rows) ?></p>
                </div>
                <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg p-4 space-y-1">
                    <p class="text-xs text-mb-subtle uppercase">Round Robin Staff</p>
                    <p class="text-sm text-white"><?= e(implode(', ', $staffNames)) ?></p>
                </div>
            </div>

            <form method="POST" id="leadImportRunForm" class="space-y-6">
                <input type="hidden" name="stage" value="import">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($mappingFields as $fieldKey => $meta): ?>
                        <div>
                            <label class="block text-sm text-mb-silver mb-2">
                                <?= e($meta['label']) ?>
                                <?php if ($meta['required']): ?>
                                    <span class="text-red-400">*</span>
                                <?php else: ?>
                                    <span class="text-mb-subtle text-xs">(optional)</span>
                                <?php endif; ?>
                            </label>
                            <select name="map[<?= e($fieldKey) ?>]" <?= $meta['required'] ? 'required' : '' ?>
                                class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors">
                                <option value="">-- Ignore field --</option>
                                <?php foreach ($headers as $idx => $headerLabel): ?>
                                    <?php $selected = ($suggestedMap[$fieldKey] ?? null) === (int) $idx; ?>
                                    <option value="<?= (int) $idx ?>" <?= $selected ? 'selected' : '' ?>>
                                        <?= e($headerLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Default Source</label>
                        <select name="default_source"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors">
                            <?php foreach ($leadSourcesMap as $slug => $label): ?>
                                <option value="<?= e((string) $slug) ?>" <?= ((string) $slug === $defaultSource) ? 'selected' : '' ?>>
                                    <?= e((string) $label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Default Inquiry Type</label>
                        <select name="default_inquiry_type"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors">
                            <option value="daily" selected>Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="wedding_rental">Wedding Rental</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm text-mb-silver mb-2">Default Initial Status</label>
                        <select name="default_status"
                            class="w-full bg-mb-black border border-mb-subtle/20 rounded-lg px-3 py-2.5 text-sm text-white focus:outline-none focus:border-mb-accent transition-colors">
                            <option value="new" selected>New</option>
                            <option value="contacted">Contacted</option>
                            <option value="future">Book Later</option>
                        </select>
                    </div>
                </div>

                <div class="bg-mb-black/40 border border-mb-subtle/20 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 border-b border-mb-subtle/20 flex items-center justify-between">
                        <h4 class="text-sm text-white font-medium">Preview (first 5 rows)</h4>
                        <span class="text-xs text-mb-subtle">Use mapping above to match these columns.</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs text-left">
                            <thead class="bg-mb-black/50 text-mb-silver">
                                <tr>
                                    <th class="px-3 py-2 border-b border-mb-subtle/20">Row</th>
                                    <?php foreach ($headers as $headerLabel): ?>
                                        <th class="px-3 py-2 border-b border-mb-subtle/20"><?= e($headerLabel) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($rows, 0, 5) as $previewRow): ?>
                                    <tr class="odd:bg-mb-black/20 even:bg-transparent">
                                        <td class="px-3 py-2 border-b border-mb-subtle/10 text-mb-subtle">
                                            <?= (int) ($previewRow['row_number'] ?? 0) ?>
                                        </td>
                                        <?php foreach (($previewRow['cells'] ?? []) as $cell): ?>
                                            <td class="px-3 py-2 border-b border-mb-subtle/10 text-mb-silver whitespace-nowrap">
                                                <?= e((string) $cell) ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="leadImportRunProgress"
                    class="hidden bg-mb-black/60 border border-mb-subtle/20 rounded-lg px-4 py-3">
                    <p class="text-xs text-mb-silver mb-2">Import in progress. Please wait...</p>
                    <div class="h-2 bg-mb-surface rounded-full overflow-hidden">
                        <div class="h-full bg-mb-accent animate-pulse w-3/4"></div>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <a href="import.php"
                        class="text-xs text-mb-subtle hover:text-white transition-colors">
                        Start over with another file
                    </a>
                    <button type="submit" id="leadImportRunSubmit"
                        class="bg-green-500/80 text-white px-5 py-2 rounded-full hover:bg-green-500 transition-colors text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                        Start Import
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
    const prepareForm = document.getElementById('leadImportPrepareForm');
    const prepareSubmit = document.getElementById('leadImportPrepareSubmit');
    const prepareProgress = document.getElementById('leadImportPrepareProgress');
    if (prepareForm) {
        prepareForm.addEventListener('submit', function (event) {
            const hasStaff = prepareForm.querySelector('input[name="staff_ids[]"]:checked');
            if (!hasStaff) {
                event.preventDefault();
                alert('Select at least one staff member for round robin assignment.');
                return;
            }
            if (prepareSubmit) {
                prepareSubmit.disabled = true;
                prepareSubmit.textContent = 'Preparing...';
            }
            if (prepareProgress) {
                prepareProgress.classList.remove('hidden');
            }
        });
    }

    const runForm = document.getElementById('leadImportRunForm');
    const runSubmit = document.getElementById('leadImportRunSubmit');
    const runProgress = document.getElementById('leadImportRunProgress');
    if (runForm) {
        runForm.addEventListener('submit', function () {
            if (runSubmit) {
                runSubmit.disabled = true;
                runSubmit.textContent = 'Importing...';
            }
            if (runProgress) {
                runProgress.classList.remove('hidden');
            }
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
