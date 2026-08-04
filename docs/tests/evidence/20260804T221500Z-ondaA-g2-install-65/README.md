# Evidência Onda A — G2 install passivo `_65`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T221500Z-ondaA-g2-install-65` |
| Plano | passo **2.1** — Onda A Gate B1 G2 |
| Appliance | `192.168.100.254` (produção Systemup; backup Veeam diário) |
| Upgrade | `_62` → `_65` (`IGNORE_OSVERSION=yes`) |
| Estado pós-install | `enabled=false`, `mode=monitor`, `legacy_global` |
| G2 | **PASS** (G2.1–G2.5) |

## Ficheiros

| Ficheiro | Descrição |
|----------|-----------|
| `g2-checks.txt` | pfctl, versão, serviço |
| `diagnose-post-g2.txt` | Diagnose completo pós-upgrade |

## Notas

- Pacote compilado FB15 instalado em appliance FB16 — aceite com `IGNORE_OSVERSION` (padrão lab).
- G3–G4 permanecem PENDENTE (passos 2.2–2.3).
