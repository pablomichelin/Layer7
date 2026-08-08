# Layer7 Identity — agente DC MVP (IM5 / 20.20)
#
# Le Security Event Log (4624 logon / 4634|4647 logoff), filtra tipos
# Interactive/Network/RemoteInteractive/CachedInteractive, e faz POST
# HTTPS + token + HMAC-SHA256 para o receiver identity_dc no appliance.
#
# Requisitos: PowerShell 5.1+, conta com Event Log Readers (NAO Domain Admin).
# Desenho: docs/01-architecture/desenho-canal-agente-dc-20.20.md (A1–A7)
#
# Uso:
#   .\Layer7IdentityDcAgent.ps1 -ConfigPath .\config.json
#   .\Layer7IdentityDcAgent.ps1 -ConfigPath .\config.json -Once   # um ciclo (lab)
#
# Instalacao como tarefa: .\Install-Layer7IdentityDcAgent.ps1

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ConfigPath,

    [switch]$Once,

    [int]$MaxCycles = 0
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Write-AgentLog {
    param(
        [string]$Path,
        [string]$Level,
        [string]$Message
    )
    $line = "{0:yyyy-MM-ddTHH:mm:ssZ} [{1}] {2}" -f (Get-Date).ToUniversalTime(), $Level, $Message
    if ($Path) {
        $dir = Split-Path -Parent $Path
        if ($dir -and -not (Test-Path -LiteralPath $dir)) {
            New-Item -ItemType Directory -Path $dir -Force | Out-Null
        }
        Add-Content -LiteralPath $Path -Value $line -Encoding UTF8
    }
    Write-Host $line
}

function Get-UnixTimestamp {
    return [int][double]::Parse((Get-Date -UFormat %s))
}

function Get-Sha256Hex {
    param([byte[]]$Bytes)
    $sha = [System.Security.Cryptography.SHA256]::Create()
    try {
        return (($sha.ComputeHash($Bytes) | ForEach-Object { $_.ToString("x2") }) -join "")
    } finally {
        $sha.Dispose()
    }
}

function Get-HmacSha256Hex {
    param(
        [string]$Secret,
        [string]$Canonical
    )
    $hmac = New-Object System.Security.Cryptography.HMACSHA256
    try {
        $hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($Secret)
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($Canonical)
        return (($hmac.ComputeHash($bytes) | ForEach-Object { $_.ToString("x2") }) -join "")
    } finally {
        $hmac.Dispose()
    }
}

function Enable-LabTrustAllCerts {
    if (-not ([System.Management.Automation.PSTypeName]"Layer7TrustAllCerts").Type) {
        Add-Type @"
using System.Net;
using System.Security.Cryptography.X509Certificates;
public class Layer7TrustAllCerts : ICertificatePolicy {
  public bool CheckValidationResult(ServicePoint s, X509Certificate c, WebRequest r, int p) { return true; }
}
"@
    }
    [System.Net.ServicePointManager]::CertificatePolicy = New-Object Layer7TrustAllCerts
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
}

