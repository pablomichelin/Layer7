# Plano extensão — IPv6 completo (pós-fecho produção)

**Estado do plano:** `ABERTO` (rev. `2026-08-04c` — um só START-HERE; salvaguardas)  
**Tipo:** extensão pós-Onda J do plano mestre de fecho  
**SSOT deste plano:** este ficheiro  
**SSOT de estado:** `CORTEX.md` (secção *Trilha IPv6*)  
**Arranque de chat (único):** [`../00-overview/START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md)  
**Mapa de lógica/código:** [`../01-architecture/f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md)  
**Gates:** [`../09-blocking/plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md)  
**Decisão formal:** [`../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md)

---

## 0. Relação com o plano mestre (fecho)

O plano [`plano-fecho-producao-e-consolidacao.md`](plano-fecho-producao-e-consolidacao.md)
permanece **`FECHADO`** (Ondas P0–J, produção enforce `1.9.0`, fecho `2026-08-05`).

Este documento é a **trilha seguinte** — não reabre nem altera o veredicto do fecho.
Continua a numeração de passos a partir de **11.1** (Onda J) com passos **12.x**.

A governança da trilha IPv6 foi preparada em `2026-08-04` (durante o fecho
documental) e **activada como fila seguinte** após Onda J — ordem histórica
intencional, não conflito de datas.

| Plano | Ondas | Estado |
|-------|-------|--------|
| Fecho produção | P0–J | **FECHADO** |
| IPv6 completo | **V0–V6** | **ABERTO** — passo actual: **12.5** (V2) |

### Desambiguação — passos 12.x vs test-matrix §12

| Referência | Significado |
|------------|-------------|
| Passos **12.1–12.13** deste plano | Trilha IPv6 (V0–V6) |
| `test-matrix.md` **§12** testes 12.1/12.2 | Blacklists F4.2 (Onda D) — **outra coisa** |

Commits: `trilha-ipv6/12.x: …`

**Motivação:** FP-010 / AUD-007 / REV-018 — pipeline L3–L7 é IPv4-only; redes
dual-stack contornam enforcement scoped e classificação nDPI em IPv6.

---

## 0.1 Definição de “IPv6 completo”

| # | Critério de saída (I1–I8) | Prova |
|---|---------------------------|-------|
| I1 | Limitação V1 **visível** na GUI e docs (sem claim dual-stack falso) | ADR-0024 + banner Diagnostics |
| I2 | Regras PF scoped têm paridade `inet`/`inet6` (REV-018 fechado) | `test_scoped_pf_inc.php` + GV1 |
| I3 | Daemon captura e classifica fluxos IPv6 (nDPI) | `cap_*` v6 > 0 no appliance; GV3 |
| I4 | Políticas e allowlist aceitam IPv6/CIDR v6; decisão runtime v6 | testes C + GV4 |
| I5 | `pfctl -T add` e kill de estados funcionam para endereços IPv6 | smoke enforce v6; GV4 |
| I6 | GUI valida e persiste configuração IPv6 sem truncamento silencioso | testes PHP + GV2 |
| I7 | DNS forçado / block page / VIP isenção v6 **decididos** (implementar ou ADR de exclusão explícita) | GV5 ou ADR emenda |
| I8 | Malha lab dual-stack repetível + evidência `run_id` | `plano-gates-ipv6.md` GV6–GV7 |

**Fora de I1–I8 (não bloquear fecho da trilha V0–V4):**

- MITM TLS / ECH
- IPv6 em inventário MAC→IP (leases DHCPv6 — fase posterior)
- Paridade total com todas combinações NAT64/NPT (documentar limite se aplicável)

---

## 1. Princípios invioláveis

