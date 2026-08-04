# Evidência Onda B — G5 two-client (PASS)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T224500Z-ondaB-g5-two-client-PASS` |
| Plano | passo **3.1** — G5 two-client (reteste) |
| Appliance | `192.168.100.254` |
| Pacote | `1.8.11_66` (`SHA256=f471b47e3448fdf2a60c45a43cb20330e140a7e04654d90ec1f3d07104b135a0`) |
| Cliente A | `192.168.100.234` (`server`) |
| Cliente B | `192.168.100.235` (`zpro-aimirim`) |
| Veredicto | **PASS** — rollback aplicado |

## Correcção aplicada

Hook `discover_pkg_rules("pfearly")` em `layer7_generate_rules()` — blocks Layer7
**antes** do `pass in quick on $LAN inet from any to any` (linha ~633 vs ~388 em
`/tmp/rules.debug` após `/etc/rc.filter_configure`).

## O que foi testado

1. Config temporária: `scoped_hybrid` + `mode=enforce` + política block YouTube só para `192.168.100.234`
2. `filter_configure` via `/etc/rc.filter_configure`
3. Tabela `layer7_pdst_0` populada com IPs YouTube (`142.251.150.4`, `.152.4`, `.154.4`, `.155.4`)
4. `curl -4 https://www.youtube.com` com `--resolve` para IP na tabela

## Resultado

| Teste | Esperado | Observado |
|-------|----------|-----------|
| A → IP em `layer7_pdst_0` | bloqueado (não HTTP 200) | **`000`** (conexão falhou) ✓ |
| B → mesmo IP | HTTP 2xx | **HTTP 200** ✓ |
| Regra `layer7:pdst:g5-yt-block` antes pass LAN | sim | linha **388** vs pass LAN **633** ✓ |
| Contador PF na regra pdst | packets > 0 | **12 packets** (720 bytes) ✓ |
| `layer7_generate_rules("filter")` em enforce | só tabelas | sem `block drop` ✓ |

Nota: `curl` normal (sem `--resolve`) em A pode ainda devolver 200 se o destino
resolver para IP fora da tabela — comportamento esperado até o daemon popular
`layer7_pdst_0` via DNS/nDPI em produção.

## Rollback

- Restaurado `/tmp/layer7.json.pre-g5-retest`
- `/etc/rc.filter_configure` + `layer7d restart`
- Estado final: `enabled=true`, `mode=monitor`, `legacy_global`
