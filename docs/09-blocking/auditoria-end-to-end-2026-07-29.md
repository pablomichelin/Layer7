# Auditoria técnica end-to-end — Layer7 pfSense

**Data:** 2026-07-29  
**Rodada:** Etapa 1 — auditoria local read-only (sem appliance, sem builder, sem commit)  
**Empresa:** Systemup Solução em Tecnologia  
**Versão repo:** `1.8.11_31` (`PORTREVISION=31`)  
**Release pública:** `1.8.11_24` (`v1.8.11_24`)  
**Estado geral:** **CANDIDATO INTERNO EM VALIDAÇÃO**

---

## VEREDITO EXECUTIVO

| Critério | Estado |
|----------|--------|
| Release pública `_24` segura em **monitor passivo** | ☑ Evidência histórica Fase 1 + observação 2026-07-29 |
| Release `_24` pronta para **enforce scoped per-client** | ☐ **NO-GO** — legacy_global + bugs FP-001..020 |
| Candidato `_31` pronto para **instalação passiva** lab | ☐ **NO-GO** — gate G2 pendente (ABI FB16) |
| Candidato `_31` pronto para **enforce produção** | ☐ **NO-GO** — G3–G7 pendentes |
| Publicar `_31` como release GitHub | ☐ **NO-GO** |
| Working tree limpo para release | ☐ Modificações locais não relacionadas |
| Testes locais macOS | ☑ `run-local.sh` PASS (PHP SKIP) |
| Gates físicos two-client / state kill | ☐ **PENDENTES** |

**Veredicto release:** **NO-GO** para `_31` e para activação enforce. Manter `_24` como referência pública; próximo passo autorizado = **Gate passivo B1** (`plano-gates-producao.md`).

---

## A) Inventário factual

### A.1 Git e release

| Item | Valor |
|------|-------|
| Branch | `main` |
| HEAD | `2ba9b5f9855105322894fe5b1fd8cc39d209fc99` |
| Mensagem | `docs: record Layer7 1.8.11_31 build evidence` |
| `origin/main` | Alinhado (`up to date`) |
| Describe | `v1.8.11_24-22-g2ba9b5f` |
| Tags pacote | `v1.8.11_23`, `v1.8.11_24` (última pública) |
| Modified | `00-LEIA-ME-PRIMEIRO.md`, `license-server/backend/src/routes/activate.js` |
| Untracked | `artifacts/pfSense-pkg-layer7-1.8.11_{24,25,26,29,30,31}.pkg.sha256` |

### A.2 Versões

| Artefacto | Valor |
|-----------|-------|
| PORTVERSION | `1.8.11` |
| PORTREVISION (repo) | `31` |
| Release pública | `1.8.11_24` SHA256 `1d5573f0a0c7803a87d8cb536ad9eee43e85daa9bf98bf7edc84ef554e2c7818` |
| Candidato repo | `1.8.11_31` SHA256 `dc5118dd01193a83a6c6d15cc3ae4ca300647294a5b188e1991a363b4c453e33` |
| `layer7d -V` (código) | `"${PKGVERSION}"` → `1.8.11_31` quando built |
| nDPI | 5.x estático (`/usr/local/lib/libndpi.a` no builder) |
| enforcement_model default | `legacy_global` |
| scoped_hybrid | Experimental OFF por defeito |

### A.3 Diferenças `_24` → `_31` (22 commits)

| Versão | Backlog | Tema principal |
|--------|---------|----------------|
| `_25` | BG-053 | PID rc.d, interfaces reais, scoped validation |
| `_26` | BG-054 | Contenção logs L1 (`log_store.c`) |
| `_27` | BG-055 | Hash fluxo, pdst/psrc, state kill, TTL SNI |
| `_28` | BG-056 | `pallow` + tag `L7ALLOW` (sem pass quick) |
| `_29` | BG-057 | Sintaxe anti-QUIC `on <if> inet` |
| `_30` | BG-058 | Flow table probe + evicção |
| `_31` | BG-059 | `NDPI_STATE_CLASSIFIED` + giveup@48 |

**Diff estatístico:** 81 ficheiros, +5067 / −625 linhas (`v1.8.11_24..HEAD`).

**Evidência bugs em `_24` (git show tag):**

- `capture.c`: `flow_hash()` sem canonicalização bidireccional; `ndpi_is_protocol_detected()` sem `NDPI_STATE_CLASSIFIED`.
- `rc.d/layer7d`: PID read sem tolerância EOF/no newline.

### A.4 Mapa de componentes

