# Auditoria / decisão — reconciliação enforcement `1.8.11_24`…`_65` vs base `1.9.46`

**Data:** 2026-08-09  
**Tipo:** artefacto canónico de auditoria e decisão (F4 / Caminho B)  
**Escopo:** documental read-only — **sem** código, **sem** build, **sem** pacote,
**sem** instalação/activação, **sem** tocar `.254` / `.24` / `.54`, **sem**
apagar evidências untracked abortadas  
**HEAD analisado:** `1165e44` (`PORTVERSION=1.9.46`)  
**Canal GitHub `releases/latest`:** `v1.9.46`  
(`SHA256=10998477ef7ae966e6c3566baeb973f922858fc72cc4d3a2dcdd0fb17bae72f5`)

---

## 0. Declaração obrigatória do bloco

| Campo | Valor |
|-------|-------|
| **Objectivo** | Reconciliar com evidência a linha histórica de enforcement `1.8.11_24`…`_65` face à base actual `1.9.46`, sem assumir que as linhas podem ser misturadas, e eleger **um único** pacote seguro para o próximo bloco técnico — ou declarar **NO-GO**. |
| **Impacto** | Fecha ambiguidade operacional (instalar `_NN` histórico vs ficar em `1.9.x`); alinha backlog/checklist que ainda apontam para B1/`_31`/`_65`; não altera runtime. |
| **Risco** | Baixo (só docs). Risco residual se alguém ignorar o veredicto e instalar `1.8.11_*` sobre lab `1.9.46` (perda de MITM/Identity/IPv6/pfnearly). |
| **Testes** | Inventário por changelog + presença de símbolos/ficheiros em HEAD + tags Git locais + API GitHub Releases. **Não** houve appliance, PF nem build. |
| **Rollback** | Reverter este commit documental; estado runtime inalterado. |

---

## 1. Veredicto executivo

| Decisão | Estado |
|---------|--------|
| Instalar / activar qualquer `1.8.11_24`…`_65` como próximo bloco | **NO-GO** |
| Misturar artefacto `1.8.11_*` com base/código `1.9.46` | **NO-GO** |
| Usar tags Git locais `v1.8.11_32` / `_56` / `_65` como SSOT de código | **NO-GO** (drift — ver §7) |
| **Único pacote elegível** para próximo bloco técnico em lab | **`1.9.46`** (já publicado; `latest`) |
| Promoção produção enforce para além de `1.9.8` | **PENDENTE GO** (fora deste bloco; não confundir com `latest`) |
| Activação MITM permanente | **NO-GO** (trilha Identity+MITM; novo GO + runbook) |

**Síntese:** a linha `_24`…`_65` **não é candidata de instalação**. As correcções
de enforcement dessa linha **já estão integradas** na genealogia que produziu
`1.8.11_69` → `1.9.0` → … → `1.9.46`. O próximo bloco técnico deve partir de
**`1.9.46`**, com rollback lab **`1.9.42`**. Produção enforce permanece
**`1.9.8`** até GO humano de promoção.

---

## 2. Inventário de diferenças — código e dependências

### 2.1 Dependências (port / daemon)

| Aspecto | `1.8.11_24` (tag Git `v1.8.11_24`) | `1.8.11_65` (release notes / CHANGELOG) | Base actual `1.9.46` (tree HEAD) |
|---------|-------------------------------------|------------------------------------------|----------------------------------|
| `PORTVERSION` / revisão | `1.8.11` / `24` | `1.8.11` / `65` (artefacto GitHub) | `1.9.46` / `0` |
| Fontes daemon base | `main/config_parse/policy/enforce/license/blacklist/bl_config/allowlist` (+`capture` se nDPI) | + `log_store` (desde `_26`) | + `features`, `log_store`, `identity_map`, `identity_ldap`, `identity_radius`, `identity_dc` |
| Helper TLS | ausente | ausente | `src/layer7-tlsproxy/` (`WITH_LAYER7_TLSPROXY=yes`) |
| `LIB_DEPENDS` | nenhum OpenLDAP | nenhum OpenLDAP | `libldap.so.2` (`openldap26-client`) |
| nDPI | estático `/usr/local/lib/libndpi.a` + libpcap | idem | idem (+ pthread via identity) |
| Contagem ficheiros `src/layer7d` | 21 (tag `_24`) | ~subset sem Identity | 32 `.c`/`.h` |

