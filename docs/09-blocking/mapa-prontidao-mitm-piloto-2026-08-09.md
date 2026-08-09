# Mapa canónico — prontidão MITM para piloto (`2026-08-09`)

**Tipo:** auditoria **somente leitura / documental** (sem mutação lab, código, build ou release).  
**Veredicto:** **NÃO PRONTO PARA PILOTO** — evidência verificável cobre teste controlado temporário; **não** cobre janela piloto operacional/comercial.  
**Pacote de referência lab/`latest`:** `1.9.46` (`SHA256=10998477ef7ae966e6c3566baeb973f922858fc72cc4d3a2dcdd0fb17bae72f5`).  
**Produção enforce base (sem MITM):** `1.9.8` (inalterada por este mapa).  
**Arranque:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**SSOT execução:** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md)  
**SSOT vivo:** [`../../CORTEX.md`](../../CORTEX.md)

---

## Definições (obrigatórias — anti-overclaim)

| Termo | Significado canónico | Estado |
|-------|----------------------|--------|
| **Teste controlado** | Janela ≤15 min; src `/32` × dst `/32` + SNI lab; rollback imediato | **PASS** `215442Z` |
| **Piloto** | Janela multi-hora/dias; clientes/destinos nomeados; CA/trust operacional; suporte; critérios de saída; failsafe | **NÃO atingido** |
| **Permanente / produção MITM** | Intercept ON sem janela de teste; políticas estáveis | **NO-GO** (decisão humana mantida) |
| **Pronto para piloto** | (a)+(ops)+(GO humano piloto) com evidência; **não** equivale a GO permanente | **NO-GO** neste mapa |

**Regra:** nenhum documento pode marcar “pronto para piloto” sem evidência de soak/ops + GO humano explícito de piloto.

---

## (a) Implementado e evidenciado (verificável)

| # | Item | Evidência / gate | Nota |
|---|------|------------------|------|
| A1 | Runtime `layer7-tlsproxy` no `.pkg` (default OFF) | `1.9.39`+; MANUAL addenda; GI2.1 | Opt-in |
| A2 | Intenção vs efectivo (`mitm.enabled` / `mitm_effective`) | 20.9; contrato IPC; `test_mitm_config.php` | Sem gates ⇒ effective false |
| A3 | Rdr só `source_cidr` ∧ `dest_cidr` (proibido `from any`) | `1.9.42`; BG-087 | Hardening produção |
| A4 | Anti-QUIC UDP/443 escopo MITM + `filter_configure_sync` | `1.9.46`; Gate C `210753Z` | Edge **sem** `--disable-quic` |
| A5 | Leaf SNI `serverAuth` (D1) | `gate-D1-leaf-sni`; tlsproxy ≥0.1.3 | Corrige KEY_USAGE |
| A6 | Control-plane timeout / anti-hang | `1.9.43/44`; harness timeout | D0 F1-bis |
| A7 | Tabelas PF MITM live | `1.9.45`+ | |
| A8 | GI2 / GI3 lab | `20260809T060000Z-20.11-gi2-gi3-54` | S3 Edge `.24` |
| A9 | Topologia lab `198.18.0.10` via `.54` (A+B+C) | `180157Z` / `180624Z` / `181302Z` | Path via GW |
| A10 | GO teste controlado `.254` + rollback | `215442Z` + preflight `215218Z` | `quic_mode=block`; **não** permanente |
| A11 | Builder regressões MITM | gates-obrigatorios §B **PASS** | Antes de publish |
| A12 | Squid rejeitado | ADR-0026 / spike | Permanente |
| A13 | Identity rede FECHADA (ortogonal) | 20.33 / GI9 | Não bloqueia MITM |
| A14 | Baseline appliance pós lab-pair | `225533Z` → MONITOR; MITM OFF | Bom ponto de partida |

---

## (b) Lacunas técnicas concretas

| ID | Lacuna | Porquê bloqueia piloto | Severidade |
|----|--------|------------------------|------------|
| T1 | **Sem failsafe de janela longa** | Teste = `at` 15 min ad-hoc; piloto precisa auto-disable configurável + prova | Alta |
| T2 | **Observabilidade operador insuficiente para soak** | Contadores/banner/`mitm_effective` claros na GUI durante ON; evidência só de janela curta | Alta |
| T3 | **S6 ECH = NA/limite** | Comportamento previsível documentado, **não** exercitado; piloto deve declarar limite honesto | Média (aceite se honesto) |
| T4 | **Prova CE física ausente** | Lab = pfSense Plus; ADR-0022 — não inferir CE só de Plus | Média |
| T5 | **Soak multi-hora não evidenciado** | S1/S2/GI3 = lab curto; sem evidência de estabilidade 4–24 h | Alta |
| T6 | **CA piloto vs CA efémera** | Teste apaga CA; piloto exige ciclo export GPO / rotação / revogação documentado **e** exercitado | Alta (ops+tech) |
| T7 | **Escopo multi-cliente não aprovado** | Só `.24` evidenciado; `.234/.235` proibidos sem GO | Depende do GO piloto |
| T8 | **Resíduos documentais “runtime AUSENTE”** | Plano/ADR/R-T desactualizados vs `1.9.46` — risco de agente errar | Média (docs; corrigido neste bloco) |