```
[GUI PHP] → layer7.json (+ blacklists/config.json)
                │
    ┌───────────┼───────────┐
    ▼           ▼           ▼
 layer7d     pfSense      Unbound
 (nDPI/      filter       anti-DoH
  DNS/       reload       (config.xml)
  policy)       │
    │           ▼
    │      layer7.inc → PF tables + rules
    └──────► pfctl -T add / -k
```

Ver matriz detalhada: `matriz-cadeia-de-enforcement.md`.

### A.5 Dependências (Makefiles)

| Alvo | Dependências |
|------|--------------|
| `layer7d` | OpenSSL libcrypto.a, opcional libndpi.a + libpcap |
| Fontes | 10× `.c` daemon + `capture.c` se nDPI |
| PHP pacote | API pfSense (`filter_configure`, `get_real_interface`) |
| Scripts | `layer7-pfctl`, `rc.d/layer7d`, hooks `layer7.xml` |

---

## B) Matriz compatibilidade CE / Plus / FreeBSD

Documento dedicado: `matriz-compatibilidade-ce-plus-freebsd.md`.

**Síntese:** produto declarado CE-only; lab observado Plus 26.03.1 / FreeBSD 16; builder FreeBSD 15. **Gap crítico FP-011** — binário `_31` não instalado em FB16 nesta rodada.

---

## C) Análise estática por subsistema

### C.1 Lifecycle daemon (rc.d, pidfile, reload, monitor)

| Aspecto | Avaliação | Evidência |
|---------|-----------|-----------|
| Arranque | `daemon -f -p` + chmod pidfile | `rc.d/layer7d:57-78` |
| PID newline | Corrigido `_25` | `test_rc_pidfile.sh` PASS |
| SIGHUP reload | close/open captures, flush cache | `main.c:2566-2684` |
| Monitor passivo | `enabled=false` → sleep idle | `main.c:2735-2738` |
| Stop | flush-all defesa profundidade | `rc.d stop` + `main.c:2506` |

### C.2 Interfaces lógico vs real

| Aspecto | Avaliação |
|---------|-----------|
| Normalização | PHP `layer7_config_interfaces_normalize()` no save |
| Daemon | Usa string literal do JSON — sem fallback |
| Risco | JSON manual com `lan` → `captures=0` (AUD-008) |

### C.3 libpcap

Promisc ON, snaplen 1536, timeout 100 ms, IPv4, DLT_EN10MB/RAW. Falha parcial por interface não aborta outras.

### C.4 Flow table / normalização

65536 slots; hash canónico `_27`; probe 64 slots + evicção LRU `_30`; métricas JSON `_30+`.

### C.5 nDPI

Contrato `_31`: finalizar só em `NDPI_STATE_CLASSIFIED` ou `giveup` no orçamento 48 pacotes.

### C.6 Policy engine / precedência

`layer7_decide_for_client()`: excepções → políticas (sort) → default allow. Blacklist não override allow explícito (`_27+`).

### C.7 psrc / pdst / quarentena / scope_global

| Match | Tabela | Condição |
|-------|--------|----------|
| App/cat/host normal scoped | `pdst_N` | default |
| Quarentena | `psrc_N` | `quarantine_origin=true` |
| App-only sem origem | `pdst_N` + `scope_global` | GUI validation |
| Legacy | `layer7_block_dst` | global |

### C.8 Enforcement PF

Tabelas, anchors, `L7ALLOW`, anti-QUIC, NAT `natrules/layer7_nat`. Ver `matriz-cadeia-de-enforcement.md`.

### C.9 State table

Ver `matriz-state-table.md`. Kill selectivo `_27+`; **gate físico pendente**.

### C.10 DNS / blacklists

UT1 assinado F1.3; `blsrc_N` + `bld_N`; TTL cache; QNAME CNAME fix `_27`.

### C.11 Licença fail-safe

| Cenário | Comportamento `_31` |
|---------|---------------------|
| Boot sem licença | `s_ge=0` + flush | `main.c:2440-2441` |
| Recheck inválida | `enforce_ge_downgrade()` | `main.c:2725-2727` |
| Grace | enforce permitido | `refresh_enforce_cfg` |
| Revogação offline | `.lic` antigo válido até grace — limite F3 | doc |

### C.12 Segurança C / PHP / shell

| Área | Achado |
|------|--------|
| C | Parser JSON frágil (`strstr`) — FP-015 |
| C | Fork pfctl sem sandbox — expected |
| PHP | Inputs validados em allowlist/scoped — testes |
| Shell | Lint PASS 12 scripts |
| License | Ed25519 prod key embarcada (não dev zeros) |