**Regra de não-mistura:** um `.pkg` `1.8.11_*` **não** carrega tlsproxy/Identity/
OpenLDAP da árvore `1.9.46`; reinstalar `_NN` sobre lab actual é **regressão**,
não “hot-fix de enforcement”.

### 2.2 Diff estatístico (tree `package/` + `src/layer7d/` vs tags locais usáveis)

| Base histórica (tag local) | Diff → HEAD | Nota de fiabilidade da tag |
|----------------------------|-------------|----------------------------|
| `v1.8.11_24` | 79 ficheiros; +26522 / −2375 | Tag coerente com release `_24` |
| `v1.8.11_64` | 67 ficheiros; +18186 / −1824 | Tag aponta a docs/SHA `_64` |
| `v1.8.11_65` | igual ao intervalo `_64` nesta medição | **Tag local aponta a changelog `_64` e `PORTREVISION=64`** — **não** usar como SSOT de código `_65` |
| `v1.8.11_32`, `v1.8.11_56` | (inválido para inventário) | Ambas resolvem para o **mesmo** commit de docs `1.8.11_23` |

Tags **ausentes** no clone local (candidatos só documentados / artefacto):
`_25`…`_31`, `_46`, `_48`, `_67`, `_69`. Tags `v1.9.*` **ausentes** no clone
local apesar de `v1.9.46` existir como GitHub Release (`latest`).

### 2.3 Inventário por candidato `_24`…`_65` (papel vs base `1.9.46`)

Legenda **face a `1.9.46`:** `SUPERSEDIDO` = conteúdo absorvido e ultrapassado;
`NÃO INSTALAR` = artefacto defeituoso ou tag inútil; `HISTÓRICO` = só rollback
de memória / referência.

| Versão | Tema principal (BG) | Publicação | Face a `1.9.46` | Evidência |
|--------|---------------------|------------|-----------------|-----------|
| `_24` | Caminho B E0–E3 | GitHub + tag OK | HISTÓRICO / SUPERSEDIDO | release notes; bugs FP-001..020 documentados |
| `_25` | BG-053 PID/iface/scoped | candidato (sem tag Git) | SUPERSEDIDO | CORTEX / CHANGELOG |
| `_26` | BG-054 logs L1 | candidato | SUPERSEDIDO | `log_store` em HEAD |
| `_27` | BG-055 hash/pdst/state kill | candidato | SUPERSEDIDO | `layer7_capture_flow_hash`, `quarantine_origin` |
| `_28` | BG-056 `L7ALLOW`/`pallow` | candidato (supersedido por `_29`) | SUPERSEDIDO | `L7ALLOW` em `layer7.inc` |
| `_29` | BG-057 anti-QUIC `on <if> inet` | candidato | SUPERSEDIDO | geração `on {$qi} inet` em HEAD |
| `_30` | BG-058 flow probe/evict | candidato | SUPERSEDIDO | `cap_evicted` em `main.c` |
| `_31` | BG-059 `NDPI_STATE_CLASSIFIED` | candidato | SUPERSEDIDO | `capture.c` |
| `_32` | BG-061 flush lifecycle | GitHub; **tag local errada** | SUPERSEDIDO | CHANGELOG; R-21; **não confiar na tag local** |
| `_33`…`_47` | updater GUI, block page, HTTPS portal, etc. | maioria publicada | SUPERSEDIDO | CHANGELOG |
| `_48` | BG-064 VIP perfis | **nunca construído** | N/A | consolidado em `_49` |
| `_49`…`_54` | UX VIP/perfis | publicadas | SUPERSEDIDO | CHANGELOG |
| `_55` | BG-070 incompleto | publicada **defeituosa** | **NÃO INSTALAR** | CORTEX / CHANGELOG |
| `_56` | BG-070 integral | GitHub; **tag local errada** | SUPERSEDIDO | **não confiar na tag local** |
| `_57`…`_63` | VIP GUI/limites/DNS/UX | publicadas | SUPERSEDIDO | CHANGELOG |
| `_64` | BG-075 VIP PF live | GitHub + tag docs | SUPERSEDIDO | `layer7_static_origin_tables_apply_to_pf` em HEAD |
| `_65` | BG-076 i18n/FA6 | GitHub Release OK; **tag Git local drift** | SUPERSEDIDO (só GUI) | release API tem `.pkg`; tag local = changelog `_64` |

