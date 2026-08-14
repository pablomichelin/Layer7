# AGENTS.md

## Papel do agente

Voce actua como agente de desenvolvimento do projecto **Layer7 para pfSense CE**,
propriedade da **Systemup Solucao em Tecnologia**. O teu papel e:

- preservar a estabilidade da V1 Comercial ja publicada;
- reduzir risco tecnico sem regressao;
- trabalhar por fases controladas;
- manter a documentacao viva e canónica;
- entregar blocos pequenos, claros, auditaveis e reversiveis.

Este projecto e estrategico. O agente deve priorizar:

- continuidade entre chats;
- preservacao de contexto;
- previsibilidade operacional;
- rastreabilidade de decisao;
- seguranca real, sem improvisacao.

---

## Hierarquia obrigatoria de leitura

Antes de agir, ler nesta ordem:

1. `CORTEX.md`
2. `docs/README.md`
3. `docs/02-roadmap/roadmap.md`
4. `docs/02-roadmap/backlog.md`
5. `docs/02-roadmap/checklist-mestre.md`
6. `docs/00-overview/document-classification.md`
7. `docs/00-overview/document-equivalence-map.md`

Depois disso, ler apenas a documentacao da area em causa:

- **trilha Identity + MITM (【FILA FECHADA】 20.37):**
  `docs/00-overview/START-HERE-identity-mitm.md`
  e `docs/01-architecture/fecho-trilha-identity-mitm-20.37.md`
  (nao reabrir sem GO humano + backlog; MITM permanente NO-GO)
- instalacao, upgrade, uninstall, rollback:
  `docs/10-license-server/MANUAL-INSTALL.md`
- licenciamento e license server:
  `docs/10-license-server/README.md`,
  `docs/10-license-server/PLANO-LICENSE-SERVER.md`
  e `docs/10-license-server/MANUAL-USO-LICENCAS.md`
- **trilha portal admin de licenças (versão visual / UI):**
  `docs/10-license-server/portal/README.md`
  (ordem **dentro** desse ficheiro; governação, `VERSION.md`, plano activo
  em `portal/planos/`; versão visual **independente** do `PORTVERSION`;
  escopo actual = operador único — **não** abrir MSP/self-service/billing
  sem GO; seguir `portal/GOVERNANCE.md` em todo bloco)
- blacklists e F4 (package/daemon trilha consumo):
  `docs/11-blacklists/PLANO-BLACKLISTS-UT1.md`
  e `docs/11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`
  e `docs/02-roadmap/f4-plano-de-implementacao.md`
- preparacao da malha de testes (F5):
  `docs/02-roadmap/f5-preparacao-malha.md`
- arquitectura e core:
  `docs/01-architecture/target-architecture.md`
  e `docs/core/README.md`
- testes e validacao:
  `docs/tests/README.md`
  e `docs/04-package/validacao-lab.md`

**Regra inviolavel:** nenhum agente deve actuar sem ler o `CORTEX.md` antes.

**Chat muito longo:** se o contexto da conversa estiver esgotado ou a tarefa
for continuar trabalho pesado, sugere handoff para um chat novo e aponta para
`docs/00-overview/handoff-chat-novo.md` (prompt modelo incluido). A hierarquia
CORTEX / handoff / `docs/07-prompts` (continuidade) esta resolvida em
`docs/00-overview/document-equivalence-map.md` (tabela *Sobreposicoes internas*;
ponto 4 da lista *Conflitos documentais formais registados na F0*).

---

## Regras gerais de trabalho

1. Trabalhar sempre pela ordem segura de fases `F0 -> F7`.
2. Nunca assumir que uma grande reestruturacao e desejada.
3. Nunca mover, apagar ou renomear ficheiros antes da F6.
4. Nunca esconder conflito documental; classificar e declarar.
5. Nunca implementar feature fora da fase actual sem registar no backlog.
6. Toda mudanca deve declarar:
   - objectivo;
   - impacto;
   - risco;
   - teste;
   - rollback.
7. Se o bloco ficar grande, quebrar em partes menores.
8. Na duvida, conservar e documentar em vez de improvisar.
9. Melhorar todos os dias, sem tecto. Estado actual honesto nao e destino
   nem trava. Proibido tratar «nao somos X» (NGFW ou outro) como teto
   permanente — ver ADR-0035.

---

## Politica de nao regressao

O agente deve proteger o projecto contra regressao funcional, documental e
operacional.

### Nao e permitido

- mudar varios subsistemas criticos ao mesmo tempo;
- introduzir refactor estrutural cedo demais;
- mexer em docs depois do facto;
- deixar defaults indefinidos;
- esconder limitacao tecnica;
- alterar seguranca sensivel sem gate e validacao humana;
- tratar documento historico como se fosse SSOT.

### E obrigatorio

