# Evidência — P3-8 recheck read-only do cut `30.11` (`2026-08-14`)

**RUNID:** `20260814T200900Z`  
**Chat:** BG-146 (recheck) → BG-148 (fecho documental)  
**HEAD à data do recheck:** `b6b9e36` (P3-6 local). Paths do cut idênticos
em `f4894d1` (merge BG-147).  
**Modo:** só leitura — `gh api` + `curl` anónimo. Sem upload, sem token
Bearer, sem `.244` / `.254` / builder, sem alteração de release.

A auditoria `2026-08-14` deixou P3-8 aberto porque **não** contactou o
GitHub. O recheck abaixo foi o pedido do gestor em BG-146.

## Resultados

| Check | `20260812T013145Z` | Recheck 14-08 (BG-146) |
|-------|--------------------|------------------------|
| Release id | `313502667` | `313502667` |
| Tag | `blacklists-ut1-current` | igual; `prerelease=true`; `draft=false` |
| `asset_count` | **0** | **0** (`assets=[]`) |
| Anónimo nofollow ×4 | 404 / size 9 | **404 / size 9** |
| Anónimo follow ×4 | 404 / size 9 | **404 / size 9** (sem 302→CDN) |
| Residual CDN | nenhum neste vantage | **nenhum** (URL efectiva = GitHub) |
| Primary sem token | 401 | **401** (manifesto e `.sig`) |

Os quatro URLs anónimos:

- `…/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt`
- `…/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt.sig`
- `…/blacklists-ut1-current/layer7-blacklists-ut1.tar.gz`
- `…/blacklists-ut1-current/blacklists-signing-public-key.pem`

Contraste (**não** é P3-8): `releases/latest` = `v1.9.63`, 7 assets
(`.pkg` + `.sha256` + cadeia F1.2). O cut **não** tocou no canal do pacote.

Primary autenticado **não** foi reexecutado (token só no appliance;
`.254` proibido). A prova canónica de GET com Bearer permanece
[`../20260812T003214Z-30.11-auth-get-254/`](../20260812T003214Z-30.11-auth-get-254/).

**Veredicto:** cut `30.11` continua **FECHADO**. P3-8 **AVALIADO** —
sem mudança de runtime. Residual: **P3-9** (cliente/docs ainda anunciam
o URL morto).