**Pós-`_65` (fora do intervalo pedido, mas fecha a ponte para `1.9.46`):**
`_66` pfnearly (BG G5), `_68` check-in, `_69` SIGHUP blacklists → `1.9.0`
(semver limpa, mesmo binário que `_69`) → IPv6/`1.9.8` → Identity/MITM até
`1.9.46`.

---

## 3. Correcções: integradas em `1.9.46` vs ainda ausentes / abertas

### 3.1 Integradas na base `1.9.46` (código presente + linhagem CHANGELOG)

| ID / tema | Introduzido em | Evidência em HEAD |
|-----------|----------------|-------------------|
| PID rc.d sem newline / iface real / scoped validation | `_25` | `layer7d_pid_from_file` / normalização iface (F4.1) |
| Contenção logs L1 | `_26` | `log_store.c` |
| Hash bidireccional, pdst/psrc, state kill, allow≻BL | `_27` | `capture_flow_key.h`, `policy.c`, `enforce.c` |
| `pallow` + tag `L7ALLOW` (sem `pass quick`) | `_28` | `layer7.inc` / `allowlist.h` |
| Anti-QUIC `on <if> inet` | `_29` | `layer7.inc` anti-QUIC por iface |
| Flow probe + evicção + métricas | `_30` | `cap_evicted` / `cap_dropped` |
| `NDPI_STATE_CLASSIFIED` + giveup@48 | `_31` | `capture.c` |
| Flush `exc_allow` / BL apply / pkg-deinstall | `_32` / BG-061 | flush paths + R-21 histórico |
| VIP/excepções materializadas no PF live | `_64` / BG-075 | `layer7_static_origin_tables_apply_to_pf` |
| GUI i18n/FA6 | `_65` / BG-076 | `lang/en.php` / icon helpers (linha absorvida) |
| pfnearly (blocks antes do pass LAN) | `_66` | `layer7_pf_early` / pfnearly |
| Gates G2–G7 produção | `_69` / `1.9.x` | `plano-gates-producao.md` + ESTADO-PRODUTO |
| Dual-stack IPv6 comercial documentado | `1.9.1`…`1.9.8` | trilha FECHADA |
| Identity + MITM opt-in (default OFF) | `1.9.14`…`1.9.46` | `identity_*`, tlsproxy, `mitm_effective` |

### 3.2 Ainda abertos / não “corrigíveis” por reinstalar `_24`…`_65`

| Item | Estado em `1.9.46` | Nota |
|------|--------------------|------|
| FP-009 `legacy_global` default | **by_design** | scoped continua experimental até política E8 |
| FP-015 parser JSON frágil | **open** (contrato de teste) | não fecha com `_NN` antigo |
| BG-028 trust chain release | **fase 0 / não activo** | ADR-0023 |
| CE físico no build histórico | **LIMITAÇÃO** ADR-0022 | aceite; lab Plus/FB16 |
| MITM permanente | **NO-GO** | Gate C / teste controlado ≠ activação permanente |
| Promoção enforce > `1.9.8` | **PENDENTE GO** | `latest=1.9.46` ≠ produção enforce |
| Correcções “só em `_25`…`_65` e ausentes de `1.9.46`” | **nenhuma material identificada** | o gap é o inverso: `1.9.46` tem **mais** superfície |

---

## 4. Único candidato recomendado (próximo bloco técnico)

### Escolha

**`1.9.46`** — único pacote recomendado como base de trabalho lab / próximo
bloco técnico sobre o produto actual.

### Justificativa

1. É o `releases/latest` real (`v1.9.46` + `.pkg` + `.sha256` verificados via API).
2. Contém a linhagem completa `_25`…`_69` + IPv6 + Identity/MITM (OFF).
3. Qualquer `1.8.11_24`…`_65` é regressão de superfície e de dependências.
4. Tags Git de vários `_NN` estão **corruptas/desalinhadas** no clone — não
   há base segura para “escolher” um candidato intermédio a partir do Git.
