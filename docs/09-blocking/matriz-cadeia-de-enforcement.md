# Matriz — cadeia de enforcement Layer7

**Data:** 2026-07-29  
**Versão analisada (repo):** `1.8.11_31` (candidato interno)  
**Release pública de referência:** `1.8.11_24`  
**Estado:** CANDIDATO INTERNO EM VALIDAÇÃO — matriz derivada de código + docs; gates físicos pendentes.

---

## 1. SSOT de configuração

| Etapa | Componente | Ficheiro / path | Função |
|-------|------------|-----------------|--------|
| 1 | GUI WebGUI | `package/.../www/packages/layer7/*.php` | CRUD políticas, settings, allowlist, blacklists |
| 2 | Persistência | `/usr/local/etc/layer7.json` | SSOT operacional (não `config.xml`) |
| 3 | Blacklists | `/usr/local/etc/layer7/blacklists/config.json` | Regras UT1 separadas |
| 4 | Unbound anti-DoH | `config.xml` (via hook) | Único toque em `config.xml` |

**Nota:** `config.xml` do pfSense não transporta políticas Layer7. O diagrama canónico é `layer7.json → cliente`.

---

## 2. Fluxo textual config → cliente

```
[Operador GUI]
    │ layer7_save_json() + layer7_config_interfaces_normalize()
    ▼
[/usr/local/etc/layer7.json]
    ├─ SIGHUP ─────────────────────────────► [layer7d]
    │                                         apply_config()
    │                                         layer7_parse_json()
    │                                         open_captures() / nDPI / DNS
    │                                         layer7_decide_for_client()
    │                                         layer7_pf_resolve_block_target()
    │                                         pfctl -T add + pfctl -k
    │
    └─ filter_configure() ─────────────────► [pfSense filter reload]
                                              layer7_generate_rules("filter")
                                              layer7_pf_default_rules_text()
                                              layer7_policy_enforcement_rules_text()  [scoped only]
                                              layer7_blacklist_filter_rules_text()
                                              layer7_policy_allow_rules_text()       [pallow + L7ALLOW]
    │
    └─ layer7_resync() (boot) ─────────────► ensure tables, NAT anchor, daemon start
```

---

## 3. Matriz de decisão (policy engine)

| Ordem | Camada | Função | Ficheiro | Precedência |
|-------|--------|--------|----------|-------------|
| 0 | Gate runtime | `s_ge` (enforce + licença válida) | `main.c:refresh_enforce_cfg` | Sem `s_ge` → sem PF add |
| 1 | Gate pacote | `layer7_pf_should_enforce()` | `layer7.inc` | `enabled=false` ou `mode=monitor` → sem regras block |
| 2 | Excepções | `layer7_decide_for_client()` | `policy.c:1159` | allow/block/tag por origem |
| 3 | Políticas | first-match após sort prioridade↓ id↑ | `policy.c` | block/allow/tag |
| 4 | Default | allow implícito | `policy.c` | baseline |
| 5 | Blacklist | callback pós-decisão (não override allow explícito desde `_27`) | `main.c`, `blacklist.c` | DNS/SNI → `layer7_bld_N` |

---

## 4. Matriz runtime PF — `legacy_global` vs `scoped_hybrid`

| Dimensão | `legacy_global` (default) | `scoped_hybrid` (experimental) |
|----------|---------------------------|--------------------------------|
| Regra PF política block | `block … to <layer7_block_dst>` global | `from {src} to <layer7_pdst_N>` e/ou `from <layer7_psrc_N> to !localsubnets` |
| Tabela daemon block normal | `layer7_block_dst` | `layer7_pdst_N` (app/host/cat) |
| Quarentena origem | `layer7_block` (excepção) | `layer7_psrc_N` só se `quarantine_origin=true` |
| Per-client real | **Não** — destino bloqueado para todos | **Sim** — PF restringe origem |
| Emissão `block_dst` | Sim | Omitida |
| Allow explícito | `layer7_pallow_N` + tag `L7ALLOW` (`_28+`) | Idem |
| Self-heal tabelas | Core + bld + flush 0..23 | + verificação pdst/psrc/pallow |

---

## 5. Inventário de tabelas PF

### 5.1 Core (fixas)

