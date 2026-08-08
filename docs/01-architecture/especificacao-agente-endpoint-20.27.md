# Especificação — Agente endpoint Identity (passo 20.27 / IM7)

**Estado:** `ACEITE` (especificação documental `2026-08-08` — passo **20.27**)  
**Tipo:** especificação de produto **antes** de MVP código (20.28) ou ADR de adiamento  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) IM7  
**ADR:** [`../03-adr/ADR-0027-identity-userid-multi-fonte.md`](../03-adr/ADR-0027-identity-userid-multi-fonte.md) §2 (fonte endpoint)  
**Canal reutilizado:** A1–A5 de [`desenho-canal-agente-dc-20.20.md`](desenho-canal-agente-dc-20.20.md)  
**Posicionamento PME:** [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md)  
**Arranque:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**Mapa:** M-21  
**Baseline lab:** `1.9.28` · enforce produção `1.9.8`  
**MITM:** DEFER 20.7a — irrelevante para este canal

> **Regra:** nenhum código de agente endpoint no produto até **GO** no passo
> **20.28** (MVP) **ou** ADR formal de adiamento/exclusão (GI8.1).

---

## 1. Objectivo

Especificar o **agente endpoint** que reporta *utilizador interactivo + IP(s)
locais* ao mapa daemon (`L7_ID_SRC_ENDPOINT`), para melhorar a exactidão
além do User-ID de **rede** (RADIUS / agente DC).

| Entrega 20.27 | Conteúdo |
|---------------|----------|
| OS alvo | Windows MVP; outros OS adiados |
| Canal | Reuso A1–A5 (HTTPS+HMAC; LAN; rate limit; secret revogável) |
| Auth | Token/secret por appliance (igual DC); mTLS fase 2 |
| Heartbeat | Intervalo, TTL, logoff implícito |
| Limites honestos | O que **não** resolve (TS/VDI → IM8; NAT simétrico extremo) |
| Decisão 20.28 | Critérios GO MVP vs ADIAR |

---

## 2. Problema que resolve (e o que não resolve)

### 2.1 Resolve

| Cenário | Com RADIUS/DC só | Com agente endpoint |
|---------|------------------|---------------------|
| Posto Windows com IP estável + logon AD | Bom (DC 4624) | Igual ou melhor (confirmação no host) |
| Posto com IP visto no firewall ≠ IP no evento AD (VPN/split) | Frágil | Reporta IP(s) **locais** + user do host |
| Utilizador muda de posto sem logoff limpo | last-writer / multi_user | Heartbeat no posto antigo expira; novo posto reporta |
| Laptop fora do domínio com user local | Fora de escopo DC | Opcional: reportar user local (flag) |

### 2.2 Não resolve (honestidade H*)

| Limite | Tratamento |
|--------|------------|
| Vários users no **mesmo** IP (TS/RDS/VDI) | **IM8** (user→porta); sem IM8 → `multi_user` / não-match `ad_*` |
| Exactidão tipo GlobalProtect / always-on VPN identity | **Fora de nicho** PME (posicionamento) |
| macOS / Linux desktop | Fora do MVP 20.28; especificação permite extensão futura |
| MITM / visibilidade HTTPS | DEFER; agente não inspecciona TLS |

Sem este agente, o produto continua válido como **User-ID de rede** (R-H).

---

## 3. Arquitectura canónica

```text
  Posto Windows (utilizador)              Appliance pfSense (Layer7)
  ┌──────────────────────────┐            ┌──────────────────────────┐
  │ Agente endpoint (serviço │  HTTPS +   │ layer7d                  │
  │ ou tarefa agendada)      │  HMAC      │  receiver partilhado*    │
  │ user interactivo + IPs   │ ─────────► │  (identity_dc thread ou  │
  │ heartbeat / logoff       │  LAN       │   identity_endpoint)     │
  │ secret local restrito    │            │       │                  │
  └──────────────────────────┘            │       ▼                  │
                                          │  identity_map upsert     │
                                          │  (src=ENDPOINT)          │
                                          └──────────────────────────┘
  * Preferência: mesmo porto 8743 + path distinto; ACL peers = IPs LAN
    dos postos (ou subnets) — ver §5.
```

