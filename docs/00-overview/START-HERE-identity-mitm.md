# START HERE — Identity + MITM Add-on 【MITM GO lab — PoC-0 idle】

> **Visual:** Identity de rede **FECHADA** (`2026-08-08` — GI9 PASS).  
> **MITM:** **GO lab** (`2026-08-09`) — passo **20.9 PASS**; PoC-0 idle `src/layer7-tlsproxy/`.  
> Runtime produto **ausente** do `.pkg`; `mitm_effective` **sempre false**; **sem** intercept em `.254`/`.234`/`.235`; Squid **rejeitado**.  
> Homologação Identity: `20260808T174100Z-im9-20.33-homolog-1.9.29`.

```text
docs/00-overview/START-HERE-identity-mitm.md
```

### Continuidade em chat limpo (obrigatório)

1. Colar **apenas** o caminho acima (ou o prompt da secção abaixo).  
2. O agente **deve** seguir a *Leitura obrigatória* deste ficheiro **antes** de codificar.  
3. O **passo actual** está na tabela *Estado actual* e no bloco *Progresso compacto* — deve coincidir com o plano §0 e o CORTEX (secção Identity).  
4. **Não** usar `START-HERE-fecho-producao.md` para executar MITM. Squid permanece **rejeitado**. Intercept de produto só após S1–S8 + **GO produto** (20.10).  
5. Se CORTEX / plano / este ficheiro divergirem no passo actual → **parar** e declarar conflito (não improvisar).

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt (ler primeiro) |
| [`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md) | **Ideia, objectivo, nicho PME, barra UX** — ACEITE |
| [`plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) | **SSOT de execução** (ondas IM0–IM9, passos 20.x) |
| [`identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md) | Mapa código / superfícies / não-regressão |
| [`especificacao-agente-endpoint-20.27.md`](../01-architecture/especificacao-agente-endpoint-20.27.md) | Espec IM7 agente endpoint (20.27 PASS) |
| [`plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md) | Gates GI0–GI9 |
| [`spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) | Spike MITM — DEFER 20.7a + **reopen GO 2026-08-08** |
| [`desenho-layer7-tlsproxy-mitm.md`](../01-architecture/desenho-layer7-tlsproxy-mitm.md) | Desenho opção E — produto sem runtime; PoC idle no repo |
| [`contrato-ipc-layer7-tlsproxy-20.9.md`](../01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md) | Contrato IPC 20.9 — intenção vs `mitm_effective` |
| [`poc-layer7-tlsproxy-lab.md`](../09-blocking/poc-layer7-tlsproxy-lab.md) | PoC lab pós-GO — **fora** de produção |
| ADR-0025 / 0026 / 0027 / 0028 / **0029** | Aceito; **0026** 20.9 + GO lab idle; **0029** IM7 diferido + IM8 excluído |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional **vivo** do produto |
| [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) | Filas **fecho + IPv6** (não reabrir) |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.  
**Não** usar `START-HERE-fecho-producao.md` para executar Identity/MITM (esse é manutenção pós-fecho).  
**Não** reabrir Identity de rede / agente endpoint sem GO humano separado.

---

## Estado actual (actualizar a cada passo)


| Campo | Valor |
|-------|-------|
| Plano | Identity rede **FECHADA**; MITM **GO lab** PoC-0 (rev. `2026-08-09t`) |
| Passo actual | **20.9 PASS**; PoC-0 idle; próximo **20.10** **bloqueado** (S1–S4/S6 + GO produto) |
| Pré-20.10 (seguro) | PoC [`../09-blocking/poc-layer7-tlsproxy-lab.md`](../09-blocking/poc-layer7-tlsproxy-lab.md) + runbook S*; **sem** intercept produção |
| Posicionamento | **PME / Identity-first** — [`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md) |
| Produção enforce / `latest` (baseline) | **`1.9.8`** pin enforce; lab/`latest` **`1.9.38`** |
| Rollback enforce conhecido | **`1.9.0`** |
| Lab real | [`../08-lab/lab-topology.md`](../08-lab/lab-topology.md) — `.254` + `.234` + `.235`; SSH pfSense **8=Shell** |
| Código do produto nesta trilha | Identity **20.22+ PASS**; MITM **20.8–20.9** em `1.9.38`; PoC idle fora do `.pkg` |
| Rev. do plano | **`2026-08-09t`** |
| Entitlement comercial | Modelo **X = base** / **Y = Identity (âncora PME)**; legado **T1**; Y+ MITM (SKU; runtime produto ausente) |
| MITM | **GO lab 2026-08-09** — PoC-0 idle; intenção gravável; `mitm_effective` **false**; Squid **rejeitado**; GI2/GI3 **DEFERRED** |
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
4. **MITM opcional no SKU** — reopen GO `2026-08-08`; **20.9 PASS** (intenção/bypass/IPC); runtime helper próprio (`layer7-tlsproxy`, opção E) **ainda ausente**; **nunca** Squid.
5. **Zero impacto** no `1.9.8` com módulos OFF / sem entitlement.
6. **Barra de qualidade:** utilizável por TI de empresa pequena/média (estados claros, testes de ligação, limites honestos, sem overclaim NGFW).

