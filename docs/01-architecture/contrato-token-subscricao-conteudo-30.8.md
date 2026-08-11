# Contrato — Token de subscrição de conteúdo (passo `30.8`)

**Estado:** **FECHADO documental** (`2026-08-10`) — revisão humana **OK** (`sim`, mesmo dia)  
**Passo:** `30.8` (AP2) · **BG-117** (desenho)  
**ADR:** [ADR-0031](../03-adr/ADR-0031-entitlement-entrega-conteudo.md) (**Aceito**)  
**Plano:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) § AP2  
**Gates:** GA4.1 (este doc) · GA4.14 (RR-2) · restante GA4 em `30.9`–`30.11`  
**Código neste passo:** **nenhum**

---

## 1. Objectivo

Fechar o contrato do **token de subscrição de conteúdo** antes de qualquer
emissão (`30.9`) ou cliente (`30.10`), de forma a que:

1. o download de conteúdo **corrente** exija entitlement criptográfico;
2. a cópia sem subscrição **degrade sozinha** (listas obsoletas) — A-06;
3. appliances legitimamente offline **não** percam enforce nem blacklists locais
   (R-C, R-D, N3, N4);
4. o limite **RR-2** (redistribuição a partir de um appliance licenciado) fique
   explícito e não seja overclaim.

O manifesto Ed25519 actual (**ADR-0005**) continua a garantir **integridade**.
O token garante **entitlement**. São camadas ortogonais.

---

## 2. Decisões fechadas neste passo

| # | Decisão | Valor canónico | Notas |
|---|---------|----------------|-------|
| D1 | Validade nominal do token (`exp − iat`) | **30 dias** (2 592 000 s) | Alinhado ao modelo de ameaças (H2 / “ex. 30 dias”) |
| D2 | Tolerância de skew no verificador | **±1 dia** (86 400 s) | NTP/ajuste legítimo; não é “grace de conteúdo” |
| D3 | Grace pós-`exp` no servidor de conteúdo | **0** | Após `exp`, conteúdo **corrente** recusa; cliente mantém cópia local |
| D4 | Conteúdo **histórico** / já materializado | **Servido/usado sem token** | R-D: nunca apagar; enforce intacto |
| D5 | Obtenção | Campo na **resposta de check-in** activa | Sem endpoint novo obrigatório em `30.9` (pode existir helper interno) |
| D6 | Armazenamento no appliance | `/var/db/layer7/content-subscription.json` modo `0600` | Fora de `/tmp`; não é autoridade de enforce |
| D7 | Apresentação ao servidor de conteúdo | Header `Authorization: Bearer <base64url(envelope)>` | Fallback: `X-Layer7-Content-Token` com o mesmo valor |
| D8 | Chave de assinatura | **Mesma** Ed25519 de assinatura de `.lic` (license server) | Pubkey já no `.pkg` (`license-signing-public-key.pem`); CDN precisa da mesma pubkey. Chave dedicada = evolução futura com ADR |
| D9 | Scope | `content` (string); MVP inclui blacklists UT1 + catálogos oficiais | Expandir scopes só com emenda a este contrato |
| D10 | Relação com enforce | **Nenhuma** | Token ≠ licença de runtime; falha de token **nunca** reduz enforce |

Estas decisões só mudam com emenda explícita a este documento + nota no plano §0.

---

## 3. Formato do token

Envelope JSON (mesmo padrão conceptual do `.lic`):

```json
{
  "data": "<string JSON compacta do payload>",
  "sig": "<128 hex chars — Ed25519 sobre os bytes UTF-8 de data>"
}
```

Payload dentro de `data` (campos obrigatórios):

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `v` | int | Versão do contrato — **`1`** |
| `hardware_id` | string | Fingerprint do appliance (mesmo identificador do `.lic`) |
| `license_id` | int ou string | Identificador estável da licença no license server |
| `scope` | string | `content` no MVP |
| `iat` | int | Unix UTC (emissão) |
| `exp` | int | Unix UTC (`iat + 2592000` salvo política servidor ≤ esse tecto) |
| `jti` | string | UUID/opaco — anti-replay fraco / auditoria |

Campos opcionais: `customer_id`, `features` (informativo; **não** desbloqueia
Identity/MITM — isso continua no `.lic` / passo `30.7`).

**Regras de verificação (servidor de conteúdo e cliente local):**

