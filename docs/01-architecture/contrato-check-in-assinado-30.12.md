# Contrato — Check-in assinado com nonce / anti-replay (passo `30.12`)

**Estado:** **FECHADO documental** (`2026-08-12`) — desenho revisto neste bloco  
**Passo:** `30.12` (AP3) · **BG-119** (desenho)  
**ADR:** [ADR-0032](../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md) (**Aceito**; emenda ADR-0021)  
**Plano:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) § AP3  
**Gates:** GA5.1 (este doc) · restante GA5 em `30.13`–`30.15`  
**Código neste passo:** **nenhum**  
**Evidência:** [`../tests/evidence/20260812T013200Z-30.12-protocol-design/`](../tests/evidence/20260812T013200Z-30.12-protocol-design/)

---

## 0. Cartão do passo (Composer §8.2)

```text
PASSO: 30.12
PERMITIDO: contrato arquitectural; actualizar CORTEX/plano/START-HERE/gates/ADR refs; evidência documental
PROIBIDO: código; deploy; secrets; mexer .254/CF/DNS/license-server; abrir 30.13+; fail-closed rede; enforce/MITM/IPv6
STOP SE: decisão comercial nova (ex. forçar default check-in = 30.14); AP2 instável
GATE: GA5.1
DoD: decisões D1–D12 fechadas; casos replay/servidor falso/N3 enumerados; links SSOTs coerentes
```

---

## 1. Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| **Objectivo** | Fechar o contrato do check-in **assinado** com **nonce** (anti-replay) antes de qualquer código em `30.13`, corrigindo A-05 sem violar R-C/N3. |
| **Impacto** | Documental agora; em `30.13` muda envelope HTTP do check-in e verificação no `layer7d`. Clientes antigos mantêm caminho legado até fim da transição. |
| **Risco** | Nulo neste passo (só docs). Residual: RR-5 (patch local) e RR-1 (falta ainda GO `30.14`). |
| **Teste mínimo** | Revisão do contrato; tabela de casos C1–C10; links ADR-0032 / GA5.1. |
| **Rollback** | Reverter este documento + estado nos SSOTs; zero efeito em runtime. |

---

## 2. Estado actual (baseline — só leitura)

Hoje (`1.9.54` / license-server live):

| Lado | Comportamento |
|------|----------------|
| Pedido | `POST /api/license/check-in` com `{key, hardware_id}` — **sem nonce** |
| Resposta activa | JSON simples (`status`, `expiry`, `customer`, `features`, intervalos) + `content_subscription` assinado (contrato `30.8`) |
| Resposta denied | JSON simples `{status, error}` **sem** assinatura |
| Cliente (`license.c`) | Aceita `status == "active"` no JSON **sem** verificar assinatura do envelope |

Isto é exactamente A-05: servidor falso via `/etc/hosts` pode manter licença viva.

---

## 3. Decisões fechadas neste passo

| # | Decisão | Valor canónico | Notas |
|---|---------|----------------|-------|
| D1 | Endpoint | **Mesmo** `POST /api/license/check-in` | Sem URL nova |
| D2 | Pedido v2 | Acrescenta `nonce` (obrigatório para cliente novo) | `key` + `hardware_id` mantêm-se |
| D3 | Nonce | 32 bytes aleatórios CSPRNG, encoding **base64url** sem padding | Cliente gera por tentativa |
| D4 | Resposta v2 (activa **e** denied) | Envelope `{ "data": "<JSON compacto>", "sig": "<128 hex Ed25519>" }` | `sig` sobre bytes UTF-8 de `data` |
| D5 | Chave | **Mesma** Ed25519 de assinatura de `.lic` / token conteúdo | Pubkey já no `.pkg`; chave dedicada = evolução futura + ADR |
| D6 | Anti-replay | Servidor **eco** do `nonce` dentro de `data`; cliente rejeita se `nonce` ≠ pedido | Servidor **não** precisa de store de nonce para o MVP |
| D7 | Binding | `data.hardware_id` **deve** igualar o pedido / fingerprint local | Mitiga cópia de resposta entre boxes |
| D8 | `content_subscription` | Permanece objecto aninhado **dentro** de `data` quando `status=active` | Continua contrato `30.8`; envelope exterior agora também assinado |
| D9 | Cliente novo (`30.13`) | Sempre envia `nonce`; **rejeita** resposta sem envelope/`sig` válido | Fecha A-05 para builds novos |
| D10 | Transição / legado | Pedido **sem** `nonce` → servidor pode responder JSON legado (ADR-0021) | Só para clientes antigos; **não** reabre A-05 nos novos |
| D11 | Rede / N3 / R-C | Falha de rede, timeout, 5xx, verify falha ⇒ **zero** impacto em enforce enquanto dentro de `max_offline_hours` | Revogação só com resposta **assinada** `revoked`/`expired` aceite |
| D12 | Relação com `30.14` | Default ON = GO/`30.14` (fora deste contrato documental) | **`30.14` FECHADO** `20260812T015519Z` — ver runbook migração |