5. Produção enforce **não** muda automaticamente: permanece `1.9.8` até GO.

### Rollback

| Contexto | Acção |
|----------|-------|
| Lab após trabalho em `1.9.46` | Reinstall **`1.9.42`** + `layer7-pfctl flush-all` + MITM OFF (CORTEX) |
| Produção enforce | Permanecer / voltar a **`1.9.8`**; rollback enforce histórico **`1.9.0`** |
| Se alguém instalou `1.8.11_*` por erro no lab actual | Remover pacote; reinstalar **`1.9.46`** (ou `1.9.42`); **não** “completar” com cherry-pick de `_NN` |

### O que **não** é o próximo candidato

- `_31`, `_32`, `_65`, `_69` como alvo de instalação nova  
- misturar `.pkg` antigo com tree `1.9.46`  
- promover `1.9.46` a produção enforce sem GO

---

## 5. Gates exactos, ambiente e evidências **antes** de instalação/activação

> Este bloco **não autoriza** instalação nem activação. Define a barra mínima
> se/quando um bloco técnico futuro for aprovado sobre **`1.9.46`**.

### 5.1 Ambiente

| Nó | Papel | Restrição desta auditoria |
|----|-------|---------------------------|
| `192.168.100.12` | Builder FreeBSD 15 | sem build nesta rodada |
| `192.168.100.254` | Appliance lab (Plus/FB16) | **não tocar** |
| `192.168.100.24` / `.54` | nós auxiliares MITM/lab | **não tocar** |
| `192.168.100.234` / `.235` | clientes two-client | proibidos para MITM permanente (CORTEX) |

Evidências untracked abortadas a **preservar** (não apagar):

- `docs/tests/evidence/20260809T212230Z-phaseBD-d1-254/`
- `docs/tests/evidence/20260809T212234Z-phaseBD-d1-254/`

### 5.2 Gates (ordem) — pacote `1.9.46`, **passivo primeiro**

Referência canónica de sequência: `plano-gates-producao.md` (G0–G7),
adaptada à base actual (não a `_31`/`_65`).

| Gate | Critério mínimo | Evidência |
|------|-----------------|-----------|
| **G0** | Working tree limpo para o bloco; docs desta auditoria lidas | `git status`; este ficheiro |
| **Pré-G2** | Snapshot VM; SHA256 do `.pkg` = `10998477…ae72f5`; `enabled=false`; MITM OFF | snapshot + `sha256` + JSON |
| **G2** | Install/revalidate passivo; `service layer7d status`; zero blocks Layer7; tabelas dinâmicas vazias; `layer7d -V` / `ldd` OK no OS do appliance | `run_id` + outputs |
| **G3** | `pfctl -nf` ruleset completo (anti-QUIC / L7ALLOW / pallow / exc_allow) | log parser |
| **G4** | Monitor: `captures>0`, métricas `cap_*`, sem bloqueio indevido, logs L1 | stats JSON |
| **G5** | Two-client scoped **só** se o bloco for enforce scoped (não MITM) | `validacao-lab` §12 + `run_id` |
| **G6** | Licença fail-safe / flush | DR-05 / stop serviço |
| **MITM** | Fora de G2–G7 clássicos: seguir `START-HERE-identity-mitm.md` + runbook `1.9.46`; permanente **NO-GO** sem novo GO | evidências Gate C já existentes **não** substituem GO permanente |

**Parar imediatamente** se G2.5 (binário/ABI) falhar. **Não** activar enforce
nem MITM “para validar” um candidato `1.8.11_*`.

### 5.3 Evidências já existentes (não reabrir linha `_24`…`_65`)

| Tema | `run_id` / doc |
|------|----------------|
| Produção enforce `1.9.8` | `20260805T150500Z-gv7.4-promocao-1.9.8` |
| Two-client alinhado `1.9.8` | `20260805T162500Z-prod-align-two-client-1.9.8` |
| Gate C MITM `1.9.46` | `20260809T210753Z-phaseBD-d1-254` |
| GO teste controlado `.254` | `20260809T215442Z-phaseBD-d1-254` |
| Auditorias históricas NO-GO `_31` | `auditoria-end-to-end-2026-07-29.md`, `diagnostico-multitask-2026-07-30.md` |

