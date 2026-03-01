<#
  sync_to_server_update.ps1
  Copies ALL oRentPHP files → SERVER UPDATE, file by file.
  Only skips config/db.php (server has its own credentials).

  Usage:
    powershell -ExecutionPolicy Bypass -File ".\sync_to_server_update.ps1"
#>

$src = $PSScriptRoot
$dst = Join-Path $PSScriptRoot "SERVER UPDATE"

if (-not (Test-Path $dst)) {
    Write-Host "  ERROR: SERVER UPDATE folder not found at $dst" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "  Syncing oRentPHP → SERVER UPDATE..." -ForegroundColor Cyan

$copied  = 0
$skipped = 0

# Get every file inside $src, skip SERVER UPDATE folder and .git
$allFiles = Get-ChildItem -Path $src -File -Recurse | Where-Object {
    $rel = $_.FullName.Substring($src.Length + 1)
    # Never copy db.php
    if ($rel -eq 'config\db.php') { return $false }
    # Never recurse into SERVER UPDATE, .git, or uploads
    $topLevel = $rel.Split('\')[0]
    $topLevel -notin @('SERVER UPDATE', '.git', 'uploads')
}

foreach ($file in $allFiles) {
    $rel     = $file.FullName.Substring($src.Length + 1)
    $dstPath = Join-Path $dst $rel

    # Compare content — copy if different or missing
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
Write-Host ""
