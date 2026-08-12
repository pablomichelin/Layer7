# Evidência — 30.16 / BG-122 / GA6.1–GA6.2

**UTC:** `20260812T023529Z`  
**Passo:** decisão de licença distribuída (mitiga A-02)

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | Eliminar o ponto único `if (ge && !s_lic.valid)` como único gate de enforce |
| Impacto | `layer7d` (`license_enforce_gate.c` + `main.c`); candidato `1.9.57`; **sem** release/`.254` |
| Risco | Médio (caminho crítico) — mitigado por fail-safe (discordância ⇒ monitor) e legibilidade |
| Teste | Unit N1/N2 + anti-forja A-02 (`test_license_enforce_gate.c`) **PASS** |
| Rollback | Reverter commit; em campo futuro, `.pkg` anterior |

## Desenho (caminho seguro)

1. **Gate A** — bit `valid` (canónico de `license_check`)
2. **Gate B** — recomputa a partir de `expiry`/`expired`/`grace`/`clock_suspect`
3. **Cruzamento** — `allows_enforce` exige A==B==1; discordância ⇒ 0
4. **Hot-paths** — `enforce_armed()` = `s_ge && allows_enforce` em DNS/flow/apply

**Não feito:** ofuscação, license-server, deploy, GitHub Release, `.254`, MITM/IPv6, passos `30.17+`.

## Saídas

- `unit-enforce-gate.txt` — RESULT: PASS
- `main-gate-sites.txt` — sítios de decisão em `main.c`
