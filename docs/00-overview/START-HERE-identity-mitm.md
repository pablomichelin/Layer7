# START HERE — Identity + MITM Add-on 【`1.9.47` · P4 ABORT · P5 aguarda ficha】

> **GO produto** `2026-08-09` — [`GO-produto-20.10.md`](../09-blocking/GO-produto-20.10.md).  
> **P3 PASS** (`1.9.47`) — janela `max_window`/`deadline_unix`, auto-disable, GUI, audit metadados.  
> **P4 CLOSED FAIL/ABORT** (`234042Z`) — supervisor nao armado; rollback limpo; **não** PASS 4h.  
> **P5** aguarda **ficha de site de cliente** — **proibido** piloto externo/permanente.  
> **Gate C / GO teste** (`1.9.46`, `215442Z`) — **NÃO** permanente.  
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
| ADR-0025 / 0026 / 0027 / 0028 / **0029** | Aceito; **0026** implementação em curso |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional **vivo** do produto |
| [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) | Filas **fecho + IPv6** (não reabrir) |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.  
**Não** usar `START-HERE-fecho-producao.md` para executar Identity/MITM (esse é manutenção pós-fecho).  
**Não** reabrir Identity de rede / agente endpoint sem GO humano separado.

---

## Estado actual (actualizar a cada passo)


| Campo | Valor |
|-------|-------|
| Plano | Identity **FECHADA**; MITM **GO produto**; lab/`latest` **`1.9.47`** (P3 PASS; P4 FAIL/ABORT) |
| Passo actual | **`1.9.47`** MONITOR/MITM OFF (pós-P4 FAIL/ABORT); permanente **NO-GO** |
| Prontidão piloto | **NÃO PRONTO activar externo** — P1+P2+P3 **PASS**; P4 FAIL/ABORT; P5 aguarda ficha — [`../09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md`](../09-blocking/mapa-prontidao-mitm-piloto-2026-08-09.md) |
| Escopo / runbook piloto | [`../09-blocking/GO-escopo-piloto-mitm-generico.md`](../09-blocking/GO-escopo-piloto-mitm-generico.md) · [`../09-blocking/runbook-piloto-mitm-generico.md`](../09-blocking/runbook-piloto-mitm-generico.md) |
| Gate activação externa | Ficha **nomeada** (cliente/responsáveis/src/dst/SNI/janela/saída) — **não** é lacuna de engenharia |
| Gates obrigatórios | [`../09-blocking/gates-obrigatorios-1.9.43-mitm.md`](../09-blocking/gates-obrigatorios-1.9.43-mitm.md) — **B/C PASS**; produção temporária **PASS** |
| Próximo | **P5 só com ficha**; MITM OFF; **proibido** piloto externo/permanente |
| Evidência P4 | [`../tests/evidence/20260809T234042Z-p4-soak-254/`](../tests/evidence/20260809T234042Z-p4-soak-254/) — **CLOSED FAIL/ABORT** |
| Rev. do plano | **`2026-08-09aw`** |
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
6. **Barra de qualidade:** utilizável por TI de empresa pequena/média (estados claros, testes de ligação, limites honestos, sem overclaim NGFW).

### Honestidade MVP (ler)

- Sem agente endpoint (IM7) / TS (IM8), o produto entrega **User-ID de rede**, não exactidão tipo GlobalProtect.
- MITM **não** é pré-requisito de Identity. Identity rede está **fechada**; MITM tem listen/rdr/página **gated** (`mitm_effective`).
- Captive portal: usar o do pfSense — fora desta trilha.
- **Não** prometemos paridade com Fortinet / Palo Alto / Check Point.
- **Não** activar intercept em produção sem smoke S8 OFF + GO explícito.

## O que esta trilha **não** é

- Reabrir fecho P0–J ou IPv6 V0–V6.
- Substituir o captive portal do pfSense.
- Activar MITM ou AD por defeito em upgrades.
- MITM “universal obrigatório” ou motor TLS nível NGFW enterprise.
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
10. ADRs da trilha (0025, **0026 intenção 20.9 / runtime diferido**, 0027, 0028)
11. Área do passo (ex.: license → `docs/10-license-server/`; enforce → `docs/05-daemon/pf-enforcement.md`)