### Honestidade MVP (ler)

- Sem agente endpoint (IM7) / TS (IM8), o produto entrega **User-ID de rede**, não exactidão tipo GlobalProtect.
- MITM **não** é pré-requisito de Identity. Identity rede está **fechada**; MITM está em **scaffolding + intenção** (sem intercept; `mitm_effective` false).
- Captive portal: usar o do pfSense — fora desta trilha.
- **Não** prometemos paridade com Fortinet / Palo Alto / Check Point.
- **Não** há terminação TLS / block page HTTPS via MITM até existir runtime + S1–S8 + GO lab.

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
Contexto: trilha Identity + MITM Add-on; baseline produção 1.9.8; lab/latest **1.9.38**.
Posicionamento: PME Identity-first (posicionamento-pme-identity-first.md).
Identity de rede: FECHADA (20.33/GI9). MITM: reopen GO 2026-08-08; passo actual **20.9 PASS**.
Arranque: docs/00-overview/START-HERE-identity-mitm.md
Desenho: docs/01-architecture/desenho-layer7-tlsproxy-mitm.md (opção E; runtime AUSENTE).
Contrato IPC: docs/01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md
Ler na ordem do START-HERE; executar só o passo actual do plano.
Regras: não-regressão; opt-in; mitm.enabled = intenção; mitm_effective sempre false sem runtime;
um passo 20.x por bloco; português; barra UX PME (U*/P*/H*/N*); sem overclaim NGFW;
sem claim de intercept; Squid rejeitado; não reabrir fecho/IPv6/Identity rede/endpoint sem GO;
GI2/GI3 runtime DEFERRED até S1–S8 + GO lab.
Tarefa: GO lab activo — PoC-0 idle (src/layer7-tlsproxy). NÃO intercept em .254/.234/.235;
NÃO empacotar sem GO; NÃO claim effective=true; NÃO 20.10 produto.
Próximo seguro: PoC-1 IPC em lab isolado OU medir S* só fora de produção.
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

## Progresso compacto (espelho do plano)

