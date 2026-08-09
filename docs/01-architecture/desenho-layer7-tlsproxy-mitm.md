# Desenho — `layer7-tlsproxy` (MITM opt-in, opção E)

**Estado:** `ARQUITECTURA + PoC-0 idle` (GO lab `2026-08-09`)  
**Tipo:** desenho canónico + scaffold idle (`src/layer7-tlsproxy/`) — **fora** do `.pkg`  
**Runtime MITM produto:** **NÃO empacotado**; `mitm_effective` false  
**Intercept TLS:** **NÃO aprovado** em produção; lab isolado só sob PoC + flags  
**Candidata:** opção **E** — helper próprio (`layer7-tlsproxy`)  
**Squid / `pfSense-pkg-squid`:** **REJEITADO** (permanente neste produto)  
**Identity de rede:** **FECHADA** (20.33 / GI9) — ortogonal; não reabrir nesta fila  
**ADR política:** [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md)  
**Spike:** [`../09-blocking/spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md)  
**PoC lab:** [`../09-blocking/poc-layer7-tlsproxy-lab.md`](../09-blocking/poc-layer7-tlsproxy-lab.md)  
**Block page OFF:** [`../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md`](../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md)  
**Arranque trilha:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md)  
**Plano (passos IM2):** [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) §20.8–20.11  
**Concorrência daemon:** [`../03-adr/ADR-0028-concorrencia-io-daemon-identity.md`](../03-adr/ADR-0028-concorrencia-io-daemon-identity.md)

> **Regra inviolável:** este documento **não** autoriza intercept em `.254`/`.234`/`.235`,
> nem claim `mitm_effective=true`, nem hot path `layer7d`. PoC idle/IPC em lab isolado
> está sob [`poc-layer7-tlsproxy-lab.md`](../09-blocking/poc-layer7-tlsproxy-lab.md).

---

## 1. Estado

| Campo | Valor |
|-------|--------|
| Data do GO humano (reabertura desenho) | **2026-08-08** |
| GO lab (PoC idle) | **2026-08-09** — [`poc-layer7-tlsproxy-lab.md`](../09-blocking/poc-layer7-tlsproxy-lab.md) |
| Veredicto spike 20.7a (histórico) | **DEFER** — Squid rejeitado; Identity avançou |
| Fase actual | Desenho + **PoC-0 idle** (sem bind/TLS) |
| Código `layer7-tlsproxy` | **Idle no repo** (`src/layer7-tlsproxy/`); **fora** do `.pkg` |
| Medições S1–S8 | S1–S5/S7/S8 PASS; **S3 PASS** Edge Windows; **S6 NA/limite** |
| Intercept selectivo / 20.10–20.11 | **20.10b+20.11 PASS** (`1.9.41`); gated; sem produção |
| Identity (User-ID de rede) | **FECHADA** — fora do escopo deste desenho |
| Entitlement SKU `mitm` | Pode existir (ADR-0025/0026); `mitm_effective` só com gates |

---

## 2. Objectivo e não-objectivos

### Objectivo

Definir uma arquitectura **opt-in** e **selectiva** para inspeção TLS no
mesmo pacote `pfSense-pkg-layer7`, via processo **separado**
`layer7-tlsproxy`, de forma a:

1. servir **block page HTTPS legível** quando o operador activa MITM + CA
   no domínio (GPO);
2. preservar **ADR-0017** byte-a-byte com `mitm.enabled=false`;
3. não degradar o loop de captura/enforce do `layer7d` (ADR-0028);
4. permitir decisão GO/NO-GO de lab com critérios **mensuráveis S1–S8**.

### Não-objectivos

| Fora de escopo | Motivo |
|----------------|--------|
| Paridade NGFW (Fortinet / Palo Alto / Check Point) | Posicionamento PME — sem overclaim |
| MITM universal / sempre ON | Inaceitável (ADR-0026) |
| Squid / `ssl_bump` / `pfSense-pkg-squid` | Rejeitado pelo operador |
| TLS in-process no `layer7d` | Rejeitado MVP (conflito ADR-0028) |
| Reabrir Identity / agente endpoint / captive | Filas fechadas ou fora de escopo |
| Analytics/SIEM, console multi-firewall | Fora da trilha |
| Implementar código C/proxy neste documento | Só desenho |

---

## 3. Arquitectura — processo separado + IPC leve

```text
  Cliente LAN                    Appliance                         Internet
  ─────────                      ─────────                         ────────
       │                              │                                │
       │  TLS (fluxos seleccionados)  │                                │
       ├─────────────────────────────►│ layer7-tlsproxy                │
       │                              │  · termina TLS (CA local)      │
       │                              │  · policy: allow|block|bypass  │
       │                              │  · block → página HTTPS        ├──────►
       │                              │                                │
       │                              │ IPC leve (metadados/policy)    │
       │                              │◄───────────────────────────────┤
       │                              │ layer7d                        │
       │  demais tráfego              │  pcap + nDPI + PF + identity    │
       │  (sem intercept)             │  (hot path SEM IO TLS)         │
       └─────────────────────────────►│                                │
```

| Componente | Responsabilidade | Proibições |
|------------|------------------|------------|
| `layer7-tlsproxy` | Terminação TLS selectiva; CA; bypass; block page HTTPS; métricas S1–S2 | Não partilhar loop pcap; não persistir payload (S7) |
| `layer7d` | Policy/mapa/identity; enforce PF; fonte de decisão allow/block | Sem terminate TLS; sem OpenSSL no hot path |
| IPC leve | Pedidos de decisão + metadados (SNI, peer, verdict) | Sem IO bloqueante no thread de captura |
| GUI / config | Toggle, CA ops, bypass, entitlement gate | Segredos CA **fora** do git e de `layer7.json` em claro |

**Princípios:**

- Opt-in + entitlement `mitm`; default **OFF**.
- Selectivo (não universal): só destinos/políticas marcadas para intercept.
- Falha previsível: se o helper cair, tráfego não seleccionado continua;
  modo OFF ≡ ADR-0017.
- Biblioteca/TLS exacta: **não fechada neste rascunho** (excepto: **não** Squid).

---

## 4. Superfícies de produto

| Superfície | Default / regra | Notas |
|------------|-----------------|-------|
| Entitlement `mitm` | Sem token → módulo **inerte** | GUI pode upsell; daemon/helper não interceptam |
| `mitm.enabled` | **`false`** | Upgrade nunca activa MITM |
| CA (gerar / importar / export GPO) | Só com entitlement + toggle consciente | Chaves **nunca** no repositório |
| Bypass (IP / CIDR / SNI) | Lista obrigatória no desenho MVP | Gestão pfSense / VIP: fora do intercept por defeito |
| Block page HTTPS | Só com MITM ON + CA confiada no cliente | Com OFF: ADR-0017 (HTTP sinkhole; HTTPS sem HTML legível) |
| QUIC / HTTP3 | Caminho **escrito** antes de GO lab (S5) | Bloquear / downgrade TCP / bypass — sem “ver depois” |
| ECH | Limite documentado (S6) | Não crash; não fail-closed da LAN |
| Logging | Metadados apenas por defeito (S7) | Sem payload desencriptado em disco |

---

## 5. Fluxo OFF ≡ ADR-0017

Com `mitm.enabled=false` **ou** sem entitlement `mitm`:

| Aspecto | Comportamento exigido |
|---------|----------------------|
| Processo helper | **Não** corre (ou idle sem bind de intercept) |
| Redirect / terminação TLS | **Ausente** |
| Block page | DNS sinkhole + HTTP local (ADR-0017) |
| HTTPS bloqueado | Erro de certificado / timeout / PF — **sem** HTML MITM |
| Smoke S8 | Comparável à baseline sem módulo MITM activo |

Qualquer implementação futura deve tratar S8 como **gate de não-regressão**
antes de GO lab de intercept.

---

## 6. Checklist S1–S8

Procedimento canónico: [`../09-blocking/runbook-s1-s8-mitm-pre-runtime.md`](../09-blocking/runbook-s1-s8-mitm-pre-runtime.md).  
**Intercept NÃO está aprovado** até medição + GO lab.

| # | Critério | Limiar | Método de medição | Estado |
|---|----------|--------|-------------------|--------|
| S1 | Overhead CPU (intercept selectivo, tráfego de referência) | ≤ **+25%** vs baseline lab | Lab `.54` localhost+inline | **PASS** — 20.11 (`~13%` busy) |
| S2 | Latência adicional por handshake TLS interceptado | ≤ **150 ms** p95 | Lab `.54` | **PASS** — localhost p95≈3.1 ms; inline≈15.5 ms |
| S3 | Block page HTTPS + CA | ≥ **1 browser Windows corporativo** com CA | Edge 151 + screenshot `.24` | **PASS** — `s3-windows/` |
| S4 | Bypass list | Fluxo em bypass **não** block-page | Lab SNI bypass | **PASS** — 20.11 |
| S5 | QUIC/HTTP3 | Decisão escrita (block / downgrade TCP / bypass) | Documento + caminho | **PASS** — `bypass` default |
| S6 | ECH | Limite documentado; prova lab se exercitável | Nota lab | **NA / limite** (não exercitado; não PASS) |
| S7 | Privacidade | Sem payload em disco por defeito | Auditoria lab + wipe CA | **PASS** — 20.11 |
| S8 | MITM OFF ≡ ADR-0017 / sem listener | Smoke OFF | `.38` real + 20.11 rollback | **PASS** |

---

## 7. Ordem de implementação (20.8 → 20.11)

Alinhada ao plano IM2. Runtime / intercept só após S1–S8 + GO lab.
Contrato IPC: [`contrato-ipc-layer7-tlsproxy-20.9.md`](contrato-ipc-layer7-tlsproxy-20.9.md).

| Passo | Conteúdo | Pré-condição | Estado doc |
|-------|----------|--------------|------------|
| **20.8** | Gestão CA (gerar / importar / export GPO; segredos fora do git) | Desenho aceite; entitlement; GUI/ops | **PASS** (`1.9.37`) |
| **20.9** | Toggle intenção `mitm.enabled` + bypass endurecido + `quic_mode` + contrato IPC; `mitm_effective` sempre false sem runtime | 20.8 PASS | **PASS** (`1.9.38`) |
| **20.10** | Intercept selectivo + block page HTTPS | **S1–S8 medidos** + **GO lab** + **GO produto** | **20.10a+b PASS** (`1.9.41`); gated; sem produção |
| **20.11** | Lab CA completo; gates **GI2–GI3** | 20.10 estável em lab | **PASS** (`1.9.41`; S3 Edge Windows; evidência `20260809T060000Z-20.11-gi2-gi3-54`) |

```text
Desenho (este ficheiro)
  → aceite humano do desenho
  → 20.8 CA PASS
  → 20.9 intenção + bypass + IPC PASS (mitm_effective=false)
  → PoC lab + medir S1–S8
  → GO lab
  → 20.10 intercept selectivo + página HTTPS
  → 20.11 GI2–GI3
```

**Identity:** permanece **FECHADA**; não misturar passos IM3–IM9 nesta fila.

---

## 8. Riscos e rollback

### Riscos

| ID | Risco | Severidade | Mitigação |
|----|-------|------------|-----------|
| R1 | Segundo produto (motor TLS) consome meses sem valor PME | Alta | Desenho-first; GO lab só com S1–S8; sem paridade NGFW |
| R2 | Regressão hot path `layer7d` | Crítica | Processo separado + IPC leve; proibição TLS in-process |
| R3 | MITM ON por upgrade/acidente | Alta | Default `false`; entitlement; S8 obrigatório |
| R4 | CA / privacidade (payload em disco) | Alta | Segredos fora do git; S7 default metadados |
| R5 | QUIC/ECH “depois vemos” | Média | S5/S6 gates escritos antes de GO lab |
| R6 | Voltar a Squid por pressão de prazo | Alta | Rejeição permanente registada (spike + ADR-0026) |

### Rollback

| Nível | Acção |
|-------|--------|
| Config | `mitm.enabled=false` + reload |
| Entitlement | Remover `mitm` do token |
| Pacote | Não publicar helper até GO lab; rollback de release para build sem runtime MITM |
| Ops CA | Remover CA dos clientes via GPO (só se alguma vez distribuída) |
| Estado actual | **Só documentação** — rollback = não iniciar código |

---

## 9. Ligações canónicas

| Documento | Papel |
|-----------|--------|
| [`ADR-0026`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) | Política SKU opt-in; S1–S8; Squid rejeitado; reabertura com GO |
| [`spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) | Spike 20.7/20.7a; opções A–E; veredicto DEFER; esboço opção E |
| [`START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md) | Arranque da trilha; Identity FECHADA; regras de não-regressão |
| [`ADR-0017`](../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md) | Verdade com MITM OFF |
| [`ADR-0028`](../03-adr/ADR-0028-concorrencia-io-daemon-identity.md) | Sem IO bloqueante no captura; baseline perf |
| [`plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) | Passos 20.8–20.11; R-T / R-P |
| [`contrato-ipc-layer7-tlsproxy-20.9.md`](contrato-ipc-layer7-tlsproxy-20.9.md) | Intenção vs `mitm_effective`; bypass; `quic_mode`; IPC futuro |
| [`runbook-s1-s8-mitm-pre-runtime.md`](../09-blocking/runbook-s1-s8-mitm-pre-runtime.md) | Ordem S1–S8 pré-runtime; smoke S8; sem intercept |
| [`posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) | Barra PME; sem overclaim NGFW |

---

## 10. Decisão pedida ao operador (próximo)

1. Desenho opção E aceite; **20.8/20.9 PASS**; **GO lab** PoC-0 idle.  
2. **Não** avançar **20.10** até S1–S4/S6 medidos + **GO produto**.  
3. Squid permanece rejeitado; sem claim de intercept em produção.

---

## Histórico

| Data | Evento |
|------|--------|
| 2026-08-06 | Spike 20.7a DEFER; saltar 20.8–20.11 |
| 2026-08-08 | GO humano reabre **desenho** MITM (opção E); Identity permanece FECHADA |
| 2026-08-08 | Criação deste ficheiro — runtime **não** iniciado; S1–S8 **não** medidos |
| 2026-08-08 | **20.8 PASS** — scaffolding CA/bypass; sem intercept |
| 2026-08-08 | **20.9 PASS** — intenção vs `mitm_effective`; contrato IPC; 20.10 bloqueado até S1–S8+GO lab |
| 2026-08-09 | **GO lab** — PoC-0 idle em `src/layer7-tlsproxy/`; proibido intercept produção |
