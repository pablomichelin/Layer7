# Estratégia de Atualização nDPI

## Contexto

O Layer7 usa o **nDPI** (ntop Deep Packet Inspection) como engine de classificação de tráfego. Ao contrário de listas de URLs/domínios (como as do SquidGuard), o nDPI tem dois componentes de detecção:

| Componente | Tipo | Atualização |
|---|---|---|
| **Dissectors de protocolo** | Código C compilado em `libndpi.so` | Requer recompilação |
| **Custom protocols file** | Ficheiro de texto (`protos.txt`) | Runtime, sem recompilação |

## O que cada componente detecta

### Dissectors compilados (libndpi.so)
- Reconhecimento por payload (deep packet inspection real)
- ~350 protocolos: BitTorrent, YouTube, Netflix, TLS/QUIC fingerprinting, etc.
- Atualizado a cada release do nDPI (~2-4x/ano)
- **Para atualizar: recompilar no builder FreeBSD**

### Custom protocols file (protos.txt)
- Regras por porta: `tcp:8080@HTTP`
- Regras por host/domínio: `host:"api.exemplo.com"@CustomApp`
- Regras por IP: `ip:1.2.3.4@CustomService`
- Filtros nBPF: `nbpf:"host 10.0.0.1 and port 443"@SpecialTraffic`
- **Para atualizar: editar ficheiro e recarregar daemon (SIGHUP)**

## Comparação com SquidGuard

| Aspecto | SquidGuard | Layer7 (nDPI) |
|---|---|---|
| Base de detecção | Listas de URLs/domínios | DPI de payload + regras customizadas |
| Formato | Texto (URLs por linha) | Código C + texto (protos.txt) |
| Atualização online | `squidGuard -C all` | Recompilar libndpi (core) ou editar protos.txt (custom) |
| Frequência típica | Diária (listas blacklist) | Trimestral (releases nDPI) + ad-hoc (protos.txt) |
| O que cobre | URLs/domínios conhecidos | Protocolos por comportamento de rede |

## Cenário: múltiplos firewalls (fleet)

Com dezenas de firewalls, a estratégia muda:

| Operação | Frequência | O que fazer |
|---|---|---|
| Bloquear novo domínio/IP/porta | Frequente | Editar `protos.txt` → `fleet-protos-sync.sh` (sem recompilação) |
| Atualizar core nDPI | ~2-4x/ano | Compilar 1x no builder → `fleet-update.sh` para todos |

**Princípio: compilar UMA VEZ, distribuir para N firewalls.**

## Fluxo 1: Custom protocols (sem recompilação — uso diário)

O ficheiro `/usr/local/etc/layer7-protos.txt` aceita regras por host, porta, IP e filtros nBPF.
O daemon recarrega estas regras ao receber SIGHUP, sem reiniciar.

### Formato

```
# Bloquear domínios (match por SNI/DNS/HTTP Host)
host:"torrent-tracker.com"@BitTorrent
host:"vpn-provider.com"@VPN
host:"app-interna.empresa.local"@CustomApp

# Portas específicas
tcp:9090@Prometheus
udp:51820@WireGuard

# IPs de serviços conhecidos
ip:203.0.113.50@PartnerAPI

# Filtros nBPF avançados
nbpf:"host 10.0.0.1 and port 443"@SpecialTraffic
```

### Distribuir para 52 firewalls (um comando)

```sh
# Editar o ficheiro master localmente
vim layer7-protos.txt

# Sincronizar para todos os firewalls + SIGHUP
./scripts/release/fleet-protos-sync.sh -i firewalls.txt -f layer7-protos.txt
```

O script copia o ficheiro via SCP e envia SIGHUP ao daemon em cada firewall.
Não reinicia o serviço. Não requer pacote novo. Efeito imediato.

### Um firewall só

```sh
# Editar diretamente no pfSense
vi /usr/local/etc/layer7-protos.txt
kill -HUP $(pgrep layer7d)
```

## Fluxo 2: Atualizar core nDPI (recompilação — trimestral)

Quando o nDPI lança versão nova com protocolos adicionais:

```sh
# 1. No builder (UMA VEZ):
./scripts/release/update-ndpi.sh
# Resultado: novo .pkg pronto

# 2. Distribuir para todos os firewalls:
./scripts/release/fleet-update.sh -i firewalls.txt -p pfSense-pkg-layer7-0.1.0.pkg --parallel 4
```

O `fleet-update.sh` faz para cada firewall:
1. Copia `.pkg` via SCP
2. Para daemon → instala → inicia
3. Verifica versão e PID
4. Reporta sucesso/falha

Com `--parallel 4`, atualiza 4 firewalls em simultâneo.

### Ficheiro de inventário (`firewalls.txt`)

```
192.168.0.195   # escritório principal
10.0.1.1        # filial SP
10.0.2.1        # filial RJ
10.0.3.1        # filial BH
# linhas com # são ignoradas
```

## Comparação: o que requer recompilação vs. não

| Ação | Recompilação | Script | Efeito |
|---|---|---|---|
| Bloquear `torrent-tracker.com` | Não | `fleet-protos-sync.sh` | Imediato (SIGHUP) |
| Adicionar regra por porta | Não | `fleet-protos-sync.sh` | Imediato (SIGHUP) |
| Categorizar IP de parceiro | Não | `fleet-protos-sync.sh` | Imediato (SIGHUP) |
| nDPI reconhecer novo protocolo | Sim (1x) | `fleet-update.sh` | Após reinstalar pkg |
| Alterar lógica do daemon | Sim (1x) | `fleet-update.sh` | Após reinstalar pkg |

## Riscos e mitigações

| Risco | Mitigação |
|---|---|
| nDPI muda API entre versões | Testar no builder antes de deploy; manter versão pinada |
| Link do repo nDPI muda | nDPI é mantido pela ntop.org (estável desde 2012); repo em github.com/ntop/nDPI |
| Nova versão quebra compatibilidade | Compilação condicional (`HAVE_NDPI`); fallback sem nDPI funciona |
| Custom protos.txt com erro | Daemon loga warning e ignora regra inválida |
| SSH falha para um firewall | `fleet-update.sh` loga falha e continua os demais; verificar logs |

## Cadência recomendada

- **protos.txt**: conforme necessidade (bloquear novo domínio, novo parceiro)
- **libndpi**: a cada release major do nDPI (~trimestral, verificar github.com/ntop/nDPI/releases)
- **Verificar**: `layer7d -V` mostra versão; SIGUSR1 mostra `cap_classified`

## Roadmap pós-V1

| Fase | Funcionalidade |
|---|---|
| V1 | `fleet-protos-sync.sh` + `fleet-update.sh` para N firewalls |
| V1.1 | GUI no pfSense para editar `protos.txt` localmente |
| V2 | Repositório pkg privado (FreeBSD `pkg update && pkg upgrade`) |
| V2+ | Console central para gestão de protos.txt e políticas multi-firewall |
