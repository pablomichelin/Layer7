# Layer7 Identity — agente DC (IM5 / 20.20)

Agente leve para **Domain Controller** Windows: lê Security Event Log
(4624 / 4634 / 4647), envia logon/logoff ao receiver HTTPS do appliance
(`identity_dc`, porto **8743**, token + HMAC-SHA256).

| Peça | Ficheiro |
|------|----------|
| Serviço (loop) | `Layer7IdentityDcAgent.ps1` |
| Config exemplo | `config.example.json` |
| Instalar tarefa | `Install-Layer7IdentityDcAgent.ps1` |
| Remover tarefa | `Uninstall-Layer7IdentityDcAgent.ps1` |
| Cliente lab (1 evento) | `Send-Layer7IdentityEvent.ps1` |

Desenho canónico: [`../../01-architecture/desenho-canal-agente-dc-20.20.md`](../../01-architecture/desenho-canal-agente-dc-20.20.md).

## Pré-requisitos (PME)

1. No appliance: Identity ON + **Receiver agente DC** ON, token gerado, **ACL**
   com o IP do DC, bind num IP LAN (nunca `0.0.0.0`).
2. Firewall LAN: DC → appliance:**8743**/TCP.
3. No DC: *Audit Logon* Success activo; conta do agente com leitura do
   Security log (**Event Log Readers** — **não** Domain Admin).
4. Relógios DC e appliance coerentes (skew default 300 s).
5. PowerShell 5.1+ no DC.

## Instalação rápida

```powershell
# 1) Copiar pasta identity-dc-agent para o DC
$dir = "C:\ProgramData\Layer7\identity-dc-agent"
New-Item -ItemType Directory -Path $dir -Force | Out-Null
Copy-Item -Recurse .\* $dir -Force

# 2) Configurar token (da GUI Identity — mostrado uma vez)
Copy-Item "$dir\config.example.json" "$dir\config.json"
notepad "$dir\config.json"
# url  = https://<IP-LAN-appliance>:8743/v1/identity/events
# token = <colar>
# trust_all_certs = true só em lab com cert auto-assinado

# 3) Teste manual de um ciclo
powershell -NoProfile -ExecutionPolicy Bypass `
  -File "$dir\Layer7IdentityDcAgent.ps1" -ConfigPath "$dir\config.json" -Once

# 4) Instalar arranque automatico (Administrador)
powershell -NoProfile -ExecutionPolicy Bypass `
  -File "$dir\Install-Layer7IdentityDcAgent.ps1" -ConfigPath "$dir\config.json"
```

Cliente lab (sem Event Log):

```powershell
$env:L7_DC_URL = "https://192.168.1.1:8743/v1/identity/events"
$env:L7_DC_TOKEN = "<token>"
.\Send-Layer7IdentityEvent.ps1 -User alice -Ip 10.0.0.50 -Event logon
```

## Comportamento MVP

| Evento Windows | Envio |
|----------------|--------|
| 4624 + LogonType ∈ {2,3,10,11} + IP útil | `event=logon` |
| 4634 / 4647 | `event=logoff` (IP do cache local do agente) |
| Contas `*$` (máquina) | Ignoradas (default) |
| IP `-`, `::1`, `127.0.0.1`, link-local | Ignoradas (User-ID de rede honesto) |

O agente **não** faz LDAP/grupos — isso continua no worker LDAP do appliance.

## Limites honestos (H*)

- User-ID de **rede**: IP no AD pode ≠ IP visto no firewall (NAT/Wi‑Fi).
- Sem agente endpoint (IM7): não é exactidão tipo GlobalProtect.
- Cert auto-assinado: `trust_all_certs=true` só lab; em produção importe o
  `.crt` do appliance e ponha `false`.

## Remoção / rollback

```powershell
.\Uninstall-Layer7IdentityDcAgent.ps1
# No appliance: desligar Receiver agente DC ou revogar token
```

## Gate lab (GI6.5 mínimo)

1. Logon interactivo/RDP no domínio → mapa no appliance com `src=dc_agent`.
2. Logoff → entrada removida (ou ausente após TTL).
3. Token errado → HTTP 401/rejeição; sem escrita no mapa.
4. Identity/DC OFF → zero listener 8743.
