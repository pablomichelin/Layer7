# Evidência S8 — teste real two-client MITM OFF (`1.9.38`)

| Campo | Valor |
|-------|--------|
| `run_id` | `20260809T022400Z-s8-real-two-client-1.9.38` |
| Tipo | **Teste real** (não simulado) |
| Appliance | `192.168.100.254` — `layer7d` **1.9.38** |
| Cliente A | `192.168.100.234` (`server`) |
| Cliente B | `192.168.100.235` (`zpro-aimirim`) |
| Orquestração | Mac → SSH `root@234`/`root@235` (chave) + `root@254` |
| Mutação | **Nenhuma** (só leitura + curls HTTP(S)) |
| Runbook | [`../../../09-blocking/runbook-s1-s8-mitm-pre-runtime.md`](../../../09-blocking/runbook-s1-s8-mitm-pre-runtime.md) |
| Topologia | [`../../../08-lab/lab-topology.md`](../../../08-lab/lab-topology.md) |

## Resultados

| Check | A `.234` | B `.235` | Appliance |
|-------|----------|----------|-----------|
| `http://example.com` | **200** | **200** | — |
| `https://example.com` | **200** | **200** | — |
| `https://www.youtube.com/` | **200** | **200** | — |
| DNS YouTube | IPs Google reais (sem sinkhole) | idem | — |
| `mitm_effective` | — | — | **false** |
| `mitm_runtime_available` | — | — | **false** |
| `mitm` config | — | — | **null** (OFF) |
| `layer7-tlsproxy` / Squid | — | — | **ausentes** |
| Block page ADR-0017 | **não exercitada** neste run (sem política sinkhole activa nestes clientes) | idem | blockpage files presentes; nginx `:80` |

## Veredicto

| Campo | Valor |
|-------|--------|
| Resultado | **PASS parcial (real)** |
| Cumpre | Caminho two-client sem MITM; effective/runtime false; sem intercept |
| Pendente S8 completo | Exercício ADR-0017 com **block+sinkhole scoped** (ex. só `.234`) + rollback |
| Autoriza `20.10`? | **NÃO** |

## Ficheiros

- `01-appliance.txt`
- `02-client-a-234.txt`
- `03-client-b-235.txt`

## Segurança

- Hosts de produção funcional — sem alteração de políticas neste bloco.
- Sem claim de intercept / `mitm_effective=true`.
