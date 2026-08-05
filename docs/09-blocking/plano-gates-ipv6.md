# Plano — gates IPv6 (trilha V0–V6)

**Data:** 2026-08-04  
**Rev.:** 2026-08-05f  
**Versão alvo inicial / série:** `1.9.0` → `1.9.1` → `1.9.2` → … (`PORTREVISION=0`)  
**Versão alvo fecho:** patch `1.9.n` da mesma série (passo 12.13; sem salto a `1.10.0`)  
**Produção enforce actual:** `1.9.0` (inalterada até GV7 + GO humano)  
**SSOT trilha:** [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md)  
**Arranque:** [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md)

> Passos **12.x** desta trilha ≠ `test-matrix.md` §12 (blacklists F4.2).

---

## 1. Princípios

1. Nenhum GV substitui G0–G7 IPv4 — **regressão IPv4 obrigatória** em cada onda com `.pkg`.
2. Ordem: **honestidade (GV0) → PF (GV1) → builder (GV2) → appliance captura (GV3) → enforce (GV4) → NAT (GV5) → fecho (GV6–GV7)**.
3. GV5 pode ficar **ADIADO** com ADR — não bloqueia GV7 se I7 satisfeito por exclusão formal.
4. Evidência em `docs/tests/evidence/<run_id>/`.
5. Salvaguardas IPv6 (mapa §8) são **critério de GV1+**, não opcionais.

---

## 2. Gates

### GV0 — Governança e disclosure (Onda V0)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| GV0.1 | ADR-0024 **publicado e Aceito** (este gate = revisão do pacote V0, não recriar ADR) | Revisão doc | **PASS** (`2026-08-04`; rev. b) |
| GV0.2 | Mapa rastreabilidade publicado (M-01..M-25 + §8 salvaguardas) | Mapa | **PASS** (publicado; manter vivo) |
| GV0.3 | Banner/limitação visível na GUI | Diagnostics ou Status | **PASS** (passo 12.2 — `layer7_diagnostics.php`; visível no appliance após próximo `.pkg` que inclua o ficheiro) |
| GV0.4 | `matriz-limitacoes-dpi.md` alinhada | Sem claim dual-stack falso | **PASS** (passo 12.1, `2026-08-04`) |
| GV0.5 | CORTEX checklist trilha IPv6 + arranque único START-HERE-fecho | Secção dedicada | **PASS** (rev. b) |

**GV0 onda completa:** **PASS** (`2026-08-04`, passos 12.1–12.2). Confirmação visual no appliance depende do próximo pacote com a GUI.

### GV1 — Paridade PF scoped (Onda V1)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| GV1.1 | `layer7_policy_enforcement_rules_text` emite `inet6` para pdst/psrc | Diff + `test_scoped_pf_inc.php` | **PASS** (builder `2026-08-04`) |
| GV1.2 | `pallow` / `pexc` / `exc_allow` com `inet6` | Teste PHP | **PASS** (builder) |
| GV1.3 | `pfctl -nf` PASS com enforce scoped ON | Appliance rules.debug | **PENDENTE** (candidato `1.9.0`) |
| GV1.4 | REV-018 marcado fechado no mapa | Commit docs | **PASS** (12.3) |
| GV1.5 | Desenho NDP/ICMPv6: LAN v6 não parte (sem block indevido a NDP) | Review + smoke | **PASS desenho** (sem block icmp6 genérico); smoke appliance PENDENTE |
| GV1.6 | `localsubnets` (ou equivalente) cobre prefixos locais IPv6 | Review ruleset | **PENDENTE** appliance dual-stack |
| GV1.7 | Política de exclusão: não adicionar `fe80::/10`, `ff00::/8`, `::1` a tabelas block | Código/teste | **PASS** validadores `layer7_ipv6_valid` / `cidr6` (S-03); populate daemon = V3 |

### GV2 — Builder e testes locais (Ondas V2–V4)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| GV2.1 | `tests/run-local.sh` PASS (incl. novos testes v6) | macOS/CI | **PASS** (builder `2026-08-04`–`05`; 12.4–12.9; incl. `test_policy_decide`, `test_allowlist`, `test_ipv6_gui_inc`) |
| GV2.2 | Build pacote no builder FreeBSD | Makefile | **PASS** (builder `2026-08-04`; 12.4–12.5) |
| GV2.3 | `php -l` + testes funcionais PHP PASS | builder | **PASS** (builder; `test_ipv6_gui_inc.php` 12.9) |
| GV2.4 | `layer7d -t` smoke PASS | builder | **PASS** (builder `2026-08-04`; 12.4–12.5) |

