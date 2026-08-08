# ADR-0027 — Identity User-ID multi-fonte (sem captive portal)

**Estado:** Aceito (rev. `d` — canal agente DC A1–A7 fechados no desenho 20.20)  
**Data:** 2026-08-05  
**Aceite:** `2026-08-05` — passo **20.2** / GI0; desenho canal **20.20** `2026-08-07`  
**Decisores:** Operador (GO aceitação no passo 20.2); detalhe A1–A7 no 20.20  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**Desenho canal DC:** [`../01-architecture/desenho-canal-agente-dc-20.20.md`](../01-architecture/desenho-canal-agente-dc-20.20.md)  
**Relação:** evolui além de ADR-0011/0012 (dispositivo MAC→IP)

---

## Contexto

- Layer7 aplica políticas por IP/CIDR, MAC→IP e destino (app/host/SNI).
- ADR-0011 excluiu LDAP/802.1X/captive naquela fase.
- Clientes AD precisam de políticas por **utilizador/grupo**.
- NGFWs usam mapa dinâmico user→IP (+ agente/TS quando necessário) — não “user mágico” no L3.
- Captive portal do pfSense: **fora de escopo**.
- O padrão ADR-0012 (`device_ips` via PHP no resync) é **demasiado lento** para User-ID.

---

## Decisão (proposta)

### 1. Motor Identity (User-ID)

Módulo gated por entitlement `identity`:

- **SSOT de sessão no daemon:**  
  `user` → `{ ips[] (v4/v6), groups[], source, seen_at, expires_at }`.
- Refresh contínuo; TTL agressivo; limpeza stale; diagnóstico GUI.
- Pacote **configura** fontes/LDAP; **não** é SSOT do mapa (diferente de `device_ips`).
- LDAP/LDAPS **expande grupos** e valida contas — **não** substitui fonte de sessão.
- IPv6 privacy: **N endereços** por utilizador.

### 2. Fontes de sessão (canónicas MVP)

| Fonte | Arquitectura canónica | Prioridade |
|-------|------------------------|------------|
| RADIUS accounting | Layer7 = **accounting receiver** (UDP, secret, ACL NAS); User-Name + Framed-IP | IM5 MVP |
| Eventos de logon AD | **Agente leve no Domain Controller** → push autenticado ao appliance | IM5 MVP |
| Agente endpoint | Heartbeat user(+IP) | IM7 — especificação **20.27** [`especificacao-agente-endpoint-20.27.md`](../01-architecture/especificacao-agente-endpoint-20.27.md); MVP ou ADIAR em **20.28** |
| TS/VDI agent | user→portas | IM8 posterior |
| Captive Layer7 | — | **EXCLUÍDO** |
| WinRM/WMI outbound do FreeBSD para DC | — | **Não canónico** |

MVP fecho parcial: LDAP + **≥1** fonte {RADIUS, agente DC}. Ambas no plano completo.

### 2.1 Canal do agente DC (rev. `d` — desenho 20.20 fechado)

O receiver no appliance é uma **superfície de ataque nova**. Requisitos
mínimos A1–A7; **detalhe fino aceite** em
[`../01-architecture/desenho-canal-agente-dc-20.20.md`](../01-architecture/desenho-canal-agente-dc-20.20.md)
(`2026-08-07`). **Sem código** até esse desenho (cumprido).

| # | Requisito | Decisão 20.20 (MVP) |
|---|-----------|---------------------|
| A1 | Transporte **TLS** com autenticação mútua: mTLS (preferido) **ou** token por appliance + HMAC do corpo — nunca push em claro | **MVP:** HTTPS + token por appliance + **HMAC-SHA256** do corpo. **mTLS = fase 2** (PME: secret partilhado alinhado a RADIUS) |
| A2 | Listener **bind só nas interfaces LAN configuradas** (nunca WAN por defeito); porta dedicada documentada | Porto **8743/tcp**; bind só IPs LAN; ACL de IPs dos DCs obrigatória (vazia ⇒ não arranca) |
| A3 | Payload mínimo: `user`, `ip`, `event (logon/logoff)`, `timestamp` — sem passwords, sem SIDs desnecessários | JSON `POST /v1/identity/events`; `event`: logon\|logoff\|heartbeat; `domain` opcional |
| A4 | Rate limit + tamanho máximo de payload no receiver (protecção DoS) | Body ≤ 4096 B; 50 evt/s/peer; 200 evt/s global; HTTP 429 |
| A5 | Credencial do agente revogável no appliance sem reinstalar o agente no DC | Secret 0600 `identity-dc.secret`; GUI gera/revoga; agente só actualiza ficheiro local |
| A6 | Agente no DC corre com **privilégio mínimo** (leitura do event log; nunca domain admin) | Event Log Readers; eventos 4624/4634 (ou 4647); nunca Domain Admin |
| A7 | Relógio: eventos com skew > tolerância configurável são descartados com log | Default **300 s** (60–900); log `identity_dc_skew` |

O mesmo padrão A1–A5 aplica-se ao agente endpoint (IM7) — detalhe em
[`especificacao-agente-endpoint-20.27.md`](../01-architecture/especificacao-agente-endpoint-20.27.md).

### 2.2 Concorrência no daemon