| Peça | Responsabilidade |
|------|------------------|
| Agente endpoint | Descobre user interactivo + IPs unicast; heartbeat; logoff |
| Receiver | Valida TLS + HMAC + skew + rate limit; escreve mapa `ENDPOINT` |
| GUI | Toggle “agente endpoint”; secret; ACL subnets; texto H* |
| LDAP | Continua a expandir **grupos** — não é fonte de sessão |

**Precedência de fontes no mapa** (já ADR-0027): last-writer + audit;
`multi_user` se users concorrentes no mesmo IP.

---

## 4. Sistema operativo (MVP)

| OS | MVP 20.28 | Notas |
|----|-----------|-------|
| **Windows 10/11** (domínio ou workgroup) | **Sim** | Serviço SYSTEM **ou** Scheduled Task do utilizador |
| Windows Server como posto | Não prioritário | Usar agente DC se for DC; senão igual desktop |
| macOS / Linux | Não | Extensão futura; mesmo contrato JSON |
| Mobile / BYOD MDM | Não | Fora de escopo |

### 4.1 Privilegio mínimo

| Modo de instalação | Privilégio | Capacidade |
|--------------------|------------|------------|
| **A — Serviço SYSTEM** (recomendado PME) | LocalSystem | Lê sessão interactiva (WTS/Query); sobrevive logoff UI |
| **B — Task do utilizador** | User | Mais simples; para ao logoff; bom para lab |

**Proibido:** Domain Admin; credenciais AD no agente; telemetria para cloud.

### 4.2 Descoberta de user e IP

1. **User:** sessão interactiva activa (console/RDP no posto).  
   Normalizar como `layer7_idmap_normalize_user` (DOMAIN\user / UPN → local).
2. **IPs:** endereços unicast IPv4/IPv6 das interfaces UP, excluindo
   link-local e loopback; máximo alinhado a `L7_IDMAP_MAX_IPS_PER_USER`.
3. **Eventos:** `heartbeat` (periódico), `logon` (primeiro report / mudança
   de user), `logoff` (serviço detecta fim de sessão ou shutdown).

Contas máquina (`*$`) → **não reportar**.

---

## 5. Canal e autenticação (A1–A5)

Reutilizar o desenho DC com deltas:

| # | DC (20.20) | Endpoint (20.27) |
|---|------------|------------------|
| A1 | HTTPS + Bearer + HMAC-SHA256 | **Igual** (mesmo secret **ou** secret dedicado `identity-endpoint.secret`) |
| A2 | Porto **8743**; bind LAN; ACL IPs dos DCs | **Mesmo porto**; ACL = subnets LAN dos postos **ou** lista; lista vazia ⇒ endpoint receiver OFF |
| A3 | `POST /v1/identity/events` | Preferir `POST /v1/identity/endpoint/events` (path distinto; mesmo schema + `sourceHint`) |
| A4 | Body ≤ 4096; rate limits | **Igual**; orçamento separado por peer (postos >> DCs) |
| A5 | Secret revogável na GUI | **Igual**; rotação sem reinstalar MSI (actualizar ficheiro) |

**mTLS:** fase 2 (igual DC) — não bloqueia MVP.

### 5.1 Payload

```json
{
  "user": "joao.silva",
  "ips": ["10.0.0.55", "fe80::…"],
  "ip": "10.0.0.55",
  "event": "heartbeat",
  "timestamp": 1770123456,
  "domain": "EMPRESA",
  "hostname": "PC-JOAO-01",
  "agent": "layer7-endpoint/0.1"
}
```

| Campo | Obrigatório | Notas |
|-------|-------------|-------|
| `user` | sim | Canónico após normalize no receiver |
| `ip` **ou** `ips[]` | sim | `ip` = primário; `ips` opcional multi-homed |
| `event` | sim | `logon` \| `logoff` \| `heartbeat` |
| `timestamp` | sim | Unix; skew default 300 s |
| `domain` | não | Informativo |
| `hostname` | não | Diagnóstico GUI |
| `agent` | não | Versão do agente |