1. **Plano manda; agente obedece.** Um passo `12.x` por bloco de entrega.
2. **Não regressão IPv4.** Todo gate inclui smoke IPv4 existente (`run-local.sh`, F5 mínima).
3. **Ordem segura:** V0 → V1 → … → V6. **Não** saltar para daemon (V2) antes de PF parity (V1) sem ADR.
4. **Documentação no mesmo bloco** que código (CORTEX, changelog, mapa rastreabilidade, testes).
5. **Versionamento:** commit por bloco; quando o passo exigir `.pkg`, subir
   **`PORTVERSION` em patch** na linha `1.9.x` (`1.9.1`, `1.9.2`, …) com
   `PORTREVISION=0`. **Proibido** sufixo `_N` / `PORTREVISION` após `1.9.0`.
6. **Produção enforce** permanece `1.9.0` até GV7 + GO humano da trilha IPv6
   (promoção para o patch lab então estável, ex. `1.9.n`).
7. **Appliance:** agente único em passos com install/enforce; multitarefa só em V0 (docs) com ficheiros disjuntos.
8. **macOS ≠ gate.** Builder FreeBSD + appliance obrigatórios para GV3+.
9. **Salvaguardas IPv6 (obrigatórias a partir de V1):** ver mapa §8 — NDP/ICMPv6,
   `localsubnets` v6, exclusão de link-local/multicast/`::1`, desenho de
   extension headers na captura. **Não** emitir `inet6` de block sem estes
   critérios no desenho e nos gates GV1/GV3/GV4.
10. **Arranque único:** só [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md).

---

## 2. Visão macro — Ondas V0–V6

```text
V0 (honestidade)   V1 (PF)        V2–V3 (daemon)     V4 (GUI)      V5 (NAT/DNS)    V6 (fecho)
ADR + docs + GUI   inet6 scoped   capture+policy+     validação     rdr v6 +       gates lab +
banner             REV-018        enforce+allowlist   IPv6 campos   block page     release+audit
```

| Onda | Passos | Objectivo | BG principal |
|------|--------|-----------|--------------|
| **V0** | 12.1–12.2 | Governança, ADR-0024, matriz, banner GUI | BG-078 |
| **V1** | 12.3 | Paridade PF `inet6` em scoped/pallow/pexc/exc_allow | BG-079 |
| **V2** | 12.4–12.5 | Captura Ethernet `0x86DD`, fluxos, nDPI IPv6 | BG-080 |
| **V3** | 12.6–12.8 | Policy CIDR v6, enforce `pfctl`, allowlist v6 | BG-081 |
| **V4** | 12.9 | Package/GUI validação e persistência IPv6 | BG-082 |
| **V5** | 12.10–12.11 | DNS forçado / block page / VIP v6 (gate humano) | BG-083 |
| **V6** | 12.12–12.13 | Gates GV6–GV7, release, fecho trilha no CORTEX | BG-084 |

---

## 3. Multitarefa e file ownership

### 3.1 Modo por onda

| Onda | Modo | Multitarefa |
|------|------|-------------|
| V0 | Coordenador + workers docs | Sim (mapa ∥ ADR ∥ GUI copy) |
| V1 | Agente único | Não (`layer7.inc` quente) |
| V2–V3 | Agente único | Não (`src/layer7d/*` quente) |
| V4 | Agente único ou coord.+worker GUI | Só se GUI ∥ docs disjuntos |
| V5 | Agente único | Não (PF + Unbound + NAT) |
| V6 | Agente único | Não (appliance + release) |

### 3.2 Ficheiros quentes (serializar)

| Ficheiro / área | Owner |
|-----------------|-------|
| `CORTEX.md`, `backlog.md`, `CHANGELOG.md` | Coordenador ou agente único V6 |
| `package/.../layer7.inc` | V1, V4, V5 (nunca em paralelo) |
| `src/layer7d/*` | V2, V3 |
| `MANUAL-INSTALL.md` | Só quando passo exigir (V5/V6 release) |
| `Makefile` / `PORTVERSION` | Coordenador ou agente único em release |

### 3.3 Grafo de dependências

