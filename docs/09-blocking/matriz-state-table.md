# Matriz — state table PF e invalidação de sessões

**Data:** 2026-07-29  
**Versão repo:** `1.8.11_31` | **Release pública:** `1.8.11_24`  
**Estado:** CANDIDATO INTERNO EM VALIDAÇÃO

---

## 1. Problema operacional

Inserir um IP numa tabela PF (`pfctl -T add`) **não** interrompe estados TCP/UDP já estabelecidos. O Layer7 deve executar `pfctl -k` após decisões de block para evitar bypass por sessão persistente (FP-003, corrigido em `_27`).

---

## 2. Matriz de acções pós-`pfctl -T add`

| Cenário | Modelo | Tabela | Comando kill | Função | Ficheiro |
|---------|--------|--------|--------------|--------|----------|
| Excepção block (quarentena) | Qualquer | `layer7_block` | `pfctl -k host src` | `layer7_pf_exec_kill_states_host` | `enforce.c` |
| Política block scoped quarentena | `scoped_hybrid` | `layer7_psrc_N` | `pfctl -k host src` | idem | `main.c:1026-1036` |
| Política block destino | `legacy_global` | `layer7_block_dst` | `pfctl -k dst` | `layer7_pf_exec_kill_states_to` | `enforce.c` |
| Política block destino scoped | `scoped_hybrid` | `layer7_pdst_N` | `pfctl -k src -k dst` (par) | `layer7_pf_exec_kill_state_pair` | `enforce.c` |
| Allow / monitor | Qualquer | — | Nenhum | — | — |
| Tag | Qualquer | `layer7_tagged` | Nenhum automático | — | — |

---

## 3. Implementação (`enforce.c`)

| Função | Comando efectivo | Uso |
|--------|------------------|-----|
| `layer7_pf_exec_kill_states_to(dst)` | `pfctl -k <dst_ip>` | Legacy global — mata todos os estados para o destino |
| `layer7_pf_exec_kill_states_host(src)` | `pfctl -k <src_ip>` | Quarentena — mata todos os estados do cliente |
| `layer7_pf_exec_kill_state_pair(src,dst)` | `pfctl -k src -k dst` | Scoped pdst — mata par origem/destino |

**Fork/exec:** `layer7_pf_exec_table_add()` em `enforce.c:103` — subprocesso `/sbin/pfctl`.

---

## 4. Matriz de flush (remoção de bloqueios)

| Evento | Flush tabelas | State kill | Evidência |
|--------|---------------|------------|-----------|
| `stop_req` (SIGTERM/SIGINT) | `enforcement_flush_all_tables()` | Implícito ao esvaziar tabelas | `main.c:2500-2513` |
| SIGHUP: enforce→passivo | `enforce_ge_downgrade()` → flush | Sim (tabelas vazias) | `main.c:2588-2591` |
| Licença inválida (recheck) | `enforce_ge_downgrade(prev_ge,"license_recheck")` | Sim | `main.c:2725-2727` |
| Licença inválida (boot) | `s_ge=0` + flush | Sim | `main.c:2440-2441` |
| `enabled=false` / `mode=monitor` | Pacote: sem regras block; daemon idle | Depende de reload PF | `layer7_pf_should_enforce()` |
| `rc.d stop` | `layer7-pfctl flush-all` | — | `rc.d/layer7d:80-113` |
| Mudança `enforcement_model` | `layer7_flush_dynamic_tables()` + filter_configure | — | `layer7.inc` |

---

## 5. Comparação `_24` vs `_31`

| Comportamento | `_24` (pública) | `_31` (candidato) |
|---------------|-----------------|-------------------|
| Kill após block policy | **Insuficiente / ausente** para scoped e parcial legacy | Kill selectivo por cenário |
| Flush licença inválida | **Presente** (REV-002 corrigido em `_24`) | Mantido + `enforce_ge_downgrade()` |
| Flush stop/reload | Presente | Presente |
| Evidência appliance | **Não executada** | **Pendente** (critério 7 revisão funcional) |

---

## 6. Riscos e limitações

| ID | Risco | Severidade | Estado |
|----|-------|------------|--------|
| FP-003 | Sessão persistente após block | Crítica em `_24` | **Corrigido código `_27`**; gate físico pendente |
| AUD-011 | UDP stateless / QUIC long-lived pode exigir kill repetido | Média | SUSPEITA — não testado |
| AUD-012 | Kill `host` em quarentena afecta todo tráfego do cliente (by design) | Alta | REPRODUZÍVEL por código |
| FP-010 | IPv6 states não geridos (captura IPv4-only) | Alta | Limitação arquitectural |

---

## 7. Testes mínimos exigidos (gate)

1. Cliente A abre HTTPS para destino bloqueado; após decisão, sessão existente deve cair (`curl`/`tcpdump`).
2. Cliente B (não alvo) mantém sessão paralela ao mesmo destino em `scoped_hybrid`.
3. Após TTL cache / remoção tabela, reconexão comporta-se conforme política.
4. Licença inválida durante enforce → tabelas vazias + tráfego permitido (salvo regras nativas pfSense).
5. `service layer7d stop` → `layer7-pfctl flush-all` → tabelas Layer7 vazias.

---

## Referências

- `docs/09-blocking/revisao-funcional-pre-producao-2026-07-29.md` (FP-003)
- `docs/04-package/validacao-lab.md` sec. 12 (two-client)
- `src/layer7d/enforce.c`, `src/layer7d/main.c`
