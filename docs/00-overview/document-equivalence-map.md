# Mapa de Equivalencia Documental

## Finalidade

Este mapa mostra como os documentos da raiz se relacionam com o centro
documental canónico em `docs/`.

**F6 H5 (`2026-08-05`):** o **texto completo** de `00-`…`16-` vive em
[`docs/archive/raiz-legado/`](../archive/raiz-legado/); na raiz do repo
permanecem **stubs** com o mesmo nome. Planos fecho+IPv6:
[`docs/archive/planos-fechados/`](../archive/planos-fechados/) (+ stubs em
`docs/02-roadmap/`).

Ele existe para evitar tres erros comuns:

1. abrir um documento historico e tratá-lo como SSOT;
2. assumir que raiz e `docs/` dizem exactamente a mesma coisa;
3. confundir stub / arquivo 【FECHADO】 com plano activo.

---

## Legenda de relacao

- **Equivalencia**: o documento em `docs/` cobre o mesmo papel de forma mais actual.
- **Complementaridade**: ambos sao uteis, mas o canónico tem prioridade.
- **Sobreposicao**: os dois tratam o tema com intersecao parcial.
- **Conflito**: existe divergencia material; seguir a fonte canónica indicada.
- **Sem par directo**: o documento fica preservado por contexto, sem espelho claro.

---

## Mapa raiz -> docs

