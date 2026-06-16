# Revisão de código pré-instalação — Layer7 para pfSense CE

**Data:** 2026-06-15  
**Versão analisada:** código no workspace (base publicada `1.8.11_23`; Caminho B E0–E3 em progresso)  
**Âmbito:** `src/layer7d/`, `package/pfSense-pkg-layer7/`, `tests/`  
**Restrição:** revisão estática + `tests/run-local.sh`; sem build FreeBSD nem validação em appliance nesta sessão.

---

## Resumo executivo

| Severidade | Contagem |
|------------|----------|
| Crítico | 6 |
| Alto | 14 |
| Médio | 18 |
| Baixo | 7 |
| Documentação | 5 |

**Testes locais:** `sh tests/run-local.sh` → **PASS** (4 suites C, ~95 asserções). **SKIP:** `test_scoped_pf_inc.php` e lint PHP (PHP não instalado no host de revisão). Lint shell: PASS (10 scripts).

### Veredicto de confiança para instalar

**Não recomendado instalar com expectativa de “políticas por dispositivo / YouTube só para um cliente” sem correções prévias.**

A causa-raiz confirmada no código é o modelo **legacy_global** (default): decisão de política é per-client, mas enforcement PF usa tabela global `layer7_block_dst` — bloquear um destino para um IP origem bloqueia esse destino para **toda a rede**. A GUI (grupos, MAC→IP, políticas por dispositivo) promete comportamento que o runtime legacy não cumpre.

O **Caminho B** (`scoped_hybrid`) aborda isso estruturalmente (E0–E3 implementados), mas:

- permanece **experimental**, default `legacy_global`;
- **gate two-client no appliance pendente** (E4–E8 não concluídos);
- há bugs de integração daemon ↔ pacote ↔ PF (reload de regras, índices de tabela, IPv6, origem-only).

Para uso **monitor-only** ou bloqueio global homogéneo (todos os clientes, mesma política), o produto é mais estável após Fase 1 (`1.8.11_18`+), mas licenciamento, TTL de blacklist e flush em falha de licença ainda apresentam riscos operacionais.

---

## Contexto e objectivo Layer7

Layer7 para pfSense CE é um pacote + daemon `layer7d` que classifica tráfego (nDPI, DNS, SNI opt-in), aplica políticas por cliente/grupo/interface com precedência (exceções → políticas → default allow), e materializa bloqueios em tabelas PF sem MITM. Inclui allowlist de destinos, blacklists UT1 escopadas, licenciamento Ed25519 e GUI tipo UDM Pro (perfis, dispositivos, toggles). O objectivo operacional é filtragem L7 com enforcement escopado por política — não bloqueio collateral global.

---

## Metodologia

1. Leitura obrigatória: `CORTEX.md`, `AGENTS.md`, `target-architecture.md`, `docs/core/*`, `plano-enforcement-100-porcento.md`, `pf-enforcement.md`.
2. Revisão sistemática do daemon (`main.c`, `policy.c`, `enforce.c`, `config_parse.c`, `license.c`, `capture.c`, `allowlist.c`, `blacklist.c`, `bl_config.c`).
3. Revisão do pacote (`layer7.inc`, `layer7-pfctl`, rc scripts, GUI PHP, `layer7.xml`).
4. Análise de `tests/` e execução de `sh tests/run-local.sh`.
5. Cada achado: ID, severidade, área, ficheiro/linhas, facto técnico, incoerência com o objectivo, impacto operacional, correcção sugerida.

---

## Achados detalhados

### Crítico

#### REV-001 — Enforcement global por defeito vs promessa de políticas por cliente

| | |
|---|---|
| **Severidade** | Crítico |
| **Área** | integração |
| **Ficheiros** | `src/layer7d/main.c` (~1157–1205), `package/.../layer7.inc` (609–617), `config_parse.c` (default `legacy_global`) |

**O que está errado:** Com `enforcement_model=legacy_global` (default), fluxos classificados e DNS legacy adicionam IPs a `layer7_block_dst`. Regra PF: `block drop quick inet to <layer7_block_dst>` — sem `from {cliente}`.

**Por que não faz sentido:** Caminho A entregou grupos, `device_macs`→`device_ips`, `src_hosts`; operador configura “bloquear YouTube para o filho”. Decisão em `policy.c` considera origem; PF ignora origem.