---

## D) Registro de defeitos (IDs sequenciais AUD-*)

Convenção existente: **REV-** (revisão 2026-06-15), **FP-** (revisão funcional 2026-07-29), **BG-** (backlog).  
Esta rodada introduz **AUD-001..AUD-015** para achados da auditoria E2E; referencia IDs anteriores quando aplicável.

---

### AUD-001 — Gate two-client pendente bloqueia validação scoped

| Campo | Valor |
|-------|-------|
| **Severidade** | Crítica |
| **Componente** | testes / release |
| **Versões** | `_24`..`_31` |
| **Sintoma** | Nenhuma evidência de two-client PASS |
| **Evidência** | CORTEX, checklist-mestre, validacao-lab §12; FP-016 |
| **Causa-raiz** | Gate nunca executado no appliance |
| **Impacto** | scoped_hybrid não verificável |
| **Probabilidade** | Certa (bloqueio processo) |
| **Correcção mínima** | Executar G5 com run_id |
| **Estado** | REPRODUZÍVEL (processo) |

---

### AUD-002 — Release pública `_24` contém defeitos enforcement conhecidos

| Campo | Valor |
|-------|-------|
| **Severidade** | Crítica |
| **Componente** | capture / policy / PF |
| **Versões** | `_24` |
| **Sintoma** | "Não bloqueia" / "bloqueia tudo" reportados |
| **Evidência** | `git show v1.8.11_24:capture.c` — hash simples, nDPI parcial |
| **Causa-raiz** | FP-001, FP-002, FP-003, FP-020 em `_24` |
| **Impacto** | enforce não confiável |
| **Correcção mínima** | Não usar `_24` para enforce; candidato `_31` + gates |
| **Estado** | REPRODUZÍVEL (código tag) |

---

### AUD-003 — Candidato `_31` sem evidência appliance

| Campo | Valor |
|-------|-------|
| **Severidade** | Alta |
| **Componente** | package / QA |
| **Versões** | `_31` |
| **Sintoma** | Build PASS mas zero install passivo |
| **Evidência** | CORTEX "gate passivo pendente"; esta rodada |
| **Causa-raiz** | Política intencional produção intocada |
| **Impacto** | NO-GO release |
| **Correcção mínima** | Bloco B1 plano gates |
| **Estado** | REPRODUZÍVEL |

---

### AUD-004 — ABI FreeBSD 15 vs 16 não validada (FP-011)

| Campo | Valor |
|-------|-------|
| **Severidade** | Alta |
| **Componente** | build / runtime |
| **Versões** | `_31` |
| **Sintoma** | Binário pode falhar em FB16 |
| **Evidência** | Builder FB15; appliance FB16 observado |
| **Causa-raiz** | nDPI estático linkado no builder |
| **Impacto** | Daemon não arranca |
| **Correcção mínima** | G2.5 `ldd` + `layer7d -t` no appliance |
| **Estado** | SUSPEITA prioritária |

---

### AUD-005 — legacy_global default vs UX per-device (REV-001 / FP-009)

| Campo | Valor |
|-------|-------|
| **Severidade** | Crítica (expectativa) |
| **Componente** | policy / UX |
| **Versões** | todas |
| **Sintoma** | Bloquear app para um device bloqueia todos |
| **Evidência** | `layer7_block_dst` + default JSON | `layer7.inc:1455` |
| **Causa-raiz** | By design até E8 |
| **Impacto** | Claim comercial parcial |
| **Classificação** | Limitação + claim incorreto na GUI |
| **Estado** | REPRODUZÍVEL |

---

### AUD-006 — Parser JSON frágil (FP-015)

| Campo | Valor |
|-------|-------|
| **Severidade** | Média |
| **Componente** | config_parse.c |
| **Sintoma** | Ordem/campos JSON pode quebrar parse |
| **Evidência** | `strstr` parsing | `config_parse.c` |
| **Estado** | REPRODUZÍVEL (código) |

---

### AUD-007 — IPv4-only na captura (FP-010)

| Campo | Valor |
|-------|-------|
| **Severidade** | Alta |
| **Componente** | capture.c |
| **Classificação** | Limitação arquitectural V1 |
| **Estado** | REPRODUZÍVEL |

---

### AUD-008 — Interface lógica no JSON quebra captura

| Campo | Valor |
|-------|-------|
| **Severidade** | Alta |
| **Componente** | GUI/daemon |
| **Versões** | `_24` FAIL; `_25+` mitigado no save |
| **Evidência** | BG-053; edição manual JSON |
| **Estado** | REPRODUZÍVEL |