Estas decisões só mudam com emenda explícita a este documento + nota no plano §0.

---

## 4. Formato

### 4.1 Pedido (cliente novo)

```json
{
  "key": "<license_key>",
  "hardware_id": "<fingerprint>",
  "nonce": "<base64url-32-bytes>"
}
```

Campo opcional futuro (não obrigatório no MVP): `client_ts` (unix) — **não** usado
para autorizar; se presente, só auditoria.

### 4.2 Envelope de resposta v2

```json
{
  "data": "<string JSON compacta do payload>",
  "sig": "<128 hex chars — Ed25519 sobre UTF-8 de data>"
}
```

### 4.3 Payload `data` — activa

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `v` | int | sim | Versão do contrato de envelope — **`1`** |
| `status` | string | sim | `active` |
| `hardware_id` | string | sim | Eco do fingerprint |
| `nonce` | string | sim | Eco do nonce do pedido |
| `expiry` | string | sim | Data `YYYY-MM-DD` (igual hoje) |
| `customer` | string | sim | Nome (igual hoje) |
| `features` | string | sim | CSV normalizado |
| `check_in_interval_hours` | int | sim | Política servidor |
| `max_offline_hours` | int | sim | Política servidor |
| `iat` | int | sim | Unix UTC emissão da resposta |
| `content_subscription` | object | se activo | Envelope `{data,sig}` do contrato `30.8` |

### 4.4 Payload `data` — denied

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| `v` | int | sim | `1` |
| `status` | string | sim | `revoked` \| `expired` \| outros denied estáveis |
| `hardware_id` | string | sim | Eco |
| `nonce` | string | sim | Eco |
| `error` | string | sim | Mensagem estável |
| `iat` | int | sim | Unix UTC |

**Nota:** denied **também** assinado (D4) — impede injecção trivial de
`revoked` por MITM sem chave; o atacante A-05 continua a ser o que tenta
forjar `active`.

### 4.5 Regras de verificação (cliente novo)

1. Envelope bem formado; `sig` hex 64 bytes.
2. Verificar Ed25519 com pubkey de licença do `.pkg`.
3. Parse de `data`; `v == 1`.
4. `nonce` == nonce enviado neste pedido.
5. `hardware_id` == fingerprint local.
6. `|now − iat| ≤ 86400` (skew ±1 dia) — rejeitar relógios absurdos; **não**
   fail-closed enforce se rejeitar por skew (tratar como check-in falho / rede).
7. Se `status == active` → marcar check-in OK; persistir `content_subscription`
   se presente (regras `30.8`/`30.10` inalteradas).
8. Se `status` ∈ {`revoked`,`expired`} → invalidar conforme ADR-0021 (já
   implementado) **só** após passos 1–6 PASS.
9. Qualquer falha 1–6, HTTP não-JSON, ausência de `sig`, ou JSON legado
   **num cliente novo** → check-in **falhou**; **não** alterar enforce além das
   regras offline já existentes (N3).

---

## 5. Transição (GA5.13 — desenho)

```text
                    pedido tem nonce?
                     /            \
                   sim            não
                    |              |
            resposta v2        JSON legado
            (envelope+sig)     (ADR-0021 / hoje)
                    |              |
            cliente novo       cliente antigo
            exige sig          aceita JSON
```

| Fase | Servidor | Cliente |
|------|----------|---------|
| Agora (`30.12`) | só docs | — |
| `30.13` | dual-mode (D10) | novo exige envelope |
| Pós-`30.14` + janela | (opcional) exigir nonce sempre — **só com GO/runbook** | base instalada migrada |

