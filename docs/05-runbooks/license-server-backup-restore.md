# Runbook — Backup e Restore do PostgreSQL do License Server

## Finalidade

Este runbook materializa a F2.5 para fechar o backup/restore minimo do
PostgreSQL do license server e o caminho essencial de recuperacao do stack.

Referencias normativas:

- `docs/01-architecture/f2-arquitetura-license-server.md`
- `docs/02-roadmap/f2-plano-de-implementacao.md`
- `docs/05-runbooks/license-server-segredos-bootstrap.md`
- `docs/10-license-server/MANUAL-USO-LICENCAS.md`

---

## 1. Escopo do backup oficial

Coberto por este runbook:

- dump logico do PostgreSQL via `./backup-postgres.sh`
- restore logico do PostgreSQL via `./restore-postgres.sh`
- validacao minima do ambiente apos restore

Fora deste runbook:

- rotacao da `ED25519_PRIVATE_KEY`
- observabilidade ampliada
- automacao de retention/pipeline

Os segredos do stack (`.env`, `POSTGRES_PASSWORD`, `ED25519_PRIVATE_KEY`) nao
entram no dump SQL e devem ser protegidos pelo runbook de segredos.

---

## 2. Pre-condicoes

- executar a partir de `license-server/` ou do directório real do deploy;
- `docker compose` funcional;
- stack `db` em execucao;
- espaco em disco suficiente para o dump;
- segredo e `.env` do deploy ja guardados em cofre interno.

---

## 3. Gerar backup

Backup com nome automatico:

```bash
cd /opt/layer7-license
./backup-postgres.sh
```

Backup com caminho explicito:

```bash
cd /opt/layer7-license
./backup-postgres.sh /var/backups/layer7/license-server-2026-04-01.sql
```

Comportamento do script:

- executa `pg_dump` dentro do container `db`;
- gera dump com `--clean --if-exists`;
- nao inclui ownership nem privileges locais do host.

Validacao minima do artefacto:

```bash
test -s /var/backups/layer7/license-server-2026-04-01.sql
head -n 5 /var/backups/layer7/license-server-2026-04-01.sql
```

---

## 4. Restore

Regra obrigatoria: nunca fazer restore sem janela controlada e backup do
estado actual antes da operacao.

```bash
cd /opt/layer7-license

# Backup do estado actual antes do restore
./backup-postgres.sh /var/backups/layer7/pre-restore-$(date -u +%Y%m%dT%H%M%SZ).sql

# Restore explicito
./restore-postgres.sh /var/backups/layer7/license-server-2026-04-01.sql --yes

# Revalidar a API sobre o banco restaurado
docker compose restart api
```

Comportamento do restore:

- aplica o dump SQL sobre o `POSTGRES_DB` actual;
- exige confirmacao explicita `--yes`;
- falha fechado se o ficheiro nao existir ou se o SQL parar com erro.

---

## 5. Validacao minima apos restore

```bash
cd /opt/layer7-license

docker compose exec -T api node bootstrap-admin.js status
curl -s -H 'Host: license.systemup.inf.br' http://127.0.0.1:8445/api/health
```

Se o operador perdeu a password administrativa ou se o banco restaurado
voltar antes da ultima rotacao, usar o fluxo oficial:

```bash
docker compose exec -T \
  -e ADMIN_BOOTSTRAP_PASSWORD='substituir_por_segredo_real' \
  api node bootstrap-admin.js reset-password --email admin@systemup.inf.br
```

---

## 6. Recuperacao minima do stack

Quando o host ou o volume do banco precisarem de recomposicao:

1. Restaurar `.env` e os segredos operacionais a partir do cofre interno.
2. Subir `db`, `api`, `web` e `nginx` com `docker compose up -d --build`.
3. Restaurar o dump SQL com `./restore-postgres.sh <dump.sql> --yes`.
4. Reiniciar a API e validar `/api/health`.
5. Se necessario, redefinir a password do admin via `bootstrap-admin.js`.

---

## 7. Troubleshooting

### Backup falha com erro de `docker compose`

- confirmar que a stack foi iniciada no directório correcto;
- confirmar que o serviço se chama `db` no `docker-compose.yml`.

### Restore falha sem `--yes`

- comportamento esperado para evitar destrutividade acidental;
- repetir o comando apenas depois de confirmar a janela.

### Restore aplicado mas login nao funciona

- validar `.env` restaurado e segredo Ed25519;
- validar se a password administrativa mudou depois da data do dump;
- usar `bootstrap-admin.js reset-password` apenas se o problema for
  credencial.

---

## 8. Rollback

- Se o restore falhar ou trouxer estado errado, aplicar imediatamente o dump
  `pre-restore-*.sql` gerado antes da operacao.
- Rollback de codigo/docs: `git revert <commit-da-f2.5>`
- Nao improvisar restore manual de tabelas parciais fora de janela controlada.