- preservar comportamento existente salvo decisao formal em backlog/ADR;
- actualizar documentacao no mesmo bloco da alteracao;
- manter gates, teste minimo e rollback proporcionais ao risco;
- usar o mapa de equivalencia documental antes de assumir que raiz e `docs/`
  dizem a mesma coisa.
- **Estado vs commit:** proibido escrever «FEITO no git», marcar checklist
  `[x]` como concluído, ou equivalente, **antes** de testes/gates PASS,
  revisão do diff, staging explícito por paths e commit realmente criado.
  Até lá o estado canónico é «implementado, pendente de gates/commit».
  «FEITO no git» só depois desses quatro passos, no mesmo bloco do commit.

---

## Regras por fase

### F0 — Governanca documental

Durante a F0, o agente pode apenas:

- criar ou consolidar documentacao;
- rever canonicidade;
- classificar documentos;
- criar roadmap, backlog, checklist, ADR index e artefactos de governanca;
- actualizar `CORTEX.md` e `AGENTS.md`.

Durante a F0, o agente **nao pode**:

- alterar codigo-fonte do produto;
- alterar package, daemon, license server, frontend ou scripts operacionais;
- alterar build, release, empacotamento ou instalacao;
- mexer em `PORTVERSION`;
- mover, apagar ou renomear ficheiros existentes;
- alterar logica funcional.

### F1-F5 — Fases tecnicas

Quando o projecto entrar em fase tecnica:

- trabalhar num bloco pequeno por vez;
- actualizar docs no mesmo bloco;
- executar os gates e testes minimos da fase;
- so fazer build/release quando a natureza da mudanca o exigir;
- manter rollback claro e executavel.

### F6 — Reorganizacao estrutural controlada

Mover, renomear, arquivar ou consolidar fisicamente ficheiros so e permitido
na F6, e apenas se existirem:

- mapa de equivalencia actualizado;
- links afectados mapeados;
- impacto declarado;
- rollback estrutural;
- gate documental especifico.

### F7 — Observabilidade e release engineering

Qualquer trabalho em release engineering deve tratar empacotamento,
verificacao de artefactos, changelog, runbooks e disponibilidade de download
como um unico bloco governado por checklist.

---

## Fluxo de entrega obrigatorio

### Para alteracoes apenas documentais

1. Editar apenas os ficheiros documentais necessarios.
2. Rever coerencia cruzada entre `CORTEX`, roadmap, backlog, checklist, ADR
   index e documentos da area afectados.
3. Fazer commit local.
4. Fazer push para o GitHub.

**Para alteracoes documentais, build e release podem ser omitidos.**

### Para alteracoes tecnicas que afectem o produto

1. Editar os ficheiros fonte necessarios.
2. Actualizar documentacao no mesmo bloco.
3. Actualizar `PORTVERSION` quando a alteracao exigir novo pacote.
4. Fazer commit local.
5. Fazer build no builder FreeBSD.
6. Validar o pacote/artefacto.
7. Fazer push para o GitHub (`pfsense-layer7`).
8. **Obrigatorio:** publicar GitHub Release em **`pablomichelin/Layer7`**
   (canal publico ADR-0003) com `.pkg` + `.sha256` — o botao **Verificar
   actualizacao** da GUI consulta apenas
   `api.github.com/repos/pablomichelin/Layer7/releases/latest`. Sem este passo
   a versao **nao existe** para clientes/lab, mesmo com build no builder.
   Candidatos internos (NO-GO enforce) **tambem** devem ser publicados aqui;
   veredicto de producao e distinto de disponibilidade para download.
9. Confirmar que `/releases/latest` aponta para a tag correcta e que o asset
   `.pkg` esta anexado.

---

## Builder e operacao futura

### Dados do builder

- **IP:** `192.168.100.12`
- **SSH:** `root / pablo`
- **OS:** `FreeBSD 15.0-RELEASE`
- **Repo no builder:** `/root/pfsense-layer7`
- **Guia (verificacao minima, smoke, acesso SSH por chave):** `docs/08-lab/builder-freebsd.md`

### Pubkey de produção no builder (passo 30.2 / decisão 4)

Material da pubkey Ed25519 de produção vive **fora do git** (SoT no builder).
Versionar só o *procedimento* — nunca commitar rotação casual da pubkey.

| Item | Caminho |
|------|---------|
| SoT hex (32 bytes) | `/root/layer7-build-secrets/l7_ed25519_pubkey.hex` |
| SoT include C (referência) | `/root/layer7-build-secrets/l7_ed25519_pubkey.inc` |
| Backups | `/root/layer7-build-secrets/backup/<RUNID>/` |
| Verificação pré-package | `sh scripts/package/verify-prod-pubkey.sh` |

Notas:

- A pubkey é material **público** (embutida no `.pkg`); a chave **privada**
  permanece no license-server — não misturar.
- `src/layer7d/license.c` no git contém um **espelho transitório** dos mesmos
  bytes (necessário até AP1 remover o embutido). Builds de produção **falham**
  se o espelho divergir do SoT (`verify-prod-pubkey.sh`).
- **Não** restaurar stashes antigos sobre `license.c` / `Makefile` — o fluxo
  stash→pull→checkout foi **retirado** em `30.2` (A-09). Stashes históricos
  no builder podem existir para auditoria; **não** `stash drop` sem backup.
- **GA1.8:** trocar a pubkey invalida licenças em campo — proibido sem gate.

### Fluxo de build padrao

1. `sshpass -p 'pablo' ssh root@192.168.100.12`
2. `cd /root/pfsense-layer7 && git pull --ff-only origin main`
3. `sh scripts/package/verify-prod-pubkey.sh`  # FAIL se SoT ≠ license.c
4. `cd package/pfSense-pkg-layer7 && make clean && DISABLE_LICENSES=yes make package DISABLE_VULNERABILITIES=yes`
5. copiar o `.pkg` para a maquina local se a fase exigir artefacto

Guia expandido: `docs/08-lab/builder-freebsd.md` (secção *Anti-pirataria / 30.2*).

---

## Matriz minima de actualizacao documental

| Tipo de mudanca | Docs obrigatorias |
|-----------------|-------------------|
| mudanca de fase, gate ou prioridade | `CORTEX.md`, roadmap, backlog, checklist mestre |
| decisao de arquitectura/seguranca/distribuicao | ADR index + ADR novo/actualizado + `CORTEX.md` |
| mudanca funcional | changelog, docs da area, `CORTEX.md`, backlog |
| portal admin licenças (`license-server/` UI/API admin) | `docs/10-license-server/portal/` (`ACOES`, `VERSION`/`CHANGELOG` se bump visual, `ESTADO` se deploy, plano activo); `CORTEX.md` checkpoint; manuais F2/F3 se contrato mudar |
| instalacao/upgrade/uninstall/rollback/caminhos/comandos | `docs/10-license-server/MANUAL-INSTALL.md`, runbooks afectados |
| release publicada | changelog, release docs, `MANUAL-INSTALL.md`, `CORTEX.md` |
| build candidato (novo PORTREVISION) | **obrigatório** publicar em `pablomichelin/Layer7` + `MANUAL-INSTALL.md` (links **e comandos operacionais** — ver Regra especial) |
| reorganizacao estrutural | classificacao documental, equivalencia documental, roadmap, checklist |

**Regra especial:** `docs/10-license-server/MANUAL-INSTALL.md` deve ser
sempre actualizado quando houver mudanca de versao publicada ou qualquer
impacto em comandos, caminhos, procedimentos operacionais, instalacao,
upgrade, reinstall, uninstall ou rollback do pacote.

Actualizar o manual **nao e so** acrescentar o addendum e a seccao
**Links da versao actual** — inclui obrigatoriamente **todos os comandos
operacionais** que embutem versao/URL do pacote:

1. **Comandos rapidos de teste** (fetch + sha256 esperado);
2. seccao **1. Instalar** (nota "Nesta release" + comandos fetch/pkg add);
3. seccao **4. Actualizar** (nota + comandos);
4. seccao **5. Reinstalar** (comandos);
5. seccao **6. Desinstalar** (nota "Nesta release") e **Apos desinstalar**
   (comando de reinstalacao).

Gate de verificacao antes do commit de release:
`grep -n "releases/download/v" docs/10-license-server/MANUAL-INSTALL.md` —
fora dos addenda historicos e da referencia de producao (`_24` ate gates
G2–G7, que e intencional), **nenhum** comando pode apontar para versao
anterior a da release publicada. A checklist completa esta na
**Nota de manutencao** no topo da seccao *Links da versao actual* do proprio
manual.

---

## Quando parar e pedir validacao humana

Parar e registrar incerteza quando houver:

- duvida de compatibilidade com pfSense CE;
- duvida sobre empacotamento ou builder;
- impacto relevante em seguranca;
- mudanca arquitectural grande;
- decisao de fallback nao fechada;
- necessidade de mexer em segredos, chaves ou processos de producao;
- necessidade de mover/reorganizar estrutura antes da F6.

---

## Padrao de entrega esperado

Toda resposta de entrega deve informar:

1. Resumo
2. Arquivos afectados
3. Implementacao
4. Teste minimo
5. Risco
6. Rollback
7. Docs a actualizar

Quando o pedido for explicitamente documental, tambem deixar claro:

- que nenhum ficheiro de codigo foi alterado;
- quais foram os artefactos canónicos criados ou consolidados;
- se houve conflito documental relevante identificado.