---

### AUD-009 — Trust chain pacote inactiva (BG-028)

| Campo | Valor |
|-------|-------|
| **Severidade** | Alta |
| **Componente** | release-engineering |
| **Sintoma** | Instalação manual SHA256 only |
| **Estado** | REPRODUZÍVEL (doc) |

---

### AUD-010 — Anti-QUIC syntax `_24` (FP-018)

| Campo | Valor |
|-------|-------|
| **Severidade** | Crítica em `_24` com toggle ON |
| **Versões** | `_24` FAIL; `_29+` corrigido |
| **Evidência** | `inet on <if>` rejeitado pfSense 26 |
| **Estado** | REPRODUZÍVEL (pré-gate read-only) |

---

### AUD-011 — State kill sem evidência física (FP-003)

| Campo | Valor |
|-------|-------|
| **Severidade** | Crítica |
| **Versões** | `_27+` código corrigido |
| **Estado** | SUSPEITA até G5.5 |

---

### AUD-012 — Pressão flow table (FP-012)

| Campo | Valor |
|-------|-------|
| **Severidade** | Alta |
| **Versões** | `_30+` métricas |
| **Estado** | SUSPEITA |

---

### AUD-013 — Testes PHP SKIP no host auditoria

| Campo | Valor |
|-------|-------|
| **Severidade** | Média |
| **Componente** | QA local |
| **Evidência** | `run-local.sh` SKIP php |
| **Correcção** | Executar no builder ou instalar PHP macOS |
| **Estado** | REPRODUZÍVEL |

---

### AUD-014 — Working tree sujo para release

| Campo | Valor |
|-------|-------|
| **Severidade** | Média |
| **Componente** | governança |
| **Evidência** | `git status` modified + untracked |
| **Estado** | REPRODUZÍVEL |

---

### AUD-015 — Documentação pf-enforcement desactualizada

| Campo | Valor |
|-------|-------|
| **Severidade** | Baixa |
| **Componente** | docs |
| **Evidência** | D-ENC-001 matriz enforcement |
| **Estado** | REPRODUZÍVEL |

---

## E) Testes executados nesta rodada

| Teste | Resultado | Notas |
|-------|-----------|-------|
| `sh tests/run-local.sh` | **PASS** | 8 suites C + shell; PHP SKIP |
| `git log v1.8.11_24..HEAD` | OK | 22 commits |
| `git diff v1.8.11_24..HEAD --stat` | OK | 81 files |
| `git show v1.8.11_24:capture.c` | OK | Confirma bugs _24 |
| Builder / appliance | **NÃO EXECUTADO** | Conforme regras |

---

## F) Divergências documentação vs código

| ID | Doc | Código | Tipo |
|----|-----|--------|------|
| D-ENC-001 | pf-enforcement topo | scoped pdst/psrc | Doc stale |
| D-ENC-002 | UT1 layer7_bl_except | blsrc_N | Histórico |
| D-ENC-003 | config.xml SSOT | layer7.json | Terminologia |
| D-ENC-004 | Per-device blocking | legacy_global | Claim parcial |

---

## G) Comparativo `_24` vs `_31`

Ver secção A.3 e matrizes associadas. **Conclusão:** `_31` corrige em código **FP-001..008, FP-017..020** (e parcialmente FP-004/012); **FP-009..016 permanecem abertos** (design, limitação, processo ou defect não corrigido); **nenhuma** correção validada fisicamente no appliance.

---

## H) Cadeia de enforcement

Ver `matriz-cadeia-de-enforcement.md`.

---

## I) Licenciamento fail-safe

Recheck horário chama `enforce_ge_downgrade()` — REV-002 fechado em `_24+`. Cenários F3 DR-05 **pendentes** no appliance.

---

## J) Segurança

Allowlist rejeita `/0` (REV-003). Sem exec injection evidente nos paths auditados. `activate.js` modificado localmente — **fora do scope pacote**; não auditado nesta rodada.

---

## K) Blacklists / DNS

Pipeline F1.3 activo; runtime alinhado com ADR blacklists. Gate 10b appliance pendente.

---

## L) Limitações arquitecturais

Ver `matriz-limitacoes-dpi.md`. V1: sem MITM, sem IPv6, sem console central.

---

## M) Top 10 defeitos / suspeitas prioritárias