function Send-Layer7IdentityEvent {
    param(
        [hashtable]$Config,
        [string]$User,
        [string]$Ip,
        [ValidateSet("logon", "logoff", "heartbeat")][string]$Event,
        [string]$Domain
    )

    $ts = Get-UnixTimestamp
    $bodyObj = [ordered]@{
        user      = $User
        ip        = $Ip
        event     = $Event
        timestamp = $ts
    }
    if ($Domain) {
        $bodyObj["domain"] = $Domain
    }
    $bodyJson = ($bodyObj | ConvertTo-Json -Compress)
    $bodyBytes = [System.Text.Encoding]::UTF8.GetBytes($bodyJson)
    if ($bodyBytes.Length -gt 4096) {
        throw "Body > 4096 bytes (limite A4)"
    }

    $uri = [Uri]$Config.url
    $path = $uri.AbsolutePath
    if ([string]::IsNullOrWhiteSpace($path)) {
        $path = "/v1/identity/events"
    }

    $bodyHash = Get-Sha256Hex -Bytes $bodyBytes
    $canonical = "$ts`nPOST`n$path`n$bodyHash"
    $sig = Get-HmacSha256Hex -Secret $Config.token -Canonical $canonical

    $headers = @{
        "X-Layer7-Token"     = $Config.token
        "X-Layer7-Timestamp" = "$ts"
        "X-Layer7-Signature" = $sig
        "Content-Type"       = "application/json"
    }

    $resp = Invoke-WebRequest -Uri $Config.url -Method POST -Headers $headers `
        -Body $bodyBytes -UseBasicParsing
    return [int]$resp.StatusCode
}

function Read-AgentConfig {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Config nao encontrada: $Path"
    }
    $raw = Get-Content -LiteralPath $Path -Raw -Encoding UTF8
    $j = $raw | ConvertFrom-Json
    if (-not $j.url -or -not $j.token) {
        throw "config.json exige 'url' e 'token'"
    }
    $logonTypes = @(2, 3, 10, 11)
    if ($j.logon_types) {
        $logonTypes = @($j.logon_types | ForEach-Object { [int]$_ })
    }
    $skipMachine = $true
    if ($null -ne $j.skip_machine_accounts) {
        $skipMachine = [bool]$j.skip_machine_accounts
    }
    $poll = 2
    if ($j.poll_seconds) {
        $poll = [int]$j.poll_seconds
        if ($poll -lt 1) { $poll = 1 }
    }
    $trust = $true
    if ($null -ne $j.trust_all_certs) {
        $trust = [bool]$j.trust_all_certs
    }
    $statePath = "C:\ProgramData\Layer7\identity-dc-agent\state.json"
    if ($j.state_path) { $statePath = [string]$j.state_path }
    $logPath = "C:\ProgramData\Layer7\identity-dc-agent\agent.log"
    if ($j.log_path) { $logPath = [string]$j.log_path }
    $domain = ""
    if ($j.domain) { $domain = [string]$j.domain }

    return @{
        url                   = [string]$j.url
        token                 = [string]$j.token
        domain                = $domain
        trust_all_certs       = $trust
        poll_seconds          = $poll
        logon_types           = $logonTypes
        skip_machine_accounts = $skipMachine
        state_path            = $statePath
        log_path              = $logPath
    }
}

function Read-AgentState {
    param([string]$Path)
    $state = @{
        last_record_id = 0
        sessions       = @{}
    }
    if (Test-Path -LiteralPath $Path) {
        try {
            $j = Get-Content -LiteralPath $Path -Raw -Encoding UTF8 | ConvertFrom-Json
            if ($j.last_record_id) {
                $state.last_record_id = [long]$j.last_record_id
            }
            if ($j.sessions) {
                $map = @{}
                foreach ($p in $j.sessions.PSObject.Properties) {
                    $map[$p.Name] = [string]$p.Value
                }
                $state.sessions = $map
            }
        } catch {
            # estado corrompido → recomeça
        }
    }
    return $state
}

function Write-AgentState {
    param(
        [string]$Path,
        [hashtable]$State
    )
    $dir = Split-Path -Parent $Path
    if ($dir -and -not (Test-Path -LiteralPath $dir)) {
        New-Item -ItemType Directory -Path $dir -Force | Out-Null
    }
    $obj = [ordered]@{
        last_record_id = [long]$State.last_record_id
        sessions       = $State.sessions
    }
    ($obj | ConvertTo-Json -Depth 4) | Set-Content -LiteralPath $Path -Encoding UTF8
}

function Test-IsUsableIp {
    param([string]$Ip)
    if ([string]::IsNullOrWhiteSpace($Ip)) { return $false }
    if ($Ip -eq "-" -or $Ip -eq "::" -or $Ip -eq "::1") { return $false }
    if ($Ip -eq "127.0.0.1") { return $false }
    try {
        $addr = [System.Net.IPAddress]::Parse($Ip)
        if ($addr.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork) {
            $b = $addr.GetAddressBytes()
            if ($b[0] -eq 169 -and $b[1] -eq 254) { return $false }
        }
        return $true
    } catch {
        return $false
    }
}

function Get-EventField {
    param(
        $Event,
        [string]$Name
    )
    try {
        $xml = [xml]$Event.ToXml()
        $node = $xml.Event.EventData.Data | Where-Object { $_.Name -eq $Name } | Select-Object -First 1
        if ($node) {
            return [string]$node.'#text'
        }
    } catch {
        return $null
    }
    return $null
}

function Invoke-AgentPoll {
    param(
        [hashtable]$Config,
        [hashtable]$State
    )

    $filter = @{
        LogName = "Security"
        Id      = @(4624, 4634, 4647)
    }
    # Janela curta: eventos desde o ultimo ciclo (poll*3 com minimo 10s)
    $lookback = [Math]::Max(10, $Config.poll_seconds * 3)
    $start = (Get-Date).AddSeconds(-1 * $lookback)
    $filter["StartTime"] = $start

    $events = @()
    try {
        $events = @(Get-WinEvent -FilterHashtable $filter -ErrorAction SilentlyContinue |
            Sort-Object RecordId)
    } catch {
        Write-AgentLog -Path $Config.log_path -Level "WARN" -Message ("Get-WinEvent: " + $_.Exception.Message)
        return
    }

    # Arranque a frio: so bookmark — nao reenviar historico (PME / rate limit).
    if ([long]$State.last_record_id -le 0 -and $events.Count -gt 0) {
        $State.last_record_id = [long]($events[-1].RecordId)
        Write-AgentState -Path $Config.state_path -State $State
        Write-AgentLog -Path $Config.log_path -Level "INFO" `
            -Message ("cold start bookmark RecordId=$($State.last_record_id)")
        return
    }

    foreach ($ev in $events) {
        if ($ev.RecordId -le $State.last_record_id) {
            continue
        }
        $State.last_record_id = [long]$ev.RecordId

        $id = [int]$ev.Id
        if ($id -eq 4624) {
            $user = Get-EventField -Event $ev -Name "TargetUserName"
            $domain = Get-EventField -Event $ev -Name "TargetDomainName"
            $ip = Get-EventField -Event $ev -Name "IpAddress"
            $logonTypeRaw = Get-EventField -Event $ev -Name "LogonType"
            $logonType = 0
            if ($logonTypeRaw) { [void][int]::TryParse($logonTypeRaw, [ref]$logonType) }

            if ([string]::IsNullOrWhiteSpace($user)) { continue }
            if ($Config.skip_machine_accounts -and $user.EndsWith("$")) { continue }
            if ($Config.logon_types -notcontains $logonType) { continue }
            if (-not (Test-IsUsableIp -Ip $ip)) {
                Write-AgentLog -Path $Config.log_path -Level "INFO" `
                    -Message ("skip logon sem IP util: user=$user type=$logonType ip=$ip")
                continue
            }

            $dom = $Config.domain
            if (-not $dom -and $domain) { $dom = $domain }

            try {
                $code = Send-Layer7IdentityEvent -Config $Config -User $user -Ip $ip `
                    -Event "logon" -Domain $dom
                $State.sessions[$user] = $ip
                Write-AgentLog -Path $Config.log_path -Level "INFO" `
                    -Message ("logon OK HTTP $code user=$user ip=$ip type=$logonType")
            } catch {
                Write-AgentLog -Path $Config.log_path -Level "ERROR" `
                    -Message ("logon FAIL user=$user ip=$ip: " + $_.Exception.Message)
            }
        } elseif ($id -eq 4634 -or $id -eq 4647) {
            $user = Get-EventField -Event $ev -Name "TargetUserName"
            if ([string]::IsNullOrWhiteSpace($user)) { continue }
            if ($Config.skip_machine_accounts -and $user.EndsWith("$")) { continue }

            $ip = $null
            if ($State.sessions.ContainsKey($user)) {
                $ip = $State.sessions[$user]
            }
            if (-not (Test-IsUsableIp -Ip $ip)) {
                Write-AgentLog -Path $Config.log_path -Level "INFO" `
                    -Message ("skip logoff sem IP em cache: user=$user")
                continue
            }

            $dom = $Config.domain
            try {
                $code = Send-Layer7IdentityEvent -Config $Config -User $user -Ip $ip `
                    -Event "logoff" -Domain $dom
                $State.sessions.Remove($user)
                Write-AgentLog -Path $Config.log_path -Level "INFO" `
                    -Message ("logoff OK HTTP $code user=$user ip=$ip")
            } catch {
                Write-AgentLog -Path $Config.log_path -Level "ERROR" `
                    -Message ("logoff FAIL user=$user ip=$ip: " + $_.Exception.Message)
            }
        }
    }

    Write-AgentState -Path $Config.state_path -State $State
}

# ---- main ----

$cfg = Read-AgentConfig -Path $ConfigPath
if ($cfg.trust_all_certs) {
    Enable-LabTrustAllCerts
}

Write-AgentLog -Path $cfg.log_path -Level "INFO" `
    -Message ("agente DC arranque url=$($cfg.url) poll=$($cfg.poll_seconds)s once=$Once")

$state = Read-AgentState -Path $cfg.state_path
$cycle = 0

while ($true) {
    $cycle++
    Invoke-AgentPoll -Config $cfg -State $state
    if ($Once) { break }
    if ($MaxCycles -gt 0 -and $cycle -ge $MaxCycles) { break }
    Start-Sleep -Seconds $cfg.poll_seconds
}

Write-AgentLog -Path $cfg.log_path -Level "INFO" -Message "agente DC fim"