**Proibido neste desenho:** desligar o caminho legado sem GO `30.14` / runbook.

---

## 6. Casos de teste enumerados (implementação = `30.13`)

| # | Caso | Resultado esperado |
|---|------|--------------------|
| C1 | Pedido com nonce; servidor legítimo; sig OK | Cliente aceita `active`; intervals/token actualizados |
| C2 | Resposta JSON legado **sem** `sig` a cliente novo | **Rejeitada** (check-in falho); enforce intacto se dentro offline |
| C3 | Resposta com `sig` inválida / pubkey errada | Rejeitada |
| C4 | Replay: reenviar envelope antigo com nonce velho | Rejeitada (`nonce` ≠ pedido actual) |
| C5 | Servidor falso `/etc/hosts` devolve `active` sem chave | Rejeitada |
| C6 | Servidor falso devolve `active` assinado com chave inventada | Rejeitada |
| C7 | Denied assinado `revoked` | Cliente invalida licença (comportamento ADR-0021) |
| C8 | Rede down / timeout | Enforce intacto (N3) dentro de `max_offline_hours` |
| C9 | Pedido **sem** nonce (cliente antigo) | Servidor responde legado; appliance antigo continua |
| C10 | `content_subscription` aninhado em `data` activo | Persistência `30.10` continua; verify do token `30.8` intacto |

---

## 7. O que **não** muda

- Formato `.lic`, activação, binding, ausência de CRL offline (ADR-0021).
- Defaults de intervalo 168h / offline 336h (salvo override servidor já existente).
- `check_in_enabled` default OFF até `30.14` (GO).
- Token de conteúdo (contrato `30.8`) — apenas fica **dentro** do envelope assinado.
- Pinning TLS — **fora** deste passo (transporte continua HTTPS/`curl`; A-05
  fecha-se pela assinatura do payload, não por pinning).
- Fail-closed por rede, kill-switch, ofuscação — **proibidos**.

---

## 8. Limites honestos

| RR / regra | Declaração |
|------------|------------|
| **R-C / N3** | Indisponibilidade ≠ revogação. |
| **RR-5 / R-A** | Root pode patchar `layer7d` para ignorar verificação — assinatura impede servidor falso, não crack local. |
| **RR-1** | Cut `30.11` **feito**; protecção comercial completa de revogação em campo ainda exige GO/`30.14`. |
| **GA5.2–5.13** | Continuam **PENDENTE** até código/campo (`30.13`+) e GO onde aplicável. |

---

## 9. Mapa de implementação (`30.13` — **FECHADO** `20260812T013913Z`)

| Componente | Estado |
|------------|--------|
| `license-server/.../check-in.js` + `check-in-policy.js` | **Feito** — nonce + envelope D4; dual-mode D10 |
| `src/layer7d/license.c` | **Feito** — nonce; verify sig+nonce+hw+iat; rejeita legado |
| Testes C/JS | **Feito** — evidência `20260812T013913Z-30.13-checkin-signed` |
| `.pkg` | Candidato `1.9.55` no Makefile; **sem** GitHub Release em `30.13` |

**Rollback:** `.pkg` `1.9.54` + deploy anterior do servidor (dual-mode permite
rollback do cliente sem partir legado).

---

## 10. Checklist DoD `30.12`

- [x] Decisões D1–D12 fechadas
- [x] Formato pedido/resposta / payload activa e denied
- [x] Casos replay, servidor falso, N3, legado (C1–C10)
- [x] Ortogonalidade com contrato `30.8` (`content_subscription`)
- [x] Sem código / deploy / secrets
- [x] GA5.1 apontável a este documento
- [x] `30.14` / default ON **não** aberto

---

## Referências

- [ADR-0032](../03-adr/ADR-0032-check-in-obrigatorio-e-assinado.md)
- [ADR-0021](../03-adr/ADR-0021-check-in-online-e-revogacao-remota.md)
- [`contrato-token-subscricao-conteudo-30.8.md`](contrato-token-subscricao-conteudo-30.8.md)
- [`modelo-ameacas-antipirataria.md`](modelo-ameacas-antipirataria.md) § A-05
- Gates: [`../09-blocking/plano-gates-antipirataria.md`](../09-blocking/plano-gates-antipirataria.md) GA5
