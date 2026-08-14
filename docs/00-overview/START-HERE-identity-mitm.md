# START HERE — Identity + MITM Add-on 【FILA FECHADA · 20.37】

> **GO produto** `2026-08-09` — [`GO-produto-20.10.md`](../09-blocking/GO-produto-20.10.md).  
> **P3 PASS** (`1.9.47`) — janela `max_window`/`deadline_unix`, auto-disable, GUI, audit metadados.  
> **P4 CLOSED FAIL/ABORT** (`234042Z`) — supervisor nao armado; rollback limpo; **não** PASS 4h.  
> **P4.1** **`1.9.59`** publicado — supervisor on-box (cron 1 min); **live** no `.254`.  
> **P4 retry** `170000Z` **CLOSED FAIL** — `health_ssh_fail` sample=14; rollback no fecho incompleto.  
> **P4.2** — diagnóstico + harness auth `-T` (`tests/harness/mitm-p4-soak/`); testes locais **PASS**.  
> **P4 soak retry2** `224009Z` **CLOSED PASS** — 16/16 health tries=1; `rollback_clean=1`; MITM **OFF** (verify `02:54:33Z`).  
> **20.37 PASS** — fila **FECHADA** ([`fecho-trilha-identity-mitm-20.37.md`](../01-architecture/fecho-trilha-identity-mitm-20.37.md)).  
> **20.36 PASS** — soak `.254` alinhado a **`1.9.63`**; MITM **OFF**.  
> **`1.9.63`** — MITM como política (até desligar + copy); ADR-0035; lab/`latest` + soak.  
> **Não reabrir** sem GO humano + backlog. Evolução = manutenção ou plano novo.  
> **`1.9.62`** — copy de operador MITM/Identity.  
> **`1.9.61`** — Lista VIP texto simples + DHCP.  
> **`1.9.60`** — `entitle-ok` PATH absoluto (rc.d).  
> **20.35 PASS** — MITM productizado na GUI (até desligar + copy operador); publicado **`1.9.63`**.  
> **Ambição:** melhorar todos os dias, sem tecto; paridade NGFW no tempo.  
> **Gate C / GO teste** (`1.9.46`, `215442Z`) — default OFF; este bloco **não** liga permanente.  
> Hardening **`1.9.42`+** — rdr exige `source_cidr`∧`dest_cidr` (proibido `from any`).  
> **Sem** intercept permanente em `.254`/`.234`/`.235`. Squid rejeitado.  
> **TLS:** proibido suavizar validação / `--ignore-certificate-errors` — [`politica-tls-sem-bypass.md`](../09-blocking/politica-tls-sem-bypass.md).

```text
docs/00-overview/START-HERE-identity-mitm.md
```

### Continuidade em chat limpo (obrigatório)

