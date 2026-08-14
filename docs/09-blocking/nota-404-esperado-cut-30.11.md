# Nota — 404 esperado no espelho GitHub após o cut `30.11` (P3-9 opção A)

**Estado:** **AVALIADO no git** (`2026-08-14`; BG-150 / opção A)  
**Pedido:** documentar que o 404 anónimo do espelho é o **contrato** pós-cut;
manter os URLs no cliente. **Sem** mudança de runtime.  
**Não é:** P3-8 (prova de que o cut continua vazio) · GA4.11 (repor assets)  
**HEAD de partida:** `82c69d6` (fecho documental P3-8)

---

## Contrato

O cut `30.11` (`20260812T011217Z`) retirou os quatro assets da release
rolling `blacklists-ut1-current` (id `313502667`). Recheck P3-8
(`20260814T200900Z` / confirmação `20260814T201800Z`):

| Check | Resultado | Leitura operacional |
|-------|-----------|---------------------|
| `asset_count` | **0** (`assets=[]`) | Release vazia de propósito |
| GET anónimo nos 4 URLs de *download* | **404** / size 9 (nofollow e follow; sem 302→CDN) | **404 esperado** — não é incidente |
| Primary `downloads.systemup.inf.br` sem token | **401** | Canal autenticado intacto |
| `releases/latest` / `v1.9.63` | 7 assets (`.pkg` + cadeia F1.2) | Canal do **pacote** (BG-030) — **não** é este espelho |

Os quatro URLs anónimos (ainda anunciados no cliente e neste manual):

- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt`
- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt.sig`
- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-ut1.tar.gz`
- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/blacklists-signing-public-key.pem`

**Proibido** tratar o 404 como regressão, «reparar» com `gh release upload`,
ou reabrir o espelho anónimo sem GO humano (GA4.11 / A-06).

---

## Três coisas distintas

| ID | Pergunta | Estado |
|----|----------|--------|
| **P3-8** | O cut no GitHub ainda está vazio? | **Fechado** (BG-146/BG-148) — prova, não cliente |
| **P3-9 opção A** | O anúncio do URL morto confunde ops? | **Este bloco** — docs «404 esperado»; URLs **mantidos** |
| **GA4.11** | Repor assets na tag? | Runbook pronto; **proibido sem GO** — reabre A-06 |

Remover os URLs do runtime (`update-blacklists.sh` / `layer7.inc` /
`config.json.sample`) é um **bloco futuro** com GO + `PORTVERSION`. Não é
esta opção. Manter o URL permite rollback GA4.11 à frota **sem** `.pkg`
novo (contrato 30.8).

---

## O que o updater faz (código intacto)

- `≥1.9.53`: sem token válido **não** há fetch de conteúdo corrente
  (hold-active / LKG / enforce intactos).
- Com token, a ordem default é primary Systemup e depois o mirror GitHub.
- 404 no manifesto GitHub falha o candidato **antes** de `pkeyutl` / tarball;
  não promove snapshot; não apaga LKG; não desliga enforce.
- Os 404×4 do P3-8 foram `curl` de auditoria, não o updater.

---

## Ligação

- Prep/fecho do cut: [`prep-cut-30.11-espelho.md`](prep-cut-30.11-espelho.md)
- Recheck P3-8: [`../tests/evidence/20260814T200900Z-p38-cut-recheck/`](../tests/evidence/20260814T200900Z-p38-cut-recheck/)
- Rollback (só com GO): [`../13-runbooks/content-mirror-rollback-ga4.11.md`](../13-runbooks/content-mirror-rollback-ga4.11.md)
- Auditoria: [`auditoria-licencas-auth-deploy-2026-08-14.md`](auditoria-licencas-auth-deploy-2026-08-14.md)