Threads/IO das fontes seguem **ADR-0028** (nenhuma chamada bloqueante no
loop de captura; mapa partilhado com rwlock). Pré-requisito de IM3–IM5.

### 3. Políticas

- Alvos `ad_users` / `ad_groups`.
- Match/enforce consulta o **mapa daemon** (IPs actuais).
- Precedência: esboço no plano §3.1; formalizar em `docs/core/precedence.md` (20.25).

### 4. Fail-mode (rev. `b`)

| Situação | Comportamento default |
|----------|------------------------|
| LDAP/fonte em baixo | Usar **cache** até TTL; depois políticas `ad_*` → **não-match** (módulo Identity não aplica block/allow por AD) |
| Produto base IP/MAC/CIDR | **Continua** a funcionar (isolamento) |
| Fail-closed “bloquear toda a LAN” se Identity falhar | **Proibido** por defeito |
| Operador pode endurecer | Só com toggle explícito futuro + docs (fora do MVP se não houver GO) |

### 4.1 Conflito multi-user no mesmo IP (rev. `c` — regra de segurança)

`last-writer + audit` resolve o caso normal (troca de posto, novo logon).
Mas quando o **mesmo IP tem utilizadores concorrentes** (NAT interno,
proxy, TS sem agente), aplicar a política do "último" pode **bloquear ou
libertar o utilizador errado** — isso é incidente de segurança, não só
imprecisão. Regra canónica:

| Situação detectada | Comportamento |
|--------------------|---------------|
| Sessões de **users diferentes** no mesmo IP dentro da janela de conflito (configurável, default 60 s) e sem fonte TS | IP marcado **`multi-user`**: políticas `ad_*` → **não-match** nesse IP (fallback seguro) + evento de audit `identity_ip_conflict` |
| IP sai do estado multi-user (só um user activo após TTL) | Volta ao match normal |
| Fonte TS/VDI activa (IM8) | Desambiguação por porta; estado multi-user não se aplica |

“Não aplicar política ao user errado” **prevalece** sobre “aplicar sempre
alguma política”. GUI de diagnóstico mostra IPs em estado `multi-user`.

### 4.2 Arranque a frio e reload (rev. `c`)

| Evento | Comportamento do mapa |
|--------|------------------------|
| Restart do daemon / reboot | Mapa começa **vazio**; políticas `ad_*` em não-match até fontes repopularem (RADIUS interim-update, replay do agente DC, persistência best-effort 20.14). Janela documentada na GUI |
| `SIGHUP` (reload config) | Mapa vivo **sobrevive** (ADR-0028 §4); só config das fontes é relida |
| Persistência best-effort (20.14) | Snapshot periódico com TTLs; entradas expiradas nunca são restauradas |

### 4.3 Limites de escala (rev. `c` — fixar em IM3/IM4)

| Recurso | Limite default (ajustável) |
|---------|----------------------------|
| IPs por utilizador (IPv4+IPv6, privacy addresses) | 16 |
| Sessões totais no mapa | 4096 (eviction: mais antigo primeiro + log) |
| Profundidade de nested groups LDAP | 5 |
| Membros por grupo expandido | 4096 |
| TTL sessão sem refresh | 1 h (RADIUS/agente renovam) |

Estouro de limite = log + comportamento previsível (não crash, não
fail-closed); valores finais confirmados no lab GI4/GI5.

### 5. Honestidade (MVP)

- Sem IM7/IM8: **User-ID de rede**, não exactidão tipo GlobalProtect.
- Janela stale; NAT partilhado; multi-user mesmo IP sem TS = não fiável.
- Topologia: IP reportado pelo AD pode ≠ IP visto no firewall — limite documentado.
- Identity **não** implica MITM.

### 6. Isolamento de falhas

Falha Identity **não** desliga enforcement base nem políticas IP/MAC.

---

## Consequências

- Novo código daemon/GUI; defaults OFF.
- Lab: DC com agente **ou** NAS/RADIUS; não exigir WinRM no pfSense.
- Comercial: SKU Y com `identity` (ADR-0025).
- Escala: limites de nested groups / máx. members na IM4.

---

## Alternativas rejeitadas

| Alternativa | Motivo |
|-------------|--------|
| Só LDAP sem mapa de sessão | Não sabe quem está no IP |
| SSOT `identity_ips` via PHP resync | Stale demais (rev. `b`) |
| Reimplementar captive | Excluído |
| WinRM canónico a partir do FreeBSD | Frágil no CE |
| Agente-only MVP | Maior atrito; agente endpoint = IM7 |
| Bloquear Identity até MITM | Rejeitado (ortogonal) |

---

## Rollback

- Desligar Identity; remover entitlement; `ad_*` não-match.
- Pacote baseline `1.9.8`.

---

## Referências

- ADR-0011, ADR-0012, ADR-0014, ADR-0028 (concorrência/IO daemon)  
- Plano §0.0 R-C…R-Q, §3.1  
- [`../01-architecture/desenho-canal-agente-dc-20.20.md`](../01-architecture/desenho-canal-agente-dc-20.20.md)  
- [`../core/policy-matrix.md`](../core/policy-matrix.md), [`../core/precedence.md`](../core/precedence.md)