1. Colar **apenas** o caminho acima (ou o prompt da secção abaixo).  
2. O agente **deve** seguir a *Leitura obrigatória* deste ficheiro **antes** de codificar.  
3. O **passo actual** está na tabela *Estado actual* e no bloco *Progresso compacto* — deve coincidir com o plano §0 e o CORTEX (secção Identity).  
4. **Não** usar `START-HERE-fecho-producao.md` para executar MITM. Squid permanece **rejeitado**.  
5. Se CORTEX / plano / este ficheiro divergirem no passo actual → **parar** e declarar conflito (não improvisar).

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt (ler primeiro) |
| [`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md) | **Ideia, objectivo, nicho PME, barra UX** — ACEITE |
| [`plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) | **SSOT de execução** (ondas IM0–IM9, passos 20.x) |
| [`GO-produto-20.10.md`](../09-blocking/GO-produto-20.10.md) | GO humano produto — **dado** |
| [`prep-20.10-checklist.md`](../09-blocking/prep-20.10-checklist.md) | Prep **FECHADO**; 20.10a/b **PASS** |
| [`identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md) | Mapa código / superfícies / não-regressão |
| [`especificacao-agente-endpoint-20.27.md`](../01-architecture/especificacao-agente-endpoint-20.27.md) | Espec IM7 agente endpoint (20.27 PASS) |
| [`plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md) | Gates GI0–GI9 |
| [`spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) | Spike MITM — DEFER 20.7a + **reopen GO 2026-08-08** |
| [`desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) | Desenho opção E |
| [`contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md) | Contrato IPC — intenção vs `mitm_effective` |
| [`poc-layer7-tlsproxy-lab.md`](../09-blocking/poc-layer7-tlsproxy-lab.md) | PoC lab — **fora** de produção |
| [`GO-opcao-A-inline-lab-54.md`](../09-blocking/GO-opcao-A-inline-lab-54.md) | GO humano Opção A (inline só `.54`) — **PASS** |
| ADR-0025 / 0026 / 0027 / 0028 / 0029 / **0035** | Aceito; **0035** ambição NGFW + ficha retirada |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional **vivo** do produto |
| [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) | Filas **fecho + IPv6** (não reabrir) |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.  
**Não** usar `START-HERE-fecho-producao.md` para executar Identity/MITM (esse é manutenção pós-fecho).  
**Não** reabrir Identity de rede / agente endpoint sem GO humano separado.

---

## Estado actual (actualizar a cada passo)


| Campo | Valor |
|-------|-------|
| Plano | **【FILA FECHADA】** — 20.37; Identity rede FECHADA; MITM productizado `1.9.63` |
| Passo actual | **20.37 PASS** — fecho documental; soak + latest `1.9.63` MITM OFF |
| Prontidão piloto | Ficha **já não é gate**. Operação = GUI + entitlement. Default OFF. Soak `.254` = `1.9.63` MITM **OFF**. — [`../09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md`](../09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md) |
| Escopo / runbook piloto | [`../09-blocking/GO-escopo-piloto-mitm-generico.md`](../09-blocking/GO-escopo-piloto-mitm-generico.md) · [`../09-blocking/runbook-piloto-mitm-generico.md`](../09-blocking/runbook-piloto-mitm-generico.md) |
| P4.1 / retry | [`../09-blocking/runbook-p4-retry-supervisor-onbox.md`](../09-blocking/runbook-p4-retry-supervisor-onbox.md) |
| Gate activação | **RETIRADO** (ADR-0035) — sem ficha-papel |
| Gates obrigatórios | [`../09-blocking/gates-obrigatorios-1.9.43-mitm.md`](../09-blocking/gates-obrigatorios-1.9.43-mitm.md) — **B/C PASS** |
| Próximo | Manutenção / plano novo com GO; **não** reabrir esta fila; sem ligar MITM permanente |
| Evidência 20.37 | [`../tests/evidence/20260814T035500Z-20.37-fecho-identity-mitm/`](../tests/evidence/20260814T035500Z-20.37-fecho-identity-mitm/) — **FILA FECHADA** |
| Evidência 20.36 | [`../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/) — soak = `1.9.63` MITM OFF |
| Evidência P4 retry2 | [`../tests/evidence/20260813T224009Z-p4-retry2-254/`](../tests/evidence/20260813T224009Z-p4-retry2-254/) — **CLOSED PASS** |
| Evidência P4 | [`../tests/evidence/20260809T234042Z-p4-soak-254/`](../tests/evidence/20260809T234042Z-p4-soak-254/) — **CLOSED FAIL/ABORT** |
| Evidência P4 retry | [`../tests/evidence/20260813T170000Z-p4-retry-254/`](../tests/evidence/20260813T170000Z-p4-retry-254/) — **CLOSED FAIL** |
| Evidência pós-fail | [`../tests/evidence/20260813T223009Z-p4-postfail-verify-254/`](../tests/evidence/20260813T223009Z-p4-postfail-verify-254/) — MITM **OFF** (pré-retry2) |
| Rev. do plano | **`2026-08-14bh`** |
| MITM | `intercept_ready=true`; rdr só com source∧dest; GI2/GI3 **PASS**; S6 **NA/limite** |
| Identity (User-ID) | Mapa no **daemon**; RADIUS + agente DC; sem captive (ADR-0027) — **FECHADA** (20.33/GI9) |
| Exactidão MVP | User-ID de **rede** (ADR-0029: sem agente PC; TS excluído) |
| Captive portal pfSense | **FORA DE ESCOPO** |
| Não-regressão | Obrigatória — plano §1 e mapa §0 |
| Barra UX PME | Critérios U*/P*/H*/N* do posicionamento — obrigatórios na revisão de passo |

### Desambiguação de números

| Referência | Significado |
|------------|-------------|
| Passos **20.x** deste plano | Trilha Identity + MITM |
| Passos **12.x** | Trilha IPv6 — **FECHADA** (arquivo) |
| `test-matrix` §12 | Blacklists UT1 — **outra coisa** |
| `features=full` no `.lic` actual | Compat: tratar como **todas as features base históricas**; **não** implica MITM/Identity até contrato novo |

---

## O que esta trilha é

Grande evolução **opt-in** do Layer7 para o nicho **PME / MSP em pfSense**:

