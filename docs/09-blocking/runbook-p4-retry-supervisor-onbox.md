# Runbook — P4.1 supervisor on-box + retry P4 (sem activação neste bloco)

**Estado:** **P4 soak retry2 CLOSED PASS** `224009Z` (`1.9.59` MITM **OFF**); P4.1 live; P4.2 harness. **Sem** MITM permanente.  
**Causa P4 FAIL/ABORT:** supervisor remoto/failsafe **não armado** (aprovação Skip); P3 auto-expiry sozinho foi considerado insuficiente como failsafe operacional.  
**Evidência P4:** [`../tests/evidence/20260809T234042Z-p4-soak-254/`](../tests/evidence/20260809T234042Z-p4-soak-254/).  
**Mapa:** [`mapa-prontidao-mitm-piloto-2026-08-09.md`](mapa-prontidao-mitm-piloto-2026-08-09.md).  
**Arranque:** [`../00-overview/START-HERE-identity-mitm.md`](../00-overview/START-HERE-identity-mitm.md).

---

## Declaração

| Campo | Valor |
|-------|--------|
| **Objectivo** | Failsafe operacional **no appliance** (cron 1 min) para expire/cleanup sem GUI nem Mac; soak futuro sem Skip |
| **Impacto** | Package/GUI control-plane; **não** alarga rdr; default MITM OFF |
| **Risco** | Baixo — tick é no-op com MITM OFF; cinto impede OFF→ON |
| **Teste** | `test_mitm_config.php` + `test_mitm_regress.php` PASS (local) |
| **Rollback** | Pacote `1.9.58`; cron removido no deinstall |

**Este documento não autoriza** activar MITM em `.254` / `.234` / `.235` nem piloto externo.

---

## O que P4.1 entrega

1. Cron `* * * * *` → `layer7-mitm-window-tick.php` (armado no `pkg-install`).  
2. Tick chama `layer7_mitm_lifecycle_tick` (expire + teardown).  
3. Stamp `/var/run/layer7/mitm.window-supervisor` (armado se fresco ≤180 s).  
4. GUI MITM mostra **Supervisor on-box (P4.1)** armado/stale.  
5. **Nunca** liga `mitm.enabled`.

P3 (`deadline_unix` / fail-closed em `mitm_effective`) **mantém-se**. P4.1 só garante o cleanup periódico **sem** depender de abrir a GUI ou de um watchdog no portátil.

---

## Gate para retry P4 (lab)

Retry de soak **só** com **GO lab explícito** (não é este bloco) **e**:

| # | Pré-condição | NO-GO se falhar |
|---|--------------|-----------------|
| 1 | Pacote ≥ `1.9.59` com cron armado | Stamp ausente/stale após 3 min |
| 2 | GUI: supervisor **armado** | Skip / “depois” |
| 3 | Watchdog Mac **auto-arm** (sem prompt Skip) | Qualquer Skip |
| 4 | Escopo src∧dst∧SNI lab (nunca `from any`) | Dest aberto / `.234`/`.235` |
| 5 | MITM actualmente OFF; rollback script pronto | Resíduo ON |

Watchdog: o script de soak deve arrancar `p4-watchdog.sh` **no mesmo passo** que activa a janela — sem pergunta humana. Se o watchdog não arrancar ⇒ **ABORT imediato** (não deixar MITM ON).

---

## P5

**Proibido** até ficha de site nomeada (cliente/responsáveis/src/dst/SNI/janela/saída) + P4 retry PASS.  
P4.1 **não** substitui a ficha.

---

## Resultado do P4 retry `170000Z`

| Campo | Valor |
|-------|--------|
| Veredicto | **CLOSED FAIL** (`2026-08-13T20:12:00Z`) |
| Causa | `health_ssh_fail` sample=14 — `AUTH_FAIL no_key_no_SSHPASS_no_passfile` (orquestrador, não motor TLS) |
| Rollback no fecho | incompleto (`rollback_clean=0`) |
| Pós-fail `223009Z` | MITM OFF; P4.1 cron live; residual `ca.present=true` sem ficheiros |
| Evidência soak | [`../tests/evidence/20260813T170000Z-p4-retry-254/`](../tests/evidence/20260813T170000Z-p4-retry-254/) |
| Evidência verify | [`../tests/evidence/20260813T223009Z-p4-postfail-verify-254/`](../tests/evidence/20260813T223009Z-p4-postfail-verify-254/) |

**Novo soak** exige GO lab **e** o harness [`../../tests/harness/mitm-p4-soak/`](../../tests/harness/mitm-p4-soak/) (probe `-T`). **Não** activar MITM neste bloco.

Diagnóstico: [`diagnostico-p4-retry-health-ssh-fail-20260813.md`](diagnostico-p4-retry-health-ssh-fail-20260813.md).