`source` no mapa = `L7_ID_SRC_ENDPOINT`.  
**Sem** passwords, hashes de password, SIDs completos, processos, ficheiros.

### 5.2 Heartbeat e TTL

| Parâmetro | Default MVP | Notas |
|-----------|-------------|-------|
| Intervalo heartbeat | **60 s** | Configurável 30–300 s |
| TTL no mapa para fonte ENDPOINT | **180 s** (3× heartbeat) | Após TTL sem refresh → expire (como outras fontes) |
| Logoff explícito | `event=logoff` | `remove_ip` / `remove_user` conforme payload |
| Missed heartbeats | 2–3 | Soft: só TTL; sem quarantine |

---

## 6. Empacotamento e operação PME (U*/P*)

| Artefacto | Forma |
|-----------|-------|
| Instalador | MSI ou script PowerShell (padrão do agente DC em `docs/samples/`) |
| Config | JSON local: URL appliance, token path, heartbeat |
| Deploy | GPO / Intune / script MSP — documentar 1 página no MANUAL |
| Diagnóstico | Event Log Application `Layer7Endpoint`; GUI Status: contadores `identity_endpoint_*` |

**Barra O3:** lab típico “instalar em 1 posto + ver sessão na GUI” ≤ 1 tarde
junto com Identity já configurado.

---

## 7. Segurança

1. Secret só em ficheiro 0600 / ACL SYSTEM; nunca no git.  
2. Listener nunca WAN por defeito.  
3. Rate limit + body cap (A4).  
4. Skew de relógio (A7).  
5. Identity OFF / sem entitlement → zero listener / zero threads (ADR-0028).  
6. Agente não abre portas inbound no posto (só cliente HTTPS outbound).

---

## 8. Relação com políticas e precedência

- Match `ad_users` / `ad_groups` já implementado (20.24); o agente só
  **alimenta** o mapa.
- Precedência: [`../core/precedence.md`](../core/precedence.md) — sem camada
  nova.
- Conflito ENDPOINT vs DC/RADIUS no mesmo IP: last-writer + audit; se users
  diferentes → `multi_user`.

---

## 9. Critérios GO / ADIAR (passo 20.28)

| Opção | Quando escolher | Entrega |
|-------|-----------------|---------|
| **GO MVP** | Cliente PME precisa de exactidão no posto; MSP pede GPO; lab DC+RADIUS insuficiente | MSI/PS1 + receiver path + GUI + testes; GI8.1 |
| **ADIAR (ADR)** | Valor Identity de rede (RADIUS+DC+LDAP+`ad_*`) basta para o nicho; custo de frota de agentes alto | ADR “IM7 diferido”; GUI H* “User-ID de rede; agente endpoint não incluído”; GI8.1 por exclusão honesta |

**Recomendação de produto (2026-08-08):** com IM3–IM6 **PASS** e posicionamento
PME Identity-first, o MVP Identity de **rede** já entrega O1. O agente
endpoint é **melhoria de exactidão**, não bloqueio do fecho IM9 parcial.
Preferir **ADIAR com ADR** no 20.28 **salvo** GO humano explícito para MSI.

---

## 10. Fora de escopo deste documento

- Implementação TS/VDI (IM8 / 20.29–20.30).  
- Agente macOS/Linux.  
- mTLS obrigatório.  
- Captive portal.  
- Código no `layer7d` / package (só após GO 20.28).

---

## 11. Checklist de aceitação da especificação (20.27)

| # | Item | Estado |
|---|------|--------|
| S1 | OS MVP definido (Windows) | PASS |
| S2 | Canal A1–A5 reutilizado com deltas | PASS |
| S3 | Auth + secret revogável | PASS |
| S4 | Heartbeat + TTL | PASS |
| S5 | Limites H* documentados | PASS |
| S6 | Critérios GO vs ADIAR para 20.28 | PASS |
| S7 | Mapa M-21 / plano / START-HERE actualizados | (neste bloco) |

---

## Histórico

| Data | Evento |
|------|--------|
| 2026-08-08 | Criação — passo **20.27** PASS documental |