1. **Add-on comercial** no mesmo `pfSense-pkg-layer7`.
2. **Licença X (base)** vs **Y (Identity)** via entitlements — Y é a oferta âncora.
3. **Identity** estilo User-ID de rede: mapa **daemon** user↔IP + LDAP + RADIUS accounting + agente DC (+ agente/TS depois).
4. **MITM opcional no SKU** — runtime `layer7-tlsproxy` (opção E); **nunca** Squid.
5. **Zero impacto** no `1.9.8` com módulos OFF / sem entitlement.
6. **Barra de qualidade:** utilizável por TI PME/MSP **e** a subir rumo a paridade NGFW (ADR-0035); limites honestos do estado *actual*.

### Honestidade MVP (ler)

- Sem agente endpoint (IM7) / TS (IM8), o produto entrega **User-ID de rede**, não exactidão tipo GlobalProtect.
- MITM **não** é pré-requisito de Identity. Identity rede está **fechada**; MITM tem listen/rdr/página **gated** (`mitm_effective`).
- Captive portal: usar o do pfSense — fora desta trilha.
- **Ainda não** temos paridade com Fortinet / Palo Alto / Check Point — o rumo é chegar lá (ADR-0035).
- **Não** activar intercept em produção sem smoke S8 OFF + GO explícito.

## O que esta trilha **não** é

- Reabrir fecho P0–J ou IPv6 V0–V6.
- Substituir o captive portal do pfSense.
- Activar MITM ou AD por defeito em upgrades.
- MITM “universal obrigatório” sem política (NGFW também usa perfil/escopo).
- Analytics/SIEM pesado, console multi-firewall, rebind automático de licença.
- Caminho Squid / `pfSense-pkg-squid`.
- Reabrir Identity de rede ou agente endpoint sem GO separado.

---

## Leitura obrigatória (chat novo nesta trilha)

Ordem **estrita** — não improvisar:

1. **Este ficheiro** (`START-HERE-identity-mitm.md`)
2. [`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md) — ideia/objectivo/nicho/UX
3. [`AGENTS.md`](../../AGENTS.md)
4. [`CORTEX.md`](../../CORTEX.md) — secção *Trilha Identity + MITM*
5. [`plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) — **passo actual** + princípios
6. [`desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) — desenho opção E (antes de runtime)
7. [`contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md) — intenção vs effective (20.9)
8. [`identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md)
9. [`plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md)
10. ADRs da trilha (0025–0029, **0035** ambição NGFW / ficha fora)
11. Área do passo (ex.: license → `docs/10-license-server/`; enforce → `docs/05-daemon/pf-enforcement.md`)

Baseline produto (não reabrir):

- [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)
- [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) (só quando houver release)

---

## Prompt — continuar a trilha (copiar e colar)

```text
Contexto: trilha Identity + MITM Add-on; baseline produção 1.9.8; lab/latest 1.9.63; soak .254 = 1.9.63 MITM OFF.
Posicionamento: PME Identity-first (posicionamento-pme-identity-first.md).
Identity de rede: FECHADA (20.33/GI9). MITM: GO produto; Gate C PASS (anti-QUIC escopo).
Arranque: docs/00-overview/START-HERE-identity-mitm.md
GO produto: docs/09-blocking/GO-produto-20.10.md
Runbook P4.1: docs/09-blocking/runbook-p4-retry-supervisor-onbox.md
Runbook activação: docs/09-blocking/runbook-activacao-mitm-producao-1.9.46.md
Desenho: docs/01-architecture/desenho-layer7-tlsproxy-mitm.md
Contrato IPC: docs/01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md
Ler na ordem do START-HERE; ADR-0035 aceite; 20.37 PASS — FILA FECHADA.
Estado: soak .254 = 1.9.63 MITM OFF; latest 1.9.63.
Tarefa seguinte: NÃO reabrir esta fila sem GO + backlog; permanente NO-GO.
```


## Prompt — propor desvio / ADR

```text
Contexto: trilha Identity + MITM (START-HERE-identity-mitm.md).
Proposta de desvio: <descrição>
Impacto / risco / teste / rollback / passo afectado:
Alinhamento ao posicionamento PME:
Não implementar até GO. Responder em português.
```

---

## TESTES / GATES OBRIGATÓRIOS (`1.9.43`)

SSOT: [`../09-blocking/gates-obrigatorios-1.9.43-mitm.md`](../09-blocking/gates-obrigatorios-1.9.43-mitm.md)