---

## 6. Conflitos documentais reais

| ID | Conflito | Fonte A (desactualizada / parcial) | Fonte B (prevalece) | Resolução desta auditoria |
|----|----------|------------------------------------|---------------------|---------------------------|
| D1 | “Próximo passo = B1 install `_31`/`_65`” | checklist-mestre (itens `_31`/BG-060); backlog BG-053…060 “gate pendente”; `09-blocking/README` “Caminho B em execução / B1 `_31`” | `plano-gates-producao.md` (G2–G7 PASS); `ESTADO-PRODUTO…` (enforce `1.9.8`); CORTEX (`latest=1.9.46`) | **B1 histórico encerrado por supersessão**; não reinstalar `_31`/`_65` |
| D2 | “Produção enforce = `_24`” em notas `_64`/`_65` e vários BG | CORTEX secções históricas / backlog Observações | CORTEX checkpoint + ESTADO-PRODUTO: enforce **`1.9.8`** | Tratar menções a `_24` como **histórico** |
| D3 | Ledger FP/AUD congelado em `_31` NO-GO | `matriz-unificada-rev-fp-aud.md` (2026-07-30) | Gates posteriores PASS + esta reconciliação | Ledger = **histórico de defeitos**; estado de release actual ≠ NO-GO global do produto |
| D4 | Tags Git ≠ releases | tags locais `_32`/`_56`/`_65` | GitHub Releases + CHANGELOG + tree HEAD | **SSOT de artefacto = GitHub Release + SHA256**; tags desalinhadas = higiene pendente |
| D5 | `latest` vs produção enforce | confusão operacional possível | CORTEX: `latest=1.9.46`, enforce=`1.9.8` | Manter dualismo explícito |
| D6 | README `09-blocking` vs fecho G2–G7 | “Caminho B em execução” | plano-gates + ESTADO | Actualizar índice (esta rodada) |

**Não-conflito (esclarecimento):** MITM Gate C PASS em `1.9.46` **não**
reabre a linha `_24`…`_65` nem autoriza enforce MITM permanente.

---

## 7. Menor auditoria adicional (opcional, não bloqueante para eleger `1.9.46`)

Se for preciso higiene Git (não é pré-requisito para trabalhar em `1.9.46`):

1. Inventariar `git ls-remote --tags origin 'v1.8.11_*' 'v1.9.*'` vs
   `gh api .../releases`.
2. Listar tags locais que não apontam ao commit do `PORTVERSION` declarado
   (`_32`, `_56`, `_65` já confirmados).
3. Propor correcção de tags **só** com GO humano (sem reescrever histórico
   publicado sem decisão).

**Não** propor rebuild/`pkg`/`PORTVERSION` nesta auditoria adicional.

---

## 8. Referências usadas

- `CORTEX.md`, `docs/README.md`, roadmap/backlog/checklist, classificação,
  equivalência
- `docs/02-roadmap/f4-plano-de-implementacao.md`
- `docs/04-package/validacao-lab.md`
- `docs/09-blocking/plano-gates-producao.md`
- `docs/09-blocking/auditoria-end-to-end-2026-07-29.md`
- `docs/09-blocking/diagnostico-multitask-2026-07-30.md`
- `docs/09-blocking/matriz-unificada-rev-fp-aud.md`
- `docs/changelog/CHANGELOG.md`
- `docs/06-releases/release-notes-1.8.11_24.md` (+ drafts `_25`…`_27`)
- `docs/00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`
- GitHub Releases API: `v1.9.46` (latest), `v1.8.11_65`

---

## 9. Decisão final

**NO-GO** para eleger qualquer candidato `1.8.11_24`…`_65` como próximo bloco
de instalação/activação.

**GO documental** apenas para adoptar **`1.9.46`** como única base técnica
seguinte em lab, com rollback **`1.9.42`**, produção enforce inalterada em
**`1.9.8`**, MITM permanente **NO-GO**, e preservação das pastas de evidência
abortadas listadas em §5.1.