```text
TRILHA IDENTITY + MITM — progresso
- Passo actual: **20.9 PASS** (intenção mitm.enabled; bypass endurecido; quic_mode; contrato IPC)
- Próximo código: **20.10** BLOQUEADO até S1–S8 + GO lab (sem intercept)
- Continuidade agora: **GO lab** PoC-0 idle; **S5+S7+S8 PASS**; restam **S1–S4/S6** em lab isolado
- PoC: src/layer7-tlsproxy + docs/09-blocking/poc-layer7-tlsproxy-lab.md
- Lab: docs/08-lab/lab-topology.md — SSH pfSense menu → **8 Shell**
- Evidência S8: docs/tests/evidence/20260809T024500Z-s8-adr0017-real-1.9.38/
- Evidência S5/S7: docs/tests/evidence/20260809T031200Z-s5-s7-pre-runtime/
- Plano rev.: 2026-08-09t
- Identity rede: **FECHADA** (20.33 / GI9 PASS)
- 20.8: PASS (`1.9.37`) — schema/CA/bypass/status; tlsproxy AUSENTE
- 20.9: PASS (`1.9.38`) — intenção vs mitm_effective=false; contrato-ipc-layer7-tlsproxy-20.9.md
- 20.33: PASS (homolog two-client `20260808T174100Z-im9-20.33-homolog-1.9.29`)
- 20.7a: DEFER histórico; reopen GO → 20.8→20.9
- Baseline enforce: 1.9.8; lab/`latest`: **1.9.38**
- Squid: REJEITADO; GI2/GI3 runtime: DEFERRED
- Desenho: docs/01-architecture/desenho-layer7-tlsproxy-mitm.md
- Contrato: docs/01-architecture/contrato-ipc-layer7-tlsproxy-20.9.md
- Runbook S1–S8: docs/09-blocking/runbook-s1-s8-mitm-pre-runtime.md
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
| Spike MITM + reopen | [`../09-blocking/spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) |
| Runbook S1–S8 pré-runtime | [`../09-blocking/runbook-s1-s8-mitm-pre-runtime.md`](../09-blocking/runbook-s1-s8-mitm-pre-runtime.md) |
| Lab real (two-client) | [`../08-lab/lab-topology.md`](../08-lab/lab-topology.md) |
| Features / SKU | [`../03-adr/ADR-0025-entitlements-addon-identity-mitm.md`](../03-adr/ADR-0025-entitlements-addon-identity-mitm.md) |
| MITM (intenção 20.9; runtime diferido) | [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) |
| Identity multi-fonte | [`../03-adr/ADR-0027-identity-userid-multi-fonte.md`](../03-adr/ADR-0027-identity-userid-multi-fonte.md) |
| Desenho canal agente DC | [`../01-architecture/desenho-canal-agente-dc-20.20.md`](../01-architecture/desenho-canal-agente-dc-20.20.md) |
| Agente Win (samples) | [`../samples/identity-dc-agent/`](../samples/identity-dc-agent/) |
| Concorrência/IO daemon | [`../03-adr/ADR-0028-concorrencia-io-daemon-identity.md`](../03-adr/ADR-0028-concorrencia-io-daemon-identity.md) |
| Block page actual (sem MITM) | [`../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md`](../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md) |
| Identidade dispositivo (MAC) | [`../03-adr/ADR-0011-fonte-de-identidade-de-dispositivo.md`](../03-adr/ADR-0011-fonte-de-identidade-de-dispositivo.md), [`ADR-0012`](../03-adr/ADR-0012-politicas-por-dispositivo-mac-para-ip.md) |
| Limitações DPI | [`../09-blocking/matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md) |
| Licenças | [`../10-license-server/MANUAL-USO-LICENCAS.md`](../10-license-server/MANUAL-USO-LICENCAS.md) |
| Backlog | [`../02-roadmap/backlog.md`](../02-roadmap/backlog.md) (BG-085…) |
| Handoff genérico | [`handoff-chat-novo.md`](handoff-chat-novo.md) |

---

## Regras invioláveis (resumo)

1. **Plano manda** — um passo `20.x` por entrega.  
2. **Não-regressão** — `1.9.8` behaviour com features OFF / `features` legado.  
3. **Opt-in** — MITM e Identity default OFF; `mitm.enabled` = intenção; `mitm_effective` false sem runtime + S1–S8 + GO lab.  
4. **Entitlement** — GUI pode mostrar upsell; **daemon** é autoridade do gate.  
5. **Sem captive** nesta trilha.  
6. **Sem segredos** no git (chaves CA, bind AD, etc.).  
7. **PME Identity-first** — sem overclaim NGFW; Squid rejeitado; sem claim de intercept sem S1–S8.  
8. Documentação + testes mínimos + rollback **no mesmo bloco**.  
9. **Identity rede fechada** — não reabrir sem GO separado.
