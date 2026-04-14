# Runbook — Segredos e Bootstrap Administrativo do License Server

## Finalidade

Este runbook materializa a F2.5 para fechar a disciplina minima de segredos,
ownership operacional e bootstrap administrativo do license server sem abrir
novas superfícies de API nem depender de credenciais fixas em documentação.

Referencias normativas:

- `docs/01-architecture/f2-arquitetura-license-server.md`
- `docs/02-roadmap/f2-plano-de-implementacao.md`
- `docs/03-adr/ADR-0007-publicacao-segura-license-server.md`
- `docs/03-adr/ADR-0008-autenticacao-e-sessao-license-server.md`
- `docs/03-adr/ADR-0009-protecao-superficie-administrativa-license-server.md`
- `docs/03-adr/ADR-0010-integridade-transacional-e-validacao-crud-license-server.md`
- `docs/10-license-server/MANUAL-USO-LICENCAS.md`

---

## 1. Politica minima oficial

- Nenhum segredo operacional do license server pode ser commitado em Git,
  issue, PR, changelog ou manual operacional.
- O ficheiro `.env` do deploy e secreto local do host; deve existir fora do
  versionamento e com permissao minima `600`.
- `POSTGRES_PASSWORD` permanece segredo persistente local do stack actual.
- `ED25519_PRIVATE_KEY` pode ser fornecida por variavel directa ou por
  `ED25519_PRIVATE_KEY_FILE` quando houver montagem segura do ficheiro no
  container da API.
- `ADMIN_BOOTSTRAP_PASSWORD` e segredo transitório de bootstrap/recuperacao;
  nao e credencial normativa permanente e deve ser removido do shell/.env
  logo apos o uso.
- `seed.js` permanece apenas por compatibilidade e delega para o bootstrap
  oficial; o fluxo normativo passa a ser `bootstrap-admin.js`.

---

## 2. Ownership operacional

| Segredo / activo | Quem gera | Quem guarda | Quem usa | Rotacao minima |
|------------------|-----------|-------------|----------|----------------|
| `POSTGRES_PASSWORD` | owner operacional do license server | cofre interno da Systemup + `.env` local do host | PostgreSQL e API via `DATABASE_URL` | em incidente, troca de host ou suspeita de exposicao |
| `ADMIN_BEARER_JWT_SECRET` | owner operacional do license server | cofre interno da Systemup + `.env` local do host | backend da API para a ponte Bearer administrativa opcional | em incidente, troca de host ou suspeita de exposicao |
| `JWT_SECRET` | legado de deploys antigos | `.env` local do host apenas enquanto houver stack antiga | compatibilidade transitória de upgrade para a ponte Bearer | remover/substituir por `ADMIN_BEARER_JWT_SECRET` apos alinhar o deploy |
| `ED25519_PRIVATE_KEY` | owner de licenciamento/Systemup | cofre interno offline + host de producao | backend da API para assinar `.lic` | em incidente de exposicao ou troca formal do par de chaves |
| `ADMIN_BOOTSTRAP_PASSWORD` | owner operacional do license server | temporariamente no shell seguro ou cofre interno | apenas operador autorizado durante `init` ou `reset-password` | a cada bootstrap, recuperacao ou suspeita de exposicao |
| cookie `layer7_admin_session` | backend em runtime | browser do operador + tabela `admin_sessions` | frontend/backend same-origin | revogacao por logout, reset de password ou expiracao |

---

## 3. Bootstrap oficial

Ferramenta oficial:

```bash
docker compose exec -T api node bootstrap-admin.js status
docker compose exec -T api node bootstrap-admin.js init
docker compose exec -T api node bootstrap-admin.js reset-password --email admin@systemup.inf.br
```

Comportamento oficial:

- `status`: mostra quantos admins existem; nao altera estado.
- `init`: cria o primeiro admin apenas se a tabela `admins` estiver vazia.
- `reset-password`: redefine a password de um admin existente e revoga as
  sessoes activas desse admin no mesmo passo.