| Documento da raiz (stub) | Texto em arquivo | Correspondente em `docs/` | Fonte canónica | Relacao | Accao |
|--------------------------|------------------|---------------------------|----------------|---------|--------|
| `CORTEX.md` | — | `docs/README.md`, `docs/02-roadmap/*` | `CORTEX.md` | Complementaridade | manter |
| `AGENTS.md` | — | checklist / README | `AGENTS.md` | Complementaridade | manter |
| `README.md` | — | `docs/README.md` | `docs/README.md` (nav interna) | Complementaridade | preservar |
| `00-LEIA-ME-PRIMEIRO.md` | [`raiz-legado/00-…`](../archive/raiz-legado/00-LEIA-ME-PRIMEIRO.md) | `docs/README.md` + `CORTEX.md` | `CORTEX.md` | Sobreposicao | **arquivado H5** |
| `01-VISAO-GERAL-E-ESCOPO.md` | [`raiz-legado/01-…`](../archive/raiz-legado/01-VISAO-GERAL-E-ESCOPO.md) | `product-charter.md` | `product-charter.md` | Equivalencia | **arquivado H5** |
| `02-ARQUITETURA-ALVO.md` | [`raiz-legado/02-…`](../archive/raiz-legado/02-ARQUITETURA-ALVO.md) | `target-architecture.md` | `target-architecture.md` | Equivalencia | **arquivado H5** |
| `03-ROADMAP-E-FASES.md` | [`raiz-legado/03-…`](../archive/raiz-legado/03-ROADMAP-E-FASES.md) | `roadmap.md` | `roadmap.md` | Conflito (V0 vs F0–F7) | **arquivado H5** |
| `04-BACKLOG-MVP-E-VERSOES.md` | [`raiz-legado/04-…`](../archive/raiz-legado/04-BACKLOG-MVP-E-VERSOES.md) | `backlog.md` | `backlog.md` | Conflito | **arquivado H5** |
| `05-ESTRUTURA-REPOSITORIO-CURSOR-GITHUB.md` | [`raiz-legado/05-…`](../archive/raiz-legado/05-ESTRUTURA-REPOSITORIO-CURSOR-GITHUB.md) | `docs/README.md` + mapas | `docs/README.md` | Sobreposicao | **arquivado H5** |
| `06-PADROES-DE-DESENVOLVIMENTO-E-SEGURANCA.md` | [`raiz-legado/06-…`](../archive/raiz-legado/06-PADROES-DE-DESENVOLVIMENTO-E-SEGURANCA.md) | `AGENTS.md` | `AGENTS.md` | Equivalencia | **arquivado H5** |
| `07-PLANO-DE-IMPLEMENTACAO-PASSO-A-PASSO.md` | [`raiz-legado/07-…`](../archive/raiz-legado/07-PLANO-DE-IMPLEMENTACAO-PASSO-A-PASSO.md) | roadmap/backlog | roadmap/backlog | Sobreposicao | **arquivado H5** |
| `08-PLANO-DE-TESTES-E-HOMOLOGACAO.md` | [`raiz-legado/08-…`](../archive/raiz-legado/08-PLANO-DE-TESTES-E-HOMOLOGACAO.md) | `docs/tests/*` | `docs/tests/*` | Equivalencia | **arquivado H5** |
| `09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md` | [`raiz-legado/09-…`](../archive/raiz-legado/09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md) | `MANUAL-INSTALL.md` | `MANUAL-INSTALL.md` | Sobreposicao | **arquivado H5** |
| `10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md` | [`raiz-legado/10-…`](../archive/raiz-legado/10-RUNBOOK-OPERACIONAL-E-ROLLBACK.md) | `13-runbooks/` | `MANUAL-INSTALL` + runbooks | Equivalencia | **arquivado H5** |
| `11-RISCOS-LIMITACOES-E-DECISOES.md` | [`raiz-legado/11-…`](../archive/raiz-legado/11-RISCOS-LIMITACOES-E-DECISOES.md) | CORTEX + backlog + ADR | CORTEX | Sobreposicao | **arquivado H5** |
| `12-PLANO-DE-DOCUMENTACAO-E-GITHUB.md` | [`raiz-legado/12-…`](../archive/raiz-legado/12-PLANO-DE-DOCUMENTACAO-E-GITHUB.md) | docs F0 | docs F0 | Equivalencia | **arquivado H5** |
| `13-MODELOS-DE-ISSUES-E-PRS.md` | [`raiz-legado/13-…`](../archive/raiz-legado/13-MODELOS-DE-ISSUES-E-PRS.md) | `.github/` + ADR | template PR | Complementaridade | **arquivado H5** |
| `14-CHECKLIST-MESTRE.md` | [`raiz-legado/14-…`](../archive/raiz-legado/14-CHECKLIST-MESTRE.md) | `checklist-mestre.md` | `checklist-mestre.md` | Conflito | **arquivado H5** |
| `15-PROMPT-MESTRE-CURSOR.md` | [`raiz-legado/15-…`](../archive/raiz-legado/15-PROMPT-MESTRE-CURSOR.md) | `AGENTS` + `CORTEX` | `AGENTS`/`CORTEX` | Conflito | **arquivado H5** |
| `16-REFERENCIAS-OFICIAIS.md` | [`raiz-legado/16-…`](../archive/raiz-legado/16-REFERENCIAS-OFICIAIS.md) | sem par directo | arquivo | Sem par | **arquivado H5** |
| `release-body.md` | — | releases/changelog | releases | Sobreposicao | rever F7 |
| `logica.md` | — | sem par | nenhum | Sem par | preservar |

---

## Sobreposicoes internas relevantes em `docs/`

