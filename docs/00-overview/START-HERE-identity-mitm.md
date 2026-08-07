# START HERE — Identity + MITM Add-on (trilha activa)

> **Visual:** fila **ABERTA** (`2026-08-06` — PME Identity-first; MITM DEFER).  
> Este ficheiro é o **único arranque** desta trilha. Colar só este caminho num chat limpo.

```text
docs/00-overview/START-HERE-identity-mitm.md
```

### Continuidade em chat limpo (obrigatório)

1. Colar **apenas** o caminho acima (ou o prompt da secção abaixo).  
2. O agente **deve** seguir a *Leitura obrigatória* deste ficheiro **antes** de codificar.  
3. O **passo actual** está na tabela *Estado actual* e no bloco *Progresso compacto* — deve coincidir com o plano §0 e o CORTEX (secção Identity).  
4. **Não** usar `START-HERE-fecho-producao.md` nem evidências antigas de MITM para decidir Squid/PoC — MITM está **DEFER**.  
5. Se CORTEX / plano / este ficheiro divergirem no passo actual → **parar** e declarar conflito (não improvisar).

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt (ler primeiro) |
| [`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md) | **Ideia, objectivo, nicho PME, barra UX** — ACEITE |
| [`plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) | **SSOT de execução** (ondas IM0–IM9, passos 20.x) |
| [`identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md) | Mapa código / superfícies / não-regressão |
| [`plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md) | Gates GI0–GI9 |
| [`spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) | Spike MITM — **DEFER formal 20.7a** |
| ADR-0025 / 0026 / 0027 / 0028 | Aceito; **0026 implementação diferida** |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional **vivo** do produto |
| [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) | Filas **fecho + IPv6** (não reabrir) |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.  
**Não** usar `START-HERE-fecho-producao.md` para executar Identity/MITM (esse é manutenção pós-fecho).

---

## Estado actual (actualizar a cada passo)


| Campo | Valor |
|-------|-------|
| Plano | **ABERTO** — Identity + MITM Add-on (rev. `2026-08-07g`) |
| Posicionamento | **PME / Identity-first** — [`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md) |
| Produção enforce / `latest` (baseline) | **`1.9.8`** (não alterar sem release governada) |
| Rollback enforce conhecido | **`1.9.0`** |
| Passo actual | **IM5 / 20.20** — agente Windows Event Log (receiver appliance **PASS** parcial) |
| Código do produto nesta trilha | **20.20 receiver PASS** (`identity_dc`); desenho A1–A7 PASS; 20.19 RADIUS; … |
| Rev. do plano | **`2026-08-07g`** |
| Entitlement comercial | Modelo **X = base** / **Y = Identity (âncora PME)**; legado **T1**; Y+ MITM futuro |
| MITM | **DEFER 20.7a** — Squid rejeitado; GI2/GI3 DEFERRED; saltar 20.8–20.11 |
| Identity (User-ID) | Mapa no **daemon**; RADIUS + agente DC; sem captive (ADR-0027) — **caminho de valor** |
| Exactidão MVP | User-ID de **rede** (não GlobalProtect) até IM7/IM8 |
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
4. **MITM opcional no SKU** — **implementação diferida** (20.7a); reabrir só com novo GO + S1–S8 (helper próprio, nunca Squid).
5. **Zero impacto** no `1.9.8` com módulos OFF / sem entitlement.
6. **Barra de qualidade:** utilizável por TI de empresa pequena/média (estados claros, testes de ligação, limites honestos, sem overclaim NGFW).

### Honestidade MVP (ler)

- Sem agente endpoint (IM7) / TS (IM8), o produto entrega **User-ID de rede**, não exactidão tipo GlobalProtect.
- MITM **não** é pré-requisito de Identity e está **diferido**.
- Captive portal: usar o do pfSense — fora desta trilha.
- **Não** prometemos paridade com Fortinet / Palo Alto / Check Point.

## O que esta trilha **não** é

