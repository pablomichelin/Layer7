# START HERE — Identity + MITM Add-on (trilha activa)

> **Visual:** fila **ABERTA** (`2026-08-05`).  
> Este ficheiro é o **único arranque** desta trilha. Colar só este caminho num chat limpo.

```text
docs/00-overview/START-HERE-identity-mitm.md
```

| Documento | Papel |
|-----------|--------|
| **Este ficheiro** | Arranque de chat + estado + prompt (ler primeiro) |
| [`plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) | **SSOT de execução** (ondas IM0–IM9, passos 20.x) |
| [`identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md) | Mapa código / superfícies / não-regressão |
| [`plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md) | Gates GI0–GI9 |
| [`spike-mitm-20.7.md`](../09-blocking/spike-mitm-20.7.md) | Spike MITM 20.7 (desenho + S1–S8) |
| ADR-0025 / 0026 / 0027 / 0028 | Decisões — **Aceito** (`2026-08-05`, T1) |
| [`CORTEX.md`](../../CORTEX.md) | SSOT operacional **vivo** do produto |
| [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) | Filas **fecho + IPv6** (não reabrir) |

**Não criar** outro `START-HERE-*` para subtarefas desta fila.  
**Não** usar `START-HERE-fecho-producao.md` para executar Identity/MITM (esse é manutenção pós-fecho).

---

## Estado actual (actualizar a cada passo)

| Campo | Valor |
|-------|-------|
| Plano | **ABERTO** — Identity + MITM Add-on |
| Produção enforce / `latest` (baseline) | **`1.9.8`** (não alterar sem release governada) |
| Rollback enforce conhecido | **`1.9.0`** |
| Passo actual | **IM2 / 20.7** — spike MITM: desenho pronto; **PoC lab PENDENTE** |
| Código do produto nesta trilha | **IM1 fechado** (GI1 PASS); MITM/Identity runtime ainda não |
| Rev. do plano | **`2026-08-05c`** (contratos técnicos fechados; 20.2 PASS) |
| Entitlement comercial | Modelo **X = base** / **Y = add-on**; legado **T1** (`full`→`base`) — ADR-0025 Aceito |
| MITM | Opt-in; **spike 20.7 GO/NO-GO**; pode **DEFER** sem bloquear Identity (ADR-0026 Aceito) |
| Identity (User-ID) | Mapa no **daemon**; RADIUS receiver + **agente no DC**; sem captive (ADR-0027 Aceito) |
| Exactidão MVP | User-ID de **rede** (não GlobalProtect) até IM7/IM8 |
| Captive portal pfSense | **FORA DE ESCOPO** |
| Não-regressão | Obrigatória — plano §1 e mapa §0 |

### Desambiguação de números

| Referência | Significado |
|------------|-------------|
| Passos **20.x** deste plano | Trilha Identity + MITM |
| Passos **12.x** | Trilha IPv6 — **FECHADA** (arquivo) |
| `test-matrix` §12 | Blacklists UT1 — **outra coisa** |
| `features=full` no `.lic` actual | Compat: tratar como **todas as features base históricas**; **não** implica MITM/Identity até contrato novo |

---

## O que esta trilha é

Grande evolução **opt-in** do Layer7:

1. **Add-on comercial** no mesmo `pfSense-pkg-layer7`.
2. **Licença X (base)** vs **Y (add-on)** via entitlements.
3. **MITM opcional** no SKU — implementação só após **spike GO**; pode ficar DEFER.
4. **Identity** estilo NGFW: mapa **daemon** user↔IP + LDAP + RADIUS accounting + agente DC (+ agente/TS depois).
5. **Zero impacto** no `1.9.8` com módulos OFF / sem entitlement.

### Honestidade MVP (ler)

- Sem agente endpoint (IM7) / TS (IM8), o produto entrega **User-ID de rede**, não exactidão tipo GlobalProtect.
- MITM **não** é pré-requisito de Identity.
- Captive portal: usar o do pfSense — fora desta trilha.

## O que esta trilha **não** é

- Reabrir fecho P0–J ou IPv6 V0–V6.
- Substituir o captive portal do pfSense.
- Activar MITM ou AD por defeito em upgrades.
- MITM “universal obrigatório” sem GO e sem entitlement.
- Analytics/SIEM pesado, console multi-firewall, rebind automático de licença.

---

## Leitura obrigatória (chat novo nesta trilha)

Ordem **estrita** — não improvisar:

1. **Este ficheiro** (`START-HERE-identity-mitm.md`)
2. [`AGENTS.md`](../../AGENTS.md)
3. [`CORTEX.md`](../../CORTEX.md) — secção *Trilha Identity + MITM*
4. [`plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) — **passo actual** + princípios
5. [`identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md)
6. [`plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md)
7. ADRs da trilha (**Aceito** — 0025 T1, 0026, 0027, 0028)
8. Área do passo (ex.: license → `docs/10-license-server/`; enforce → `docs/05-daemon/pf-enforcement.md`; limites TLS → `docs/09-blocking/matriz-limitacoes-dpi.md` + ADR-0017)