| Area | Sobreposicao | Fonte canónica actual | Observacao |
|------|--------------|-----------------------|------------|
| testes | `docs/04-tests/README.md` vs `docs/tests/README.md` | `docs/tests/README.md` | a area `04-tests` fica historica ate F6 |
| roadmap/backlog/checklist | resumos antigos em raiz vs docs novos | `docs/02-roadmap/*` | raiz fica historica |
| instalacao | tutorial longo vs manual install | `docs/10-license-server/MANUAL-INSTALL.md` | tutorial fica preservado por compatibilidade |
| release/distribuicao | ADR-0002 `.txz` (historico) vs ADR-0003 **`.pkg`** (canonico) | `docs/03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md` + `CORTEX.md` + `docs/06-releases/README.md` + `MANUAL-INSTALL.md` | ADR-0002 preservado; confusao resolvida na hierarquia oficial |
| prompts/continuidade | `docs/07-prompts/next-chat-phase-a-option1.md` vs checkpoint do `CORTEX.md` | `CORTEX.md` | prompt antigo fica historico |
| continuidade (chat longo, handoff) | `docs/00-overview/handoff-chat-novo.md` (procedimento, prompt, pos-clone) vs `CORTEX.md` (Politica de continuidade entre chats) | `CORTEX.md` para estado e prioridade; `handoff-chat-novo.md` para o *como* sem contrariar o SSOT | o handoff expande; nunca substitui fase, backlog ou checkpoint do CORTEX |
| IPv6 / dual-stack | notas antigas «planeado V2» vs limitação FP-010 | [`【FECHADO】 plano-ipv6`](../archive/planos-fechados/plano-ipv6-completo.md) + mapa + ADR-0024; START-HERE; ESTADO-PRODUTO | trilha **FECHADA**; stubs em `02-roadmap/` |
| Fecho vs estado vivo | planos arquivados vs CORTEX | CORTEX = vivo; ESTADO-PRODUTO = veredicto; `archive/planos-fechados/` = histórico | em “está aberto?”, vence ESTADO-PRODUTO + CORTEX |
| Escopo produto | charter vs pack/PRD/catálogo vs README raiz | `pack-produto-layer7.md` → `prd-layer7.md` + `catalogo-funcionalidades.md` + CORTEX | charter resume; README raiz pode estar atrasado na versão |
| Anti-pirataria estado vivo | START-HERE / plano §0 / fecho `30.19` / gates vs soak 20.36 | `CORTEX.md` (vivo) + `START-HERE-antipirataria.md`; fecho `30.19` = engenharia; **BG-127** = evidência | `.254` vivo = `1.9.63` MITM OFF (`20260814T034904Z-20.36-soak-align-163-254`); BG-127 **PASS** `20260814T224213Z` (histórico PARTIAL `20260814T051611Z`); `1.9.54` = histórico e2e AP2 |
| Builder ABI 15 vs 16 | «preferir a mesma major» em `builder-freebsd.md` vs builder 15 + appliance Plus/16 | `CORTEX.md` + BG-106 + P2-14 (BG-152) + [`../08-lab/builder-freebsd.md`](../08-lab/builder-freebsd.md) | builder permanece 15; `-f` no Plus/16 é workaround aceite, **não** suporte nativo ABI 16; builder 16 **não** provado |
| Expiry TZ / `timegm` | REV-030 «meio-dia UTC ou `timegm`» vs P2-13/P3-7 (meia-noite local) | [`../01-architecture/f3-expiracao-revogacao-grace.md`](../01-architecture/f3-expiracao-revogacao-grace.md) + auditoria P2-13/P3-7 (BG-153) | REV-030 é histórico; **não** aplicar `timegm`/`gmmktime` (altera o contrato e piora Brasil/UTC) |
| Frontend package / BG-174 | GUI conceptual, plano VIP/UX e auditoria/redesign novo | [`frontend-redesign-analise.md`](frontend-redesign-analise.md) = decisão; [`../01-architecture/frontend-redesign-inventario-paridade.md`](../01-architecture/frontend-redesign-inventario-paridade.md) = paridade; [`frontend-redesign-wireframes.md`](frontend-redesign-wireframes.md) = aceitação visual; [`../02-roadmap/plano-redesign-frontend.md`](../02-roadmap/plano-redesign-frontend.md) = execução | documentos anteriores permanecem contexto/histórico; Identity+MITM só entra no inventário e **não** é reaberta |

---

## Conflitos documentais formais registados na F0

1. **Roadmap**: a raiz fala em uma sequencia antiga de fases; o canónico
   actual passa a ser F0-F7.
2. **Backlog**: a raiz reflecte backlog V0/V1; o backlog canónico actual passa
   a ser o backlog por severidade/componente/fase sugerida.
3. **Artefacto de distribuicao**: ADR historico e varias docs antigas falam em
   `.txz`; o estado operacional conhecido e `.pkg`.
4. **Continuidade entre chats**: prompts antigos existiam em `docs/07-prompts`,
   mas a continuidade oficial passa a viver no `CORTEX.md`, complementada em
   `docs/00-overview/handoff-chat-novo.md` (procedimento, prompt modelo e
   verificacao opcional pos-clone), sem conflito com a hierarquia do CORTEX.