**Impacto operacional:** Bloquear YouTube (ou banco, social) para um dispositivo bloqueia para **todos** na LAN. Sensação de “nunca funcionou” / “bloqueia bancos para todos”.

**Correcção sugerida:** Não instalar para per-device sem `scoped_hybrid` validado; ou corrigir default após gate E8; documentar explicitamente na GUI até então.

---

#### REV-002 — Licença inválida não remove bloqueios PF existentes

| | |
|---|---|
| **Severidade** | Crítico |
| **Área** | daemon / segurança |
| **Ficheiros** | `src/layer7d/main.c` 2353–2366 vs 2247–2250 |

**O que está errado:** No recheck horário, se `layer7_license_check()` falha, `s_ge = 0` mas **não** chama `enforcement_flush_all_tables()`. SIGHUP só flusha na transição `prev_ge && !s_ge`.

**Por que não faz sentido:** Modelo comercial: sem licença válida → monitor-only. Bloqueios PF residuais violam isso.

**Impacto operacional:** Após expiração/revogação, tráfego continua bloqueado até stop manual do serviço ou reload PF.

**Correcção sugerida:** Ao detectar `s_ge: 1→0` por licença, chamar `enforcement_flush_all_tables()` como no SIGHUP.

---

#### REV-003 — CIDR `/0` na allowlist = bypass total de bloqueio por destino

| | |
|---|---|
| **Severidade** | Crítico |
| **Área** | daemon / segurança |
| **Ficheiros** | `src/layer7d/allowlist.c` 257–258 |

```c
if (p <= 0)
    return 1;
```

**O que está errado:** Prefixo `0` (ex. `0.0.0.0/0`) faz `l7_allowlist_contains_ip()` retornar true para qualquer IPv4.

**Por que não faz sentido:** Allowlist deve isentar destinos específicos; `/0` anula enforcement de destino.

**Impacto operacional:** Entrada mal configurada ou import corrupto → bypass completo de bloqueios DNS/SNI/destino.

**Correcção sugerida:** Rejeitar `prefix < 1` em validação e em `contains_ip`; tratar `prefix == 0` como inválido.

---

#### REV-004 — Blacklist DNS/SNI sem TTL nem sweep

| | |
|---|---|
| **Severidade** | Crítico |
| **Área** | daemon |
| **Ficheiros** | `src/layer7d/main.c` 1093–1122, 1247–1270 |

**O que está errado:** Adds a `layer7_bld_N` não passam por `enforce_cache_add()` / `enforce_cache_sweep()`. Políticas DNS usam TTL clamped ≥300s.

**Por que não faz sentido:** IPs DNS são temporários; blacklist sem expiração acumula entradas `persist` indefinidas.

**Impacto operacional:** Tabelas PF crescem sem limite; IP reutilizado (CDN) bloqueado para todos; bloqueio “fantasma” após mudança de DNS.

**Correcção sugerida:** Integrar blacklist no cache TTL ou sweep periódico de `layer7_bld_*`.

---

#### REV-005 — `scoped_hybrid`: política block só-origem sem destino/app é ineficaz

| | |
|---|---|
| **Severidade** | Crítico |
| **Área** | integração |
| **Ficheiros** | `layer7.inc` 438–443; `policy.c` 995–1002; `enforce.c` 153–168; `layer7.inc` 609–617 |

**O que está errado:** PHP ignora políticas block sem `hosts` nem `ndpi_app`/`ndpi_category`. Daemon: `policy_enforce_kind` → `L7_ENFORCE_NONE`; runtime pode cair em `layer7_block_dst`, mas scoped **não emite** regra para essa tabela.

**Por que não faz sentido:** Política “bloquear grupo de dispositivos” sem hosts/apps não tem caminho PF em scoped mode.

**Impacto operacional:** Operador activa scoped, configura block por grupo — **zero bloqueio** ou comportamento incoerente.

**Correcção sugerida:** Definir semântica (quarentena src-scoped ou rejeitar na GUI); nunca popular `layer7_block_dst` em scoped sem regra PF.

---

#### REV-006 — Caminho B incompleto; gate appliance pendente

| | |
|---|---|
| **Severidade** | Crítico |
| **Área** | documentação / integração |
| **Ficheiros** | `CORTEX.md`, `plano-enforcement-100-porcento.md` |

