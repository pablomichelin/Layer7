# Plano mestre — Fecho de produção e consolidação Layer7

**Estado do plano:** `ACTIVO` (rev. `2026-08-05c` — GO Onda F `_69`)  
**Tipo:** execução governada (software + documentação + versionamento)  
**SSOT deste plano:** este ficheiro  
**SSOT de estado do projecto:** `CORTEX.md`  
**Arranque de chat:** [`../00-overview/START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md)

---

## 0. Snapshot do projecto (leitura rápida — actualizar só no CORTEX)

| Campo | Valor actual (`2026-08-05`) |
|-------|-----------------------------|
| **Fase roadmap** | F4 aberta (F3 **fechada** `2026-08-04`); F0–F2 concluídas |
| **Canal público `latest`** | `1.8.11_69` |
| **Produção enforce (referência)** | **`1.8.11_69`** — GO Onda F (`2026-08-05`) |
| **Candidato lab para gates** | **`1.8.11_69`** |
| **`PORTVERSION` / `PORTREVISION` no repo** | `1.8.11` / `69` |
| **Appliance lab observado** | `192.168.100.254` — pfSense **Plus** `26.03.1` / FreeBSD `16` (validar CE na Onda E) |
| **Builder** | `192.168.100.12` — FreeBSD `15.0-RELEASE` |
| **Gates G2–G7** | **PASS** no appliance (`plano-gates-producao.md`) |
| **F3** | **Fechada** (`2026-08-04`) — `F3 pode fechar`; evidência Onda C |
| **Passo actual do plano** | Ver checklist em `CORTEX.md` (secção *Plano mestre*) |

**Regra pós-GO:** `latest` = produção enforce (`_69`). Rollback imediato: `_68`.

**Dois marcos distintos (não confundir):**

| Marco | Onda | Significado |
|-------|------|-------------|
| **GO enforce** | F (passos 7.1–7.2) | Versão promovida a referência enforce no CORTEX (R1 parcial + R6) |
| **Produto pronto comercial** | J (passo 11.1) | R1–R12 todos verdes ou excepções assinadas |

O GO na Onda F **exige** Ondas A–B PASS **e** C–D–E PASS (não é opcional para promoção enforce).

---

## 0.1 Resultado final desejado (definição de “pronto”)

O projecto só se considera **pronto para utilização comercial com enforce**
quando **todas** as condições abaixo forem verdadeiras ao mesmo tempo:

| # | Condição de saída | Prova |
|---|-------------------|-------|
| R1 | Existe **uma** versão de produção enforce validada no appliance (não só builder) | Gates G2–G7 PASS + veredicto humano GO |
| R2 | `scoped_hybrid` só é recomendado após two-client PASS; default continua seguro até E8 | `validacao-lab.md` §12 + smoke scoped |
| R3 | F3 fechada (licenciamento previsível no appliance) | Campanha F3 com relatório `F3 pode fechar` |
| R4 | F4 fechada (package/daemon/blacklists com evidência lab) | Secções 10a / 10b / 11 PASS |
| R5 | Lista VIP + exclusões PF validadas no live | `validacao-lab.md` §19–20 PASS |
| R6 | Canal público alinhado: `releases/latest` = versão GO (ou prerelease lab explícito) | GitHub + `MANUAL-INSTALL.md` + CORTEX |
| R7 | Trust chain do **pacote** activa (BG-028) ou decisão formal adiada com risco aceite | Manifesto Ed25519 ou ADR de adiamento |
| R8 | Malha F5 mínima: backlog ↔ testes ↔ smoke appliance repetível | `docs/tests/*` + checklist |
| R9 | Árvore `docs/` consolidada sem duplicados confusos (F6) | Mapa equivalência actualizado + links OK |
| R10 | Release engineering (F7) com checklist: build → SHA → release → manual → CORTEX | Uma release executada só por checklist |
| R11 | Versionamento disciplinado: cada bloco com commit; cada artefacto com PORTREVISION/tag | Git + GitHub Releases |
| R12 | Documentação viva coerente: CORTEX, backlog, changelog, MANUAL-INSTALL | Diff documental no mesmo bloco da mudança |

**Fora do resultado final (não bloquear GO se limpos e documentados):**

- MITM TLS / página HTTPS “bonita”
- Console multi-firewall
- IPv6 completo
- Analytics pesado / SIEM completo (só telemetria mínima F7)
- Rebind automático de licença
- Paridade total UniFi/UDM

---

## 1. Princípios invioláveis

1. **Plano manda; agente obedece.** Nenhuma “melhoria espontânea” fora do passo actual.
2. **Ordem segura:** `F0 → F7`. Este plano **não salta** F6 antes de F5, nem F7 antes de F6 (excepto trabalho documental de **mapa** sem mover ficheiros).
3. **Um owner de ficheiro por vez** (secção 3). Multitarefa só em trilhas com conjuntos de ficheiros disjuntos.
4. **Sistema a funcionar > estética.** Qualquer passo que parta monitor/lab → STOP + rollback.
5. **Versionar sempre:**
   - mudança de código/produto → commit + (se `.pkg`) `PORTREVISION` + release GitHub quando o passo exigir artefacto;
   - mudança só documental → commit documental (build pode omitir);
   - nunca deixar working tree “meio feito” sem checkpoint no CORTEX.
6. **GO Onda F (`2026-08-05`):** produção enforce = `1.8.11_69` (decisão humana registada).
7. **Não mover/renomear/apagar** ficheiros existentes antes da **Onda H (F6)**.
8. **macOS ≠ gate de produto.** Gates reais: builder FreeBSD + appliance pfSense.

---

## 2. Visão geral — início / meio / fim

```text
INÍCIO (fundação)     MEIO (fecho técnico)           FIM (consolidação)
P0 → P1            Onda A → G → GO humano          Onda H → J
congelar regras    gates + F3 + F4 + VIP + CE      F6 docs + F7 release
escolher baseline  promover versão produção        checklist final R1–R12
```

| Macro | Ondas | Objectivo macro |
|-------|-------|-----------------|
| **INÍCIO** | P0, P1 | Disciplina, baseline, lab pronto, zero ambiguidade de canal |
| **MEIO** | A–G | Fechar pontas do **software** e evidência física |
| **FIM** | H–J | Fechar pontas da **documentação/estrutura** e **release engineering** |

---

## 3. Multitarefa segura (file ownership)

**Modo preferido:** usar **coordenador + workers** sempre que o plano autorizar trilhas
com ficheiros **disjuntos** e **sem dependência de estado** entre elas. Ganha tempo
sem sacrificar ordem nem SSOT.

**Modo agente único obrigatório:** ondas com appliance mutável (A, B, C, D lab steps),
edição de ficheiros quentes, promoção GO (F), release (I–J).

### 3.1 Regra

Dois agentes **nunca** editam o mesmo ficheiro no mesmo passo.  
Ficheiros “quentes” têm **um único owner** por onda.  
**Dependência de estado:** se o worker B precisa do resultado do worker A (ex.: install
no appliance antes de captura), **não** paralelizar — sequenciar no mesmo agente ou
esperar entrega do worker A.

### 3.2 Ficheiros quentes (sempre serializar — só coordenador ou agente único)

| Ficheiro | Motivo |
|----------|--------|
| `CORTEX.md` | SSOT — só o agente **coordenador** (ou passo final da onda) |
| `package/pfSense-pkg-layer7/Makefile` | PORTREVISION |
| `docs/changelog/CHANGELOG.md` | changelog |
| `docs/10-license-server/MANUAL-INSTALL.md` | instalação |
| `docs/02-roadmap/backlog.md` | prioridade |
| `package/.../layer7.inc` | núcleo PHP |
| `src/layer7d/*` | daemon |

### 3.3 Grafo de dependências entre ondas (não violar)

```text
P0 ──► P1 ──► A (G2-G4) ──► B (G5) ──┬──► C (F3) ──┐
                                      ├──► D (F4+VIP)├──► E (CE) ──► F (GO) ──► G (F5) ──► H (F6) ──► I (F7) ──► J
                                      └── (B antes de enforce em D) ─┘

Paralelo PERMITIDO (ficheiros/estado disjuntos):
  P0: workers em docs distintos (MANUAL vs gates vs 00-LEIA-ME) + coordenador CORTEX
  P1: diagnose read-only (worker) + notas lab (worker) em paralelo
  Durante A: explore read-only (worker) + matriz testes (worker) — ZERO código
  Durante D: roteiro lab (humano/agente único) + docs blacklists (worker)
  H: lotes H1–H3 em paralelo por pasta (mapa H.0 aprovado)

Paralelo PROIBIDO:
  B ∥ C no MESMO appliance (ambos mudam licença/enforce/estado)
  B ∥ D enforce no MESMO appliance (D pode começar docs; lab enforce só após G5)
  Qualquer worker a editar ficheiro quente
  F6 (H) antes de G (F5) ou antes de GO (F)
```

### 3.4 Trilhas paralelas permitidas (exemplos)

| Trilha A (agente 1) | Trilha B (agente 2) | Coordenador |
|---------------------|---------------------|-------------|
| Evidência lab appliance (só comandos/notas em `docs/tests/evidence/...` novo) | Actualizar matriz de testes **sem** tocar CORTEX | No fim: 1 agente actualiza CORTEX + backlog |
| Ler/diagnosticar appliance (read-only) | Preparar checklist release em `docs/06-releases/` novo draft | Idem |
| Testes C em `tests/functional/*` | Docs blacklists em `docs/11-blacklists/*` | Idem |
| License-server `license-server/backend/**` | Pacote GUI `layer7_*.php` (não `layer7.inc`) | Nunca em paralelo com Makefile |

### 3.5 Modo de execução por onda

| Onda | Modo preferido | Multitarefa autorizada? | Bloqueio principal |
|------|----------------|-------------------------|-------------------|
| P0 | **Coordenador + 1–2 workers** | Sim — docs disjuntos | Coordenador fecha CORTEX |
| P1 | Coordenador + workers | Sim — diagnose read-only ∥ notas `docs/08-lab/` | Snapshot humano |
| A | **Agente único** + humano appliance | Só explore read-only ∥ docs evidência | G2–G4 serial no appliance |
| B | **Agente único** + humano | Não no appliance | G5 two-client |
| C | **Agente único** | Não (estado `.lic`) | F3 campanha |
| D | Agente único (lab) + worker docs | Docs ∥ lab só se lab não enforce ainda | G5 PASS para enforce |
| E | Agente único ou worker VM prep | Doc CE ∥ notas, não ∥ A–D no mesmo VM | VM CE dedicada |
| F–G | **Agente único** | Não | GO humano |
| H | **Coordenador + Docs-A/B/C** | Sim — por lote/pasta | Mapa H.0 aprovado |
| I–J | **Agente único** | Não | Release checklist |

### 3.6 Receitas multitarefa (copiar no coordenador)

**P0 — alinhamento documental**

| Worker | Ficheiros permitidos | Entrega |
|--------|---------------------|---------|
| W1 | `docs/09-blocking/plano-gates-producao.md`, `docs/02-roadmap/checklist-mestre.md` | Candidato `_65` + estados G0–G1 |
| W2 | `00-LEIA-ME-PRIMEIRO.md`, `docs/10-license-server/MANUAL-INSTALL.md` (só banner/links) | Dual-canal explícito |
| Coordenador | `CORTEX.md`, `backlog.md` (se IDs), este plano | Integra + commit único P0 |

**P1 — lab pronto**

| Worker | Ficheiros permitidos | Entrega |
|--------|---------------------|---------|
| W1 | Read-only: `scripts/diagnose-layer7-appliance.sh` no appliance; notas em `docs/tests/evidence/<run_id>/` | Log diagnose |
| W2 | `docs/08-lab/*.md` (notas snapshot ID, inventário) | Snapshot ID + pré-requisitos humanos |
| Coordenador | `CORTEX.md` | `candidato_lab=1.8.11_65` confirmado |

**Onda A — durante gates (sem editar código)**

| Worker | Permissão | Entrega |
|--------|-----------|---------|
| W1 | Appliance read-only + evidência `docs/tests/evidence/` | Métricas G2–G4 |
| W2 | `docs/tests/test-matrix.md`, `docs/04-package/validacao-lab.md` (notas PASS/FAIL) | Matriz actualizada |
| Coordenador | `CORTEX.md` | Checkpoint passo 2.4 |

**Protocolo coordenador:** receber entregas dos workers → verificar zero overlap → integrar → **um** commit de fecho da onda → actualizar checklist no CORTEX.

---

## 4. INÍCIO — Fundação

### P0 — Congelar disciplina e dual-canal

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Eliminar ambiguidade `_24` (enforce ref) vs `_65+` (lab/latest) |
| **Acções** | Reafirmar no CORTEX + MANUAL-INSTALL banner de risco; proibir novos polish GUI até Onda A PASS; proibir F6 físico |
| **Ficheiros** | `CORTEX.md`, `MANUAL-INSTALL.md` (secção links), este plano |
| **Teste** | Leitura cruzada: latest ≠ GO enforce até R1 |
| **Versionamento** | Commit documental |
| **Saída** | Equipa/agente não instala “latest” como produção sem GO |
| **Rollback** | Reverter commit documental |

### P1 — Baseline de candidato e lab pronto

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Confirmar **`1.8.11_65`** como candidato único para Gate B1 e preparar snapshot appliance |
| **Porquê `_65`** | Acumula fixes `_25`–`_64` (enforcement, VIP BG-075, PF, logs, nDPI); `_65` altera só GUI (i18n/ícones) — daemon = `_64` |
| **Acções** | Snapshot pfSense; inventário lab; confirmar builder `192.168.100.12`; verificar SHA256 `e7c8ca44…`; `scripts/diagnose-layer7-appliance.sh` baseline; sincronizar `plano-gates-producao.md` |
| **Pré-requisitos humanos** | SSH appliance; snapshot hypervisor; dois clientes LAN para Onda B (podem preparar-se aqui) |
| **Ficheiros** | `CORTEX.md`, `plano-gates-producao.md`, notas em `docs/08-lab/` ou `docs/tests/evidence/` |
| **Teste** | Snapshot restaurável; SHA bate; appliance acessível; G0.1–G0.2 PASS local |
| **Versionamento** | Commit documental |
| **Saída** | `candidato_lab=1.8.11_65` no CORTEX; G1 PASS no builder para `_65` (rebuild se necessário) |
| **Rollback** | Restaurar snapshot pré-P1 |

---

## 5. MEIO — Fecho técnico (software)

### Onda A — Gate B1 passivo (G2–G4)

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Provar que o candidato lab instala e corre em **monitor passivo** sem partir rede |
| **SSOT gates** | `docs/09-blocking/plano-gates-producao.md` G2–G4 |
| **Acções** | `pkg add` com `enabled=false` / `mode=monitor`; ABI/`ldd`; zero blocks; `pfctl -nf` ruleset; captura `cap_*`; evidência `run_id` |
| **Multitarefa** | Explore read-only OK; **zero** edição de código até causa-raiz no appliance |
| **Saída** | G2–G4 PASS registados; CORTEX actualizado |
| **Falha** | STOP; não abrir G5; documentar FP; rollback snapshot |

### Onda B — Gate two-client (G5)

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Provar enforce **per-client** com `scoped_hybrid` |
| **SSOT** | `docs/04-package/validacao-lab.md` §12; `tests/lab/smoke-enforcement-scoped.sh` |
| **Acções** | Clientes A/B; YouTube (ou app) block só A; sem quarentena indevida; state kill; allow vs nativo |
| **Saída** | G5 PASS; desbloqueia E4+ e discussão de promoção |
| **Falha** | Manter NO-GO; `_24` permanece referência enforce |

### Onda C — Fecho F3 (DR-05)

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Fechar licenciamento no appliance → relatório `F3 pode fechar` |
| **SSOT** | `docs/00-overview/f3-11-start-here.md`; gate F3.8 |
| **Acções** | Cenários mutáveis `.lic` (offline, grace, NIC/UUID, snapshot) via control plane legítimo |
| **Backlog** | Desbloqueia BG-006, BG-007, BG-008, BG-027 |
| **Multitarefa** | **Não** paralelo com Onda B no **mesmo** appliance se ambos mudam estado; OK sequencial ou appliance dedicado |
| **Saída** | F3 fechada no CORTEX/roadmap |

#### Trilha paralela F3 — BG-077 (check-in online / revogação remota)

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Cancelamento comercial corta enforce no appliance sem acesso físico |
| **SSOT** | `docs/01-architecture/f3-plano-check-in-online-revogacao-remota.md`; ADR-0021 |
| **Backlog** | BG-077 |
| **Relação com Onda C** | Complementa DR-05 (S09/S14); **não substitui** passos 4.1–4.2 |
| **Multitarefa** | Bloco 1 (license-server) **pode** correr em paralelo com docs/evidência 4.2; Blocos 2–3 exigem build `.pkg` e appliance |
| **Gate comercial** | Desejável **antes** do GO Onda F (revogação remota) |
| **Saída** | API `POST /api/license/check-in` + daemon + cenário S14 PASS |

### Onda D — VIP + F4 evidência

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Fechar features já no código: VIP DNS/PF live + package/blacklists/`force_dns` |
| **SSOT** | `validacao-lab.md` §10a, §10b, §11, §19–20 |
| **Acções** | Executar roteiros; marcar BG-009/010/011; confirmar tabelas `exc_allow` live (BG-075) |
| **Multitarefa** | 10a vs docs de blacklists (pastas distintas) OK; appliance steps serial |
| **Saída** | F4 fechada ou gaps listados com severidade |

### Onda E — Compatibilidade pfSense CE

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Validar claim **CE** (lab actual pode ser Plus/FB16) |
| **SSOT** | `docs/09-blocking/matriz-compatibilidade-ce-plus-freebsd.md` |
| **Acções** | VM CE dedicada: install passivo + smoke monitor mínimo |
| **Saída** | CE PASS ou limitação formal documentada (ADR se necessário) |

### Onda F — Promoção a produção (GO humano)

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Uma versão deixa de ser “candidato” e passa a **referência enforce** |
| **Pré-condições obrigatórias** | Ondas **A–B PASS**; Ondas **C–D–E PASS**; G6–G7 documentados; relatório `run_id` único |
| **Pré-condições para R1–R12 completo** | Ondas G, H, I também PASS (fecho na Onda J) |
| **Acções** | Veredicto humano GO; actualizar CORTEX (`produção enforce = 1.8.11_69`); MANUAL-INSTALL comandos **todos** na versão GO; changelog; ADR-0022 aceite (CE LIMITAÇÃO) |
| **Versionamento** | Tag GitHub = GO; releases posteriores ao GO como **prerelease** até Onda J |
| **Saída** | R1 + R6 satisfeitos (GO); R3–R5 já satisfeitos pelas ondas C–D |

### Onda G — F5 malha mínima de regressão

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Impedir regressão após GO |
| **SSOT** | `docs/02-roadmap/f5-preparacao-malha.md`; BG-012/013/014 |
| **Acções** | Mapear backlog ↔ R-IDs; smoke CI + builder + appliance checklist único; sem F6 |
| **Saída** | R8 satisfeito |

---

## 6. FIM — Consolidação documental e release

### Onda H — F6 consolidação da árvore `docs/` (e legado raiz)

**Só depois de F5 mínima (Onda G) e GO técnico (Onda F) ou decisão formal de “GO documental antecipado” no CORTEX.**

#### H.0 Mapa prévio obrigatório (sem mover ainda)

Inventariar e decidir destino de cada sobreposição (já parcialmente no mapa de equivalência):

| Actual (visível na árvore) | Problema | Destino canónico proposto |
|----------------------------|----------|---------------------------|
| `docs/04-tests/` vs `docs/tests/` | Duplicado | Manter `docs/tests/`; arquivar `04-tests` |
| `docs/package/` vs `docs/04-package/` | Duplicado | Manter `04-package/`; fundir ou arquivar `package/` |
| `docs/05-daemon/` e `docs/05-runbooks/` | Mesmo prefixo numérico | Renumerar runbooks → `13-runbooks` **ou** fundir índice (decisão no mapa H.0) |
| `docs/10-license-server/` e `docs/10-logging/` | Mesmo prefixo | Renumerar logging → `14-logging` (proposta) |
| `docs/core/`, `changelog/`, `commercial/`, `poc/`, `tutorial/` | Sem número | Manter; indexar claramente no `docs/README.md` |
| Raiz `00-`…`16-` | Legado | Preservar ou `docs/archive/raiz-legado/` conforme mapa |

#### H.1 Execução F6 por lotes (multitarefa segura)

| Lote | Owner | Move/renomeia | Proibido tocar |
|------|-------|---------------|----------------|
| H1 | Agente Docs-A | `04-tests` → archive | `CORTEX.md` |
| H2 | Agente Docs-B | `package/` vs `04-package` | `Makefile`, código |
| H3 | Agente Docs-C | renumerar `10-logging` / `05-runbooks` | `MANUAL-INSTALL.md` até lote H4 |
| H4 | Coordenador | links + `docs/README.md` + equivalência + CORTEX | — |

Cada lote: **mapa de links afectados → move → grep links partidos → commit → tag opcional `docs-f6-loteN`.**

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | R9 — árvore navegável sem pastas gémeas |
| **Saída** | Equivalência + classificação actualizadas; zero link partido nos canónicos |
| **Rollback** | `git revert` do lote / restore paths |

### Onda I — F7 release engineering + confiança

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | R7 + R10 — publicar sem memória tribal |
| **Acções** | Checklist BG-017; activar BG-028 (manifesto pacote) ou ADR de adiamento; BG-018 telemetria mínima; BG-029 refresh blacklists; runbook único build→sign→publish |
| **Saída** | Uma release completa só com checklist |

### Onda J — Aceitação final

| Campo | Conteúdo |
|-------|----------|
| **Objectivo** | Checklist R1–R12 todos verdes ou excepções assinadas |
| **Acções** | Auditoria conjunta (humano + agente); actualizar CORTEX “PRODUTO PRONTO PARA UTILIZAÇÃO”; handoff comercial se aplicável (`docs/commercial/`) |
| **Saída** | Fim do plano; manutenção passa a backlog normal |

---

## 7. Guia de passos numerados (execução dia-a-dia)

Usar esta lista como **fila única**. Marcar no CORTEX o passo actual.

| Passo | Onda | Objectivo do passo | Resultado mensurável | Modo | Versionar? |
|-------|------|--------------------|----------------------|------|------------|
| 0.1 | P0 | Dual-canal explícito | Banner + CORTEX + gates + `00-LEIA-ME` | Coord.+workers | Commit docs |
| 0.2 | P0 | Congelar polish GUI / F6 | Regra no plano+CORTEX | Agente único | Commit docs |
| 1.0 | P1 | G0–G1 no candidato `_65` | `run-local.sh` PASS; build builder OK | Agente único | Commit se rebuild |
| 1.1 | P1 | Confirmar candidato `_65` | Nome+SHA no CORTEX + `plano-gates` | Coordenador | Commit docs |
| 1.2 | P1 | Snapshot appliance | Snapshot ID registado | Humano+agente | Nota lab |
| 1.3 | P1 | Diagnose baseline | Log diagnose guardado | Worker read-only | Evidência |
| 2.1 | A | Install passivo G2 | G2 PASS (`_65`) | Agente único | — |
| 2.2 | A | PF parser G3 | G3 PASS | Agente único | — |
| 2.3 | A | Captura monitor G4 | G4 PASS | Agente único | Commit docs evidência |
| 2.4 | A | Checkpoint CORTEX | NO-GO ou A-PASS | Coordenador | Commit docs |
| 3.1 | B | Two-client G5 | G5 PASS | Agente único | Commit docs |
| 3.2 | B | Smoke scoped | script exit 0 | Agente único | Commit se fix |
| 4.1 | C | DR-05 campanha | Evidências S* | Agente único | Pack F3 |
| 4.2 | C | Relatório F3 | `F3 pode fechar` | Agente único | Commit docs |
| 4.3 | C / F3 | BG-077 Bloco 1 — API check-in | `POST /api/license/check-in` + testes | Agente único | Commit backend |
| 4.4 | C / F3 | BG-077 Blocos 2–3 | daemon + S14 PASS | Agente único | PORTREVISION + evidência |
| 4.5 | C / F3 | BG-077 Bloco 4 | runbook cancelamento | Agente único | Commit docs |
| 5.1 | D | Lab 10a/10b/11 | F4 evidência | Agente único | Commit docs |
| 5.2 | D | Lab VIP §20 | VIP live OK | Agente único | Commit docs |
| 6.1 | E | CE passivo | Matriz CE | Agente único | Commit docs |
| 7.1 | F | GO humano | Produção = `1.8.11_69` | Agente único | Release + MANUAL + CORTEX |
| 7.2 | F | Alinhar latest | latest = GO | Agente único | GitHub |
| 8.1 | G | Mapa testes F5 | BG-012 avançado | Agente único | Commit |
| 8.2 | G | Smoke repetível | Checklist único | Agente único | Commit |
| 9.0 | H | Mapa F6 H.0 | Tabela destinos aprovada | Coordenador | Commit docs **sem move** |
| 9.1+ | H | Lotes H1–H4 | Árvore limpa | Coord.+Docs-A/B/C | Commit por lote |
| 10.1 | I | Checklist release | BG-017 | Agente único | Commit |
| 10.2 | I | Trust pacote BG-028 | Manifesto ou ADR | Agente único | Release |
| 11.1 | J | Auditoria R1–R12 | Todos verdes | Agente único | Commit CORTEX final |

**Correção de bugs descobertos num gate:**  
criar bloco `FIX-n` mínimo → testes → PORTREVISION se preciso → **voltar ao passo do gate**, nunca saltar para F6.

---

## 8. Versionamento (obrigatório)

### 8.1 Regras

| Tipo de mudança | Git | PORTREVISION / tag | Docs no mesmo bloco |
|-----------------|-----|--------------------|---------------------|
| Só docs/plano | commit | não | CORTEX se estado mudar |
| Fix/código pacote | commit | sim se `.pkg` novo | CHANGELOG + MANUAL se release |
| Release pública | commit + push | tag `vPORTVERSION_PORTREVISION` | MANUAL (todos os comandos) + CORTEX |
| Lab sem GO | pode publicar | preferir clareza “candidato”; não promover enforce no CORTEX | dual-canal |
| F6 move | commit **por lote** | tag opcional docs | equivalência + README |

### 8.2 Mensagem de commit (padrão)

```text
<onda/passo>: <porque>

<impacto numa linha>
```

Exemplo: `onda-A/2.4: registar G2-G4 PASS no candidato 1.8.11_65`.

### 8.3 Nunca

- Amend de commit já pushed
- Publicar `_55` ou artefacto incompleto
- Alterar `PORTREVISION` sem necessidade de pacote novo
- Dois agentes a bumparem Makefile em paralelo

---

## 9. Critérios de STOP (parar e pedir humano)

Parar imediatamente se:

- rede/lab partida após install;
- dúvida CE vs Plus/ABI;
- necessidade de segredo/chave de produção;
- vontade de “já que estamos, vamos renomear docs” antes da Onda H;
- conflito entre este plano e CORTEX (resolver no CORTEX, depois actualizar este plano);
- dois agentes a editar ficheiro quente.

---

## 10. Relação com docs existentes (não duplicar SSOT)

| Tema | Continua a mandar |
|------|-------------------|
| Estado/fase | `CORTEX.md` |
| Gates enforce | `docs/09-blocking/plano-gates-producao.md` |
| Lab detalhado | `docs/04-package/validacao-lab.md` |
| F3 | `docs/00-overview/f3-11-start-here.md` + arquitectura F3 |
| F4 | `docs/02-roadmap/f4-plano-de-implementacao.md` |
| F5 | `docs/02-roadmap/f5-preparacao-malha.md` |
| F6/F7 definição | `docs/02-roadmap/roadmap.md` |
| Backlog IDs | `docs/02-roadmap/backlog.md` |
| Instalação | `docs/10-license-server/MANUAL-INSTALL.md` |
| Equivalência docs | `docs/00-overview/document-equivalence-map.md` |

Este plano **orquestra** a ordem; não substitui os SSOT de área.

---

## 11. Checklist de progresso (copiar para CORTEX ao avançar)

```text
PLANO FECHO/CONSOLIDAÇÃO — progresso
- Passo actual: <id>
- Onda: <P0|P1|A…J>
- Candidato lab: 1.8.11_69
- Produção enforce: 1.8.11_69 (GO Onda F 2026-08-05)
- Canal latest: 1.8.11_69
- G0-G1: <PENDENTE|PASS>
- G2-G4: <PENDENTE|PASS|FAIL>
- G5: <PENDENTE|PASS|FAIL>
- G6-G7: <PENDENTE|PASS>
- F3: <ABERTA|FECHADA>
- F4: <ABERTA|FECHADA>
- VIP §20: <PENDENTE|PASS>
- CE: <PENDENTE|PASS|LIMITAÇÃO>
- GO humano (Onda F): SIM 2026-08-05
- Produto pronto (Onda J): <NÃO|SIM>
- F5 mínima: <PENDENTE|PASS>
- F6: <NÃO INICIADA|H.0|H1…|FECHADA>
- F7/BG-028: <PENDENTE|PASS|ADR ADIADO>
- R1-R12: <contagem verdes>/12
- Próximo passo autorizado: <id>
- Multitarefa activa: <não | onda + workers + ficheiros>
```

---

## 12. Histórico do plano

| Data | Evento |
|------|--------|
| 2026-08-04 | Criação do plano mestre + START-HERE após análise híbrida read-only |
| 2026-08-04b | Rev. alinhamento: candidato lab fixado `_65`; dual-canal; multitarefa com grafo de dependências; G0/G1 no passo 1.0; GO exige C–E PASS; docs gates/checklist/00-LEIA-ME sincronizados |
| 2026-08-04c | Passo 1.1 PASS: candidato `_65` confirmado (SHA256 + GitHub `latest` + PORTREVISION=65); CORTEX avança para passo 1.2 |
| 2026-08-04d | Passo 1.3 PASS: diagnose baseline; passo 1.2 PASS (Veeam + MANUAL-INSTALL) |
| 2026-08-04e | Passo 2.1 PASS: G2 install passivo `_65` no appliance `254` |
| 2026-08-04f | Onda A PASS: G3–G4 (passos 2.2–2.4); próximo 3.1 G5 two-client |
| 2026-08-04g | Onda B G5 FAIL: clientes 234/235; LAN pass any antes regras Layer7; rollback aplicado |
| 2026-08-04h | Fix `_66` pfnearly; G5.1–G5.2 PASS (reteste); G5.3–G5.7 pendentes; próximo 3.2 ou Onda C |
| 2026-08-04i | Onda B PASS: G5.1–G5.7 no appliance 254 (`_66`); próximo 4.1 Onda C (F3 DR-05) |
| 2026-08-04j | Onda E LIMITAÇÃO: passo 6.1 — sem VM CE na malha lab; ADR-0022; proxy Plus `_69` parcial; próximo Onda F prep (humano) ou Onda G |
| 2026-08-05a | Pós-Veeam: cleanup `g5-test-bl`; reteste paridade CE; Onda G 8.1 mapa F5; próximo 8.2 |
| 2026-08-05b | Onda G PASS: passo 8.2 checklist smoke (`20260805T005650Z`); próximo Onda F prep ou 9.0 H.0 |
| 2026-08-05c | **Onda F PASS:** GO enforce `1.8.11_69` (`20260805T010100Z`); ADR-0022 aceite; `latest` alinhado; próximo 9.0 H.0 ou Onda I |
