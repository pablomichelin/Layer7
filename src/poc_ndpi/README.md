# PoC nDPI (`layer7_ndpi_poc`)

## Objetivo (Bloco 3)

Provar integração com **libnDPI**: ler PCAP, classificar fluxos IPv4 TCP/UDP, emitir **JSONL** (`confidence: detected`) por fluxo.

## Build (FreeBSD)

```sh
pkg install -y git autoconf automake libtool pkgconf libpcap gmake
chmod +x scripts/build/build-poc-freebsd.sh
./scripts/build/build-poc-freebsd.sh
```

O binário fica em `build/poc-ndpi/layer7_ndpi_poc`.  
Tag nDPI padrão: `NDPI_TAG=4.12` (sobrescreva se o branch não existir).

## Uso

```sh
./build/poc-ndpi/layer7_ndpi_poc captura.pcap > eventos.jsonl
```

Estatísticas vão para **stderr** (pacotes, fluxos, eventos, pkts/s).

## Formato do evento (v1)

| Campo | Significado |
|-------|-------------|
| `v` | Versão do schema (1) |
| `ts_ms` | Timestamp do pacote (epoch ms) |
| `confidence` | `detected` quando nDPI classificou |
| `master` / `app` | Protocolos nDPI |
| `category` | Categoria nDPI |
| `endpoint_*` | Tupla **canonizada** (menor IP/porta primeiro) |

## Limitações do PoC

- Só **IPv4**; IPv6 fora de escopo neste arquivo.
- DLT: Ethernet, LINUX_SLL, LINUX_SLL2.
- Até **20000** fluxos; depois ignora novos.
- **Confiança** binária neste PoC; refinamento no daemon V1.

## Próximo passo

Preencher `docs/poc/resultados-poc.template.md` após testar PCAP real.
