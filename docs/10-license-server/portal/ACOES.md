# Acções — Portal Admin de Licenças

Diário obrigatório. Entrada por bloco (mesmo que só documental).  
Mais recente no topo.

---

## 2026-08-14 — BG-127 licença de teste `BG-127-TEST`

| Campo | Valor |
|-------|--------|
| Tipo | operação live (sem código / sem bump visual) |
| Versão | `2.1.0` (sem alteração) |
| Objectivo | Emitir licença de teste dedicada para GA2.7 / GA5.9 sem tocar id 13 |
| Impacto | Cliente id **9** `BG-127-TEST`; licença id **14** expiry `2026-08-16` `base`; depois **revogada** no GA5.9 |
| Risco | Médio (soak `.254`); mitigado: backup, bind só ao FP do `.254`, restore integral |
| Teste | GA2.7 PASS; GA5.9 FAIL campo (API live rejeita `nonce`); id 13 intacto |
| Rollback | `.lic`/JSON de produção restaurados; sessão admin temporária revogada |
| Resultado | **FEITO** — evidência `20260814T053905Z-bg127`; produção restaurada; MITM OFF |

---

## 2026-08-12 — 30.15 alerta multi-appliance (`2.1.0`)

| Campo | Valor |
|-------|-------|
| Tipo | código + docs (sem deploy) |
| Versão | `2.1.0` |
| Objectivo | BG-121 / GA5.12 — sinal T1 no painel; só alerta (decisão 7) |
| Impacto | API dashboard + UI fila; sem package/daemon; sem hard-limit |
| Risco | Baixo — alerta informativo; rebind filtrado |
| Teste | `multi-appliance-abuse.test.js` 5/5; `npm test` backend 133/133 |
| Rollback | Reverter commit; em deploy futuro, imagem anterior |
| Resultado | **FEITO** no git; evidência `20260812T020331Z-30.15-multi-appliance-abuse`; **sem** deploy `.244`/live |

---

## 2026-08-08 — Alinhamento pós-commit + handoff

| Campo | Valor |
|-------|-------|
| Tipo | docs / governação / segurança |
| Versão | `2.0.0` (sem bump) |
| Objectivo | Fechar dívida de continuidade após push e corrigir drift SSOT |
| Impacto | Só docs; SPA hash; próximo passo = S1–S8 docs (não 20.10) |
| Risco | Baixo (docs) |
| Teste | health live OK; SPA `index-DwHpvSVY.js`; `origin/main` @ `657d7f4` |
| Rollback | Reverter esta entrada |
| Resultado | **FEITO** — commits `5fb1009` + `657d7f4` em `origin/main`; alinhamento SSOT + runbook S1–S8; S8 PASS parcial lab; builder sync `657d7f4`; `20.10` **não** iniciado |

Notas de segurança:

- Live `https://license.systemup.inf.br` = portal **`2.0.0`** / health `ok`.
- Técnicos **não** recebem `users.manage`; `/api/users` exige `users.manage`.
- Contas `is_active=false` rejeitadas no login/sessão.
- MITM: **20.10** BLOQUEADO até S1–S8 + GO lab; sem runtime / sem intercept.

---

## 2026-08-08 — PORTAL-PLAN-004 fecho `2.0.0` (U0–U2)

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `2.0.0` |
| Objectivo | Técnicos com permissões seleccionáveis (RBAC) |
| Impacto | Schema admins; API /users; gates UI/API; major visual |
| Risco | Médio (authz); owners preservados na migração |
| Teste | backend+frontend unit; health; SPA v2.0.0 |
| Rollback | imagens anteriores; colunas aditivas |
| Resultado | **FEITO** — health OK; SPA `index-DwHpvSVY.js`; plano 004 CONCLUIDO |

---

## 2026-08-08 — Abrir PORTAL-PLAN-004 (técnicos RBAC)

| Campo | Valor |
|-------|-------|
| Tipo | docs / governação |
| Versão | `1.9.0` (sem bump — só plano) |
| Objectivo | GO multi-admin técnicos + permissões seleccionáveis |
| Impacto | Docs; código em U0–U2 |
| Risco | Baixo (docs) |
| Teste | Leitura cruzada plano/IDEIAS |
| Rollback | Reverter docs |
| Resultado | **FEITO** — plano ACTIVO |

---