1. JSON envelope bem formado; `sig` hex 64 bytes.
2. `openssl pkeyutl -verify` (ou equivalente) com pubkey de licença.
3. `v == 1`; `scope` contém entitlement necessário (`content`).
4. `now ∈ [iat − skew, exp + skew]` com `skew = 86400`.
5. Cliente **deve** recusar usar token cujo `hardware_id` ≠ fingerprint local
   (mitiga cópia acidental do ficheiro entre boxes; **não** impede RR-2 com root).

**Proibido:** HMAC com segredo no appliance; JWT com chave simétrica embutida;
assinatura local pelo daemon (circular sob root — R-A / ADR-0030).

---

## 4. Ciclo de vida

```text
license server (check-in OK)
        │ emite envelope (D5/D8)
        ▼
 /var/db/layer7/content-subscription.json  (D6)
        │
        │ update-blacklists.sh / caminho equivalente (30.10)
        │ Authorization: Bearer … (D7)
        ▼
 servidor de conteúdo corrente (primary)
        │ verifica sig+exp+scope (D1–D3)
        │ + verifica manifesto.sig (ADR-0005)  ← integridade
        ▼
 promove snapshot local (comportamento actual em sucesso)
```

### 4.1 Obtenção (check-in)

Na resposta JSON de check-in **activa** (`status: active`), acrescentar:

```json
{
  "status": "active",
  "...": "...",
  "content_subscription": {
    "data": "{...payload...}",
    "sig": "…"
  }
}
```

- Licença revogada/expirada/denied: **não** inclui `content_subscription`.
- Check-in falho / rede ausente: cliente **não apaga** token anterior ainda
  dentro de `exp` (R-J / offline legítimo).
- Renovação: cada check-in activo **substitui** o ficheiro local (novo `jti`/`iat`/`exp`).

Detalhe de schema HTTP e testes: passo **`30.9`**. Assinatura da *resposta*
completa de check-in (anti-replay) é **AP3 / `30.12`**, ortogonal: o token de
conteúdo já vai assinado no campo; AP3 endurece o envelope exterior.

### 4.2 Uso no update de conteúdo (`30.10` — contrato, não código)

1. Ler `content-subscription.json`; se ausente/inválido/expirado → **não**
   contactar URLs de conteúdo **corrente**; manter snapshot local; log WARN;
   expor estado na GUI (GA4.7).
2. Se válido → `fetch`/`curl` com Bearer; em HTTP 401/403 → igual a sem token
   (não apagar local).
3. Promoção local **só** após verificação do manifesto assinado (inalterada).
4. Falha de rede / DNS / 5xx → zero impacto em enforce (N3); retry na próxima
   janela cron.

### 4.3 Servidor de conteúdo

| Classe de URL | Token |
|---------------|-------|
| Snapshot / manifesto **corrente** (`…/current/…`) | **Obrigatório** após `30.11` |
| Snapshots históricos publicados deliberadamente | **Opcional** (D4) — não são o vector A-06 |
| Espelho GitHub anónimo corrente | Removido/limitado em **`30.11`** (GO próprio; RR-1) |

Enquanto o espelho anónimo existir, o token é **insuficiente** para fechar A-06
(RR-1). Este contrato não autoriza a retirada do espelho — só define o caminho
novo.

---

## 5. Políticas de degradação e offline

| Cenário | Conteúdo local | Update corrente | Enforce |
|---------|----------------|-----------------|---------|
| Token válido | intacto | permitido | intacto |
| Token expirado / ausente | **mantém** | bloqueado | **intacto** |
| Offline &lt; 30d com token ainda válido | intacto | permitido se rede voltar antes de `exp` | intacto |
| Offline &gt; 30d (token expirou) | **mantém** | bloqueado até check-in | **intacto** |
| License server down no check-in | mantém token antigo se `exp` ok | conforme token | intacto |
| Content server down | intacto | falha transitória | intacto |
| Licença revogada (check-in denied) | mantém | sem token novo; após `exp` pára updates | monitor pela via de licença já existente — **não** via token |

**Nunca:** apagar `/usr/local/…/blacklists`, desligar `layer7d`, ou reduzir
enforce por falta de token (R-D, R-E, R-C).

### Recuperação operador (R-J)

1. Restaurar conectividade / DNS ao license server.  
2. Forçar check-in (ou aguardar intervalo).  
3. Confirmar GUI “subscrição de conteúdo: OK” + ficheiro `0600` presente.  
4. Correr update de blacklists.  
Runbook operacional detalhado: passo `30.10` (não este doc).

---

## 6. Casos-limite (revisão humana)

