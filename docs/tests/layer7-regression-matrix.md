# Matriz de regressão Layer7

**Data:** 2026-07-30 (actualizado pós multitask)  
**Versão repo:** `1.8.11_31`  
**Estado:** CANDIDATO INTERNO CONGELADO — complementa `docs/tests/test-matrix.md`

---

## 1. Mapa teste → componente → gate

| ID | Teste | Componente | Caminho | Local macOS | Builder FB15 | Appliance | Gate prod |
|----|-------|------------|---------|-------------|--------------|-----------|-----------|
| R-01 | `test_allowlist.c` | allowlist.c | Fase 1 / allow | **PASS** | PASS | Opcional | G5 allow |
| R-02 | `test_config_parse.c` | config_parse.c | A3/E0/logging/FP-015 | **PASS** | PASS | — | G4 |
| R-03 | `test_capture_flow_key.c` | capture + flow key | `_27`/`_30`/`_31` | **PASS** | PASS | — | G4.2 |
| R-04 | `test_log_store.c` | log_store.c | `_26` L1 | **PASS** | PASS | G4.4 | G4 |
| R-05 | `test_policy_decide.c` | policy.c | Caminho B E1 | **PASS** | PASS | — | G5 |
| R-06 | `test_enforce_scoped.c` | enforce.c | Caminho B E3 | **PASS** | PASS | — | G5 |
| R-07 | `test_bl_src_match.c` | bl_config.c | Blacklists UT1 | **PASS** | PASS | G5.6 | G5 |
| R-08 | `test_rc_pidfile.sh` | rc.d/layer7d | `_25` PID | **PASS** | PASS | G2.2 | G2 |
| R-09 | `test_scoped_pf_inc.php` | layer7.inc E2 | PF scoped | **SKIP** | PASS | G3 | G5 |
| R-10 | `test_interface_normalization.php` | layer7.inc | interfaces | **SKIP** | PASS | G4.1 | G4 |
| R-11 | `test_logging_reports.php` | GUI reports | `_26` | **SKIP** | PASS | G4.4 | G4 |
| R-12 | PHP lint `php -l` | GUI pacote | package | **SKIP** | PASS | — | G1 |
| R-13 | Shell lint `sh -n` | scripts | package | **PASS** | PASS | — | G1 |
| R-14 | `smoke-layer7d.sh` | build link | daemon | — | PASS | — | G1 |
| R-15 | `smoke-monitor-mode.sh` | monitor passivo | Fase 1 | — | — | Histórico PASS | G4 |
| R-16 | `smoke-caminho-a.sh` | Caminho A | A0–A4 | — | — | Histórico PASS | G4 |
| R-17 | `smoke-enforcement-scoped.sh` | Caminho B | two-client | — | — | **PENDENTE** | G5 |
| R-18 | `layer7d -t` | config parse runtime | daemon | — | PASS | G2 | G2 |
| R-19 | `pfctl -nf` snippet | PF syntax | `_29` | — | Sintético | **PENDENTE** | G3 |
| R-20 | F3 DR-05 scenarios | licença | F3 | — | — | **PENDENTE** | G6 |
| R-21 | `test_flush_coverage.sh` | flush PF lifecycle | B-002/B-003/B-004 | **PASS** | — | — | G3 uninstall |

---

## 2. Matriz por área funcional

### 2.1 Lifecycle daemon

| Cenário | Teste | `_24` | `_31` |
|---------|-------|-------|-------|
| PID sem newline | R-08 | FAIL | PASS |
| SIGHUP reload | Manual appliance | PENDENTE | PENDENTE |
| Stop flush PF | rc.d + G6.3 | Parcial doc | PENDENTE |
| Licença inválida flush | G6.1 | Código PASS (_24+) | PENDENTE |

### 2.2 Policy / enforcement

| Cenário | Teste | `_24` | `_31` |
|---------|-------|-------|-------|
| Precedência allow > blacklist | R-05 | Parcial | PASS local |
| App → pdst (não psrc) | R-05 | FAIL | PASS |
| Scoped PF rules emitidas | R-09 | Parcial | PASS builder |
| State kill | G5.5 | FAIL | Código PASS |

### 2.3 Captura / nDPI

| Cenário | Teste | `_24` | `_31` |
|---------|-------|-------|-------|
| Hash bidireccional | R-03 | FAIL | PASS |
| Probe flow table | R-03 | FAIL | PASS |
| NDPI_STATE_CLASSIFIED | R-03 | FAIL | PASS |
| Interface real | R-10 | FAIL GUI save | PASS |

### 2.4 Blacklists / DNS

| Cenário | Teste | `_24` | `_31` |
|---------|-------|-------|-------|
| except_ips origem | R-07 | PASS | PASS |
| TTL cache enforce | Appliance | PENDENTE | PENDENTE |
| QNAME CNAME | FP-007 | FAIL | PASS código |

### 2.5 Segurança / licença

| Cenário | Teste | `_24` | `_31` |
|---------|-------|-------|-------|
| CIDR /0 allowlist | R-01 | PASS (_24 fix) | PASS |
| Licença flush | REV-002 | PASS | PASS |
| Trust chain .pkg | BG-028 | N/A | N/A |

---

## 3. Cobertura vs backlog

| Backlog | Regressão associada | Cobertura local | Gap |
|---------|---------------------|-----------------|-----|
| BG-045–048 | R-05, R-06, R-09 | Alta C/PHP builder | Appliance |
| BG-053 | R-08, R-10 | Alta | Appliance capture |
| BG-054 | R-04, R-11 | Alta local | Appliance disco |
| BG-055 | R-03, R-05, G5.5 | Alta local | State kill físico |
| BG-056 | R-09, G5.6 | Builder | Appliance |
| BG-057 | R-19 | Sintético | Ruleset completo |
| BG-058 | R-03 | Alta | Carga |
| BG-059 | R-03 | Alta | Tráfego real TLS |

---

## 4. Comando único local (macOS)

```sh
sh tests/run-local.sh
```

**Resultado 2026-07-30:** ALL LOCAL TESTS PASSED (PHP simulado SKIP — instalar PHP ou executar no builder). R-21 flush lifecycle PASS.

---

## 5. Próximas adições F5 (não implementadas nesta rodada)

- Integrar R-09..R-11 no CI macOS (dependência PHP).
- Matriz automatizada `_24` vs `_31` diff de comportamento (fixtures JSON).
- Gate artefacto: comparar SHA256 `.pkg` vs CORTEX a cada audit.
- Ver `matriz-unificada-rev-fp-aud.md` e `diagnostico-multitask-2026-07-30.md`.

---

## Referências

- `docs/tests/test-matrix.md`
- `docs/02-roadmap/f5-preparacao-malha.md`
- `tests/run-local.sh`