**O que está errado:** E4–E8 (BG-049..052) pendentes; `scope_global` match semantics; testes two-client no appliance não executados como gate.

**Por que não faz sentido:** `scoped_hybrid` exposto na GUI como opção sem validação completa.

**Impacto operacional:** Activar scoped em produção sem lab = risco alto de regressão e estados PF inconsistentes.

**Correcção sugerida:** Manter scoped só em lab até E8 + gate; ou ocultar opção na GUI comercial.

---

### Alto

#### REV-007 — `except_ips` de blacklist parseado mas nunca aplicado

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | daemon |
| **Ficheiros** | `bl_config.c` 323–325; `main.c` 829–841 (`bl_rule_matches_src`) |

**O que está errado:** `except_ips[]` carregado do JSON; `bl_rule_matches_src()` só verifica `src_cidrs`, nunca `except_ips`.

**Impacto:** IPs configurados como exceção continuam bloqueados pela blacklist.

**Correcção:** Em `bl_rule_matches_src()`, retornar 0 se `client_ip` ∈ `except_ips`.

---

#### REV-008 — Excepções ignoradas no caminho DNS legacy

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | daemon |
| **Ficheiros** | `main.c` 1074–1090; `policy.c` 1193–1213 (`layer7_domain_is_blocked`) |

**O que está errado:** Legacy DNS usa `layer7_domain_is_blocked()` — só block+schedule+host, sem excepções, sem origem, sem prioridade allow.

**Impacto:** Excepção `allow` para host/IP não impede bloqueio DNS em `legacy_global`.

**Correcção:** Unificar DNS sob `layer7_decide_for_client()` (como scoped) ou avaliar excepções antes do atalho legacy.

---

#### REV-009 — Excepção BLOCK usa `layer7_block` na decisão; runtime usa destino global

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | daemon / integração |
| **Ficheiros** | `policy.c` 1053–1066 (`fill_enforce_action` → `dec_set_pf_block`); `enforce.c` |

**O que está errado:** Excepções BLOCK preenchem `pf_table = layer7_block` (quarentena origem); runtime `layer7_pf_resolve_block_target` em scoped usa `pdst_N` ou `block_dst` — excepções não recebem `dec_set_scoped_policy`.

**Impacto:** Excepção block pode bloquear destino globalmente em vez de escopo pretendido.

**Correcção:** Alinhar semântica de excepções com políticas scoped ou documentar comportamento global explícito.

---

#### REV-010 — SIGHUP em enforce não limpa tabelas `layer7_pdst_*` / `layer7_psrc_*`

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | daemon |
| **Ficheiros** | `main.c` 2232 (`enforce_cache_flush`), 2247–2250 |

**O que está errado:** Reload normal flusha cache/`block_dst` via `enforce_cache_flush()`, mas tabelas scoped só flusham em enforce→passivo ou shutdown.

**Impacto:** Reorder de prioridade altera índice N; IPs antigos ficam na tabela errada → bloqueio collateral ou falha de bloqueio.

**Correcção:** Flush de todas tabelas dinâmicas em cada SIGHUP ou diff por policy id.

---

#### REV-011 — `s_ge` desactualizado se parse de políticas falha

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | daemon |
| **Ficheiros** | `main.c` 1759–1795 |

**O que está errado:** `s_parsed` e `s_have_parse` aplicam-se sempre; `refresh_enforce_cfg()` só se `pe_loaded`. JSON com `mode: monitor` mas `policies[]` inválido mantém `s_ge` anterior.

**Impacto:** Enforcement activo com config aparentemente desactivada.

**Correcção:** Chamar `refresh_enforce_cfg()` sempre após actualizar `s_parsed`.

---

#### REV-012 — DNS blacklist sem validação `layer7_pf_ipv4_host_ok`

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | daemon |
| **Ficheiros** | `main.c` 1109–1110 vs 1070 |

**O que está errado:** Caminho política scoped valida IP; blacklist DNS chama `layer7_pf_add_with_selfheal` sem validar `resolved_ip`.

**Impacto:** Entrada inválida → falha pfctl ou comportamento imprevisível.

**Correcção:** Guard `layer7_pf_ipv4_host_ok(resolved_ip)` antes de qualquer add.

---

#### REV-013 — Callback DNS ignora `cfg_disabled` (`enabled=false`)

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | daemon |
| **Ficheiros** | `main.c` 1033–1034 vs 1155 (`layer7_on_classified_flow`) |