| ID | Caso | Resultado esperado |
|----|------|--------------------|
| C1 | Appliance online, licença activa, primeiro check-in | Recebe token; update corrente PASS |
| C2 | Appliance offline 20 dias, token emitido no dia 0 | Update quando voltar rede, se `exp` ≥ now |
| C3 | Appliance offline 40 dias | Sem update; blacklists antigas activas; enforce OK; aviso GUI |
| C4 | Relógio +12h (NTP) | Aceite (skew D2) |
| C5 | Relógio −2 dias | Pode falhar verify; recuperação = corrigir hora + check-in (R-J); **não** fail-closed enforce |
| C6 | Token de appliance A copiado para B | Cliente B recusa (hardware_id); se B patchar cliente, RR-2 / R-A |
| C7 | Integrador licenciado re-serve tarball na LAN interna | **Possível (RR-2)** — ver §7; não é bug deste contrato |
| C8 | Manifesto com sig inválida mas token OK | Rejeitar promoção (ADR-0005) |
| C9 | Token OK, espelho GitHub ainda público (`pré-30.11`) | Pirata actualiza pelo espelho — **RR-1**; AP2 incompleto até GO `30.11` |
| C10 | Rede cortada durante `fetch` do snapshot | Abortar update; estado local anterior; enforce OK |
| C11 | Licença expirada no servidor | Sem token novo; após `exp` pára updates; enforce → monitor pela licença |
| C12 | `features` no token diz `mitm` mas `.lic` não | GUI/add-ons **não** unlock (30.7); token de conteúdo irrelevante para MITM |

---

## 7. Limite RR-2 (obrigatório — GA4.14)

O token **impede o download anónimo** de conteúdo corrente. **Não** impede que
um integrador com **uma** licença legítima:

1. actualize blacklists no appliance licenciado;
2. copie os ficheiros resultantes;
3. redistribua internamente a N instalações sem licença.

Resposta deliberada (não bloqueio técnico neste passo):

- marcação / atribuição por cliente (`30.17`);
- via contratual e EULA (AP4);
- detecção multi-appliance no check-in (`30.15`) como sinal comercial.

Qualquer texto de marketing ou runbook que diga “impossível redistribuir
listas” está **em falta** face a este contrato e ao ADR-0031.

---

## 8. Fora de escopo (explícito)

- Ofuscação, packers, anti-debug (R-G).  
- Fail-closed por rede ou kill-switch (R-C, R-E).  
- Assinatura da resposta completa de check-in (AP3 / `30.12`).  
- Retirada do espelho GitHub (`30.11` + GO humano).  
- Alterar runtime MITM / Identity / enforce (R-I).  
- Troca da pubkey de produção (GA1.8).  
- Código de emissão ou `update-blacklists.sh` (passos seguintes).

---

## 9. Mapa para implementação

| Passo | Responsabilidade |
|-------|------------------|
| **30.8** (este) | Contrato + decisões D1–D10 + RR-2 — **FECHADO** |
| **30.9** | Emitir `content_subscription` no check-in — **FECHADO** (`content-subscription.js`) |
| **30.10** | Cliente: persistir, enviar Bearer, GUI, runbook — **FECHADO** (`1.9.53`) |
| **30.11** | GO + retirar/limitar espelho corrente; comunicação; rollback do espelho |

**Rollback deste passo:** reverter o commit documental.  
**Rollback de AP2 em runtime (futuro):** `.pkg` anterior + repor espelho (GA4.11).

---

## 10. Critérios de aceite documental (GA4.1 / GA4.14)

- [x] Formato Ed25519 + `hardware_id` + validade curta definidos  
- [x] Obtenção (check-in), armazenamento, apresentação ao CDN definidos  
- [x] D1–D4 (validade, skew, grace, histórico) fechados  
- [x] Offline legítimo e degradação enumerados (R-C, R-D, N3, N4)  
- [x] RR-2 declarado com resposta atribuição+contratual  
- [x] Casos-limite C1–C12 para revisão humana  
- [x] **Revisão humana** assinalada (teste mínimo do passo) — **OK** (`2026-08-10`)

---

## Referências

- ADR-0031, ADR-0005, ADR-0030  
- `modelo-ameacas-antipirataria.md` A-06 / Camada 1  
- `update-blacklists.sh` (URLs primary/mirror actuais — baseline A-06)  
- `license-server/backend/src/check-in-policy.js` (resposta activa actual)  
- `plano-gates-antipirataria.md` GA4  
