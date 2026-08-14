# Runbook — Bloqueio de deploy integral do HEAD (P0-1 / 30.11)

**Estado:** **ACTIVO** desde `2026-08-14`  
**Achado:** P0-1 em [`../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md)  
**Backlog:** BG-128  
**Não** substitui [`license-server-publicacao-segura.md`](license-server-publicacao-segura.md) (TLS/borda). Complementa-o.

---

## Regra

É **proibido** publicar o clone git HEAD de forma integral sobre
`192.168.100.244:/opt/layer7-license` enquanto o serving autenticado `30.11`
(live) não estiver **reconciliado e versionado** no git.

O HEAD tem check-in `30.13`. O live tem `30.13` **em overlay** sobre rotas /
volume / nginx de conteúdo que o HEAD **não** versiona. Um rsync ou
`docker compose` rebuild “do repo” apaga o primary de blacklists.

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

1. Inventário allowlist (cksum) do que é live-only vs HEAD.
2. Commit no git **só** do serving `30.11`:
   `index.js` (mount content), `content-auth.js` (+ teste), `routes/content.js`,
   volume `CONTENT_BLACKLISTS_DIR` no compose, bloco nginx `downloads.systemup.inf.br`,
   `.gitignore` a **bloquear** `content/blacklists/ut1/current/*`.
3. **Excluídos** do commit: `.env`, tarball/manifest/sig/pem, SPA live,
   bind `0.0.0.0`, vhost `.253`.
4. Runbook de publicação actualizado: deploy por paths, nunca integral.
5. GO humano explícito para o **primeiro** rebuild `api` pós-commit.
6. Smoke: health license + downloads; GET sem token = 401; check-in com e
   sem nonce. **Não** repetir GA5.9 sem GO.

Até lá o bloqueio permanece mesmo que P1-1 (ou outro código) entre no HEAD.

---

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