**GV2 onda V2–V4 (captura/métricas/policy/enforce/allowlist/GUI):** **PARCIAL** — builder PASS (passos 12.4–12.9); confirmação appliance captura = **GV3**.

### GV3 — Captura IPv6 no appliance (Onda V2–V3)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| GV3.1 | Instalar candidato com `mode=monitor` | `pkg add` | **PASS** (`1.9.1` em `192.168.100.254`; `layer7d -V` 1.9.1; `legacy_global`) |
| GV3.2 | Tráfego IPv6 na LAN gera `captures` / fluxos v6 nas stats | JSON stats / syslog | **PASS** (`cap_pkts_v6`/`cap_active_v6`/`cap_classified_v6` > 0; cliente `192.168.100.244` IPv6 `2804:6c4:11d:cc00::…`) |
| GV3.3 | Tráfego IPv4 continua classificado (não regressão) | Comparar baseline | **PENDENTE** |
| GV3.4 | Zero block PF Layer7 em monitor | `pfctl -sr` | **PENDENTE** |
| GV3.5 | NDP/RA na LAN continua funcional após candidato | ping6 / neighbor | **PENDENTE** |

**GV3 onda:** **PARCIAL** — captura v6 evidenciada no appliance `1.9.1`; GV3.3–GV3.5 PENDENTE. Banner IPv6 (GV0.3) visível; `pfctl -nf /tmp/rules.debug` rc=0.

### GV4 — Enforcement IPv6 (Onda V3–V4)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| GV4.1 | Política block app em cliente só-v6 ou dual-stack v6 | curl/trace v6 | **PENDENTE** |
| GV4.2 | `pfctl -t layer7_pdst_N -T show` contém endereço IPv6 | Appliance | **PENDENTE** |
| GV4.3 | Segundo cliente não afectado (paridade G5 em v6) | Two-client v6 | **PENDENTE** |
| GV4.4 | Rollback para candidato anterior restaura tráfego v6 | `pkg` rollback | **PENDENTE** |
| GV4.5 | Enforce não coloca link-local/multicast/`::1` nas tabelas | Auditoria tabelas | **PARCIAL** (S-03 `layer7_pf_host_enforce_ok` + `test_enforce_scoped.c` PASS builder; appliance PENDENTE) |

**GV4 onda:** **PARCIAL** — código daemon v6 (12.6–12.8) PASS builder; GV4.1–GV4.4 appliance PENDENTE.

### GV5 — DNS / NAT / block page IPv6 (Onda V5 — opcional)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| GV5.1 | Decisão humana registada (implementar vs ADIAR) | ADR-0024 emenda | **PENDENTE** |
| GV5.2 | Se implementado: `rdr inet6` :53 sinkhole funcional | `nslookup -6` | **PENDENTE** |
| GV5.3 | Se implementado: block page acessível em v6 | HTTP v6 portal | **PENDENTE** |
| GV5.4 | Se ADIADO: limite explícito em MANUAL + GUI (incl. AAAA/M-09) | Doc review | **PENDENTE** |

### GV6 — Campanha dual-stack (Onda V6)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| GV6.1 | Roteiro `validacao-lab.md` §21 executado | Checklist assinado | **PENDENTE** |
| GV6.2 | Cliente dual-stack: v4 e v6 bloqueados conforme política | Evidência | **PENDENTE** |
| GV6.3 | F5 smoke IPv6 script PASS | `run-ipv6-dualstack.sh` | **PENDENTE** |

### GV7 — Fecho trilha IPv6 (Onda V6)

| # | Critério | Método | Estado |
|---|----------|--------|--------|
| GV7.1 | I1–I8 verdes ou excepções ADR assinadas | Auditoria tipo Onda J | **PENDENTE** |
| GV7.2 | Release publicada + MANUAL actualizado se operacional | GitHub Releases | **PENDENTE** |
| GV7.3 | CORTEX: trilha IPv6 **FECHADA** | Checkpoint humano | **PENDENTE** |
| GV7.4 | GO humano promoção patch estável (ex. `1.9.n`) | Operador | **PENDENTE** |

---

## 3. Ordem de execução recomendada

```text
GV0 → GV1 → GV2 → GV3 → GV4 → [GV5 ou ADIADO] → GV6 → GV7
```

**Parar e rollback** se GV3.3 (regressão IPv4) ou GV3.5 (NDP partido) falhar.

---

## 4. Referências

- [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md)
- [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md)
- [`plano-gates-producao.md`](plano-gates-producao.md) (G0–G7 IPv4 baseline)
- [`validacao-lab.md`](../04-package/validacao-lab.md) (§21 a criar na V6)
- [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md)
