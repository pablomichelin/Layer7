# Evidência P3 — janela MITM failsafe + visibilidade (`1.9.47`)

| Campo | Valor |
|-------|--------|
| Objectivo | Provar P3.1–P3.8 no control-plane (builder) sem activar MITM em `.254` |
| Impacto | Pacote `1.9.47`; defaults OFF |
| Risco | Baixo (harness `LAYER7_TEST_ROOT`) |
| Teste | `test_mitm_config.php` + `test_mitm_regress.php` no builder FreeBSD 15 |
| Rollback | Pacote `1.9.46` |

## Resultado

```text
PASS test_mitm_config.php
PASS package/pfSense-pkg-layer7/tests/test_mitm_regress.php
```

| ID | Critério | Prova |
|----|----------|-------|
| P3.1 | max_window + deadline | schema `mitm.window.*`; arm em activate |
| P3.2 | auto-disable fiável | `expire_if_needed` → `enabled=OFF` + cleanup |
| P3.3 | GUI/diag escopo+tempo | `layer7_mitm_window_status` + GUI MITM |
| P3.4 | audit metadados | `/var/log/layer7-mitm-audit.log` JSON; `payload_tls:false` |
| P3.5 | suite builder | PASS (este run) |
| P3.6 | S8 OFF pós-timeout | gate/flag/rdr limpos após expire |
| P3.7 | docs + PORTVERSION | `1.9.47` |
| P3.8 | sem `from any` | regress `rdr_line_ok` / snippet |

**Não** activado MITM nem bloqueio em `.254`/`.234`/`.235` neste bloco.
