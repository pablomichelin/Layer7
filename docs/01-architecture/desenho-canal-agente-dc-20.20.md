# Desenho — Canal agente DC → appliance (passo 20.20)

**Estado:** `ACEITE` (desenho fechado `2026-08-07` — passo **20.20**)  
**Tipo:** desenho de segurança **antes** de código (ADR-0027 §2.1; plano R-Q)  
**Plano:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) IM5 / **20.20**  
**ADR:** [`../03-adr/ADR-0027-identity-userid-multi-fonte.md`](../03-adr/ADR-0027-identity-userid-multi-fonte.md) §2.1  
**Concorrência:** [`../03-adr/ADR-0028-concorrencia-io-daemon-identity.md`](../03-adr/ADR-0028-concorrencia-io-daemon-identity.md)  
**Posicionamento PME:** [`../00-overview/posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md)  
**Arranque:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**Mapa:** M-16 (agente DC)  
**Baseline:** produção enforce `1.9.8` · lab/`latest` `1.9.17` (RADIUS 20.19)  
**MITM:** DEFER 20.7a — irrelevante para este canal

> **Regra:** nenhum código de receiver/agente até este desenho estar aceite.
> Implementação = bloco seguinte (fecho de 20.20 em código / GI6.5).

---

## 1. Objectivo

Fechar decisões **A1–A7** do canal *agente leve no Domain Controller →
push autenticado → receiver no appliance → mapa daemon*
(`L7_ID_SRC_DC_AGENT`), alinhado a:

- PME/MSP operável (barra U*/P*/H*/N*);
- não-regressão (`identity` OFF = zero threads / zero listener);
- sem WinRM/WMI outbound do FreeBSD;
- sem captive portal Layer7;
- sem secrets no git.

---

## 2. Arquitectura canónica (MVP)

```text
  Domain Controller(s)                    Appliance pfSense (Layer7)
  ┌─────────────────────┐                 ┌──────────────────────────┐
  │ Agente Windows      │  HTTPS + HMAC   │ layer7d                  │
  │ (serviço mínimo)    │ ──────────────► │  thread identity_dc      │
  │ Event Log 4624/4634 │  LAN only       │  (ADR-0028)              │
  │ token local 0600*   │                 │       │                  │
  └─────────────────────┘                 │       ▼                  │
                                          │  identity_map upsert/    │
                                          │  remove (src=DC_AGENT)   │
                                          └──────────────────────────┘
  * token emitido/revogado na GUI do appliance (nunca no AD como Domain Admin)
```

| Peça | Responsabilidade |
|------|------------------|
| Agente DC | Lê logons/logoffs; empacota evento; assina HMAC; POST TLS |
| Receiver | Valida ACL peer + TLS + token/HMAC + skew + rate limit; escreve mapa |
| GUI | Liga módulo; gera/revoga token; lista DC permitidos; porta/bind |
| LDAP (já 20.17) | Continua a expandir **grupos** — não é fonte de sessão |

**MVP fecho parcial de fontes:** RADIUS (20.19) **já** cumpre ≥1 fonte.
O agente DC é a **segunda** fonte canónica (GI6.1) e melhora exactidão
em redes com AD sem RADIUS accounting.

---

## 3. Decisões A1–A7 (fechadas)

### A1 — Transporte e autenticação

| Opção | Veredicto |
|-------|-----------|
| Push em claro (HTTP sem TLS) | **Proibido** |
| mTLS (cert cliente por DC) | **Fase 2** (endurecimento); não bloqueia MVP |
| **TLS (HTTPS) + token por appliance + HMAC-SHA256 do corpo** | **MVP canónico** |

**Motivação PME:** o operador já conhece o padrão “secret partilhado”
(RADIUS 20.19). Emitir/revogar um token na GUI é instalável numa tarde;
mTLS exige PKI operacional em cada DC e atrasa o valor Identity.

**Detalhe MVP:**

1. Listener HTTPS no appliance (TLS 1.2+; certificado: default self-signed
   gerado no appliance **ou** cert importado — documentar trust no agente).
2. Header `Authorization: Bearer <token>` **ou** header `X-Layer7-Token`.
3. Header `X-Layer7-Timestamp` (unix) + `X-Layer7-Signature: hex(HMAC-SHA256(secret, canonical))`.
4. Canonical string (bytes exactos):  
   `timestamp + "\n" + method + "\n" + path + "\n" + sha256_hex(body)`.
5. Token/secret guardados em ficheiro **0600** no appliance  
   (`/usr/local/etc/layer7/identity-dc.secret`) — **nunca** em `layer7.json`.
6. Agente guarda a mesma cópia em ficheiro local restrito (ACL SYSTEM/Admin).

**Fase 2 (opcional, mesmo porto):** mTLS com cert cliente emitido/descarregado
na GUI; HMAC pode permanecer como defesa em profundidade.

### A2 — Bind e porto

| Parâmetro | Default MVP |
|-----------|-------------|
| Porto TCP | **8743** (dedicado; documentado na GUI) |
| Bind | **só IPs LAN configurados** (lista); **nunca** WAN por defeito |
| Default se operador não escolher | primeiro IPv4 da interface LAN do pfSense |
| Expor 8743 na WAN / 0.0.0.0 sem ACL | **Proibido** como default; GUI avisa |

ACL de peers (IPs dos DCs) **obrigatória** quando o módulo está ON
(mesmo espírito da NAS ACL do RADIUS). Lista vazia ⇒ receiver **não arranca**.

Firewall: operador deve permitir DC→appliance:8743 na LAN; o pacote
**não** abre WAN automaticamente.

### A3 — Payload mínimo

`POST /v1/identity/events` — `Content-Type: application/json`

```json
{
  "user": "joao.silva",
  "ip": "10.0.0.55",
  "event": "logon",
  "timestamp": 1770123456,
  "domain": "EMPRESA"
}
```

| Campo | Obrigatório | Notas |
|-------|-------------|-------|
| `user` | sim | sAMAccountName ou UPN curto; ≤ 128 chars (mapa) |
| `ip` | sim | IPv4 ou IPv6 visto no logon (honestidade: pode ≠ IP no firewall) |
| `event` | sim | `logon` \| `logoff` \| `heartbeat` |
| `timestamp` | sim | unix seconds (UTC) |
| `domain` | não | só diagnóstico; não entra na chave do mapa MVP |

**Proibido no payload:** passwords, hashes, SIDs completos, tickets Kerberos,
conteúdo de e-mail, lista completa de grupos (grupos vêm do LDAP).

`logon` / `heartbeat` → `layer7_idmap_upsert(..., L7_ID_SRC_DC_AGENT)`.  
`logoff` → `layer7_idmap_remove_user` (MVP; refinamento multi-IP em 20.21).

### A4 — Rate limit e tamanho

| Limite | Default |
|--------|---------|
| Body máximo | **4096** bytes |
| Eventos / segundo / peer IP | **50** (token bucket) |
| Eventos / segundo global | **200** |
| Burst | 2× o rate |
| Pedidos acima do limite | HTTP 429; log sem token; **não** escrever mapa |

DoS: socket na thread própria (ADR-0028); backpressure no accept/read;
nunca bloquear o loop de captura.

### A5 — Credencial revogável

| Acção GUI | Efeito |
|-----------|--------|
| Gerar token | Cria/roda secret 0600; mostra **uma vez**; agente deve ser reconfigurado |
| Revogar | Apaga/roda secret; receiver rejeita assinaturas antigas de imediato |
| Sem reinstalar agente no DC | Agente só precisa do ficheiro de config actualizado (MSP copia novo token) |

Múltiplos DCs partilham o **mesmo** token de appliance no MVP (simples para PME).
Tokens por-DC = fase posterior se necessário.

### A6 — Privilégio mínimo no DC

| Papel | MVP |
|-------|-----|
| Conta do serviço | **não** Domain Admin |
| Direito | *Event Log Readers* (ou equivalente) no Security log |
| Eventos | 4624 (logon) / 4634 ou 4647 (logoff) — filtrar tipo de logon relevante (Interactive / RemoteInteractive / Network tipicamente; documentar filtros) |
| Instalação | MSI/serviço Windows documentado; config JSON local |

Agente **não** faz bind LDAP no DC para grupos (isso é o worker LDAP no appliance).

### A7 — Skew de relógio

| Parâmetro | Default |
|-----------|---------|
| Tolerância \|now − timestamp\| | **300 s** (configurável 60–900) |
| Fora de janela | Descartar; log `identity_dc_skew`; HTTP 400 |
| NTP | Documentar na GUI: DC e appliance devem ter hora coerente |

---

## 4. Defaults e entitlement

| Item | Default |
|------|---------|
| `identity.dc_agent.enabled` | **OFF** |
| Listener | não sobe sem entitlement `identity` + enabled + token + ACL DC |
| Com Identity OFF | **zero** threads DC; **zero** bind 8743 |

---

## 5. UX PME (obrigatório na implementação)

| Critério | Como cumprir |
|----------|--------------|
| U* estados claros | GUI: OFF / À escuta / Erro bind / Token em falta / ACL vazia |
| P* teste de ligação | Botão “Gerar token” + instruções curtas; futuro “evento de teste” do agente |
| H* limites honestos | Texto: IP do AD pode ≠ IP visto no firewall; User-ID de rede; sem agente endpoint |
| N* sem overclaim | Não dizer “igual ao GlobalProtect” |

---

## 6. Não-regressão / gates

- Smoke com Identity OFF após implementação (NR permanente do plano §6).
- Perf ±10% vs baseline 20.11a com módulo OFF.
- GI6.5: provar em lab TLS+HMAC, bind LAN, rate limit (após código).
- GI6.1: segunda fonte = este agente (RADIUS já conta como primeira).

---

## 7. Fora de escopo (este desenho)

- WinRM/WMI a partir do pfSense.
- Agente endpoint (IM7) / TS (IM8) — reutilizam A1–A5 depois.
- mTLS obrigatório no MVP.
- SIEM / analytics.
- MITM.

---

## 8. Ordem de implementação (próximo bloco)

1. Config GUI + secret 0600 + parse JSON (`identity.dc_agent`).
2. Receiver HTTPS na thread `identity_dc` → mapa.
3. Agente Windows MVP (filtro 4624/4634 + POST).
4. Testes unitários (HMAC, skew, rate limit, ACL) + lab GI6.
5. Docs MANUAL + changelog + PORTVERSION quando houver código.

---

## 9. Rollback

- Desligar `dc_agent` na GUI / entitlement OFF.
- Sem código ainda: só reverter este documento + ADR se o operador rejeitar
  a escolha A1 (TLS+HMAC vs mTLS-first).

---

## 10. Histórico

| Data | Evento |
|------|--------|
| 2026-08-05 | ADR-0027 §2.1 A1–A7 (requisitos mínimos) |
| 2026-08-07 | **20.20 desenho ACEITE** — TLS+HMAC MVP; porto 8743; payload; limites; privilégio mínimo |
| 2026-08-07 | **20.20 receiver PASS** — `identity_dc` + GUI + script PS1 lab; candidato 1.9.18 |
