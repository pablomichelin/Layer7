# Evidência Onda B — G5 completo (G5.1–G5.7 PASS)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T232800Z-ondaB-g5-full-PASS` |
| Plano | passos **3.1–3.2** — Onda B fechada |
| Appliance | `192.168.100.254` |
| Pacote | `1.8.11_66` |
| Cliente A | `192.168.100.234` |
| Cliente B | `192.168.100.235` |
| Veredicto | **PASS** — rollback aplicado |

## Resultados por gate

| Gate | Critério | Resultado |
|------|----------|-----------|
| G5.1 | scoped_hybrid + enforce | PASS (`_66` pfnearly) |
| G5.2 | A block, B allow (IP `142.251.155.4`) | A=`000`, B=`200`, PF 4 packets |
| G5.3 | App não quarentena total (FP-002) | `psrc_0` vazia; google HTTP 200 |
| G5.4 | `quarantine_origin` só A | regra `layer7:psrc:g5-quarantine-a` + pdst scoped |
| G5.5 | State kill (FP-003) | `pfctl -k` → estados `234→YT` 0 após kill |
| G5.6 | Allow vs blacklist (FP-017) | `blsrc` + `L7ALLOW`; sem `pass quick` |
| G5.7 | `smoke-enforcement-scoped.sh` | ALL PASSED (two-client via orquestrador Mac) |

## Ordem rules.debug (pfnearly)

- `layer7:allow:dst` linha **379**
- `layer7:pdst:g5-yt-block-a` linha **390**
- `pass LAN any` linha **~633** (após pfnearly)

## Rollback

- Restaurado `/tmp/layer7.json.pre-g5-full`
- `/etc/rc.filter_configure` + `layer7d restart`
- Estado final: `mode=monitor`
