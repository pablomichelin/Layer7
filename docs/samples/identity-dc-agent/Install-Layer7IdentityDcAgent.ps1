# Instala o agente Layer7 Identity DC como Scheduled Task (arranque automatico).
# Executar como Administrador no Domain Controller.
#
#   .\Install-Layer7IdentityDcAgent.ps1 -ConfigPath C:\ProgramData\Layer7\identity-dc-agent\config.json
#
# Conta: LocalSystem (leu Event Log se Audit Logon estiver activo; preferivel
# adicionar a conta do servico a "Event Log Readers" se usar conta dedicada).

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$ConfigPath,

    [string]$AgentScript = "",

    [string]$TaskName = "Layer7-IdentityDcAgent"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
    ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Execute como Administrador."
}

if (-not $AgentScript) {
    $AgentScript = Join-Path $PSScriptRoot "Layer7IdentityDcAgent.ps1"
}
if (-not (Test-Path -LiteralPath $AgentScript)) {
    throw "Agente nao encontrado: $AgentScript"
}
if (-not (Test-Path -LiteralPath $ConfigPath)) {
    throw "Config nao encontrada: $ConfigPath"
}

$cfgDir = Split-Path -Parent $ConfigPath
if ($cfgDir -and -not (Test-Path -LiteralPath $cfgDir)) {
    New-Item -ItemType Directory -Path $cfgDir -Force | Out-Null
}

# ACL restritiva no config (SYSTEM + Administrators)
icacls $ConfigPath /inheritance:r | Out-Null
icacls $ConfigPath /grant:r "SYSTEM:(R)" "Administrators:(F)" | Out-Null

$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument (
    "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden " +
    "-File `"$AgentScript`" -ConfigPath `"$ConfigPath`""
)
$trigger = New-ScheduledTaskTrigger -AtStartup
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger `
    -Settings $settings -Principal $principal -Force | Out-Null

Start-ScheduledTask -TaskName $TaskName
Write-Host "OK: tarefa '$TaskName' registada e iniciada."
Write-Host "Config: $ConfigPath"
Write-Host "Script: $AgentScript"
Write-Host "Nota: confirme Audit Logon Success no DC e ACL do peer na GUI Identity."
