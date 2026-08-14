# Prep / fecho — Cut do espelho anónimo (`30.11`)

**Estado:** **CUT EXECUTADO** (`20260812T011217Z`) — ver evidência
[`../tests/evidence/20260812T011217Z-30.11-cut-mirror/`](../tests/evidence/20260812T011217Z-30.11-cut-mirror/)  
**Data prep:** `2026-08-12` · **Data cut:** `2026-08-12T01:12:17Z`  
**Trilha:** Anti-pirataria / Anti-tamper · AP2 · BG-117  
**Plano:** [`../02-roadmap/plano-antipirataria-anti-tamper.md`](../02-roadmap/plano-antipirataria-anti-tamper.md) § `30.11`  
**Gates:** [`plano-gates-antipirataria.md`](plano-gates-antipirataria.md) GA4.10 / GA4.11 / GA4.12 / GA4.15  
**ADR:** [ADR-0031](../03-adr/ADR-0031-entitlement-entrega-conteudo.md) §4–§5  
**Rollback:** [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md)

---

## 1. Decisão humana — GA4.12 (comunicação externa)

| Campo | Valor |
|-------|--------|
| Pedido | Comunicação externa a clientes antes do cut? |
| Decisão | **Não necessária** |
| Âmbito | Decisões **internas** de desenvolvimento |
| Destinatários externos agora | **Nenhum** |
| Se houver impacto futuro | Aviso em **janela de manutenção** previamente comunicada por e-mail (ops Systemup) — **não** inventar nem enviar e-mail neste passo |
| Estado do gate GA4.12 | **N/A** (waived) — ver §1.1 |

### 1.1 Justificativa (plano / ADR / gates)

| Fonte | Leitura |
|-------|---------|
| Gate GA4.12 | «Comunicação a clientes emitida antes de 30.11» — pressupõe **audiência externa** a avisar |
| Plano § `30.11` | Entrega inclui comunicação **antes** do cut quando há dependentes do espelho |
| ADR-0031 | «Retirar o espelho afecta quem depende dele hoje — exige comunicação» — a obrigação liga-se a **dependentes** |
| Decisão gestor `2026-08-12` | Sem destinatários externos; cut é decisão interna; impacto futuro → janela de manutenção por e-mail já existente na ops |

**Conclusão canónica:** com audiência externa vazia e decisão humana registada, GA4.12
passa a **N/A** (não é PASS de emissão; não é FAIL). **Não** substitui o GO próprio
de **cut** (GA4.15 / decisão 3 de execução).

**Proibido:** inventar lista de destinatários; enviar e-mail; marcar GA4.12 PASS
como se tivesse havido emissão.

---

## 2. Pré-requisitos do cut (checklist)

| # | Pré-requisito | Estado |
|---|---------------|--------|
| P1 | `30.10` FECHADO + e2e `.254` / `1.9.54` PASS | **PASS** (`20260811T114320Z`) |
| P2 | `30.9` live (token emitido) | **PASS** |
| P3 | Primary auth GET manifesto/`.sig` 200/200 + sem token 401 | **PASS** (`20260812T003214Z`) |
| P4 | Primary sem token continua 401 (recheck prep) | **PASS** (`2026-08-12`) |
| P5 | GA4.11 rollback doc ready | **DOC READY** |
| P6 | Cópia íntegra do snapshot no origin (rollback) | **PASS** — cksums runbook = assets GitHub (823 / 64 / 113 / 31169229) |
| P7 | GA4.12 comunicação externa | **N/A** (esta ficha §1) |
| P8 | GO explícito de **cut** (GA4.15) | **PASS** — GO gestor + `delete-asset` ×4 |
| P9 | Não misturar enforce / MITM / IPv6 | **OK** (escopo só espelho) |

**Recheck primary (prep, sem token):**
`https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt`
→ HTTP **401**.

---

## 3. Alvo exacto no GitHub (espelho anónimo corrente)

| Campo | Valor |
|-------|--------|
| Repo | `pablomichelin/Layer7` |
| Tag / release | `blacklists-ut1-current` |
| Release id | `313502667` |
| Nome | Blacklists UT1 - snapshot corrente assinada (F1.3) |
| Draft | `false` |
| Prerelease | `true` |
| URL | https://github.com/pablomichelin/Layer7/releases/tag/blacklists-ut1-current |

### Assets anónimos a remover (conteúdo corrente)

