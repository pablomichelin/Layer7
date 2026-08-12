# Evidência — Cut espelho anónimo `30.11`

**RUNID:** `20260812T011217Z`  
**GO gestor:** explícito (`2026-08-12`) — delete-asset ×4 apenas  
**Repo:** `pablomichelin/Layer7`  
**Release/tag:** `blacklists-ut1-current` (id `313502667`) — **preservados**  
**Veredicto:** **CUT_DONE**

## Acção executada

| Asset | id API | size | Resultado |
|-------|--------|-----:|-----------|
| `layer7-blacklists-manifest.v1.txt` | `405033619` | 823 | **deleted** |
| `layer7-blacklists-manifest.v1.txt.sig` | `405033621` | 64 | **deleted** |
| `layer7-blacklists-ut1.tar.gz` | `405033618` | 31169229 | **deleted** |
| `blacklists-signing-public-key.pem` | `405033620` | 113 | **deleted** |

Pós-cut: `gh api …/releases/tags/blacklists-ut1-current` → **`asset_count=0`**.  
Tag object sha: `653740b7a5e2d92638044a7e7369a0ab7fa9ec32`.

## Validação

| Check | Resultado |
|-------|-----------|
| Release/tag intactos | **PASS** |
| Assets na release (API) | **0** (imediatamente e no recheck) |
| Primary sem token (manifest/sig) | **401** |
| Primary auth | não refeito no `.254` (proibido); evidência prévia `20260812T003214Z` **PASS** |
| Anónimo @ cut+~1min (`02-validate.txt`) | residual CDN: nofollow **302**; follow manifesto **200\|823**; tarball CDN **404** |
| Anónimo @ recheck `01:20:17Z` (`03-recheck-cdn.txt`) | **404×4** (nofollow + follow) — residual CDN **limpo** neste vantage |
| License `/api/health` | **200** |

**Residual CDN (honesto):** no instante pós-cut o GitHub ainda emitia
`302`→`release-assets.githubusercontent.com` (SAS) para blobs pequenos
(manifesto/PEM **200**). A release **já tinha** `asset_count=0`. Recheck
`20260812T012017Z` (~8 min depois) viu **404** em todos os quatro URLs
anónimos — residual limpo neste ponto de observação. Outros edges/PoPs
podem divergir até TTL; o cut canónico é a API da release vazia.

## Rollback

[`../../13-runbooks/content-mirror-rollback-ga4.11.md`](../../13-runbooks/content-mirror-rollback-ga4.11.md)
— `gh release upload blacklists-ut1-current … --clobber` a partir de
`/opt/layer7-license/content/blacklists/ut1/current/`.

## Fora de escopo (cumprido)

Sem alteração `.254` / Cloudflare / DNS / license-server / release de pacote.
Sem apagar release nem tag. Sem recriar assets GitHub neste fecho documental.
