# Remove a Scheduled Task do agente Layer7 Identity DC.
# Executar como Administrador.
#
#   .\Uninstall-Layer7IdentityDcAgent.ps1

[CmdletBinding()]
param(
    [string]$TaskName = "Layer7-IdentityDcAgent"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
    ).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Execute como Administrador."
}

$existing = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if (-not $existing) {
    Write-Host "Tarefa '$TaskName' nao existe."
    exit 0
}

Stop-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
Write-Host "OK: tarefa '$TaskName' removida."
