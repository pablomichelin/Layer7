# Onda D — F4.1 (sec. 10a validacao-lab) — PASS

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T211600Z-ondaD-f4-10a-PASS` |
| Appliance | `192.168.100.254` |
| Pacote | `1.8.11_68` |

## Critérios 10a

| Check | Resultado |
|-------|-----------|
| `layer7d -V` | `1.8.11_68` |
| `service layer7d status` | running pid 50017 |
| `/var/run/layer7d.pid` | `-rw-r--r--`, conteúdo numérico |

Veredicto: **PASS**

Evidência: `10a-output.txt`
