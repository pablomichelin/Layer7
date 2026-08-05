# GV4 IPv6 appliance — `1.9.4` + pass inet6 LAN

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T113500Z-gv4-ipv6-1.9.4` |
| Data | 2026-08-05 |
| Versão | `pfSense-pkg-layer7-1.9.4` |
| Appliance | `192.168.100.254` |
| Cliente A | `192.168.100.234` / `2804:6c4:11d:cc00::100b` |
| Cliente B | `192.168.100.235` / `2804:6c4:11d:cc00::100a` |

## Veredicto

| Gate | Resultado |
|------|-----------|
| Lab `pass inet6` LAN | **PASS** — regra user `Layer7 lab GV4 allow IPv6 LAN` (tracker `1785929863`); A/B `google6=200` |
| GV4.1 block app cliente v6 | **PARCIAL** — PF scoped bloqueia quando IPv6 está em `pdst`; **aprendizagem automática daemon não populou `pdst`** nesta corrida |
| GV4.2 `pdst` com IPv6 | **PASS** (`pfctl -T add` AAAA YouTube; tabela mostra GUA v6) |
| GV4.3 two-client v6 | **PASS** (com `pdst` manual): A YouTube v6 timeout; B YouTube v6 200; A Google v6 200 |
| GV4.4 restore | **PASS** — `layer7.json` produção restaurado (`legacy_global`, 4 políticas) |
| GV4.5 especiais | **PASS** — sem `::1`/`ff0x` em tabelas block; `fe80::/10` só em `layer7_localnets` (intencional) |

**Produção enforce `1.9.0` inalterada.** Regra LAN IPv6 de lab **mantida** (GO humano).

## Evidência chave (two-client após `pdst` manual)

```text
A_yt6_* = ERR/timeout
A_google6 = 200
B_yt6_* = 200
```

Regras PF emitidas:

```text
block drop quick inet6 from 2804:6c4:11d:cc00::100b to <layer7_pdst_1> ...
block drop quick inet6 from 2804:6c4:11d:cc00:250:56ff:fe9d:252c to <layer7_pdst_1> ...
```

## Achado A3 — MÉDIO — aprendizagem IPv6 → `pdst` não disparou

Com `force_dns=false` + SNI on, 12 curls IPv6 YouTube no A classificaram pouco
(`cap_classified_v6` baixo pós-restart) e **`dst_add_ok=0` / `pdst` vazio**.

Mitigação comprovada: popular `layer7_pdst_N` com AAAA (manual / futuro DNS
path v6). Ligação com V5 (DNS `rdr inet6` / inspeção AAAA) e/ou robustez
nDPI/SNI em v6.

**Não** é falha da regra PF scoped inet6.

## Rollback

1. Layer7 config: restaurado de `/tmp/layer7.json.pre-gv4-enforce`.
2. Regra LAN IPv6 (se remover):

```php
// remover filter rule descr == "Layer7 lab GV4 allow IPv6 LAN"
// write_config + filter_configure
```

## Ficheiros

| Ficheiro | Conteúdo |
|----------|----------|
| `00-add-pass-inet6.txt` | criação regra |
| `01-verify-pass-inet6.txt` | PF rule presente |
| `02-client-v6-egress.txt` | ping6/curl6 baseline |
| `03-localnets-resync.txt` | `layer7_localnets` |
| `04-gv4-setup.txt` | política scoped |
| `05-two-client-v6-traffic.txt` | 1.ª corrida (sem pdst auto) |
| `06-pdst-stats.txt` | stats vazias |
| `07-diag-daemon.txt` | diagnose |
| `08-manual-pdst.txt` | GV4.1/4.2/4.3 PASS com pdst manual |
| `09-restore-prod.txt` | restore |
| `10-post-restore-v6.txt` | egress após restore |
