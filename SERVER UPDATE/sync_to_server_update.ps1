<#
  sync_to_server_update.ps1
  Syncs oRentPHP files → SERVER UPDATE, then optionally pushes to GitHub
  so Hostinger auto-deploys via its Git integration.

  Usage:
    Sync only:
      powershell -ExecutionPolicy Bypass -File ".\sync_to_server_update.ps1"

    Sync + push to GitHub (triggers Hostinger auto-deploy):
      powershell -ExecutionPolicy Bypass -File ".\sync_to_server_update.ps1" -Deploy

    With custom commit message:
      powershell -ExecutionPolicy Bypass -File ".\sync_to_server_update.ps1" -Deploy -Message "add feature"
#>

param(
    [switch]$Deploy,
    [string]$Message = ""
)

$src = $PSScriptRoot
$dst = Join-Path $PSScriptRoot "SERVER UPDATE"

if (-not (Test-Path $dst)) {
    Write-Host "  ERROR: SERVER UPDATE folder not found." -ForegroundColor Red
    exit 1
}

# ── Phase 1: Sync files ───────────────────────────────────────────────────
Write-Host ""
Write-Host "  Syncing oRentPHP -> SERVER UPDATE..." -ForegroundColor Cyan

$copied  = 0
$skipped = 0

$allFiles = Get-ChildItem -Path $src -File -Recurse | Where-Object {
    $rel      = $_.FullName.Substring($src.Length + 1)
    $topLevel = $rel.Split('\')[0]

    # Skip these top-level folders
    if ($topLevel -in @('SERVER UPDATE', '.git', 'uploads', 'logs', '.gemini', '.agents', '.kiro')) { return $false }

    # Skip dev-only / sensitive files
    $skipFiles = @(
        'config\db.php',
        'attendance_migrate.php',
        'auth_migrate.php',
        'UPDATE_SESSION_RULES.md',
        'wipe_and_reset.sql',
        'wipe_sql_diff.php',
        'tmp_schema_init.php',
        'schema_diff.php',
        'reset_admin.php',
        'PRODUCTION_DB_STEPS.md',
        'precise_audit.php',
        'PAGINATION_IMPLEMENTATION_PLAN.md',
        'generate_wipe_sql.php',
        'dummy_data.sql',
        'database.sql',
        'compare_schema_vs_wipe.php',
        'audit_columns.php',
        'ACCOUNT_IMPLEMENTATION.md',
        'UPDATE_SESSION_RULES.md',
        'PRODUCTION_DB_STEPS.md',
        'Advance Payment Int.md'
    )
    if ($rel -in $skipFiles) { return $false }

    # Skip all .md documentation files (internal dev docs, not needed on server)
    if ($_.Extension -eq '.md') { return $false }

    # Skip log files
    if ($_.Extension -eq '.log') { return $false }

    # Skip any temporary/scratch PHP files (tmp_*.php)
    if ($_.Name -like 'tmp_*.php') { return $false }
    if ($_.Name -like 'check_*.php') { return $false }
    if ($_.Name -like 'debug_*.php') { return $false }

    # Skip archive files
    if ($rel -like '*.rar')  { return $false }
    if ($rel -like '*.zip')  { return $false }

    return $true
}

foreach ($file in $allFiles) {
    $rel     = $file.FullName.Substring($src.Length + 1)
    $dstPath = Join-Path $dst $rel

    $needsCopy = $true
    if (Test-Path $dstPath) {
        $h1 = (Get-FileHash $file.FullName -Algorithm MD5).Hash
        $h2 = (Get-FileHash $dstPath       -Algorithm MD5).Hash
        if ($h1 -eq $h2) { $needsCopy = $false }
    }

    if ($needsCopy) {
        $dir = Split-Path $dstPath -Parent
        if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
        Copy-Item $file.FullName $dstPath -Force
        Write-Host "  SYNC  $rel" -ForegroundColor Green
        $copied++
    } else {
        $skipped++
    }
}

Write-Host ""
Write-Host "  Done: $copied synced, $skipped already up to date." -ForegroundColor Cyan

# ── Phase 2: Git push → triggers Hostinger auto-deploy ───────────────────
if (-not $Deploy) {
    Write-Host "  Tip: run with -Deploy to push to GitHub and auto-deploy to Hostinger." -ForegroundColor DarkGray
    Write-Host ""
    exit 0
}

Write-Host ""
Write-Host "  Pushing to GitHub (Hostinger will auto-deploy)..." -ForegroundColor Yellow

Set-Location $dst

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm"
$commitMsg = if ($Message -ne "") { $Message } else { "deploy: $timestamp" }

& git add -A
& git commit -m "$commitMsg"
& git push origin main

Write-Host ""
Write-Host "  Pushed! Hostinger is now deploying automatically." -ForegroundColor Green
Write-Host "  Check: https://orentin.abrarfuturetech.com" -ForegroundColor Cyan
Write-Host ""