## 2026-08-08 — PORTAL-PLAN-003 fecho `1.9.0` (D0–D5)

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.9.0` |
| Objectivo | Entregar plano 003 completo (fila/contexto/entrega/2FA) |
| Impacto | UI+API admin; TOTP opcional até activar em Segurança |
| Risco | Médio (auth 2FA); mutações de negócio inalteradas |
| Teste | backend+frontend unit; health; SPA v1.9.0 |
| Rollback | imagens anteriores; desactivar 2FA se necessário |
| Resultado | **FEITO** — health OK; SPA `index-CUM8HiMh.js`; plano 003 CONCLUIDO |

---

## 2026-08-08 — Abrir PORTAL-PLAN-003 (fila/contexto/entrega)

| Campo | Valor |
|-------|-------|
| Tipo | docs / governação |
| Versão | `1.3.2` (sem bump — só plano) |
| Objectivo | Registar IDEA-040…051 + plano ordenado D0→D5 |
| Impacto | Docs portal; nenhum código runtime nesta entrada |
| Risco | Baixo |
| Teste | Leitura cruzada plano/IDEIAS/planos README |
| Rollback | Reverter commits docs |
| Resultado | **FEITO** — plano ACTIVO; execução D0 em seguida |

---

## 2026-08-08 — Nomenclatura equipamento `1.3.2`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.3.2` |
| Objectivo | Substituir Bound/Unbound/Rebind por termos claros em PT |
| Impacto | Só labels UI; API `bound` inalterada |
| Risco | Baixo |
| Teste | license-display; health; SPA v1.3.2 |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-Jyz4bwKJ.js`; labels Vinculada/Por activar |

---

## 2026-08-08 — Hotfix revisão `1.3.1`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.3.1` |
| Objectivo | Corrigir todos os achados da revisão defect-first |
| Impacto | UI datas/download/busca/select; policies update/download |
| Risco | Baixo–médio (mutações só bloqueio revoked) |
| Teste | update/download/format-date/panel-routes; health SPA |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-B-Ms_YV_.js`; `TZ=UTC` api/db; residuals P2/P3 fechados; `.env` preservado |

---

## 2026-08-08 — C2 Lista operacional + fecho plano `1.3.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.3.0` |
| Objectivo | Lista clientes com CNPJ/tags/activas; fechar PORTAL-PLAN-002 |
| Impacto | API list customers; UI lista; plano CONCLUIDO |
| Risco | Baixo |
| Teste | health; lista com activas/total; clique → ficha |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-CvZ07LNK.js`; plano CONCLUIDO |

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.2.0` |
| Objectivo | Ficha cliente útil: licenças ricas + nova licença no contexto |
| Impacto | CustomerDetail + LicenseForm query; sem mutações de negócio novas |
| Risco | Baixo |
| Teste | customer-license-summary; panel-routes; health; SPA v1.2.0 |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-BghDhRtJ.js`; testes summary/routes PASS |

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `1.1.0` |
| Objectivo | Descoberta Cliente↔Licença (clique + links) |
| Impacto | UX frontend; sem mudança de API de negócio |
| Risco | Baixo |
| Teste | lista→ficha→licença→cliente; health; SPA v1.1.0 |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-DkPpXfUZ.js`; `.env` preservado |

---

## 2026-08-08 — Abrir PORTAL-PLAN-002 (ficha cliente 360)

| Campo | Valor |
|-------|-------|
| Tipo | docs / governação |
| Versão | `1.0.0` (sem bump — só plano) |
| Objectivo | Registar IDEA-015/016 + plano ordenado C0→C1→C2 |
| Impacto | Docs portal; nenhum código runtime |
| Risco | Baixo |
| Teste | Leitura cruzada plano/IDEIAS/planos README |
| Rollback | Reverter commits docs |
| Resultado | **FEITO** — plano ACTIVO; C0 aguarda GO |

---

## 2026-08-08 — P1e Fecho `1.0.0`

| Campo | Valor |
|-------|-------|
| Tipo | docs + bump visual + deploy |
| Versão | `1.0.0` |
| Objectivo | Fechar critérios operador único e `PORTAL-PLAN-001` |
| Impacto | VERSION/CHANGELOG/historico; sidebar `v1.0.0`; plano CONCLUIDO |
| Risco | Baixo (sem mutação de negócio nova) |
| Teste | inventário SKU sem `full`; health; SPA 1.0.0 |
| Rollback | imagens anteriores / VERSION 0.5.0 |
| Resultado | **FEITO** — health OK; SPA `index-CzikQc2x.js`; inventário sem `full`; plano CONCLUIDO |

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.5.0` |
| Objectivo | Pós-revogação via substituição (nova chave + arquivar) |
| Impacto | API/UI replace; MANUAL-USO §7.1; decisão: sem desrevogar |
| Risco | Médio (chave nova + .lic antigo offline); mitigado por aviso + audit |
| Teste | license-replace-policy; health; UI substituir |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-_t9yOdDK.js`; build web `0.5.0`; `.env` restaurado pós-rsync |

### Decisão

- IDEA-012: escolhido fluxo **substituir** (não desrevogar).

### Incidente

- `rsync --delete` apagou `.env`; API em crash-loop; restore do backup
  `20260414T105834Z` + cópia fresca `20260808T220404Z`. Dados Postgres OK
  (volume). Regra: **sempre** `--exclude .env` no rsync.

---

## 2026-08-08 — P1c Rebind `0.4.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.4.0` |
| Objectivo | Workflow rebind com motivo + auditoria (GO humano) |
| Impacto | API/UI rebind; MANUAL-USO §5.6; risco .lic antigo explícito |
| Risco | Alto residual offline (grace); mitigado por aviso + audit |
| Teste | license-rebind-policy; health; UI rebind |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-C1foUQne.js`; build web `0.4.0`; nginx restarted |

