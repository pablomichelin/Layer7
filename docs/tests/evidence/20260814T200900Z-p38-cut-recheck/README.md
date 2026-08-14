# Evidência — P3-8 recheck read-only do cut `30.11` (`2026-08-14`)

**RUNID:** `20260814T200900Z`  
**Confirmação deste turno:** `20260814T201800Z` (BG-148, só leitura)  
**Chat:** BG-146 (primeiro recheck 14-08) → BG-148 (fecho + confirmação)  
**HEAD:** `1cd2320` (fecho documental); paths do cut idênticos desde `f4894d1`.  
**Modo:** só leitura — `gh api` + `curl` anónimo. Sem upload, sem token
Bearer, sem `.244` / `.254` / builder / Docker / segredo, sem alteração
de release.

A auditoria `2026-08-14` deixou P3-8 aberto porque **não** contactou o
GitHub. O gestor pediu o recheck em BG-146; este turno **reconfirmou**
os mesmos números.

## Resultados

| Check | `20260812T013145Z` | 14-08 (BG-146 + BG-148) |
|-------|--------------------|-------------------------|
| Release id | `313502667` | `313502667` |
| Tag | `blacklists-ut1-current` | igual; `prerelease=true`; `draft=false` |
| `asset_count` | **0** | **0** (`assets=[]`) |
| Anónimo nofollow ×4 | 404 / size 9 | **404 / size 9** |
| Anónimo follow ×4 | 404 / size 9 | **404 / size 9** (sem 302→CDN) |
| Residual CDN | nenhum neste vantage | **nenhum** (URL efectiva = GitHub) |
| Primary sem token | 401 | **401** (manifesto e `.sig`) |

Os quatro URLs anónimos **permanecem anunciados** (P3-9; **não** removidos):

- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt`
- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt.sig`
- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-ut1.tar.gz`
- `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/blacklists-signing-public-key.pem`

Contraste (**não** é P3-8): `releases/latest` = `v1.9.63`, 7 assets
(`.pkg` + `.sha256` + cadeia F1.2). O cut **não** tocou no canal do pacote.

Primary autenticado **não** foi reexecutado (token só no appliance;
`.254` proibido). A prova canónica de GET com Bearer permanece
[`../20260812T003214Z-30.11-auth-get-254/`](../20260812T003214Z-30.11-auth-get-254/).

**Veredicto:** cut `30.11` continua **FECHADO**. P3-8 **fechado como
evidência**. Residual P3-9 fechado à parte (BG-150 / opção A):
docs «404 esperado»; URLs **não** removidos — ver
[`../../../09-blocking/nota-404-esperado-cut-30.11.md`](../../../09-blocking/nota-404-esperado-cut-30.11.md).