Inventário live `2026-08-12` — **todos** os quatro assets da release rolling
(alinhados ao conjunto do runbook de reposição GA4.11):

| # | Asset | size | asset id (API) | sha256 (digest API) | downloads |
|---|-------|-----:|----------------|---------------------|----------:|
| 1 | `layer7-blacklists-manifest.v1.txt` | 823 | `405033619` | `3be4330830a58011bcef82d1225d224eb5d8a4d4a5836e31dceeedc503b61dae` | 26 |
| 2 | `layer7-blacklists-manifest.v1.txt.sig` | 64 | `405033621` | `27d9552270b43aac422e54cee19b4a6045629f153e1c19d793148ae0144d6de7` | 22 |
| 3 | `layer7-blacklists-ut1.tar.gz` | 31169229 | `405033618` | `4191e2ebdc13e3c87d777103528bab4fda6b273bc40c62a2c39cb820ad493d36` | 24 |
| 4 | `blacklists-signing-public-key.pem` | 113 | `405033620` | `42180a4af6fae8e85af5f499eb63f4b1bb3e156ee14a117243c5c7e72071c0f7` | 4 |

**Inventário pré-cut (HEAD histórico):** os quatro URLs anónimos
respondiam **HTTP 200** (base do cut).

**Pós-cut / recheck:** API `asset_count=0`; anónimo @ cut+~1min com
residual CDN (302 / manifesto 200); recheck `20260812T012017Z` → **404×4**.
Ver evidência `03-recheck-cdn.txt`.

**Fora do cut (não mexer neste passo):**
- releases de pacote `v1.9.*` / `latest` do produto;
- primary `downloads.systemup.inf.br` / license-server;
- appliance `.254` / Cloudflare / DNS;
- tag git `blacklists-ut1-current` em si (mantém-se; só assets — rollback = re-upload).

---

## 4. Acção executada (`20260812T011217Z`)

GO gestor explícito cumprido — **só** `delete-asset` ×4 (release/tag intactos):

| Asset | id | Resultado |
|-------|-----|-----------|
| `layer7-blacklists-manifest.v1.txt` | `405033619` | deleted |
| `layer7-blacklists-manifest.v1.txt.sig` | `405033621` | deleted |
| `layer7-blacklists-ut1.tar.gz` | `405033618` | deleted |
| `blacklists-signing-public-key.pem` | `405033620` | deleted |

Pós-cut: `asset_count=0`; release id `313502667`; tag `blacklists-ut1-current` OK.

### Validação pós-cut

1. API release: **0 assets** — **PASS**  
2. Primary sem token → **401** — **PASS**  
3. Anónimo: residual CDN @cut (302→SAS; manifesto 200; tarball 404);
   recheck `012017Z` **404×4** — `03-recheck-cdn.txt`
4. Enforce / `.254` / CF / DNS / license-server: **não tocados**  
5. Rollback: [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md)

---

## 5. Escopo cumprido / não feito

- **Feito:** `delete-asset` ×4  
- **Não feito:** apagar release/tag; mexer `.254`/CF/DNS/license-server;
  e-mail; release de pacote

---

## 6. 404 esperado (P3-9 opção A)

GET anónimo nos quatro URLs de *download* desta tag devolve **404 esperado**.
Não é incidente. Não reupload sem GO (GA4.11 / A-06). URLs no cliente
**mantidos** de propósito. Nota canónica:
[`nota-404-esperado-cut-30.11.md`](nota-404-esperado-cut-30.11.md).

---

## Ligação

- Recheck P3-8 (`2026-08-14`): [`../tests/evidence/20260814T200900Z-p38-cut-recheck/`](../tests/evidence/20260814T200900Z-p38-cut-recheck/) (`asset_count=0`; 404×4)
- P3-9 opção A (404 esperado): [`nota-404-esperado-cut-30.11.md`](nota-404-esperado-cut-30.11.md)
- Evidência primary auth: [`../tests/evidence/20260812T003214Z-30.11-auth-get-254/`](../tests/evidence/20260812T003214Z-30.11-auth-get-254/)
- Rascunho coms (histórico; não emitir): [`../13-runbooks/content-mirror-comms-ga4.12-draft.md`](../13-runbooks/content-mirror-comms-ga4.12-draft.md)
- Ficha 30.1 (dec. 3 Sim em princípio): [`decisoes-humanas-30.1.md`](decisoes-humanas-30.1.md)