```text
V0 ──► V1 ──► V2 ──► V3 ──► V4 ──► V5 ──► V6
         │              │
         └──────────────┴── GV2 (builder) após V1; GV3 (appliance) após V3 mínimo
```

**Proibido:** V2 ∥ V1 no mesmo appliance com enforce activo.  
**Proibido:** V5 sem decisão humana registada em ADR-0024 (secção V5).

---

## 4. Guia de passos numerados (fila única)

Marcar no `CORTEX.md` o passo actual (`12.x`).

| Passo | Onda | Objectivo | Resultado mensurável | Versionar? |
|-------|------|-----------|----------------------|------------|
| 12.1 | V0 | ADR-0024 + actualizar matriz limitações + índices | ADR aceite; FP-010 ligado ao plano; desambiguação 12.x | Commit docs |
| 12.2 | V0 | Banner/limitação na GUI Diagnostics + `pf-enforcement.md` | Operador vê aviso IPv4-only até I3; **GV0 completo** | Commit docs+GUI copy |
| 12.3 | V1 | Emitir `inet6` em scoped/pallow/pexc/exc_allow **com salvaguardas §8** | REV-018 fechado; GV1 PASS (incl. NDP/localsubnets) | Commit + semver se `.pkg` |
| 12.4 | V2 | Parser L2/L3 IPv6 em `capture.c` + chave de fluxo v6 | Unit tests C PASS; sem regressão v4 | Commit |
| 12.5 | V2 | nDPI sobre fluxos IPv6; métricas `cap_*` v6 | GV2 PASS builder | Commit + semver se `.pkg` |
| 12.6 | V3 | `policy.c` CIDR IPv6 `/0–128` | testes parse/match PASS | Commit |
| 12.7 | V3 | `enforce.c`/`main.c` tabelas PF e kill states v6 | GV3 smoke monitor v6 | Commit + semver se `.pkg` |
| 12.8 | V3 | `allowlist` IPv6 host/CIDR | testes allowlist PASS | Commit |
| 12.9 | V4 | `layer7.inc` + GUI validação IPv6 | GV2 PHP PASS | Commit + semver se `.pkg` |
| 12.10 | V5 | Design + implementação ou exclusão formal DNS `rdr inet6` | ADR emenda ou código; gate humano | Commit |
| 12.11 | V5 | Block page + VIP isenção v6 (se V5 activo) | smoke NAT v6 ou limite doc | Commit |
| 12.12 | V6 | Campanha lab dual-stack (`plano-gates-ipv6.md`) | GV6–GV7 PASS; evidência `run_id` | Commit docs evidência |
| 12.13 | V6 | Release + CORTEX fecho trilha IPv6 | Tag `1.9.n`; MANUAL se operacional | Release GitHub |

**Correcção em gate:** bloco `FIX-ipv6-n` → repetir gate falhado → nunca saltar onda.

---

### V3 — Lab com V4 ainda não fechada

Em 12.6–12.8 (daemon) a GUI pode ainda rejeitar IPv6. Testes de política v6
no lab usam **config JSON manual** / appliance até 12.9 — documentar no run_id.

---

## 5. Versionamento

**Política oficial pós-`1.9.0` (decisão operador):**

```text
1.9.0 → 1.9.1 → 1.9.2 → … → 1.9.n
```

- Cada pacote publicado = bump de **`PORTVERSION`** no patch (`x` em `1.9.x`).
- **`PORTREVISION` permanece sempre `0`** — nunca gerar `1.9.0_1`, `1.9.1_2`, etc.
- Código na árvore sem release: manter `PORTVERSION` actual até o bloco pedir `.pkg`.
- Produção enforce fica em **`1.9.0`** até GV7 + GO; lab usa o patch mais recente publicado.
- **Não** saltar para `1.10.0` nesta trilha salvo nova decisão humana.