**Não é lacuna de motor TLS “enterprise”:** fora de objectivo (ADR-0026 / posicionamento PME).

---

## (c) Lacunas operacionais / comerciais e decisões humanas

| ID | Decisão / lacuna | Dono | Bloqueia piloto? |
|----|------------------|------|------------------|
| H1 | **GO humano “piloto MITM”** (≠ teste 15 min; ≠ permanente) | Operador | **Sim** |
| H2 | Definir **escopo piloto:** src CIDRs, dest CIDRs/SNI, duração, horário, abort | Operador | **Sim** |
| H3 | **Cliente(s) piloto** e processo de trust CA (GPO / manual) | Ops / MSP | **Sim** |
| H4 | **SKU / entitlement `mitm`** emitido para o site piloto | Comercial + license-server | **Sim** |
| H5 | Critérios de **saída do piloto** (PASS/FAIL/rollback) | Operador | **Sim** |
| H6 | Comunicação de limites (ECH, pinning, não-NGFW) | Produto | Sim (honesty) |
| H7 | Autorizar ou não tocar `.234/.235` / produção real | Operador | Sim se escopo > `.24` |
| H8 | Janela de suporte / contacto durante piloto | Ops | Sim |

---

## (d) Sequência mínima de blocos até “pronto para piloto”

Cada bloco: objectivo / impacto / risco / teste / rollback.  
**Parar** se qualquer bloco falhar. **Sem** classificar pronto sem evidência.

### Bloco P0 — Fecho documental / SSOT (este mapa) — **em curso**

| Campo | Valor |
|-------|--------|
| Objectivo | Resolver drift “runtime AUSENTE”; fixar veredicto NÃO PRONTO; apontar próximo passo |
| Impacto | Só docs |
| Risco | Baixo |
| Teste | Links vivos; CORTEX/START-HERE/plano/ADR/backlog alinhados |
| Rollback | Reverter commit docs |

### Bloco P1 — GO humano de escopo piloto (sem código)

| Campo | Valor |
|-------|--------|
| Objectivo | Escrita formal: src/dst/SNI, duração, hosts, abort, SKU |
| Impacto | Desbloqueia P2–P5 |
| Risco | Baixo |
| Teste | Checklist H1–H5 preenchida |
| Rollback | N/A (não activar) |

### Bloco P2 — Runbook piloto (docs)

| Campo | Valor |
|-------|--------|
| Objectivo | Runbook distinto do teste 15 min: CA, GPO, monitorização, abort, cleanup |
| Impacto | Ops |
| Risco | Baixo |
| Teste | Revisão cruzada vs runbook `1.9.46` + topologia `198.18` |
| Rollback | Arquivar draft |

### Bloco P3 — **Primeiro bloco de código** (ver secção e)

| Campo | Valor |
|-------|--------|
| Objectivo | Trilho de segurança para janela piloto (failsafe + visibilidade) |
| Impacto | Package/GUI/daemon control-plane; **sem** alargar blast radius do rdr |
| Risco | Médio |
| Teste | Suite MITM local + lab `.54`/`.24` com auto-disable |
| Rollback | Flag OFF; pacote anterior `1.9.46` |

### Bloco P4 — Soak lab controlado (evidência)

| Campo | Valor |
|-------|--------|
| Objectivo | Janela ≥4 h (mínimo) scoped; Edge real; rollback limpo |
| Impacto | `.254` temporário; **não** permanente |
| Risco | Médio |
| Teste | Health periódica; zero rdr órfão; GUI/NET/SSH |
| Rollback | Runbook disable + flush |

### Bloco P5 — GO activação piloto + evidência

| Campo | Valor |
|-------|--------|
| Objectivo | Activar só o escopo H2 com pacote P3+ e runbook P2 |
| Impacto | Produção limitada |
| Risco | Alto (mitigado por escopo) |
| Teste | Critérios H5 + evidência commitada |
| Rollback | Disable imediato; reinstall `1.9.46` se necessário |

