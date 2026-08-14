# Evidência 20.36 — soak `.254` alinhado a `1.9.63`

**Run ID:** `20260814T034904Z-20.36-soak-align-163-254`  
**Estado:** **PASS**  
**GO:** humano `2026-08-14` (fechar freeze do soak; MITM permanece OFF)

| Campo | Valor |
|-------|--------|
| Host | `systemupfw.system.up` (`192.168.100.254`) |
| Antes | `1.9.59` MITM OFF (P4 retry2 CLOSED PASS) |
| Depois | `1.9.63` (`Installed on: 2026-08-14 00:49:04 -03`) |
| SHA publicado | `f47b1dd82e7d99f8a1f8e6bbd2fe101c0ed33688b45cfcfbb356367db853c373` |
| Caminho | `install.sh` oficial `v1.9.63` |
| MITM | `enabled=false` `mitm_effective=false` `NO_MITM_RDR` `NO_8443` |
| tlsproxy | parado (default OFF) |
| layer7d | running (pid 59747); `mode=monitor` |
| P4.1 cron | live (`layer7-mitm-window-tick.php`) |
| `.234` / `.235` | intocados |
| Permanente | **NO-GO** |

**Objectivo:** acabar o freeze documental do soak (janela P4 já fechada) e
alinhar `.254` ao canal `latest` sem ligar intercept.

**Rollback:** reinstalar `1.9.59`
(`SHA256=64899e157d97adf659dfb265bff169801ffe6109f32d2f75377ca5963b2c34b9`).

**Artefacto:** [`01-post-state.txt`](01-post-state.txt)
