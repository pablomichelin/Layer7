# Evidência GV4 AAAA — candidato `1.9.6` (2026-08-05)

## Objectivo

Validar que DNS A/AAAA observadas alimentam `dns_cb` → enforce/`pdst`
**sem** `pfctl -T add` manual (gap A3 / GV4).

## Pacote

- Versão: `1.9.6`
- SHA256: `fc2d7fce624f8ac0afaf68ee9b2c0850b1e956767baeb16dfc11498517e3c6e4`
- Produção enforce: permanece `1.9.0` (não promover)

## Veredicto

**GV4 PARCIAL**

| Critério | Resultado |
|----------|-----------|
| Parser A+AAAA (`dns_observe.h` / unit) | PASS |
| `dns_resolved` AAAA em tráfego real (outros hosts) | PASS |
| A → `layer7_pdst_*` quando pcap vê resposta | PASS (pontual) |
| AAAA → `pdst` (YouTube GUA) | PASS pontual (ex.: `2800:3f0:4001:839::200e`) |
| Two-client YouTube v6 bloqueado A / livre B estável | **NÃO** fechado neste run |
| Aprendizagem via unbound **local** (`@192.168.100.254`) | **Intermitente** (pcap não vê resposta) |

## Limitação conhecida (não é V5)

Respostas DNS do unbound local neste appliance Plus podem ser invisíveis ao
pcap do `layer7d`. Sem observação, não há `dns_cb` nem `pdst`. Forwarded /
respostas WAN/LAN visíveis aprendem correctamente.

## Não iniciado

- V5 / `rdr inet6` / Opção B — exige GO humano explícito (ADR-0024).

## Artefactos

`01-setup.txt` … `07-results-restart.txt` (raw lab).