**Só após P5 PASS** se pode escrever “pronto para piloto (janela X)” no CORTEX.  
**Permanente** continua a exigir GO separado.

---

## (e) Primeiro bloco de código — o quê e porquê

### Escolha canónica: **P3 — Trilho de segurança da janela piloto**

**Não** começar por novas features de intercept (já cobertas em `1.9.46` para o caso scoped).  
O salto teste→piloto falha por **ops + failsafe + visibilidade**, não por falta de rdr/anti-QUIC.

**Escopo mínimo de código (um PR):**

1. **Auto-disable / max window** configurável (ex. minutos) com failsafe fiável (não só `at` manual).  
2. **Estado operador** inequívoco na GUI/diagnóstico enquanto `mitm_effective=true` (escopo src/dst/SNI, tempo restante, QUIC mode).  
3. **Auditoria de metadados** da activação (sem payload) para evidência de piloto.  
4. Testes: extensão de `test_mitm_config.php` / regress MITM + smoke lab OFF após timeout.

| Campo | Valor |
|-------|--------|
| Objectivo | Tornar seguro um soak/piloto sem depender de disciplina humana dos 15 min |
| Impacto | Controlo de lifecycle MITM; sem mudar semântica de rdr scoped |
| Risco | Médio — control-plane; mitigar com default conservador |
| Teste | Builder suite MITM + lab auto-disable + S8 OFF |
| Rollback | Desactivar feature; voltar a `1.9.46` |

**Porquê não outro primeiro código:**

| Alternativa | Motivo de adiamento |
|-------------|---------------------|
| ECH (S6) completo | Limite já honesto; não é MVP piloto |
| Alargar destinos/CDN | Aumenta blast radius sem GO H2 |
| Agente endpoint | ADR-0029 ADIAR |
| Squid | Rejeitado |
| CE port completo | ADR-0022 — trilho paralelo, não primeiro bloco MITM |

**Pré-condição:** P0 docs **e** P1 GO de escopo (ou GO explícito “implementar P3 sem activar”).

---

## Conflitos documentais encontrados e resolução

| Conflito | Documentos | Resolução (este bloco) |
|----------|------------|------------------------|
| “runtime produto **AUSENTE**” vs `1.9.46` no `.pkg` | plano header; R-T histórico; mapa M-07 tom antigo | Marcar como **histórico pré-20.10**; SSOT actual = runtime presente, default OFF, effective gated |
| ADR-0026 “runtime diferido / effective sempre false sem runtime” sem rev. pós-Gate C | ADR-0026 | Rev. **n**: runtime shipped; Gate C + teste controlado PASS; permanente/piloto NO-GO até mapa+GO |
| Backlog checkpoint “próximo fecho IM2 / GO produção” vs permanente NO-GO | `backlog.md` | Apontar a este mapa; próximo = P0–P5 piloto, não permanente |
| “PRONTO PARA ENFORCE” (CORTEX produto base) ≠ pronto MITM piloto | CORTEX checkpoint | Manter enforce base; MITM piloto **NO-GO** explícito |
| GI2/GI3 PASS vs ADR texto “GI2–GI3 DEFERRED” | ADR consequências antigas | Consequências históricas; estado vivo = GI2/GI3 PASS lab; produção permanente NO-GO |

---

## Veredicto final

```text
MITM motor scoped (1.9.46) ........ PRONTO PARA TESTE CONTROLADO (evidência 215442Z)
MITM pronto para PILOTO ........... NÃO (faltam H1–H5 + P2–P5 + evidência soak)
MITM permanente / produção ........ NO-GO (decisão humana)
Primeiro código ................... P3 failsafe+visibilidade (após P1 ou GO explícito)
```

---

## Referências rápidas

| Tema | Doc |
|------|-----|
| Gates B/C | [`gates-obrigatorios-1.9.43-mitm.md`](gates-obrigatorios-1.9.43-mitm.md) |
| Runbook teste | [`runbook-activacao-mitm-producao-1.9.46.md`](runbook-activacao-mitm-producao-1.9.46.md) |
| Evidência Gate C | [`../tests/evidence/20260809T210753Z-phaseBD-d1-254/`](../tests/evidence/20260809T210753Z-phaseBD-d1-254/) |
| Evidência teste | [`../tests/evidence/20260809T215442Z-phaseBD-d1-254/`](../tests/evidence/20260809T215442Z-phaseBD-d1-254/) |
| ADR MITM | [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) |
| BG-087 | [`../02-roadmap/backlog.md`](../02-roadmap/backlog.md) |