- ambos os fluxos auditam o evento em `admin_audit_log`.

---

## 4. Criar o primeiro admin

Pre-condicoes:

- stack `db` e `api` em execucao;
- email, nome e password bootstrap definidos pelo owner operacional;
- password registada no cofre interno antes da execucao.

Exemplo:

```bash
cd /opt/layer7-license

ADMIN_EMAIL='admin@systemup.inf.br'
ADMIN_NAME='Layer7 Admin'
ADMIN_PASSWORD="$(openssl rand -base64 24)"

docker compose exec -T \
  -e ADMIN_BOOTSTRAP_EMAIL="$ADMIN_EMAIL" \
  -e ADMIN_BOOTSTRAP_NAME="$ADMIN_NAME" \
  -e ADMIN_BOOTSTRAP_PASSWORD="$ADMIN_PASSWORD" \
  api node bootstrap-admin.js init

unset ADMIN_PASSWORD
```

Resultado esperado:

- comando devolve `Admin inicial criado`;
- `bootstrap-admin.js status` passa a indicar `total_admins >= 1`;
- o primeiro login administrativo passa a ocorrer apenas pelo canal oficial
  `https://license.systemup.inf.br`.

---

## 5. Recuperar password administrativa

Usar apenas em incidente ou perda da credencial activa.

```bash
cd /opt/layer7-license

TARGET_ADMIN_EMAIL='admin@systemup.inf.br'
NEW_ADMIN_PASSWORD="$(openssl rand -base64 24)"

docker compose exec -T \
  -e ADMIN_BOOTSTRAP_PASSWORD="$NEW_ADMIN_PASSWORD" \
  api node bootstrap-admin.js reset-password --email "$TARGET_ADMIN_EMAIL"

unset NEW_ADMIN_PASSWORD
```

Resultado esperado:

- password do admin e redefinida;
- sessoes activas desse admin sao revogadas;
- o operador volta a entrar pelo login normal em HTTPS/TLS.

---

## 6. Regras operacionais

- Nao manter `ADMIN_BOOTSTRAP_PASSWORD` fixo na documentacao, `.env.example`
  preenchido, shell history partilhado ou ticket.
- Se o segredo bootstrap tiver de ser persistido temporariamente para
  handoff, usar cofre interno e apagar do host apos confirmacao do acesso.
- Se `ED25519_PRIVATE_KEY_FILE` for usado, o ficheiro montado deve ficar
  fora do Git e com ACL restrita ao runtime do container.
- Se houver suspeita de exposicao da `ED25519_PRIVATE_KEY`, parar a emissao
  de novas licencas e abrir procedimento formal de rotacao; isso nao faz
  parte da F2.5 e deve ser tratado como incidente operacional.

---

## 7. Troubleshooting

### `bootstrap-admin.js init` recusa com "ja existe pelo menos um admin"

- comportamento esperado depois do primeiro bootstrap;
- confirmar o estado actual:

```bash
docker compose exec -T api node bootstrap-admin.js status
```

- se o problema for apenas password perdida, usar `reset-password` e nao
  `init`.

### `reset-password` falha com "Admin nao encontrado"

- confirmar email exacto no banco:

```sql
SELECT id, email, name, created_at
FROM admins
ORDER BY id;
```

### Falta de segredo Ed25519 no arranque

- validar `.env` local do host;
- validar montagem/ACL do ficheiro caso `ED25519_PRIVATE_KEY_FILE` seja usado;
- nao arrancar o backend com segredo placeholder `CHANGE_ME`.

---

## 8. Rollback

- Rollback de codigo/docs: `git revert <commit-da-f2.5>`
- Rollback operacional do bootstrap:
  - se o `init` foi executado por engano num banco vazio, remover o admin
    criado apenas com validacao humana e janela controlada;
  - se o `reset-password` foi executado por engano, repetir o comando com a
    credencial correcta e revalidar o login.
- Nao voltar a tratar `seed.js` com defaults documentados como fluxo oficial.