| Tabela | Populador | Regra consumidora |
|--------|-----------|-------------------|
| `layer7_block` | Daemon (excepção block / quarentena) | `from <layer7_block> to !<localsubnets> !tagged L7ALLOW` |
| `layer7_block_dst` | Daemon (**legacy** policy block) | `to <layer7_block_dst> !tagged L7ALLOW` (omitida em scoped) |
| `layer7_tagged` | Daemon (action=tag) | Encadeamento manual |
| `layer7_allow_dst` | Pacote estático + daemon hint | `match … tag L7ALLOW` |

### 5.2 Dinâmicas por política (N = 0..23)

| Padrão | Semântica |
|--------|-----------|
| `layer7_pdst_{N}` | Destino bloqueado escopado |
| `layer7_psrc_{N}` | Quarentena de origem |
| `layer7_pallow_{N}` | Allow escopado por política |
| `layer7_exc_allow_{N}` | Origens de excepção allow (estático no resync) |

### 5.3 Blacklists UT1

| Padrão | Semântica |
|--------|-----------|
| `layer7_bld_{N}` | Destinos bloqueados por categoria |
| `layer7_blsrc_{N}` | Origem efectiva com `except_ips` |

### 5.4 Legado / flush only

| Tabela | Estado |
|--------|--------|
| `layer7_bl_except` | Flush/deinstall; **sem regra activa** em `layer7.inc` (substituída por `blsrc_N`) |

---

## 6. Matriz de funções críticas

| Fase | Função | Ficheiro |
|------|--------|----------|
| Parse | `layer7_parse_json()` | `config_parse.c` |
| Decisão | `layer7_decide_for_client()` | `policy.c` |
| Decisão fluxo | `layer7_flow_decide()` | `policy.c` |
| Classificação | `layer7_capture_process()` | `capture.c` |
| DNS | `layer7_on_dns_resolved()` | `main.c` |
| nDPI callback | `layer7_on_classified_flow()` | `main.c` |
| Resolve tabela | `layer7_pf_resolve_block_target()` | `enforce.c` |
| PF add | `layer7_pf_exec_table_add()` | `enforce.c` |
| State kill | `layer7_pf_exec_kill_state_pair/host/to()` | `enforce.c` |
| Flush | `enforcement_flush_all_tables()` | `main.c` |
| PF estático | `layer7_generate_rules()` | `layer7.inc` |
| Scoped rules | `layer7_policy_enforcement_rules_text()` | `layer7.inc` |
| Resync | `layer7_resync()` / `layer7_pf_config_resync()` | `layer7.inc` |
| Licença gate | `refresh_enforce_cfg()` / `enforce_ge_downgrade()` | `main.c` |

---

## 7. Divergências doc ↔ código registadas

| ID | Documento | Código | Classificação |
|----|-----------|--------|---------------|
| D-ENC-001 | `pf-enforcement.md` topo: block → sempre `layer7_block_dst` | Scoped usa `pdst`/`psrc` | Doc desactualizado |
| D-ENC-002 | Planos UT1: `layer7_bl_except` + `pass quick` | `layer7_blsrc_N` + ADR-0016 | Doc histórico |
| D-ENC-003 | Tutoriais: config em `config.xml` | SSOT = `layer7.json` | Terminologia |
| D-ENC-004 | GUI promete per-device | `legacy_global` default bloqueia globalmente | Claim parcial (REV-001 by design) |

---

## 8. Estado por versão

| Versão | Cadeia scoped | Allow L7ALLOW | State kill | nDPI final | Anti-QUIC syntax |
|--------|---------------|---------------|------------|------------|------------------|
| `_24` publicada | E0–E3 código presente; bugs conhecidos | `pass quick` histórico | Parcial / sem kill selectivo correcto | Parcial prematuro | `inet on <if>` (FP-018) |
| `_31` candidato | Corrigida `_25`–`_31` | `pallow` + tag | Kill selectivo | `NDPI_STATE_CLASSIFIED` | `on <if> inet` |
| Gate físico | **PENDENTE** ambas | **PENDENTE** | **PENDENTE** | **PENDENTE** | Parser completo **PENDENTE** |

---

## Referências

- `docs/05-daemon/pf-enforcement.md`
- `docs/03-adr/ADR-0014-enforcement-escopado-por-politica.md`
- `docs/03-adr/ADR-0016-allow-pf-sem-bypass-pfsense.md`
- `docs/09-blocking/plano-enforcement-100-porcento.md`
