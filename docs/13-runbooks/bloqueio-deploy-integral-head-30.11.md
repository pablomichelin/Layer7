# Runbook — Bloqueio de deploy integral do HEAD (P0-1 / 30.11)

**Estado:** **ACTIVO** desde `2026-08-14` — **não encerrado**  
**Achado:** P0-1 em [`../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md)  
**Backlog:** BG-128  
**Não** substitui [`license-server-publicacao-segura.md`](license-server-publicacao-segura.md) (TLS/borda). Complementa-o.

**Checkpoint `2026-08-14` (P0-1 git):** inventário allowlist **FEITO**
(read-only `15:31:37Z`) e serving `30.11` **versionado no git** (7 paths).
Isso **não** levanta o freeze. Deploy integral / rebuild / overlay no
`.244` continua **proibido** até GO humano do primeiro rebuild `api` +
smoke. Um rebuild integral do HEAD injectaria também P0-2…P1-4, que o
contentor live `bbc74a5` **não** tem.

---

## Objectivo / impacto / risco / teste / rollback — commit allowlist (P0-1 git)

| Campo | Valor |
|-------|--------|
| Objectivo | Versionar no git o serving autenticado `30.11` (rotas, volume, vhost, ignore) para um clone limpo resolver `require('./routes/content')` sem tarball/`.env` |
| Impacto | Só 7 paths `license-server/` + docs deste bloco. **Não** altera `index.js`, check-in/auth/TOTP, SPA, package/daemon, `.env`/bind, snapshot real, tokens. **Sem** host/deploy/restart |
| Risco | Baixo no git. Residual: P0-1 **mantém-se** — rebuild integral HEAD→`.244` continua a apagar o overlay live e a injectar P0-2…P1-4 |
| Teste | Hashes 3 JS + `.gitkeep` + compose = inventário; `npm test` content + backend `173/173`; `git archive` resolve `routes/content`; `check-ignore` do snapshot; `.gitkeep` tracked; diff compose/nginx exacto; scan de segredo |
| Rollback | `git revert` deste commit; o live não muda. Freeze permanece |

Hashes reconfirmados (worktree = inventário `2026-08-14T15:31:37Z`):

| Path | SHA256 |
|------|--------|
| `backend/src/content-auth.js` | `2b4a480ed1a7d67381582dede112e4761cd0d0620ff24e200ef190da2ee697fc` |
| `backend/src/content-auth.test.js` | `f06962ac40def35e3897a046cc3f709e56fcab1f98e3f66f27823782798c389d` |
| `backend/src/routes/content.js` | `8b039f2363a2d38ca46195e54feda1de6e1340f92b0287b2d7fb42994226e324` |
| `content/blacklists/ut1/current/.gitkeep` | `b737992495499531e471c1af53a8aabf551f8b4365c52670b6d03950a4e321d5` |
| `docker-compose.yml` | `7845ac363911bce8828a957cd520ff2c7d40316e37cac5eff29df4d98974f63a` |

P2-6 Bloco A (`2026-08-14`) **não** altera este compose. Ignore +
`USER node` no backend ficam no git; rebuild `api` no `.244` continua
**proibido** até GO do primeiro overlay + smoke.

---

## Regra

É **proibido** publicar o clone git HEAD de forma integral sobre
`192.168.100.244:/opt/layer7-license` enquanto o serving autenticado `30.11`
não estiver **reconciliado, versionado e publicado por overlay** com GO.

O serving `30.11` **já está no git** (allowlist). O live continua a ser
overlay `30.13` sobre stack de conteúdo. Um rsync ou `docker compose`
rebuild “do repo” no `.244` **ainda** apaga o primary / injecta código
HEAD que o live não corre.

---

## Proibido

- `rsync` do tree `license-server/` para `/opt/layer7-license` (com ou sem `--delete`)
- `docker compose build/up` a partir do HEAD a substituir compose/nginx live
- Playbook / script genérico de “deploy do main”
- `git add -A` do worktree sujo `30.11` (tarball, snapshot, risco de `.env`)
- Sincronizar SPA/`web` (HEAD `2.1.0` ≠ live `2.0.0`) sem GO de portal
- Gravar bind live `0.0.0.0:8445` como default do repo
- Usar a tag `layer7-license-api:pre-30.13-20260814T142739Z` salvo incidente
  **do overlay 30.13** (essa tag **reabre** rejeição de `nonce` e **mantém** 30.11)

---

## Permitido (sem levantar o bloqueio)

- Overlay por **allowlist de paths** (padrão já usado em `20260814T142739Z`):
  só ficheiros `30.13` extraídos de `git archive`, serviço `api` apenas
- Health / smoke já documentados (sem GA5.9 sem GO)
- Trabalho **local** de código que **não** se publica no `.244` (ex.: P1-1)
- Docs; este freeze; evidência já no repo

---

## Como levantar P0-1

Só depois de **todos**:

1. Inventário allowlist (cksum) do que é live-only vs HEAD. **FEITO**
   (`2026-08-14T15:31:37Z`; hashes reconfirmados neste bloco).
2. Commit no git **só** do serving `30.11`:
   `content-auth.js` (+ teste), `routes/content.js`, `.gitkeep`,
   volume `CONTENT_BLACKLISTS_DIR` no compose, bloco nginx
   `downloads.systemup.inf.br` sobre o nginx HEAD (P1-2 `$remote_addr`
   preservado), `.gitignore` a **bloquear** o snapshot.
   `index.js` **não** entrou neste commit — o mount `contentRoutes` já
   estava no HEAD (P0-2). **FEITO** neste bloco (git only).
3. **Excluídos** do commit: `.env`, tarball/manifest/sig/pem, SPA live,
   bind `0.0.0.0`, vhost `.253`, `package-lock.json`, check-in/auth/TOTP.
   **FEITO**.
4. Runbook de publicação actualizado: deploy por paths, nunca integral.
   **FEITO** neste bloco.
5. GO humano explícito para o **primeiro** rebuild `api` pós-commit.
   **PENDENTE** — sem isto P0-1 **não** cai.
6. Smoke: health license + downloads; GET sem token = 401; check-in com e
   sem nonce. **Não** repetir GA5.9 sem GO. **PENDENTE**.

Até 5+6 o bloqueio permanece mesmo com o serving versionado no git.

---

## Rollback preferido (P2-16)

A tag `layer7-license-api:pre-30.13-20260814T142739Z` **não** é o
rollback padrão nem o `latest`.

| Situação | Alvo | Não usar |
|----------|------|----------|
| Violação do freeze / imagem `api` mudou | Imagem pós-overlay `bbc74a5…` (`sha256:bbc74a53651f835d4dd0b07f2d5f97c2a3cd25e99c8e965309aa6ea018aadb9f`) | Tag `pre-30.13` como `latest` |
| Incidente específico do overlay `30.13` (reverter o dual-mode nonce) | Tag `layer7-license-api:pre-30.13-20260814T142739Z` | — |

Usar a tag `pre-30.13` como rollback habitual reabre rejeição de
`nonce` (GA5.9 FAIL) e **mantém** 30.11.

## Rollback se alguém violar o freeze

1. **Não** `docker compose down -v`.
2. Restaurar compose/nginx live a partir do backup do host (não do HEAD).
3. Se só a imagem `api` mudou: voltar à imagem que ainda tinha content
   (`bbc74a5…` pós-30.13, **não** a tag `pre-30.13` salvo o incidente ser o overlay).
4. Confirmar snapshot em disco e `.env` (perms `600`) intactos.
5. Health público + origin; GET primary sem token = 401.

Procedimento detalhado do overlay 30.13:
[`../tests/evidence/20260814T142739Z-30.13-api-244/README.md`](../tests/evidence/20260814T142739Z-30.13-api-244/README.md).

Nenhum segredo neste ficheiro.
