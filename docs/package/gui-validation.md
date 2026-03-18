# Validação de input — GUI Layer7 (pfSense)

Referência rápida do que o PHP valida antes de gravar `layer7.json`.

## Settings (`layer7_settings.php`)

| Campo | Regra |
|-------|--------|
| `mode` | `monitor` \| `enforce` |
| `log_level` | `error` \| `warn` \| `info` \| `debug` |
| `syslog_remote` | checkbox |
| `syslog_remote_host` | Se remoto ativo: não vazio; **IPv4** ou **hostname** (A–Z, a–z, 0–9, `.`, `-`; 1–255 chars; sem `..`) |
| `syslog_remote_port` | 1–65535 |
| `debug_minutes` | 0–720 (clamp no servidor) |
| CSRF | `form_token` |
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

Helpers em **`/usr/local/pkg/layer7.inc`**.

## Leitura só (`layer7_status.php`)

| Dado | Origem |
|------|--------|
| Versão binário | `layer7_daemon_version()` → `layer7d -V` |
| Parse JSON | `layer7d -t -c …` |