5. **License-server HEAD vs live `.244` (`2026-08-14`):** o compose/nginx
   HEAD descrevem o contrato F2.1 (`127.0.0.1:8445`) **e**, após o commit
   allowlist, o vhost `downloads` + volume `CONTENT_BLACKLISTS_DIR`. O live
   corre serving `30.11` + overlay `30.13` + bind de edge `0.0.0.0`. Fonte
   canónica do gap restante e do freeze: [`../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md`](../09-blocking/auditoria-licencas-auth-deploy-2026-08-14.md)
   + [`../13-runbooks/bloqueio-deploy-integral-head-30.11.md`](../13-runbooks/bloqueio-deploy-integral-head-30.11.md).
   **Não** tratar o compose HEAD como descrição do live (bind / P0-2…P1-4
   / healthcheck P2-6B) enquanto P0-1 estiver activo. Snapshot/`.env`
   **não** são HEAD. P2-6 Bloco B versiona `pg_isready` +
   `service_healthy` só no HEAD.
6. **Builder FreeBSD 16 (`2026-08-14`, P2-14 / BG-152):** o guia do
   builder pede «preferir a mesma major» que o appliance; o appliance de
   lab é FreeBSD 16 e o builder de produto é 15. Fonte canónica: builder
   permanece 15; `-f` no Plus/16 é política BG-106, **não** suporte nativo
   ABI 16. Builder 16 **não** está provado.
7. **Versão `latest` na governação (`2026-08-31`):** `AGENTS.md` e a nota
   BG-164 no backlog ainda citavam `v1.9.78`, enquanto o `CORTEX.md`, a tag
   e a release publicada confirmam `v1.9.79`. Resolvido neste bloco a favor
   de `v1.9.79`; referências antigas do CORTEX permanecem histórico datado.

---

## Regra de uso após F6 (Onda H — H1–H5)

- `docs/04-tests/` → stub; arquivo em `docs/archive/pre-f6/04-tests/`.
- `docs/package/` → stub; `gui-validation.md` em `docs/04-package/`.
- `docs/13-runbooks/` (ex-`05-runbooks`), `docs/14-logging/` (ex-`10-logging`).
- Raiz `00-`…`16-` → **stubs**; texto em `docs/archive/raiz-legado/` (**H5**).
- Planos fecho+IPv6 → **stubs** em `02-roadmap/`; texto em `docs/archive/planos-fechados/` (**H5**).
- Mapa completo: [`f6-mapa-consolidacao-H0.md`](f6-mapa-consolidacao-H0.md).

## Higienização residual pós-H5 (auditoria `2026-08-10`)

Não reabre H1–H5. Trata resíduo local, untracked, links partidos e ruído de
evidências **sem** apagar canónicos nem falsificar veredictos (P4 FAIL/ABORT
permanece FAIL/ABORT).

| Artefacto | Papel |
|-----------|-------|
| [`f6-plano-higiene-estrutural-residual.md`](f6-plano-higiene-estrutural-residual.md) | Plano + **gate de execução** + **lista de exclusão** |
| [`f6-inventario-higiene-estrutural-2026-08-09.md`](f6-inventario-higiene-estrutural-2026-08-09.md) | Inventário INV-* |
| [`f6-classificacao-candidatos-higiene-2026-08-09.md`](f6-classificacao-candidatos-higiene-2026-08-09.md) | MANTER / ARQUIVAR / REMOVER / CORRIGIR |

**Conflito formal (status F6):** tabelas antigas podiam dizer F6 “planeada”
enquanto o checkpoint regista H1–H5 **FECHADA** — resolvido nos SSOTs a favor
de: **H1–H5 FECHADA** + higiene residual sob BG-112 / plano acima.

## Regra de uso antes da F6 (histórico)

Antes dos moves H1–H5:

- nao mover ficheiros para “corrigir” sobreposicao;
- usar este mapa para decidir qual documento ler;
- registrar aqui novas equivalencias ou novos conflitos.
- preservar o legado em vez de o apagar.
