# P4 Phase C — INTERNAL ONLY (scope correction)
# Positive: mitm-lab.test -> 198.18.0.10
# Negative: internal host not in MITM dest (192.168.100.254:9999) — NO external DNS/IP
$ErrorActionPreference = "Continue"
$ts = Get-Date -Format o
Write-Output "$ts === PHASE_C_INTERNAL_ONLY ==="
Write-Output "DEST_POSITIVE=198.18.0.10"
Write-Output "SNI_POSITIVE=mitm-lab.test"
Write-Output "DEST_NEGATIVE_INTERNAL=192.168.100.254:9999"
Write-Output "EXTERNAL_DEST=none"

$marker = "# L7-P4-SOAK-mitm-lab.test"
$hosts = "C:\Windows\System32\drivers\etc\hosts"
$line = "198.18.0.10 mitm-lab.test $marker"
$cur = @()
if (Test-Path $hosts) {
  $cur = Get-Content $hosts -ErrorAction SilentlyContinue
}
$cur2 = @()
foreach ($l in $cur) {
  if ($l -notmatch "mitm-lab\.test" -and $l -notmatch "L7-P4-SOAK" -and $l -notmatch "L7-PHASE-C-19818") {
    $cur2 += $l
  }
}
$cur2 += $line
Set-Content -Path $hosts -Value $cur2 -Encoding ASCII
Write-Output "HOSTS_APPLIED $line"

$ca = "C:\Windows\Temp\l7-p4-mitm-ca.crt"
if (Test-Path $ca) {
  certutil -f -addstore Root $ca | Out-String | Write-Output
} else {
  Write-Output "CA_FILE_MISSING path=$ca"
}

# Positive in-scope (lab only)
try {
  $tcp = New-Object Net.Sockets.TcpClient("198.18.0.10", 443)
  $ssl = New-Object Net.Security.SslStream($tcp.GetStream(), $false, ({ $true }))
  $ssl.AuthenticateAsClient("mitm-lab.test")
  $cert = New-Object Security.Cryptography.X509Certificates.X509Certificate2($ssl.RemoteCertificate)
  Write-Output ("IN_SCOPE_PEER_SUBJECT=" + $cert.Subject)
  Write-Output ("IN_SCOPE_PEER_ISSUER=" + $cert.Issuer)
  $ssl.Close(); $tcp.Close()
} catch {
  Write-Output ("IN_SCOPE_TLS_ERR=" + $_.Exception.Message)
}

try {
  $req = [System.Net.HttpWebRequest]::Create("https://mitm-lab.test/")
  $req.Timeout = 8000
  $resp = $req.GetResponse()
  $sr = New-Object IO.StreamReader($resp.GetResponseStream())
  $body = $sr.ReadToEnd()
  $sr.Close(); $resp.Close()
  Write-Output ("IN_SCOPE_HTTP=" + [int]$resp.StatusCode)
  Write-Output ("IN_SCOPE_BODY_SNIP=" + ($body.Substring(0, [Math]::Min(240, $body.Length)) -replace "`r|`n", " "))
} catch {
  Write-Output ("IN_SCOPE_HTTP_ERR=" + $_.Exception.Message)
}

# Optional negative traffic: INTERNAL GUI only (not MITM dst 198.18.0.10).
# Out-of-scope absence is primarily proven by PF tables on appliance (not here).
try {
  $r2 = Invoke-WebRequest -Uri "https://192.168.100.254:9999/" -UseBasicParsing -TimeoutSec 8
  Write-Output ("NEG_INTERNAL_GUI_HTTP=" + [int]$r2.StatusCode)
} catch {
  Write-Output ("NEG_INTERNAL_GUI_NOTE=" + $_.Exception.Message)
}

Write-Output "HTTPS_PROBES=mitm-lab.test_only(+optional_internal_9999)"
Write-Output "EXTERNAL_PROBES=0"
Write-Output "PHASE_C_INTERNAL_DONE"