| Fase | Obrigatório | Estado |
|------|-------------|--------|
| Doc | D0 + D1 local | **PASS** |
| Builder (antes publish) | `make -C src/layer7-tlsproxy test-regress` · `test_mitm_regress.php` · `test_ctrl_exec_timeout.php` · `run-local-timeout-fix.sh` · `test_mitm_config.php` | **PASS** local P4.1 (`1.9.59`); P3 em `1.9.47` |
| Humano | B+D Edge `.24` sem bypass TLS **nem** `--disable-quic` | **PASS** (`20260809T210753Z-phaseBD-d1-254`) |
| Produção | MITM em `.254` | **PASS temporário** (`215442Z`); permanente **NO-GO** |

```sh
make -C src/layer7-tlsproxy test-regress
php package/pfSense-pkg-layer7/tests/test_mitm_regress.php
php tests/functional/test_ctrl_exec_timeout.php
sh tests/harness/mitm-activate-hang/run-local-timeout-fix.sh
php tests/functional/test_mitm_config.php
sh tests/harness/mitm-p4-soak/run-local-auth-fix.sh
sh tests/harness/mitm-p4-soak/p4-validate-local.sh
```

---

## Progresso compacto (espelho do plano)

```text
TRILHA IDENTITY + MITM — progresso
- Passo actual: **20.37 PASS** — 【FILA FECHADA】
- Fecho: docs/01-architecture/fecho-trilha-identity-mitm-20.37.md
- Latest + soak: **1.9.63** MITM OFF
- Não reabrir sem GO + backlog
```

Actualizar este bloco **e** o CORTEX **e** o plano §0 no mesmo commit documental de cada fecho de passo.

---

## Ligação rápida

