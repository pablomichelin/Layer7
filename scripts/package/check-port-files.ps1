# Garante que cada entrada em pkg-plist (exceto binário gerado no build) existe em files/.
# Correr a partir da raiz do clone: .\scripts\package\check-port-files.ps1
# Equivalente a: sh scripts/package/check-port-files.sh

$ErrorActionPreference = "Stop"
$Root = (Get-Item $PSScriptRoot).Parent.Parent.FullName
$Port = Join-Path $Root "package\pfSense-pkg-layer7"
$Plist = Join-Path $Port "pkg-plist"

if (-not (Test-Path $Plist)) {
    Write-Error "check-port-files: pkg-plist em falta: $Plist"
}

$err = 0
Get-Content $Plist | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq "" -or $line.StartsWith("#")) { return }
    if ($line.StartsWith("@")) { return }
    if ($line -eq "sbin/layer7d" -or $line -eq "/usr/local/sbin/layer7d") { return }

    $rel = $null
    if ($line.StartsWith("/etc/inc/")) {
        $rel = Join-Path $Port ("files" + $line)
    } elseif ($line.StartsWith("/usr/local/")) {
        $rel = Join-Path $Port ("files" + $line)
    } elseif ($line.StartsWith("etc/inc/")) {
        $rel = Join-Path $Port "files\$line"
    } elseif ($line -match "^%%DATADIR%%/(.+)$") {
        $rel = Join-Path $Port "files\usr\local\share\pfSense-pkg-layer7\$($Matches[1])"
    } else {
        $rel = Join-Path $Port "files\usr\local\$line"
    }

    $rel = $rel -replace "/", [System.IO.Path]::DirectorySeparatorChar
    if (-not (Test-Path $rel)) {
        Write-Host "check-port-files: plist='$line' -> não existe: $rel" -ForegroundColor Red
        $script:err = 1
    }
}

if ($script:err -ne 0) {
    Write-Host "check-port-files: FALHOU" -ForegroundColor Red
    exit 1
}
Write-Host "check-port-files: OK" -ForegroundColor Green
