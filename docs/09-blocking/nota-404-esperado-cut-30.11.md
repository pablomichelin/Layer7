# Nota — 404 esperado no espelho GitHub após o cut `30.11` (P3-9 opção A)

**Estado:** **AVALIADO no git** (`2026-08-14`; **BG-150** / opção A — **FEITO documental**)  
**URLs:** **não** removidos (legado / fallback de runtime).  
**Objectivo:** eliminar a confusão operacional. **Sem** mudança de runtime.  
**HEAD de partida:** `82c69d6` (fecho documental P3-8)  
**Evidência:** [`../tests/evidence/20260814T204500Z-p39-404-esperado/`](../tests/evidence/20260814T204500Z-p39-404-esperado/)

---

## Leitura em 30 segundos

1. A tag `blacklists-ut1-current` foi **cortada** em `30.11` (`20260812T011217Z`).
2. Os **quatro** URLs GitHub de *download* dessa tag devolvem **404 esperado**.
   Não é incidente. Não é regressão do updater.
3. O **primary** (`downloads.systemup.inf.br`) **exige token**: sem token = **401**.
4. Isto **não** é o canal do pacote (`releases/latest` / `v1.9.63` / botão
   «Verificar actualização» — BG-030).
5. Isto **não** é motivo para reupload GA4.11. Reupload sem GO reabre A-06.
6. O espelho no cliente é **legado / fallback de runtime**. **Não** se remove
   neste bloco (`update-blacklists.sh` / `layer7.inc` / `config.json.sample`
   intactos).

---

## Contrato (prova P3-8, leitura P3-9)

Release rolling `blacklists-ut1-current` (id `313502667`). Recheck
`20260814T200900Z` / confirmação `20260814T201800Z`:

| Check | Resultado | Leitura operacional |
|-------|-----------|---------------------|
| `asset_count` | **0** (`assets=[]`) | Cut `30.11` — release vazia de propósito |
| GET anónimo nos 4 URLs de *download* | **404** / size 9 (nofollow e follow; sem 302→CDN) | **404 esperado** |
| Primary sem token | **401** | Token obrigatório; canal autenticado intacto |
| `releases/latest` / `v1.9.63` | 7 assets (`.pkg` + cadeia F1.2) | Canal do **pacote** — outra tag |

Os quatro URLs (legado / fallback ainda anunciado no runtime; **não** removidos):

- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt`
- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt.sig`
- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-ut1.tar.gz`
- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/blacklists-signing-public-key.pem`

---

## O que isto **não** é

| Confusão | Facto |
|----------|--------|
| «O pacote partiu» / `latest` 404 | Canal do pacote é `v1.9.63`. A tag `blacklists-ut1-current` é **ignorada** pelo updater (BG-030). |
| «Há que repor os assets» (GA4.11) | O 404 **é** o cut. Reupload sem GO reabre o espelho anónimo (A-06). |
| «Há que apagar o URL do `.sh` / GUI» | Espelho = legado / fallback de runtime. Remover = bloco futuro + GO + `PORTVERSION`. **Não** este bloco. |
| «O primary também morreu» | Primary sem token = **401** (esperado). Com token + CDN OK = 200 (prova `20260812T003214Z`). |

P3-8 = o cut no GitHub continua vazio. P3-9 opção A = o anúncio deixa de
confundir ops. GA4.11 = rollback comercial **só com GO** — o contrário de
«fechar» o 404.

---

## O que o updater faz (código intacto)

- `≥1.9.53`: sem token válido **não** há fetch de conteúdo corrente
  (hold-active / LKG / enforce intactos).
- Com token, a ordem default é primary Systemup e depois o mirror GitHub
  (legado / fallback).
- 404 no manifesto GitHub falha o candidato **antes** de `pkeyutl` / tarball;
  não promove snapshot; não apaga LKG; não desliga enforce.
- Os 404×4 do P3-8 foram `curl` de auditoria, não o updater.

---

## Ligação

- Prep/fecho do cut: [`prep-cut-30.11-espelho.md`](prep-cut-30.11-espelho.md)
- Recheck P3-8: [`../tests/evidence/20260814T200900Z-p38-cut-recheck/`](../tests/evidence/20260814T200900Z-p38-cut-recheck/)
- Evidência P3-9 / BG-150: [`../tests/evidence/20260814T204500Z-p39-404-esperado/`](../tests/evidence/20260814T204500Z-p39-404-esperado/)
- Rollback (só com GO — **não** por causa deste 404): [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md)
- Auditoria: [`auditoria-licencas-auth-deploy-2026-08-14.md`](auditoria-licencas-auth-deploy-2026-08-14.md)