Baseline produto (não reabrir):

- [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)
- [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) (só quando houver release)

---

## Prompt — continuar a trilha (copiar e colar)

```text
Contexto: trilha Identity + MITM Add-on; baseline produção 1.9.8; lab/latest **1.9.47**.
Posicionamento: PME Identity-first (posicionamento-pme-identity-first.md).
Identity de rede: FECHADA (20.33/GI9). MITM: GO produto; Gate C PASS (anti-QUIC escopo).
Arranque: docs/00-overview/START-HERE-identity-mitm.md
GO produto: docs/09-blocking/GO-produto-20.10.md
Runbook activação: docs/09-blocking/runbook-activacao-mitm-producao-1.9.46.md
Desenho: docs/01-architecture/desenho-layer7-tlsproxy-mitm.md
Contrato IPC: docs/01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md
Ler na ordem do START-HERE; executar só o próximo bloco seguro (P5 com ficha — NÃO activar MITM sem GO; NÃO permanente).
Regras: não-regressão; opt-in; mitm.enabled = intenção; mitm_effective só com gates;
rdr só source∧dest; anti-QUIC UDP/443 só mitm_src→mitm_dst; quic_mode=block (sem bypass); um passo/bloco; português;
barra UX PME; Squid rejeitado; S6 ECH = NA/limite; NÃO activar intercept permanente em .254/.234/.235.
Estado: 1.9.47 P3 PASS; P4 ABORT (234042Z; rollback OK); Gate B+C/GO teste em 1.9.46 (215442Z);
piloto NÃO activar — P5 aguarda ficha site cliente; proibido piloto externo/permanente.
Gates: docs/09-blocking/gates-obrigatorios-1.9.43-mitm.md — B/C PASS; prod temporária PASS.
Tarefa seguinte: manter MITM OFF; P5 só com ficha nomeada; sem mutar .234/.235.
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
| Builder (antes publish) | `make -C src/layer7-tlsproxy test-regress` · `test_mitm_regress.php` · `test_ctrl_exec_timeout.php` · `run-local-timeout-fix.sh` · `test_mitm_config.php` | **PASS** (`1.9.47` P3) |
| Humano | B+D Edge `.24` sem bypass TLS **nem** `--disable-quic` | **PASS** (`20260809T210753Z-phaseBD-d1-254`) |
| Produção | MITM em `.254` | **PASS temporário** (`215442Z`); permanente **NO-GO** |

```sh
make -C src/layer7-tlsproxy test-regress
php package/pfSense-pkg-layer7/tests/test_mitm_regress.php
php tests/functional/test_ctrl_exec_timeout.php
sh tests/harness/mitm-activate-hang/run-local-timeout-fix.sh
php tests/functional/test_mitm_config.php
```

---

## Progresso compacto (espelho do plano)

```text
TRILHA IDENTITY + MITM — progresso
- Passo actual: **1.9.47** MONITOR/MITM OFF (pós-P4 FAIL/ABORT)
- Prontidão piloto: **NÃO PRONTO activar externo** (P1+P2+P3 PASS; P4 FAIL/ABORT; P5 aguarda ficha)
- P4: 20260809T234042Z CLOSED FAIL/ABORT (supervisor nao armado; rollback limpo)
- Gates: B/C PASS; GO teste .254 PASS (215442Z; 1.9.46)
- Evidência P3: 20260809T230400Z-p3-mitm-window
- Latest publicado: **1.9.47** SHA `2155daca…9df833`
- Próximo: P5 só com ficha; sem reactivar MITM; sem piloto externo/permanente
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
7. **PME Identity-first** — sem overclaim NGFW; Squid rejeitado; sem claim de intercept produção sem GO.  
8. Documentação + testes mínimos + rollback **no mesmo bloco**.  
9. **Identity rede fechada** — não reabrir sem GO separado.