| Tema | Documento |
|------|-----------|
| Posicionamento PME | [`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md) |
| Plano mestre | [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) |
| Desenho MITM (opção E) | [`../01-architecture/desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) |
| Contrato IPC 20.9 | [`../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md) |
| Mapa técnico | [`../01-architecture/identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md) |
| Gates | [`../09-blocking/plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md) |
| Evidência 20.10b | [`../tests/evidence/20260809T053000Z-20.10b-listen-rdr-https-54/`](../tests/evidence/20260809T053000Z-20.10b-listen-rdr-https-54/) |
| Evidência 20.11 | [`../tests/evidence/20260809T060000Z-20.11-gi2-gi3-54/`](../tests/evidence/20260809T060000Z-20.11-gi2-gi3-54/) |
| Evidência 1.9.42 | [`../tests/evidence/20260809T173500Z-1.9.42-source-cidr/`](../tests/evidence/20260809T173500Z-1.9.42-source-cidr/) |
| Evidência Gate C `1.9.46` | [`../tests/evidence/20260809T210753Z-phaseBD-d1-254/`](../tests/evidence/20260809T210753Z-phaseBD-d1-254/) |
| Evidência GO teste controlado | [`../tests/evidence/20260809T215442Z-phaseBD-d1-254/`](../tests/evidence/20260809T215442Z-phaseBD-d1-254/) |
| Runbook activação prod. | [`../09-blocking/runbook-activacao-mitm-producao-1.9.46.md`](../09-blocking/runbook-activacao-mitm-producao-1.9.46.md) |
| Mapa prontidão piloto | [`../09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md`](../09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md) |
| P4.1 supervisor on-box | [`../09-blocking/runbook-p4-retry-supervisor-onbox.md`](../09-blocking/runbook-p4-retry-supervisor-onbox.md) |
| Diagnóstico P4.2 `health_ssh_fail` | [`../09-blocking/diagnostico-p4-retry-health-ssh-fail-20260813.md`](../09-blocking/diagnostico-p4-retry-health-ssh-fail-20260813.md) |
| Harness P4 soak | [`../../tests/harness/mitm-p4-soak/`](../../tests/harness/mitm-p4-soak/) |
| Evidência P4 retry FAIL | [`../tests/evidence/20260813T170000Z-p4-retry-254/`](../tests/evidence/20260813T170000Z-p4-retry-254/) |
| Fecho 20.37 | [`../01-architecture/fecho-trilha-identity-mitm-20.37.md`](../01-architecture/fecho-trilha-identity-mitm-20.37.md) |
| Evidência 20.37 | [`../tests/evidence/20260814T035500Z-20.37-fecho-identity-mitm/`](../tests/evidence/20260814T035500Z-20.37-fecho-identity-mitm/) |
| Evidência 20.36 soak align | [`../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/`](../tests/evidence/20260814T034904Z-20.36-soak-align-163-254/) |
| Evidência P4 retry2 CLOSED PASS | [`../tests/evidence/20260813T224009Z-p4-retry2-254/`](../tests/evidence/20260813T224009Z-p4-retry2-254/) |
| Evidência pós-fail MITM OFF | [`../tests/evidence/20260813T223009Z-p4-postfail-verify-254/`](../tests/evidence/20260813T223009Z-p4-postfail-verify-254/) |
| Escopo piloto (P1) | [`../09-blocking/GO-escopo-piloto-mitm-generico.md`](../09-blocking/GO-escopo-piloto-mitm-generico.md) |
| Runbook piloto (P2) | [`../09-blocking/runbook-piloto-mitm-generico.md`](../09-blocking/runbook-piloto-mitm-generico.md) |
| Destino lab `198.18` via `.54` | [`../09-blocking/runbook-destino-lab-19818-via-54.md`](../09-blocking/runbook-destino-lab-19818-via-54.md) |
| Evidência Fase A `.54` | [`../tests/evidence/20260809T180157Z-phaseA-54/`](../tests/evidence/20260809T180157Z-phaseA-54/) |
| Evidência Fase B `.254` | [`../tests/evidence/20260809T180624Z-phaseB-254/`](../tests/evidence/20260809T180624Z-phaseB-254/) |
| Evidência Fase C `.24` | [`../tests/evidence/20260809T181302Z-phaseC-24/`](../tests/evidence/20260809T181302Z-phaseC-24/) |
| Evidência B+D NO-GO | [`../tests/evidence/20260809T185035Z-phaseBD-mitm-254/`](../tests/evidence/20260809T185035Z-phaseBD-mitm-254/) |
| Abort rollback D | [`../tests/evidence/20260809T185719Z-abort-rollbackD-254/`](../tests/evidence/20260809T185719Z-abort-rollbackD-254/) |
| Gates obrigatórios `1.9.43` | [`../09-blocking/gates-obrigatorios-1.9.43-mitm.md`](../09-blocking/gates-obrigatorios-1.9.43-mitm.md) |
| Gate D0 diagnóstico | [`../09-blocking/diagnostico-D0-phaseBD-mitm-20260809.md`](../09-blocking/diagnostico-D0-phaseBD-mitm-20260809.md) |
| Gate D1 leaf | [`../09-blocking/gate-D1-leaf-sni-20260809.md`](../09-blocking/gate-D1-leaf-sni-20260809.md) |
| TLS sem bypass | [`../09-blocking/politica-tls-sem-bypass.md`](../09-blocking/politica-tls-sem-bypass.md) |
| Spike MITM + reopen | [`../09-blocking/spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) |
| PoC lab tlsproxy | [`../09-blocking/poc-layer7-tlsproxy-lab.md`](../09-blocking/poc-layer7-tlsproxy-lab.md) |
| Lab real (two-client) | [`../08-lab/lab-topology.md`](../08-lab/lab-topology.md) |
| Features / SKU | [`../03-adr/ADR-0025-entitlements-addon-identity-mitm.md`](../03-adr/ADR-0025-entitlements-addon-identity-mitm.md) |
| MITM | [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) |
| Ambição NGFW / ficha | [`../03-adr/ADR-0035-ambicao-paridade-ngfw-retirada-ficha.md`](../03-adr/ADR-0035-ambicao-paridade-ngfw-retirada-ficha.md) |
| Backlog | [`../02-roadmap/backlog.md`](../02-roadmap/backlog.md) (BG-085…) |
| Handoff genérico | [`handoff-chat-novo.md`](handoff-chat-novo.md) |

---

## Regras invioláveis (resumo)

1. **Plano manda** — um passo `20.x` por entrega.  
2. **Não-regressão** — `1.9.8` behaviour com features OFF / `features` legado.  
3. **Opt-in** — MITM e Identity default OFF; `mitm.enabled` = intenção; `mitm_effective` false sem gates.  
4. **Entitlement** — GUI pode mostrar upsell; **daemon** é autoridade do gate.  
5. **Sem captive** nesta trilha.  
6. **Sem segredos** no git (chaves CA, bind AD, etc.).  
7. **PME Identity-first** + **ambição NGFW no tempo** (ADR-0035). Sem overclaim do estado *actual*. Squid rejeitado.  
8. Documentação + testes mínimos + rollback **no mesmo bloco**.  
9. **Identity rede fechada** — não reabrir sem GO separado.  
10. **Sem ficha-papel** — agentes não bloqueiam trabalho com «falta a ficha».