- Reabrir fecho P0–J ou IPv6 V0–V6.
- Substituir o captive portal do pfSense.
- Activar MITM ou AD por defeito em upgrades.
- MITM “universal obrigatório” ou motor TLS nível NGFW enterprise.
- Analytics/SIEM pesado, console multi-firewall, rebind automático de licença.
- Caminho Squid / `pfSense-pkg-squid`.

---

## Leitura obrigatória (chat novo nesta trilha)

Ordem **estrita** — não improvisar:

1. **Este ficheiro** (`START-HERE-identity-mitm.md`)
2. [`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md) — ideia/objectivo/nicho/UX
3. [`AGENTS.md`](../../AGENTS.md)
4. [`CORTEX.md`](../../CORTEX.md) — secção *Trilha Identity + MITM*
5. [`plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) — **passo actual** + princípios
6. [`identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md)
7. [`plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md)
8. ADRs da trilha (0025 T1, **0026 diferido**, 0027, 0028)
9. Área do passo (ex.: license → `docs/10-license-server/`; enforce → `docs/05-daemon/pf-enforcement.md`)

Baseline produto (não reabrir):

- [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)
- [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) (só quando houver release)

---

## Prompt — continuar a trilha (copiar e colar)

```text
Contexto: trilha Identity + MITM Add-on ABERTA; baseline produção 1.9.8.
Posicionamento: PME Identity-first (posicionamento-pme-identity-first.md); MITM DEFER 20.7a.
Arranque: docs/00-overview/START-HERE-identity-mitm.md
Ler na ordem do START-HERE; executar só o passo actual do plano.
Regras: não-regressão; opt-in OFF; um passo 20.x por bloco; português;
barra UX PME (U*/P*/H*/N*); sem overclaim NGFW; não reabrir fecho/IPv6;
captive portal fora de escopo; não implementar MITM sem novo GO.
Tarefa: continuar no passo actual (IM5 / 20.20 — agente Windows Event Log; receiver appliance ja PASS).
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
- Passo actual: IM5 / 20.20 (agente Windows Event Log)
- 20.20 receiver: PASS (identity_dc HTTPS+HMAC; GUI; script lab PS1)
- 20.20 desenho: PASS (A1–A7)
- 20.19: PASS (RADIUS; GI5.3)
- Plano rev.: 2026-08-07g
- Baseline enforce: 1.9.8
- Candidato: **1.9.21** (publicado lab/`latest`)
```

Actualizar este bloco **e** o CORTEX **e** o plano §0 no mesmo commit documental de cada fecho de passo.

---

## Ligação rápida

| Tema | Documento |
|------|-----------|
| Posicionamento PME | [`posicionamento-pme-identity-first.md`](posicionamento-pme-identity-first.md) |
| Plano mestre | [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) |
| Mapa técnico | [`../01-architecture/identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md) |
| Gates | [`../09-blocking/plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md) |
| Spike MITM DEFER | [`../09-blocking/spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) |
| Features / SKU | [`../03-adr/ADR-0025-entitlements-addon-identity-mitm.md`](../03-adr/ADR-0025-entitlements-addon-identity-mitm.md) |
| MITM (diferido) | [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) |
| Identity multi-fonte | [`../03-adr/ADR-0027-identity-userid-multi-fonte.md`](../03-adr/ADR-0027-identity-userid-multi-fonte.md) |
| Desenho canal agente DC | [`../01-architecture/desenho-canal-agente-dc-20.20.md`](../01-architecture/desenho-canal-agente-dc-20.20.md) |
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
3. **Opt-in** — MITM e Identity default OFF.  
4. **Entitlement** — GUI pode mostrar upsell; **daemon** é autoridade do gate.  
5. **Sem captive** nesta trilha.  
6. **Sem segredos** no git (chaves CA, bind AD, etc.).  
7. **PME Identity-first** — não implementar MITM sem novo GO; barra UX posicionamento.  
8. Documentação + testes mínimos + rollback **no mesmo bloco**.
