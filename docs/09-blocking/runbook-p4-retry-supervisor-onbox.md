# Runbook — P4.1 supervisor on-box + retry P4 (sem activação neste bloco)

**Estado:** **P4.1 código local** `2026-08-13` — candidato `1.9.59`; **sem** publish; **sem** activar MITM.  
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
