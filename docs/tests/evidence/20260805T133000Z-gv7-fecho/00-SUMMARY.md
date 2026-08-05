# Evidência GV7 — fecho trilha IPv6 (12.13)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T133000Z-gv7-fecho` |
| Passo | **12.13** / Onda V6 |
| Pacote de referência | `pfSense-pkg-layer7-1.9.6` (já publicado; sem novo `.pkg`) |
| Data | `2026-08-05` |

## Veredicto

**GV7.1–GV7.3 PASS** — fecho documental da trilha (núcleo dual-stack).  
**GV7.4 PENDENTE** — promoção de produção enforce **não** autorizada neste bloco.

## Auditoria I1–I8 (GV7.1)

| # | Critério | Estado | Prova |
|---|----------|--------|-------|
| I1 | Limitação visível GUI/docs | **PASS** | ADR-0024 + banner Diagnostics |
| I2 | PF scoped `inet`/`inet6` | **PASS** | REV-018; GV1; `1.9.4`+ |
| I3 | Captura/classificação IPv6 | **PASS** | GV3 (`cap_*_v6`) |
| I4 | Policy/allowlist IPv6 | **PASS** | 12.6–12.8; GV4 |
| I5 | `pfctl`/kill states v6 | **PASS** | GV4 closed `1.9.6` |
| I6 | GUI validação IPv6 | **PASS** | 12.9; `test_ipv6_gui_inc` |
| I7 | DNS/block/VIP v6 decididos | **PASS (exclusão temp.)** | ADR-0024 Opção B `2026-08-05`; retomar 12.10–12.11 |
| I8 | Malha lab dual-stack | **PASS** | GV6 `20260805T130620Z-gv6-dualstack` |

## GV7.2 — Release

- Tag `v1.9.6` = `releases/latest` em `pablomichelin/Layer7`
- Assets: `.pkg` + `.sha256` (`SHA256=fc2d7fce…`)
- `MANUAL-INSTALL.md` comandos operacionais em `1.9.6` — sem mudança de URL neste fecho

## GV7.3 — CORTEX

Trilha marcada **FECHADA (núcleo dual-stack)** com ressalva V5/BG-083 a retomar.
**Não** afirmar «IPv6 completo comercial» até Opção A (12.10–12.11) ou emenda permanente.

## GV7.4 — Promoção enforce

| Campo | Valor |
|-------|-------|
| Produção enforce | **`1.9.0`** (inalterada) |
| Lab / `latest` | **`1.9.6`** |
| GO promoção `1.9.6` → enforce | **PENDENTE** (requer GO humano explícito) |

## Fora de âmbito deste fecho

- Implementação V5 (`rdr inet6` / block page / VIP DNS v6)
- Promoção de produção enforce
- Novo bump `PORTVERSION`