---

## 2026-08-08 — P1b Auditoria `0.3.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.3.0` |
| Objectivo | UI/API de auditoria e check-ins visíveis |
| Impacto | nova rota admin `/api/audit`; detalhe licença |
| Risco | Baixo (read-only) |
| Teste | parseAuditListQuery; health; página /audit |
| Rollback | imagens anteriores |
| Resultado | **FEITO** — health OK; SPA `index-haIeulBq.js`; `/audit` no ar |

---

## 2026-08-08 — P1a Renovação `0.2.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.2.0` |
| Objectivo | Renovação rápida + ficha cliente (CNPJ/tags) |
| Impacto | API renew; UI detalhe; schema customers.cnpj/tags |
| Risco | Baixo; `.lic` antigo pode coexistir até reemissão/download |
| Teste | `license-renew-policy` + crud-validation; health pós-deploy |
| Rollback | imagens anteriores; colunas novas são aditivas |
| Resultado | **FEITO** — health OK após restart nginx; SPA `index-XzglZKGO.js`; colunas cnpj/tags OK |

---

## 2026-08-08 — P0 Fundação `0.1.0`

| Campo | Valor |
|-------|-------|
| Tipo | código + deploy + docs |
| Versão | `0.1.0` |
| Objectivo | Fechar drift SPA/API, UX chave/filtros/dashboard expiry, normalizar SKU `full`→`base` |
| Impacto | `license-server/` frontend+API; live `244`; inventário features |
| Risco | Médio no deploy (mitigado backup); baixo na UX; `.lic` antigos com `full` podem coexistir até reemissão |
| Teste | testes `crud-validation`; health; login; dashboard `expiring_30d`; lista filtros |
| Rollback | imagens Docker anteriores + restore Postgres se necessário |
| Resultado | **FEITO** — deploy live PASS; health OK; SPA nova; `full`→`base` (6 rows) |

### Notas

- Bloco P0 do `PORTAL-PLAN-001`
- Sem escala/MSP

---

## 2026-08-08 — Baseline documental `0.0.1`

| Campo | Valor |
|-------|-------|
| Tipo | Documentação / governação |
| Versão | `0.0.1` (baseline formal) |
| Objectivo | Criar organização documental da trilha portal + plano de melhoria total (operador único) |
| Impacto | Docs em `docs/10-license-server/portal/`; ligações CORTEX/docs/AGENTS; sem alteração de código runtime |
| Risco | Baixo (só docs); evitar conflito com SSOT global — hierarquia declarada em GOVERNANCE |
| Teste | Leitura cruzada dos índices; links relativos; versão `0.0.1` coerente |
| Rollback | Remover/reverter commits dos ficheiros da trilha |
| Resultado | **FEITO** — estrutura criada; plano activo de melhoria total aberto |

### Ficheiros tocados (bloco)

- `docs/10-license-server/README.md`
- `docs/10-license-server/portal/**` (governação + plano + histórico)
- `license-server/README.md`
- Ligações em `CORTEX.md`, `docs/README.md`, `document-classification.md`,
  `AGENTS.md`, `docs/02-roadmap/README.md`

### Decisões registadas

- Versão visual do portal começa em **0.0.1**
- Escopo imediato: **completude para operador único** (sem escala/vendas)
- Escala/MSP/billing → `IDEIAS.md` como `FUTURA`

---

## Modelo de entrada futura

```markdown
## YYYY-MM-DD — título curto

| Campo | Valor |
|-------|-------|
| Tipo | código / docs / deploy |
| Versão | x.y.z (ou Unreleased) |
| Objectivo | … |
| Impacto | … |
| Risco | … |
| Teste | … |
| Rollback | … |
| Resultado | FEITO / PARCIAL / BLOQUEADO |

### Notas
…
```