**O que está errado:** Fluxos nDPI respeitam `cfg_disabled()`; DNS só verifica `s_have_parse && s_ge`.

**Impacto:** Com `enabled=false` mas `s_ge` stale (#REV-011), bloqueio DNS continua.

**Correcção:** Gate `cfg_disabled(&s_parsed)` em `layer7_on_dns_resolved`.

---

#### REV-014 — Cache enforcement cheio descarta bloqueio silenciosamente

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | daemon |
| **Ficheiros** | `main.c` 610–620 |

**O que está errado:** Com `s_n_enforce_cache >= L7_DST_CACHE_MAX` (2048) e entradas válidas, novo bloqueio retorna sem log.

**Impacto:** Sob carga, tráfego proibido passa sem PF add.

**Correcção:** Evict forçado + WARN; nunca `return` silencioso.

---

#### REV-015 — Mudança `enforcement_model` não chama `filter_configure()`

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | package / integração |
| **Ficheiros** | `layer7_settings.php` 437–446 |

**O que está errado:** `pf_relevant_changed` não inclui `enforcement_model`. Só `layer7_signal_reload()` (HUP daemon).

**Impacto:** legacy↔scoped: regras PF activas desalinhadas do daemon (`layer7_block_dst` vs `layer7_pdst_N`).

**Correcção:** Incluir `enforcement_model` em `pf_relevant_changed` ou `filter_configure()` sempre que muda.

---

#### REV-016 — Políticas/grupos/excepções gravados sem `filter_configure()`

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | package / integração |
| **Ficheiros** | `layer7_policies.php`, `layer7_groups.php`, `layer7_exceptions.php`, `layer7_devices.php` (só `layer7_signal_reload`) |

**O que está errado:** Alterações que mudam índices PF, IPs embutidos `from {ip}`, `scope_global` só actualizam JSON + HUP. `filter_configure()` só em allowlist/diagnostics.

**Impacto:** Daemon e PF divergem até “Apply” manual no firewall — bloqueio errado ou inexistente.

**Correcção:** `filter_configure()` após saves que afectem políticas/grupos/dispositivos.

---

#### REV-017 — `scope_global` só no PHP; daemon nunca lê do JSON

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | integração |
| **Ficheiros** | `policy.h` 135; `policy.c` 991, 1011; `layer7_policies.php` |

**O que está errado:** Checkbox GUI grava `scope_global`; daemon sempre `dec->scope_global = 0`; sem parse na política.

**Impacto:** Runtime não distingue política global explícita vs restrita (E4 pendente).

**Correcção:** Parse `scope_global` no daemon e alinhar decisão; ou remover checkbox até E4.

---

#### REV-018 — `scoped_hybrid` sem regras `inet6`

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | package |
| **Ficheiros** | `layer7.inc` 509–534 vs 615–616 |

**O que está errado:** Regras scoped só `inet`; legacy inclui `inet6` para `layer7_block_dst`.

**Impacto:** IPv6 contorna bloqueio por política em scoped mode.

**Correcção:** Emitir pares `inet`/`inet6` para regras scoped onde aplicável.

---

#### REV-019 — App-only (`SRC_SCOPED`) = quarentena total, não bloqueio selectivo

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | daemon / documentação |
| **Ficheiros** | `main.c` 1198–1205; `layer7.inc` (regra `from <psrc> to !localsubnets`) |

**O que está errado:** Política block só `ndpi_app` adiciona `src_ip` a `layer7_psrc_N`; PF bloqueia **todo** tráfego externo desse IP.

**Impacto:** “Block BitTorrent” sem hosts → dispositivo inteiro em quarentena após primeira detecção.

**Correcção:** Documentar na GUI; ou implementar dst IPs por app + `from src to pdst`.

---

#### REV-020 — Tabelas scoped órfãs após reorder/delete

| | |
|---|---|
| **Severidade** | Alto |
| **Área** | package / integração |
| **Ficheiros** | `layer7.inc` `layer7_flush_dynamic_tables()` ~787–799 |

**O que está errado:** Flush só tabelas da config actual; índices antigos mantêm IPs `persist`.

**Impacto:** Bloqueios residuais após apagar política ou mudar prioridade.

**Correcção:** Flush `layer7_pdst_0..23` / `layer7_psrc_0..23` antes de aplicar nova config.

---

### Médio

#### REV-021 — Parser JSON frágil (strstr/heurística)

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `config_parse.c`, `policy.c`, `allowlist.c` |

**O que está errado:** Chaves por `strstr`; políticas inválidas omitidas silenciosamente; arrays truncados em `L7_MAX_*` sem erro.

**Impacto:** Config parcialmente aplicada sem alerta claro.

**Correcção:** Parser estrutural ou json-c; WARN por skip; falhar reload se política crítica inválida.

---

#### REV-022 — `dec->pf_table` para BLOCK não reflecte tabela runtime

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `policy.c` 1017–1020; `enforce.c` 163–168 |

**O que está errado:** Decisão BLOCK aponta `layer7_block`; runtime usa `block_dst` ou `pdst_N`.

**Impacto:** CLI `-e`, logs e dry-run enganam operadores.

**Correcção:** Alinhar `fill_enforce` com `layer7_pf_resolve_block_target`.

---

#### REV-023 — Allowlist: IPs em `layer7_allow_dst` sem TTL

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `main.c` 1044–1046, 1216–1217 |

**O que está errado:** Domínio allowlisteado adiciona IP a PF sem expiração; só flush em reload.

**Impacto:** IP reutilizado por CDN pode ficar “allowed” indevidamente; tabela cresce.

**Correcção:** TTL no cache allow ou sweep periódico.

---

#### REV-024 — `system()` sem verificação em flushes

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `main.c` 674, 693–712, 907, 1015 |

**O que está errado:** `(void)system(cmd)` em flushes; contraste com `run_shell_cmd_ok` noutros sítios.

**Impacto:** Falha de flush ignorada → bloqueios residuais.

**Correcção:** Usar `run_shell_cmd_ok` + log em falha.

---

#### REV-025 — `pf_table_exists` via shell+grep

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon / segurança |
| **Ficheiros** | `main.c` 856–865 |

**O que está errado:** `system("pfctl -s Tables | grep -qw ...")` — frágil, lento.

**Correcção:** Parse directo de output pfctl via `popen`/`exec`.

---

#### REV-026 — Stats JSON: escape incompleto

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `main.c` 280–289 |

**O que está errado:** `json_escape_fprint` só `"` e `\`; não `\n`, control chars.

**Impacto:** `/tmp/layer7-stats.json` inválido para consumidores.

**Correcção:** Escape RFC 8259 completo.

---

#### REV-027 — Tabela de fluxos nDPI esgota silenciosamente (64 slots)

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `capture.c` 349–384 |

**O que está errado:** Sem slot, `flow_lookup` retorna NULL → pacote ignorado sem alerta.

**Impacto:** Classificação/enforcement deixa de funcionar sob carga.

**Correcção:** Contador + WARN/syslog; métrica em stats.

---

#### REV-028 — `localtime()` não reentrante em schedules

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `policy.c` 459–460 |

**Correcção:** `localtime_r`.

---

#### REV-029 — Makefile standalone omite `allowlist.c`

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon / testes |
| **Ficheiros** | `src/layer7d/Makefile` vs `package/.../Makefile` |

**Impacto:** Build local no dir layer7d diverge do pacote pfSense.

**Correcção:** Alinhar SRCS com pacote.

---

#### REV-030 — Licença: expiry `YYYY-MM-DD` via `mktime` (TZ local)

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `license.c` 370–372 |

**Impacto:** Expiração pode variar ±1 dia consoante timezone do firewall.

**Correcção:** Meio-dia UTC ou `timegm`.

---

#### REV-031 — Fingerprint: primeira MAC de `getifaddrs` (ordem não garantida)

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `license.c` 96–117 |

**Impacto:** VM com NICs reordenadas → falsos negativos de licença.

**Correcção:** MAC determinístico (menor interface, ou todas).

---

#### REV-032 — `layer7-pfctl write_rules()` sempre legacy em disco

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | package |
| **Ficheiros** | `layer7-pfctl` 102–124 |

**O que está errado:** `/usr/local/etc/layer7/pf.conf` no disco não reflecte scoped; `ensure` não cria tabelas scoped.

**Impacto:** Diagnósticos/operadores confundidos; self-heal incompleto.

**Correcção:** `write_rules()` mode-aware; `ensure` criar tabelas scoped activas.

---

#### REV-033 — IPs `device_ips` mudam sem reload PF

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | integração |
| **Ficheiros** | `layer7.inc` `layer7_devices_resync()` |

**O que está errado:** Regras scoped embutem IPs literais; resync só HUP.

**Impacto:** DHCP churn → PF bloqueia IPs antigos.

**Correcção:** `filter_configure()` após resync; ou tabela PF de origem dinâmica.

---

#### REV-034 — Transição `enforcement_model` sem flush daemon

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | integração |
| **Ficheiros** | `main.c` (reload não detecta mudança de modelo) |

**Impacto:** IPs em `block_dst` ou `pdst_N` até stop manual.

**Correcção:** Detectar mudança de `enforcement_model` no reload e flush.

---

#### REV-035 — Diagnósticos não validam modo scoped

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | package |
| **Ficheiros** | `layer7_diagnostics.php` 12–28 |

**Impacto:** “PF OK” com scoped activo mas tabelas scoped ausentes.

**Correcção:** Validar `layer7_pdst_*`/`layer7_psrc_*` se `scoped_hybrid`.

---

#### REV-036 — `rule_matches`: critérios vazios = catch-all

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `policy.c` 977 |

**O que está errado:** Política sem apps/hosts/cats após passar iface/src → `return 1`.

**Impacto:** Política mal configurada bloqueia/monitora tudo que passa iface/src.

**Correcção:** Rejeitar na GUI/parse políticas sem critério de match.

---

#### REV-037 — Campos documentados não implementados (`ndpi_master`, `dst_port`, `dst_net`)

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | documentação / daemon |
| **Ficheiros** | `docs/core/policy-matrix.md` vs `policy.c` |

**Impacto:** Config JSON com esses campos é ignorada silenciosamente.

**Correcção:** Implementar ou remover da documentação/GUI.

---

#### REV-038 — IPv4 only na captura

| | |
|---|---|
| **Severidade** | Médio |
| **Área** | daemon |
| **Ficheiros** | `capture.c` 551 (`ip_v != 4` descartado) |

**Impacto:** Sem classificação/enforcement L7 em IPv6.

**Correcção:** Documentar limite V1; roadmap IPv6.

---

### Baixo

#### REV-039 — `parse_action` default silencioso → MONITOR

| | |
|---|---|
| **Severidade** | Baixo |
| **Área** | daemon |
| **Ficheiros** | `policy.c` 206–217 |

**Impacto:** Typo em `action` → política permissiva.

---

#### REV-040 — `parse_bool` em bl_config: inválido → false

| | |
|---|---|
| **Severidade** | Baixo |
| **Área** | daemon |
| **Ficheiros** | `bl_config.c` 77–88 |

---

#### REV-041 — Contadores `s_total_blocked` na decisão, não no sucesso pfctl

| | |
|---|---|
| **Severidade** | Baixo |
| **Área** | daemon |
| **Ficheiros** | `main.c` 1161–1162 |

**Impacto:** Métricas inflacionadas se PF falha.

---

#### REV-042 — DNS CNAME: callback pode usar nome do RR, não QNAME

| | |
|---|---|
| **Severidade** | Baixo |
| **Área** | daemon |
| **Ficheiros** | `capture.c` 227–264 |

**Impacto:** Match de política por domínio errado em cadeias CNAME raras.

---

#### REV-043 — `layer7-pfctl flush` não inclui scoped/allow

| | |
|---|---|
| **Severidade** | Baixo |
| **Área** | package |
| **Ficheiros** | `layer7-pfctl` 135–139 vs 162–165 |

**Nota:** `layer7d_stop` usa `flush-all` (correcto).

---

#### REV-044 — Grupos não listados em `layer7.xml` tabs

| | |
|---|---|
| **Severidade** | Baixo |
| **Área** | package |
| **Ficheiros** | `layer7.xml` vs `layer7_groups.php` |

**Impacto:** Navegação só via menu lateral/dispositivos.

---

#### REV-045 — `nat_rules_needed` no XML sem efeito em CE

| | |
|---|---|
| **Severidade** | Baixo |
| **Área** | package |
| **Ficheiros** | `layer7.xml` 7–8; NAT via `layer7_inject_nat_to_anchor()` |

---

### Documentação

#### REV-046 — Help Settings: scoped “equivalente ao legacy”

| | |
|---|---|
| **Severidade** | Documentação |
| **Área** | package |
| **Ficheiros** | `layer7_settings.php` 595 |

**O que está errado:** Texto diz comportamento PF equivalente até E2/E3; scoped **omite** `layer7_block_dst` global.

**Correcção:** Actualizar help-block.

---

#### REV-047 — `layer7_domain_is_blocked` ainda usado em legacy DNS

| | |
|---|---|
| **Severidade** | Documentação |
| **Área** | daemon |
| **Ficheiros** | `plano-enforcement-100-porcento.md` (deprecar); `main.c` 1074 |

**Nota:** Parcialmente resolvido em scoped; legacy mantém atalho incompleto.

---

#### REV-048 — DoH/DoT/QUIC bypass estrutural

| | |
|---|---|
| **Severidade** | Documentação |
| **Área** | arquitectura |

**O que está errado:** Bloqueio DNS depende de respostas DNS em claro; DNS cifrado contorna path DNS (limitação V1, não bug isolado).

**Correcção:** Documentar; `block_dot_doq` toggle OFF por defeito; anti-QUIC por interface.

---

#### REV-049 — OR entre apps e hosts alarga match

| | |
|---|---|
| **Severidade** | Documentação |
| **Área** | daemon |
| **Ficheiros** | `policy.c` 964–969 |

**O que está errado:** Com apps+hosts, match é OR — fluxo QUIC pode casar por host quando app não identificado.

**Impacto:** Bloqueio mais amplo que “só app” na intuição do operador.

**Correcção:** Documentar na GUI; opção AND no backlog.

---

#### REV-050 — Testes locais não cobrem nDPI, licença, capture, PF real

| | |
|---|---|
| **Severidade** | Documentação |
| **Área** | testes |

Ver secção “Limitações dos testes” abaixo.

---

## Limitações dos testes (`tests/run-local.sh`)

**Execução nesta revisão (macOS):**

| Etapa | Resultado |
|-------|-----------|
| `test_allowlist` | PASS (24 asserções) |
| `test_config_parse` | PASS (25 asserções) |
| `test_policy_decide` | PASS (35 asserções) |
| `test_enforce_scoped` | PASS (11 asserções) |
| `test_scoped_pf_inc.php` | SKIP (PHP ausente) |
| Lint PHP | SKIP |
| Lint shell | PASS (10 scripts) |

**Gaps que escondem bugs:**

- `test_policy_decide.c`: cenários sem `ndpi_app`/`ndpi_cat`; sem `scope_global`; sem TAG.
- Sem testes de `license.c`, `capture.c`, `blacklist.c`, `main.c` loop.
- Sem integração daemon↔PF↔PHP num único fixture.
- `tests/fixtures/`, `tests/traffic/`, `tests/package/` vazios.
- Smoke appliance (`smoke-caminho-a.sh`, `smoke-monitor-mode.sh`) não executados nesta sessão.
- Gate two-client scoped (secção 12 `validacao-lab.md`) pendente.
- CI `smoke-layer7d.sh` recusa Darwin; build real só FreeBSD builder.

Os testes actuais protegem regressões conhecidas (allowlist, ordem SNI no parse, `enforcement_model`, decisão scoped básica, resolução lógica PF). **Não** constituem barreira forte para os achados REV-001 a REV-020.

---

## Achados positivos (breve)

- **`enforce.c`:** `fork`/`execv` para `pfctl` no path principal — sem shell injection nos adds.
- **Validação conservadora** de nomes de tabela e IPv4 antes de operações PF.
- **Handlers de sinal** com flags `sig_atomic_t`; reload fecha captures antes de mutar config.
- **Snapshot em reload:** políticas inválidas mantêm snapshot anterior quando parse falha (parcialmente — ver REV-011).
- **Gate allowlist** antes de bloqueio em DNS e fluxos classificados.
- **Shutdown limpo** com `enforcement_flush_all_tables()`.
- **Licença:** Ed25519 via OpenSSL EVP; validação de key/url na activação; grace explícito.
- **Fase 1:** monitor passivo real (`layer7_pf_should_enforce`); allowlist `layer7_allow_dst` com `pass quick` antes de blocks.
- **Blacklists UT1:** modelo escopado `from {cidr} to <layer7_bld_N>` — referência correcta para Caminho B.
- **Caminho A:** inventário dispositivos, MAC→IP, SNI opt-in, contadores — código presente e parcialmente testado.
- **Caminho B E1–E3:** `layer7_decide_for_client()`, tabelas `pdst_N`/`psrc_N`, testes unitários locais PASS.

---

## Priorização: antes vs depois de instalar

### Corrigir **antes** de instalar (se o objectivo é filtragem per-client fiável)

| ID | Resumo |
|----|--------|
| REV-001 | Compreender/limitar legacy_global — ou scoped com bugs conhecidos |
| REV-002 | Flush PF quando licença inválida |
| REV-015, REV-016 | `filter_configure()` em mudanças de modelo/políticas |
| REV-005 | Políticas origem-only em scoped |
| REV-008 | Excepções no DNS legacy |
| REV-004 | TTL blacklist |
| REV-003 | Rejeitar CIDR `/0` allowlist |
| REV-006 | Não activar scoped em produção sem gate appliance |

### Corrigir **depois** de instalar (ou aceitar risco documentado)

| ID | Resumo |
|----|--------|
| REV-007 | `except_ips` blacklist |
| REV-010–014 | Reload, cache, gates DNS |
| REV-017–020 | scope_global, IPv6 scoped, quarentena app-only, tabelas órfãs |
| REV-021–038 | Parser, métricas, IPv6 capture, licença TZ |
| REV-039–045 | Baixos operacionais |
| REV-046–050 | Documentação e testes |

### Aceitável para **monitor-only** ou bloqueio **global homogéneo**

- REV-001 menos crítico se todas as políticas são globalmente intencionadas.
- Licenciamento e flush (REV-002) ainda recomendados.

---

## Conclusão

A revisão encontrou **problemas graves e estruturais**, não apenas detalhes cosméticos. O código **funciona** para classificação, logging, allowlist, blacklists escopadas e bloqueio global — coerente com releases validadas `1.8.11_18`–`_23` em lab. O que **não funciona** para o caso de uso típico “UDM Pro / política por filho” é o **desalinhamento decisão per-client vs imposição PF global** no modo default (`legacy_global`), mais bugs de integração no Caminho B ainda em curso.

**Honestidade:** não é que “nada funciona” — monitor, allowlist, bloqueio global e blacklists UT1 têm implementação sólida em partes. O fracasso percebido alinha-se com REV-001 e gaps de reload PF (REV-015, REV-016).

**Recomendação:** instalar apenas após alinhar expectativa (global vs scoped), corrigir REV-002 e REV-015/016, e completar gate Caminho B no appliance. Para confiança máxima, executar `validacao-lab.md` secções 11–12 e smoke two-client antes de produção.

---

## Referências

- Plano Caminho B: [`plano-enforcement-100-porcento.md`](plano-enforcement-100-porcento.md)
- Enforcement PF: [`../05-daemon/pf-enforcement.md`](../05-daemon/pf-enforcement.md)
- ADR-0014: [`../03-adr/ADR-0014-enforcement-escopado-por-politica.md`](../03-adr/ADR-0014-enforcement-escopado-por-politica.md)
- Matriz de testes: [`../tests/test-matrix.md`](../tests/test-matrix.md)

---

## Estado pós-correcção (2026-06-16)

Bloco **`1.8.11_24`** (repo; não publicado). Testes locais PASS; gate
two-client (sec. 12) ainda pendente.

| REV | Estado | Nota breve |
|-----|--------|--------------|
| REV-002 | ✅ | Flush PF em licença inválida (`enforce_ge_downgrade`) |
| REV-003 | ✅ | Rejeição CIDR `/0` na allowlist |
| REV-015 | ✅ parcial | `enforcement_model` → `filter_configure()` |
| REV-016 | ✅ parcial | `layer7_pf_config_resync()` em políticas/grupos/excepções/dispositivos |
| REV-001 | by design | `legacy_global` default; scoped requer opt-in + gate |
| REV-004–REV-014 | aberto | TTL parcial em bld; excepções DNS legacy; cache SIGHUP; etc. |
| REV-017 | ✅ parcial | `scope_global` parse no daemon; E4 semântica pendente |
| REV-018–REV-050 | aberto | IPv6 scoped, quarentena edge cases, docs/testes restantes |

Ver `CHANGELOG [1.8.11_24]` e `docs/06-releases/release-notes-1.8.11_24.md`.