| Tipo | Git | PORTVERSION | Docs |
|------|-----|-------------|------|
| Só docs | commit `trilha-ipv6/12.x:` | — | CORTEX, mapa, ADR |
| Código sem release | commit + testes | inalterado | changelog Unreleased |
| Mudança appliance | commit + builder + GV | próximo patch (`1.9.1`, `1.9.2`, …) | MANUAL se comandos mudarem |
| Fecho V6 | tag + GitHub Release | patch da série `1.9.n` então estável | CORTEX, changelog, MANUAL |

Mensagem de commit: `trilha-ipv6/12.3: paridade PF inet6 scoped (BG-079)`.
Próximo `.pkg` IPv6 (quando houver release): **`1.9.1`**.

---

## 6. Critérios de STOP

- Regressão G2–G7 IPv4 no appliance
- `pfctl -nf` FAIL após emissão `inet6`
- Captura IPv4 deixa de funcionar
- NDP/ICMPv6 essencial à LAN partido (GV3.5)
- Impasse V5 (NAT64/NPT) sem decisão humana → parar em 12.9 com I7 em ADR

---

## 7. Relação com backlog e fases F0–F7

| Fase roadmap | Ligação IPv6 |
|--------------|--------------|
| F4 | Runtime package/daemon — V1–V4 |
| F5 | Malha testes — GV + `test-matrix.md` (V6) |
| F7 | Release — passo 12.13 |

Itens backlog: **BG-078** … **BG-084** (ver `backlog.md`).

---

## 8. Checklist de progresso (copiar para CORTEX)

```text
TRILHA IPv6 — progresso
- Passo actual: 12.5
- Onda: V2
- Candidato lab: 1.9.0 (próximo .pkg: 1.9.1)
- Produção enforce: 1.9.0 (inalterada até GV7)
- GV0 (docs): PASS (12.1–12.2)
- GV1 (PF scoped inet6): PARCIAL (código PASS; appliance 1.3/1.6 PENDENTE)
- GV2 (builder): PARCIAL (12.4 PASS; 12.5 métricas)
- GV3 (captura v6 appliance): PENDENTE
- GV4 (enforce v6): PENDENTE
- GV5 (DNS/NAT v6): PENDENTE | ADIADO
- GV6 (dual-stack lab): PENDENTE
- GV7 (fecho trilha): PENDENTE
- I1–I8: I1 PASS; I2 parcial
- Próximo passo autorizado: 12.5
```

---

## 9. Referências cruzadas

| Documento | Papel |
|-----------|--------|
| [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md) | Matriz ficheiro × gap × onda |
| [`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md) | GV0–GV7 |
| [`matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md) | FP-010 estado actual |
| [`matriz-unificada-rev-fp-aud.md`](../09-blocking/matriz-unificada-rev-fp-aud.md) | REV-018, FP-010 |
| [`plano-fecho-producao-e-consolidacao.md`](plano-fecho-producao-e-consolidacao.md) | Plano anterior (fechado) |
| [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md) | **Único** arranque de chat (fecho + IPv6) |

---

## 10. Histórico

| Data | Evento |
|------|--------|
| 2026-08-04 | Passo **12.4** concluído: captura IPv6 + flow key; unit + `layer7d` build/`-t` PASS |
| 2026-08-04 | Passo **12.3** concluído: REV-018 `inet`+`inet6` scoped; `test_scoped_pf_inc` PASS; `1.9.0` |
| 2026-08-04 | Passo **12.2** concluído: banner Diagnostics + `pf-enforcement.md`; **GV0 PASS** |
| 2026-08-04 | Passo **12.1** concluído: ADR-0024, índices, mapa, matriz GV0.4 PASS |
| 2026-08-04 | Criação da trilha IPv6 (governança durante fecho); passo 12.1; BG-078..084 |
| 2026-08-04b | Arranque único em START-HERE-fecho; salvaguardas NDP; desambiguação 12.x vs test-matrix |
| 2026-08-04c | Removido `START-HERE-ipv6.md`; só existe `START-HERE-fecho-producao.md` |
