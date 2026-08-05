# Relatório QA #2 IPv4 — Layer7 `1.9.3` (pós-correcções)

| Campo | Valor |
|-------|-------|
| **Data** | 2026-08-05 |
| **Versão** | `pfSense-pkg-layer7-1.9.3` |
| **SHA256** | `fa2dca21bb5fe6e70b40f8a47ee36ce2a71670e93ba009ebd90b0f3c0ac8c8dc` |
| **Appliance** | `192.168.100.254` |
| **Cliente A** | `192.168.100.234` |
| **Cliente B** | `192.168.100.235` (`.232` continua offline) |
| **Base** | Correcções D1–D6 do relatório QA #1 (`1.9.2`) |
| **Evidência** | este directório |

---

## Veredicto

**Melhoria substancial face a `1.9.2`.** Os defeitos críticos D1–D3 e operacionais D4–D6 foram corrigidos e **revalidados no appliance**.

Two-client scoped (YouTube só no A, B livre, Google no A OK) **PASS** após aquecimento DNS/PF.

Residuais documentados abaixo (cold-start multi-IP, D7 HTTPS sem MITM, `.232` offline, Plus≠CE).

Config de produção **restaurada** no fim (`legacy_global`, 4 políticas, BL desligado).

---

## Correcções aplicadas em `1.9.3`

| ID | Correção |
|----|----------|
| **D1** | `layer7_blockpage_collect_domains` ignora policies com `src_hosts`/`src_cidrs` (e BL com `src_cidrs`) — sinkhole Unbound só para políticas globais |
| **D2** | Catch-all (Monitor geral) já não sombreia bloqueios específicos; PF emite `pdst` para policies só com hosts; match+SNI+DNS populam `pdst` |
| **D3** | Licença antes do 1.º `apply_config` → `enforce_cfg=1` + `enforce_ready` no arranque |
| **D4** | `layer7-pfctl ensure` após resync |
| **D5** | Flush âncora `layer7_g5_test` em `flush-all` / resync |
| **D6** | `config.json.sample` + criação na instalação; runtime BL testado (3015 domínios `doh`) |
| **D7** | Sem MITM — limitação aceite; HTTP block page OK |

---

## Matriz revalidação

| # | Teste | Resultado |
|---|-------|-----------|
| 1 | Instalação `1.9.3` | **PASS** |
| 2 | Licença 702 dias | **PASS** |
| 3 | Suite local | **PASS** |
| 4 | Smoke builder | **PASS** |
| 5 | D3 restart `enforce_cfg=1` / `enforce_ready` | **PASS** |
| 6 | Baseline: pornhub sinkhole + YouTube/Google livres | **PASS** |
| 7 | D1: Unbound **sem** youtube scoped; **com** pornhub global | **PASS** (`unbound_has_youtube=no`) |
| 8 | D1: DNS YouTube real em A **e** B (não sinkhole global) | **PASS** |
| 9 | D2: `flow_decide` + `enforce_block` → `layer7_pdst_2` | **PASS** |
| 10 | D2: A YouTube bloqueado (timeout); B YouTube 200; A Google 200 | **PASS** |
| 11 | D5: âncora G5 vazia | **PASS** (0 regras) |
| 12 | D6: BL `doh` loaded 3015 | **PASS** (teste; depois desligado) |
| 13 | D7 HTTPS portal | **LIMITAÇÃO** (TLS EOF; HTTP OK) |
| 14 | Restore produção | **PASS** |

---

## Novos / residuais encontrados nesta ronda

### R1 — MÉDIO — Cold-start multi-IP (scoped sem sinkhole DNS)

Antes de o DNS path observar **todos** os A records, o 1.º TCP a um IP ainda não em `pdst` pode passar (DPI classifica depois).  
Mitigação actual: `dns_block` adiciona todos os A quando a resposta DNS é vista (evidência: 9 IPs em `pdst_2`).  
**Não é bypass permanente** após aquecimento DNS.

### R2 — BAIXO — UX HTTPS na página de bloqueio (D7)

Sem MITM, HTTPS ao domínio sinkhole falha TLS. HTTP mostra «Acesso bloqueado». Aceite / ADR-0017.

### R3 — LAB — Cliente `.232` offline

Two-client usou `.235`.

### R4 — PLATAFORMA — pfSense Plus (ADR-0022)

### R5 — OPS — Publicação GitHub

`1.9.3` construída e instalada no lab; **release GitHub** (`pablomichelin/Layer7`) ainda não feita neste bloco — necessária para o botão «Verificar actualização» / canal `latest`.

---

## Comparativo QA1 → QA2

| Defeito QA1 | QA2 |
|-------------|-----|
| D1 sinkhole scoped global | **CORRIGIDO** |
| D2 pdst vazio / monitor shadow | **CORRIGIDO** (two-client PASS) |
| D3 enforce_cfg=0 no boot | **CORRIGIDO** |
| D4 tables missing | **MITIGADO** (ensure no resync) |
| D5 G5 órfã | **CORRIGIDO** |
| D6 BL inactivo | **CORRIGIDO** (activável; sample) |
| D7 HTTPS | **LIMITAÇÃO** (sem MITM) |

---

## Ficheiros de código alterados

- `src/layer7d/policy.c` — catch-all 2ª passagem  
- `src/layer7d/main.c` — ordem licença / enforce_ready  
- `package/.../layer7.inc` — D1 collect domains; pdst sem src; resync ensure+G5  
- `package/.../layer7-pfctl` — flush G5  
- `package/.../pkg-install.in` + `config.json.sample` + Makefile/`pkg-plist`  
- `tests/functional/test_policy_decide.c`, `tests/test_blockpage_config.sh`  
- `docs/changelog/CHANGELOG.md`  
- `PORTVERSION=1.9.3`

---

## Rollback

- Pacote: reinstalar `1.9.2` / `1.9.0`  
- Config lab: restaurada de `/tmp/layer7.json.pre-1.9.3-install`  
- Snapshot Veeam disponível (pré-teste)

---

## Recomendação

1. Publicar release GitHub `v1.9.3` com `.pkg` + `.sha256` se quiseres canal lab actualizado.  
2. Tratar R1 (cold-start) como melhoria futura opcional (pré-resolve / TTL curto).  
3. Retomar trilha IPv6 quando aceitares este fecho QA.  
4. Manter produção enforce em `1.9.0` até GO explícito.