Baseline produto (não reabrir):

- [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](ESTADO-PRODUTO-E-PLANOS-FECHADOS.md)
- [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md) (só quando houver release)

---

## Prompt — continuar a trilha (copiar e colar)

```text
Contexto: trilha Identity + MITM Add-on ABERTA; baseline produção 1.9.8.
Arranque: docs/00-overview/START-HERE-identity-mitm.md
Ler na ordem do START-HERE; executar só o passo actual do plano.
Regras: não-regressão; opt-in OFF; um passo 20.x por bloco; português;
não reabrir fecho/IPv6; captive portal fora de escopo.
Tarefa: continuar no passo actual (IM2 / 20.7 — spike MITM).
```

## Prompt — propor desvio / ADR

```text
Contexto: trilha Identity + MITM (START-HERE-identity-mitm.md).
Proposta de desvio: <descrição>
Impacto / risco / teste / rollback / passo afectado:
Não implementar até GO. Responder em português.
```

---

## Progresso compacto (espelho do plano)

```text
TRILHA IDENTITY + MITM — progresso
- Passo actual: IM2 / 20.7 (desenho PASS; PoC lab PENDENTE)
- IM0+IM1: PASS (GI0+GI1)
- IM2: spike doc criado; veredicto GO/DEFER/NO-GO pendente lab
- IM3–IM9: PENDENTE (Identity pode avançar se DEFER)
- Código: entitlements OK; sem MITM/Identity runtime
- Plano rev.: 2026-08-05c
- Baseline enforce: 1.9.8
```

Actualizar este bloco **e** o CORTEX **e** o plano §0 no mesmo commit documental de cada fecho de passo.

---

## Ligação rápida

| Tema | Documento |
|------|-----------|
| Plano mestre | [`../02-roadmap/plano-identity-mitm-addon.md`](../02-roadmap/plano-identity-mitm-addon.md) |
| Mapa técnico | [`../01-architecture/identity-mitm-mapa-rastreabilidade.md`](../01-architecture/identity-mitm-mapa-rastreabilidade.md) |
| Gates | [`../09-blocking/plano-gates-identity-mitm.md`](../09-blocking/plano-gates-identity-mitm.md) |
| Features / SKU | [`../03-adr/ADR-0025-entitlements-addon-identity-mitm.md`](../03-adr/ADR-0025-entitlements-addon-identity-mitm.md) |
| MITM opt-in | [`../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) |
| Identity multi-fonte | [`../03-adr/ADR-0027-identity-userid-multi-fonte.md`](../03-adr/ADR-0027-identity-userid-multi-fonte.md) |
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
7. Documentação + testes mínimos + rollback **no mesmo bloco**.