| # | ID | Título | Severidade | Estado |
|---|-----|--------|------------|--------|
| 1 | AUD-001 / FP-016 | Gate two-client pendente | Crítica | Processo |
| 2 | AUD-002 | `_24` com bugs FP-001..020 | Crítica | Código tag |
| 3 | AUD-005 / FP-009 | legacy_global vs per-device | Crítica | Design |
| 4 | AUD-004 / FP-011 | ABI FB15 vs FB16 | Alta | SUSPEITA |
| 5 | AUD-011 / FP-003 | State kill sem evidência física | Crítica | SUSPEITA |
| 6 | AUD-003 | `_31` sem install passivo | Alta | Processo |
| 7 | AUD-010 / FP-018 | Anti-QUIC `_24` | Crítica* | REPRODUZÍVEL |
| 8 | AUD-012 / FP-012 | Pressão flow table | Alta | SUSPEITA |
| 9 | AUD-009 / BG-028 | Trust chain pacote off | Alta | Doc |
| 10 | AUD-007 / FP-010 | IPv4-only | Alta | Limitação |

*Crítica quando anti-QUIC activo.

---

## N) Documentos produzidos nesta rodada

| Ficheiro | Papel |
|----------|-------|
| `auditoria-end-to-end-2026-07-29.md` | Relatório principal (este) |
| `matriz-cadeia-de-enforcement.md` | Cadeia config→PF |
| `matriz-state-table.md` | pfctl -k / flush |
| `matriz-compatibilidade-ce-plus-freebsd.md` | CE/Plus/FB |
| `matriz-limitacoes-dpi.md` | nDPI / captura |
| `plano-gates-producao.md` | Gates G0–G7 |
| `docs/tests/layer7-regression-matrix.md` | Regressão R-01..R-20 |

---

## DIAGNÓSTICO DA RODADA

```
DIAGNÓSTICO DA RODADA
1. Sintoma prioritário:
   Produto publicado (_24) não cumpre enforcement per-client confiável;
   candidato _31 corrige causas-raiz no código mas não tem evidência física.

2. Caminho de execução:
   layer7.json → layer7d (pcap/nDPI/DNS) → layer7_decide_for_client()
   → layer7_pf_resolve_block_target() → pfctl -T add → pfctl -k
   Paralelo: filter_configure → layer7.inc regras PF.

3. Evidência encontrada:
   - run-local.sh PASS (macOS); git confirma bugs em tag v1.8.11_24;
   - 22 commits _25-_31 mapeados a BG-053..059 / FP-001..020;
   - CORTEX/checklist: todos gates appliance PENDENTES;
   - working tree com modificações não commitadas.

4. Hipótese de causa-raiz:
   Iteração rápida local/builder sem gate físico two-client; default
   legacy_global mantém semântica global; _24 em produção passiva mascara
   defeitos de enforce.

5. Como reproduzir:
   - Código: git show v1.8.11_24:src/layer7d/capture.c (hash/nDPI);
   - Processo: verificar ausência run_id validacao-lab §12;
   - Local: sh tests/run-local.sh (PASS).

6. Teste que falta:
   G2–G5 plano-gates-producao.md — instalação passiva _31,
   pfctl -nf ruleset completo, two-client scoped, state kill sessão.

7. Risco:
   Activar enforce com _24 ou _31 sem gates → bloqueio collateral,
   bypass por state table, reload PF falhado (anti-QUIC _24),
   binário incompatível FB16.

8. Alteração mínima proposta:
   NENHUMA alteração de código nesta rodada. Executar Bloco B1 (install
   passivo _31 + G2–G4) com snapshot/rollback _24.

9. Gate necessário:
   G2.5 ABI + G5 two-client antes de qualquer enforce scoped em lab;
   G7 humano antes de publicar _31.

10. Rollback:
    Manter _24 passivo; se _31 instalado falhar → pkg reinstall _24,
    layer7-pfctl flush-all, filter_configure, enabled=false/monitor.
```

---

## Rollback desta rodada

Nenhum ficheiro de produto alterado. Apenas documentos novos em `docs/09-blocking/` e `docs/tests/`. Remover ficheiros criados se a rodada for descartada.

---

## Risco da auditoria

**Baixo** — read-only. Nenhuma ligação a builder/appliance/produção.

---

## Próximo passo autorizado (coordenador)

1. Aprovar **Bloco B1** (gate passivo `_31` no appliance) com run_id.
2. Sanear working tree (`activate.js`, LEIA-ME) antes de qualquer release.
3. Reexecutar `run-local.sh` no builder com PHP para fechar R-09..R-11.
4. **Não implementar correcções** até causa-raiz comprovada em appliance.

---

*Auditoria Etapa 1 — Layer7 Systemup — 2026-07-29*
