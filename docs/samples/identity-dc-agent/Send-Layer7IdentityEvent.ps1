# Layer7 Identity — agente DC (referencia MVP 20.20)
#
# Envia um evento logon/logoff/heartbeat para o receiver HTTPS do appliance.
# NAO e o servico Windows completo (Event Log 4624) — e um cliente de laboratorio.
#
# Uso (PowerShell 5+):
#   $env:L7_DC_URL = "https://192.168.1.1:8743/v1/identity/events"
#   $env:L7_DC_TOKEN = "<token da GUI>"
#   .\Send-Layer7IdentityEvent.ps1 -User alice -Ip 10.0.0.50 -Event logon
#
# Confie no certificado auto-assinado so em lab (ou importe o .crt do appliance).

param(
    [Parameter(Mandatory = $true)][string]$User,
    [Parameter(Mandatory = $true)][string]$Ip,
    [ValidateSet("logon", "logoff", "heartbeat")][string]$Event = "logon",
    [string]$Url = $env:L7_DC_URL,
    [string]$Token = $env:L7_DC_TOKEN
)

if (-not $Url -or -not $Token) {
    Write-Error "Defina -Url / -Token ou L7_DC_URL / L7_DC_TOKEN"
    exit 1
}

$ts = [int][double]::Parse((Get-Date -UFormat %s))
$bodyObj = @{
    user      = $User
    ip        = $Ip
    event     = $Event
    timestamp = $ts
}
$bodyJson = ($bodyObj | ConvertTo-Json -Compress)
$bodyBytes = [System.Text.Encoding]::UTF8.GetBytes($bodyJson)

$sha = [System.Security.Cryptography.SHA256]::Create()
$bodyHash = ($sha.ComputeHash($bodyBytes) | ForEach-Object { $_.ToString("x2") }) -join ""
$canonical = "$ts`nPOST`n/v1/identity/events`n$bodyHash"

$hmac = New-Object System.Security.Cryptography.HMACSHA256
$hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($Token)
$sig = ($hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($canonical)) |
    ForEach-Object { $_.ToString("x2") }) -join ""

$headers = @{
    "X-Layer7-Token"     = $Token
    "X-Layer7-Timestamp" = "$ts"
    "X-Layer7-Signature" = $sig
    "Content-Type"       = "application/json"
}

# Lab: ignorar erro de certificado auto-assinado
if (-not ([System.Management.Automation.PSTypeName]"TrustAllCerts").Type) {
    Add-Type @"
using System.Net;
using System.Security.Cryptography.X509Certificates;
public class TrustAllCerts : ICertificatePolicy {
  public bool CheckValidationResult(ServicePoint s, X509Certificate c, WebRequest r, int p) { return true; }
}
"@
}
[System.Net.ServicePointManager]::CertificatePolicy = New-Object TrustAllCerts
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

try {
    $resp = Invoke-WebRequest -Uri $Url -Method POST -Headers $headers -Body $bodyBytes -UseBasicParsing
    Write-Host "OK HTTP $($resp.StatusCode)"
} catch {
    Write-Error $_
    exit 1
}
