# Validação de input — GUI Layer7 (pfSense)

Referência rápida do que o PHP valida antes de gravar `layer7.json`.

## Settings (`layer7_settings.php`)

| Campo | Regra |
|-------|--------|
| `mode` | `monitor` \| `enforce` (pedido gravado). O badge do painel/Diagnósticos mostra o modo **efectivo** (`enforce_mode` do daemon). Sem licença = **monitorizar**, mesmo com pedido `enforce` (BG-167). |
| `log_level` | `error` \| `warn` \| `info` \| `debug` |
| `syslog_remote` | checkbox |
| `syslog_remote_host` | Se remoto ativo: não vazio; **IPv4** ou **hostname** (A–Z, a–z, 0–9, `.`, `-`; 1–255 chars; sem `..`) |
| `syslog_remote_port` | 1–65535 |
| `debug_minutes` | 0–720 (clamp no servidor) |
| CSRF | Protecao nativa do pfSense WebGUI (`__csrf_magic`) |
| `interfaces_csv` | Até 8 tokens `^[a-zA-Z0-9_.]{1,32}$`; vazio → `[]` |

## Policies (`layer7_policies.php`)

| Campo | Regra |
|-------|--------|
| `id` (novo) | `layer7_policy_id_valid` |
| Nome | ≤ 160 |
| Prioridade | 0–99999 |
| Ação | monitor / allow / block / tag |
| Apps / categorias CSV | `layer7_split_csv_tokens`; block/tag exigem ≥1 app ou categoria |
| `tag_table` (tag) | `layer7_pf_table_name_valid` |
| Editar | `id` imutável na GUI |

## Exceptions (`layer7_exceptions.php`)

| Campo | Regra |
|-------|--------|
| `id` (novo) | `layer7_policy_id_valid`; único |
| Host **ou** CIDR, não ambos | |
| Host | `layer7_ipv4_valid` |
| CIDR | `layer7_cidr_valid` |
| Prioridade | 0–99999 |
| Editar | `id` imutável na GUI |

## Lista VIP (`layer7_exceptions.php`, BG-071)

| Campo | Regra |
|-------|--------|
| Descrição | `layer7_vip_sanitize_label` (max 64; sem `"` ou `\`) |
| IP ou CIDR | `layer7_ipv4_valid` ou `layer7_cidr_valid`; único na lista |
| Limites | `LAYER7_VIP_MAX_HOSTS` (32) + `LAYER7_VIP_MAX_CIDRS` (16) — rejeição visível |
| Labels | `layer7.vip_meta.labels` mapa alvo → descrição; cleanup de órfãos no save |
| Export/import | Texto simples (uma linha `IP, nome`); JSON legado `layer7_vip_list` ainda aceite |
| Reservas DHCP | `layer7_dhcp_static_maps()` lê `dhcpd/<if>/staticmap` (+ DHCPv6); colunas por interface + filtro; picker na GUI; não isenta automaticamente |
| SSOT | Excepção canónica `vip-isentos` (sem chaves novas dentro do objecto) |
| Isenção DNS (BG-073) | `layer7_vip_dns_sync()` em resync; view Unbound ou fallback rdr; modo visível via `layer7_vip_dns_mode_get()` |

**Validação lab (Bloco E):** cenário «director isento de tudo» — sec. **20** em
[`validacao-lab.md`](../04-package/validacao-lab.md) (pacote lab **`>= 1.8.11_61`**
recomendado; **`>= 1.8.11_60`** minimo; enforce + block page + blacklist UT1 +
two-client + gate **20.4** `filter_configure` + ADR-0020).

Helpers em **`/usr/local/pkg/layer7.inc`**.

## Leitura só (`layer7_status.php`)

| Dado | Origem |
|------|--------|
| Versão binário | `layer7_daemon_version()` → `layer7d -V` |
| Parse JSON | `layer7d -t -c …` |

## Copy de operador (MITM / Identity / Definições)

A GUI **não** mostra ADRs, IDs de passo (`20.8`…), paths de `docs/`,
checklist de lab nem códigos internos (`N3`, `30.14`). Essas decisões
ficam na documentação do repositório. O operador vê: se o add-on está
na licença, o que o produto faz sem ele, e a quem contactar.
Gate: `php tests/functional/test_gui_operator_copy.php`.
