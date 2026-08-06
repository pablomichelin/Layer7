# Evidência — 20.11a baseline de perf (pré-código Identity)

- **Quando:** 2026-08-06 ~14:40–14:51 -03  
- **Passo:** IM3 / **20.11a**  
- **Host appliance:** `systemupfw.system.up` (`192.168.100.254`) — pfSense Plus 26.03.1  
- **Pacote medido:** `pfSense-pkg-layer7` **1.9.13** (sem runtime Identity/MITM)  
- **Pin documental enforce:** **1.9.8** (produção; rollback `1.9.0`)  
- **Licença appliance:** sem `/usr/local/etc/layer7.lic` → enforce desligado por desenho  
- **Builder:** `192.168.100.12` FreeBSD 15.0 — latência sintética `-e -n`  
- **Acção:** monitor temporário (~70 s) + tráfego lab A/B + **restore** `enabled=false`

## Veredicto

| Campo | Valor |
|-------|-------|
| 20.11a | **PASS** |
| ADR-0028 | **Aceito** (já; confirmado: 1 thread no hot path) |
| CPU / throughput | **Registados** (idle + monitor sob carga) |
| Latência classify→PF live | **Não medível** sem `.lic` — proxy builder documentado |
| Restore | **PASS** (`enabled=false`, `mode=monitor`) |
| Próximo | **20.12** — estruturas mapa Identity no daemon |

## Métricas canónicas (referência GI4–GI7 / ±10%)

### A) Idle (enabled=false)

| Métrica | Valor |
|---------|-------|
| `layer7d` %CPU | **0.0** |
| RSS | ~7.8 MB |
| Threads | **1** (`nanslp`) |
| `cap_pkts` / `cap_classified` | 0 |

### B) Monitor sob carga (~69 s; `enabled=true`, `mode=monitor`, `enforce_cfg=0`)

| Métrica | Valor |
|---------|-------|
| Δ `cap_pkts` | **211 072** (~**3 060 pkt/s**) |
| Δ `cap_classified` | **7 442** (~**108 flows/s**) |
| %CPU `layer7d` (amostras 5 s) | pico **1.2** → **0.0–0.5** |
| RSS sob captura | até ~40 MB (máx. RSS ~43 MB) |
| Threads | **1** (`bpf`) |
| `pf_add_ok` | **0** (sem licença / sem enforce) |

### C) Latência (proxy — builder, dry-run)

| Métrica | Valor |
|---------|-------|
| Método | `layer7d -n -e … BitTorrent` ×20 (sem pfctl, sem pcap) |
| p50 / p95 / max | **2.75 / 2.91 / 3.02 ms** |
| Nota | Proxy de decisão; **não** substitui amostra classify→PF com `.lic`+enforce |

## Checklist posicionamento §11

- [x] O1–O6 não violados (só medição; sem runtime Identity/MITM)  
- [x] U*/P*/H*/N* — N/A código GUI; honestidade documentada (sem `.lic`, pacote 1.9.13)  
- [x] Gate pré-requisito 20.11a / GI4.0 (baseline registada)  
- [x] START-HERE + plano §0 + CORTEX actualizados no mesmo bloco  
- [x] Sem overclaim NGFW  
- [x] Rollback: restore `layer7.json` pré-amostra (executado)

## Ficheiros

| Ficheiro | Conteúdo |
|----------|----------|
| `00-idle-snapshot.txt` | Host, pkg, vmstat, procstat, stats idle |
| `00-stats-idle.json` | Stats antes |
| `layer7.json.pre` | Config restaurada |
| `01-monitor-enable.txt` | Activação monitor + captura `vmx0` |
| `01-stats-pre.json` / `02-stats-post.json` | Deltas throughput |
| `02-cpu-under-load.txt` | Amostras %CPU |
| `03-restore.txt` | Confirmação restore |
| `04-builder-latency-proxy.txt` | Latência sintética builder |

## Limitações honestas

1. Pacote no lab = **1.9.13**, não reinstalação de **1.9.8** (ambos pré-Identity runtime).  
2. Sem `.lic` → sem medição live classificação→`pfctl`.  
3. Tráfego = clientes lab + fundo LAN (`192.168.95.x`); não é gerador saturado.
