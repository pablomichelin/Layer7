# Evidência — deploy controlado API `30.13` no `.244`

**Run ID:** `20260814T142739Z-30.13-api-244`  
**GO:** deploy controlado **somente** da API `30.13` no host `.244`  
**UTC:** `2026-08-14T14:27:39Z` (início backup/tag) · fecho smoke `2026-08-14T14:29:15Z`  
**Git HEAD sincronizado (ficheiros 30.13):** `5754bfa40478e942e4c2873bdd6bbad4b6a585f7`  
**Código / frontend / `.254` / licenças novas:** **intocados**

| Campo | Valor |
|-------|--------|
| Host | `192.168.100.244` (`zabbix6`) |
| Stack | `/opt/layer7-license` |
| Serviço alterado | **só** `api` |
| Web / db / nginx imagem | **intactas** (nginx só `restart`) |
| Apache / Grafana / MySQL / Zabbix | **intactos** (`active`) |
| `.254` | **não tocado** |
| Licença nova | **não criada** |

## Backup e tag de rollback

| Item | Valor |
|------|--------|
| Dump PostgreSQL | `/var/backups/layer7/license-server-pre-30.13-20260814T142739Z.sql` |
| Tamanho | `69834` bytes |
| SHA256 dump | `1079996e760173228ac062612ed169b74142c05e2bdce76170fc691996a66f79` |
| Tag imutável | `layer7-license-api:pre-30.13-20260814T142739Z` |
| Imagem pré | `sha256:bdc2059b958bf6917c1f427db58b029ef22cd02db030fd7f101d221095f7da16` |
| Imagem pós | `sha256:bbc74a53651f835d4dd0b07f2d5f97c2a3cd25e99c8e965309aa6ea018aadb9f` |
| `.env` | presente; perms `600`; **não** lido nem transmitido |

`rsync --delete` **não** foi usado. Restore SQL **não** foi executado. `docker compose down -v` **não** foi executado.

## Compose / sync

Live `docker-compose.yml` e `nginx/nginx.conf` **preservados** (hashes iguais pré/pós).  
HEAD commitado **não** tem o volume `CONTENT_BLACKLISTS_DIR` nem as rotas `content` que o live já corre (30.11). Um rsync integral do `HEAD` quebraria conteúdo.  
Sincronizados **só** ficheiros `30.13` extraídos de `git archive HEAD`:

- `backend/src/routes/check-in.js`
- `backend/src/crud-validation.js`
- `backend/src/check-in-policy.js`
- testes `check-in-signed` / `check-in-policy`

`index.js` live (rotas content) e `content-auth.js` **preservados**. Frontend **não** sincronizado.

## Gates

| Gate | Resultado | Nota |
|------|-----------|------|
| Preflight estado/containers/portas | **PASS** | 4 contentores layer7; Apache/Grafana/MySQL/Zabbix `active`; `8445` no host; `3001`/`5432` não expostos |
| `.env` existe | **PASS** | `600` |
| Backup + tag | **PASS** | caminhos acima |
| Testes backend locais | **PASS** | `31/31` (`check-in-signed` + policy + crud-validation) |
| Build/up só `api` + restart nginx | **PASS** | web/db image IDs iguais aos do preflight |
| Health local `127.0.0.1:8445/api/health` | **PASS** | HTTP `200` `status=ok` |
| Health público `https://license.systemup.inf.br/api/health` | **PASS** | HTTP `200` `status=ok` |
| POST check-in sintético + nonce 43 base64url | **PASS** | HTTP `404` licença inexistente; **não** `400 nonce`; envelope `data`+`sig` |
| POST check-in sem nonce (legado) | **PASS** | HTTP `404` JSON legado; **sem** envelope |
| Admin + id 13 | **PASS** | `total_admins=1`; id 13 `active`, não arquivada, não revogada; admin id 1 `is_active` |
| Restantes stacks/portas | **PASS** | 22/80/3000/3306/8445/10050/10051; web/db intactos |

**GA5.9 de campo** (revogação no `.254`) **não** foi repetido neste bloco.

## Âmbito do rollback desta evidência (P2-16)

Este procedimento **só** reverte o overlay `30.13` deste run
(`20260814T142739Z`). **Não** é o rollback padrão/`latest` do `.244`.

O rollback **preferido** do freeze P0-1 é a imagem pós-overlay
`bbc74a5…` (`sha256:bbc74a53651f835d4dd0b07f2d5f97c2a3cd25e99c8e965309aa6ea018aadb9f`).
Retaggar `pre-30.13` → `latest` reabre rejeição de `nonce` (GA5.9 FAIL)
e **mantém** 30.11. Ver
[`../../../13-runbooks/bloqueio-deploy-integral-head-30.11.md`](../../../13-runbooks/bloqueio-deploy-integral-head-30.11.md).

## Rollback executável (só incidente específico de `30.13`)

```sh
cd /opt/layer7-license
docker tag layer7-license-api:pre-30.13-20260814T142739Z layer7-license-api:latest
docker compose up -d --no-deps --no-build api
docker compose restart nginx
curl -sS -o /dev/null -w "%{http_code}\n" -H "Host: license.systemup.inf.br" http://127.0.0.1:8445/api/health
```

Não restaurar o SQL salvo incidente de dados. Não usar `docker compose down -v`.
Não usar estes comandos como rollback padrão/`latest`.

Nenhum `.env` / chave / token / licença neste directório.
