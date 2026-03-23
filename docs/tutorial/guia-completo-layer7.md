# Guia Completo — Layer7 para pfSense CE

Este documento explica todas as funcionalidades do pacote Layer7 para pfSense CE:
como instalar, configurar, criar politicas, gerir excepcoes, monitorar e manter
o sistema em producao com multiplos firewalls.

---

## Indice

1. [O que e o Layer7](#1-o-que-e-o-layer7)
2. [Arquitectura geral](#2-arquitectura-geral)
3. [Instalacao](#3-instalacao)
4. [Navegacao na GUI](#4-navegacao-na-gui)
5. [Menu: Estado](#5-menu-estado)
6. [Menu: Definicoes](#6-menu-definicoes)
7. [Menu: Politicas](#7-menu-politicas)
8. [Menu: Excepcoes](#8-menu-excepcoes)
9. [Menu: Events](#9-menu-events)
10. [Menu: Diagnostics](#10-menu-diagnostics)
11. [Formato do JSON de configuracao](#11-formato-do-json-de-configuracao)
12. [Exemplos praticos de politicas](#12-exemplos-praticos-de-politicas)
13. [Linha de comando do daemon](#13-linha-de-comando-do-daemon)
14. [Sinais do daemon](#14-sinais-do-daemon)
15. [Protocolos customizados](#15-protocolos-customizados-protos-file)
16. [Gestao de frota (fleet)](#16-gestao-de-frota-fleet)
17. [Troubleshooting](#17-troubleshooting)
18. [Glossario](#18-glossario)

---

## 1. O que e o Layer7

O Layer7 e um pacote para pfSense CE que permite classificar trafego de rede
em tempo real usando a biblioteca **nDPI** (Deep Packet Inspection). Com ele
voce pode:

- **Identificar** aplicacoes e protocolos (BitTorrent, YouTube, TikTok, etc.)
- **Bloquear** trafego de aplicacoes especificas por interface
- **Monitorar** o que esta a passar na rede sem bloquear
- **Taggear** trafego para tabelas PF (packet filter) customizadas
- **Excepcionar** IPs ou sub-redes especificas das regras

O Layer7 nao faz proxy HTTP nem MITM TLS. Ele analisa os metadados dos pacotes
(headers, SNI, padroes de protocolo) para classificar os fluxos.

---

## 2. Arquitectura geral

```
  Trafego de rede
       |
       v
  +-----------+
  |   pcap    |   Captura pacotes nas interfaces selecionadas
  +-----------+
       |
       v
  +-----------+
  |   nDPI    |   Classifica cada fluxo (app + categoria)
  +-----------+
       |
       v
  +-----------+
  |  Policy   |   Avalia excepcoes -> politicas -> acao
  |  Engine   |   (allow / block / monitor / tag)
  +-----------+
       |
       v
  +-----------+
  |  pfctl    |   Adiciona IP a tabela PF (block ou tag)
  +-----------+
```

**Componentes:**

| Componente | Descricao |
|------------|-----------|
| `layer7d` | Daemon C que corre em background |
| `layer7.json` | Configuracao central (politicas, excepcoes, settings) |
| `layer7-protos.txt` | Protocolos customizados (opcional) |
| GUI (PHP) | 6 paginas no menu Services > Layer 7 do pfSense |
| `pfctl` | Ferramenta nativa do pfSense para tabelas PF |

---

## 3. Instalacao

### 3.1 Pre-requisitos

1. pfSense CE 2.7.x ou 2.8.x
2. Acesso SSH como root ao pfSense
3. O pacote `.pkg` compilado (disponivel no GitHub Releases)

### 3.2 Preparar tabelas PF

Antes de instalar, crie as tabelas PF no pfSense. Va a:

**Firewall > Rules > (interface)** e adicione:

```
# Em /etc/pf.conf ou via GUI:
table <layer7_block> persist
table <layer7_tagged> persist
block in quick on $if from <layer7_block>
```

Ou via shell:

```bash
pfctl -t layer7_block -T add 127.0.0.254    # cria tabela
pfctl -t layer7_block -T delete 127.0.0.254  # remove dummy
pfctl -t layer7_tagged -T add 127.0.0.254
pfctl -t layer7_tagged -T delete 127.0.0.254
```

### 3.3 Instalar o pacote

```bash
# Via fetch do GitHub Release:
fetch -o /tmp/layer7.pkg https://github.com/SEU_REPO/releases/download/v0.2.0/pfSense-pkg-layer7-0.2.0.pkg

# Instalar:
IGNORE_OSVERSION=yes pkg add -f /tmp/layer7.pkg

# Verificar:
layer7d -V
# Saida esperada: 0.2.0
```

### 3.4 Iniciar o servico

```bash
# Ativar no boot:
sysrc layer7d_enable=YES

# Iniciar agora:
service layer7d onestart

# Verificar que esta a correr:
service layer7d status
```

---

## 4. Navegacao na GUI

Apos a instalacao, o pacote aparece no pfSense em:

**Services > Layer 7**

Existem **6 separadores** (abas):

| Aba | Funcao |
|-----|--------|
| **Estado** | Visao geral do daemon (a correr? versao? config valida?) |
| **Definicoes** | Configuracoes globais (on/off, modo, interfaces, log) |
| **Politicas** | Criar, editar e remover regras de classificacao |
| **Excepcoes** | IPs e sub-redes que escapam as politicas |
| **Events** | Visualizar logs do daemon filtrados por tipo |
| **Diagnostics** | Ferramentas de diagnostico, tabelas PF, sinais ao daemon |

---

## 5. Menu: Estado

**Caminho:** Services > Layer 7 > Estado

Esta pagina mostra uma visao geral do sistema:

- **Daemon em execucao:** Sim/Nao (verifica o PID em `/var/run/layer7d.pid`)
- **Versao:** resultado de `layer7d -V`
- **Modo global:** monitor ou enforce (com badge colorido)
- **Interfaces configuradas:** lista das interfaces de captura
- **Politicas ativas:** quantas estao habilitadas e quantas sao de block
- **Excepcoes:** numero total
- **Validacao de config:** saida completa de `layer7d -t` (teste do JSON)

**Links rapidos:** Abrir definicoes, Ver politicas, Diagnostics, Events.

**Quando usar:** Para uma verificacao rapida de que tudo esta a funcionar.

---

## 6. Menu: Definicoes

**Caminho:** Services > Layer 7 > Definicoes

### Campos disponiveis

| Campo | Tipo | Descricao |
|-------|------|-----------|
| **Ativar pacote** | Checkbox | Liga/desliga o daemon. Quando desligado, o daemon fica em idle. |
| **Modo global** | Select | `monitor` = apenas observa e regista; `enforce` = executa acoes reais (block, tag) via pfctl. |
| **Nivel de log** | Select | `error`, `warn`, `info` ou `debug`. Define a verbosidade no syslog. |
| **Syslog remoto** | Checkbox | Duplica eventos por UDP (RFC 3164) para um coletor externo. |
| **Host syslog** | Texto | IPv4 ou hostname do coletor (ex: `192.168.1.50`). |
| **Porta UDP** | Numero | Porta do coletor syslog (default: 514). |
| **Janela debug (min)** | Numero | 0 = normal. 1-720 = eleva temporariamente para LOG_DEBUG apos cada reload. |
| **Interfaces de captura** | Checkboxes | Lista automatica das interfaces configuradas no pfSense. Selecione onde o Layer7 ira capturar trafego. Maximo 8. |

### Como funciona a selecao de interfaces

As interfaces sao lidas automaticamente do pfSense usando `get_configured_interface_list()`.
Cada checkbox mostra o nome amigavel e o device real:

```
[x] LAN (em0)
[ ] WAN (em1)
[x] WIFI (ath0)
[ ] ADMIN (igb2)
```

Ao gravar, os nomes sao convertidos para os devices reais (`em0`, `ath0`, etc.)
que o daemon usa para abrir a captura pcap.

### Comportamento ao gravar

- Grava todas as alteracoes no ficheiro `/usr/local/etc/layer7.json`
- Envia SIGHUP ao daemon (se estiver a correr) para recarregar
- Politicas e excepcoes existentes **nao sao afetadas**

---

## 7. Menu: Politicas

**Caminho:** Services > Layer 7 > Politicas

As politicas definem **o que fazer** quando o nDPI classifica um fluxo de trafego.

### 7.1 Tabela de politicas actuais

Mostra todas as politicas com:
- **Ativa:** checkbox para habilitar/desabilitar rapidamente
- **Prioridade:** numero maior = avaliada primeiro
- **Nome:** nome descritivo
- **Acao:** monitor, allow, block ou tag
- **Correspondencia:** resumo dos filtros (apps, categorias, interfaces, IPs)
- **ID:** identificador unico
- **Acoes:** botao Editar

Botao **"Guardar estado das politicas"** grava o estado on/off de cada politica.

### 7.2 Criar nova politica

| Campo | Tipo | Descricao |
|-------|------|-----------|
| **id** | Texto | Identificador unico (letras, numeros, `_`, `-`; max 80). Ex: `p-block-bt-wifi` |
| **Nome** | Texto | Nome descritivo (max 160). Ex: `Bloquear BitTorrent no WIFI` |
| **Prioridade** | Numero | 0-99999. Politicas com prioridade maior sao avaliadas primeiro. |
| **Acao** | Select | Ver tabela de acoes abaixo. |
| **Interfaces** | Checkboxes | Interfaces onde esta politica se aplica. Nenhuma = todas. |
| **IPs de origem** | Textarea | Um IPv4 por linha (max 16). Vazio = qualquer IP. |
| **CIDRs de origem** | Textarea | Um CIDR por linha (max 8). Ex: `192.168.10.0/24` |
| **Apps nDPI** | Multi-select com pesquisa | Selecione ate 12 aplicacoes da lista nDPI. |
| **Categorias nDPI** | Multi-select com pesquisa | Selecione ate 8 categorias. |
| **tag_table** | Texto | Nome da tabela PF (obrigatorio se acao = tag). |
| **Ativa** | Checkbox | Criar ja habilitada. |

### 7.3 Acoes disponiveis

| Acao | O que faz | pfctl? |
|------|-----------|--------|
| **monitor** | Apenas regista no syslog. Nao altera trafego. | Nao |
| **allow** | Permite o trafego explicitamente. | Nao |
| **block** | Adiciona o IP de origem a tabela `layer7_block` via pfctl. | Sim |
| **tag** | Adiciona o IP de origem a uma tabela PF customizada. | Sim |

> **Importante:** As acoes `block` e `tag` so executam pfctl quando o modo global e `enforce`.
> Em modo `monitor`, a decisao e registada no log mas nenhuma acao e tomada.

### 7.4 Lista de aplicacoes e categorias nDPI

A lista e preenchida automaticamente a partir do daemon (`layer7d --list-protos`).
Inclui ~350 aplicacoes detectaveis, entre elas:

- **Redes sociais:** Facebook, Instagram, TikTok, Twitter, Telegram, WhatsApp
- **Streaming:** YouTube, Netflix, Spotify, Twitch, Disney+
- **Downloads:** BitTorrent, eDonkey, Gnutella
- **Jogos:** Xbox, PlayStation, Steam
- **Produtividade:** Microsoft365, Teams, Zoom, Google Meet
- **VPN:** WireGuard, OpenVPN, Tailscale
- **E muitas outras...**

A lista tem um **campo de pesquisa** para filtrar rapidamente:

```
Pesquisar apps... [ BitTor ]
[x] BitTorrent
```

### 7.5 Logica de avaliacao

1. **Excepcoes** sao avaliadas primeiro (por prioridade decrescente)
2. Se nenhuma excepcao casar, **politicas** sao avaliadas (por prioridade decrescente)
3. Se nenhuma politica casar: modo enforce → `allow`; modo monitor → `monitor`

Para uma politica casar, **todos** os criterios definidos devem ser verdadeiros:
- Interface do fluxo esta na lista (ou lista vazia = todas)
- IP de origem casa com `src_hosts` ou `src_cidrs` (ou vazios = qualquer)
- App nDPI esta na lista (ou lista vazia = qualquer)
- Categoria nDPI esta na lista (ou lista vazia = qualquer)

### 7.6 Editar politica

Clique em **Editar** na tabela. Todos os campos sao editaveis excepto o `id`.
Para alterar o `id`, edite o ficheiro `/usr/local/etc/layer7.json` diretamente.

### 7.7 Remover politica

Selecione a politica no dropdown e clique **Remover**. Confirmacao e solicitada.

---

## 8. Menu: Excepcoes

**Caminho:** Services > Layer 7 > Excepcoes

Excepcoes sao avaliadas **antes** das politicas. Servem para proteger IPs ou
sub-redes criticas (gestao, servidores, administradores) das regras gerais.

### 8.1 Campos de uma excepcao

| Campo | Tipo | Descricao |
|-------|------|-----------|
| **id** | Texto | Identificador unico. Ex: `ex-mgmt-servers` |
| **Hosts (IPv4)** | Textarea | Um IP por linha (max 8). |
| **CIDRs** | Textarea | Um CIDR por linha (max 8). Ex: `192.168.0.0/24` |
| **Interfaces** | Checkboxes | Limitar a interfaces especificas (vazio = todas). |
| **Prioridade** | Numero | Maior = avaliada primeiro (default: 500). |
| **Acao** | Select | `allow` (default), `block`, `monitor`, `tag`. |
| **Ativa** | Checkbox | Habilitar/desabilitar. |

### 8.2 Quando usar excepcoes

- **IP do administrador:** para que nunca seja bloqueado
- **Servidores de gestao:** SNMP, monitoring, backup
- **Sub-rede de infraestrutura:** switches, APs, controladores
- **Maquinas de teste:** que precisam de acesso total durante troubleshooting

### 8.3 Compatibilidade retroactiva

Excepcoes criadas em versoes anteriores com campos `host` (singular) e `cidr`
(singular) continuam a funcionar. O daemon reconhece ambos os formatos.

**Limite:** 16 excepcoes no total.

---

## 9. Menu: Events

**Caminho:** Services > Layer 7 > Events

Mostra os eventos do daemon a partir do syslog (`/var/log/system.log`).

### Funcionalidades

- **Filtro de texto:** digite uma palavra-chave e clique Filtrar
- **Ultimos 100 eventos:** linhas do syslog com `layer7d`
- **Eventos de enforcement:** ultimos 30 com `enforce_action` ou `pfctl add`
- **Classificacoes nDPI:** ultimos 30 com `flow_decide`

### Exemplos de eventos que vera

```
layer7d: flow_decide: iface=em0 src=192.168.1.50 app=BitTorrent cat=Download action=block reason=policy_match policy=p-block-bt
layer7d: enforce_action: block src=192.168.1.50 table=layer7_block policy=p-block-bt
layer7d: SIGUSR1 stats: ver=0.2.0 policies=3 exceptions=1 pf_add_ok=5
```

---

## 10. Menu: Diagnostics

**Caminho:** Services > Layer 7 > Diagnostics

Pagina de diagnostico avancado com ferramentas operacionais.

### Informacoes apresentadas

- **Estado do servico:** a correr ou parado, PID
- **Versao:** do binario instalado
- **Modo:** monitor ou enforce
- **Interfaces:** configuradas para captura
- **Ficheiro de protocolos custom:** existe? quantas regras?
- **Tabela `layer7_block`:** quantos IPs, lista dos primeiros 20
- **Tabela `layer7_tagged`:** quantos IPs, lista dos primeiros 20
- **Ultimas 20 linhas** do log

### Botoes de acao

| Botao | O que faz |
|-------|-----------|
| **Obter estatisticas (SIGUSR1)** | Envia sinal SIGUSR1 ao daemon. Ele imprime no syslog um resumo com contadores de pacotes, fluxos, adds PF, etc. |
| **Recarregar config (SIGHUP)** | Envia SIGHUP ao daemon. Ele relê o `layer7.json`, recria as capturas pcap e aplica as novas politicas. |

### Comandos uteis sugeridos

A pagina lista comandos para usar via SSH:

```bash
service layer7d status          # estado
service layer7d onerestart      # reiniciar
kill -USR1 $(pgrep layer7d)     # estatisticas
kill -HUP $(pgrep layer7d)      # recarregar config
pfctl -t layer7_block -T show   # IPs bloqueados
pfctl -t layer7_tagged -T show  # IPs tagueados
pfctl -t layer7_block -T flush  # limpar tabela
```

---

## 11. Formato do JSON de configuracao

O ficheiro `/usr/local/etc/layer7.json` e a unica fonte de configuracao
do daemon. A GUI le e grava neste ficheiro.

### Estrutura completa

```json
{
  "layer7": {
    "enabled": true,
    "mode": "enforce",
    "log_level": "info",
    "syslog_remote": false,
    "syslog_remote_host": "",
    "syslog_remote_port": 514,
    "debug_minutes": 0,
    "protos_file": "/usr/local/etc/layer7-protos.txt",
    "interfaces": ["em0", "ath0"],
    "policies": [
      {
        "id": "p-block-bt-wifi",
        "name": "Bloquear BitTorrent no WIFI",
        "enabled": true,
        "action": "block",
        "priority": 100,
        "interfaces": ["ath0"],
        "match": {
          "ndpi_app": ["BitTorrent"],
          "src_cidrs": ["192.168.10.0/24"]
        }
      },
      {
        "id": "p-mon-all",
        "name": "Monitor geral",
        "enabled": true,
        "action": "monitor",
        "priority": 1,
        "match": {}
      }
    ],
    "exceptions": [
      {
        "id": "ex-admin",
        "enabled": true,
        "priority": 1000,
        "action": "allow",
        "hosts": ["192.168.1.1", "192.168.1.2"],
        "cidrs": ["10.0.0.0/24"],
        "interfaces": ["em0"]
      }
    ]
  }
}
```

### Campos globais

| Campo | Tipo | Default | Descricao |
|-------|------|---------|-----------|
| `enabled` | bool | `false` | Liga/desliga o daemon |
| `mode` | string | `"monitor"` | `monitor` ou `enforce` |
| `log_level` | string | `"info"` | `error`, `warn`, `info`, `debug` |
| `syslog_remote` | bool | `false` | Ativar envio syslog UDP |
| `syslog_remote_host` | string | `""` | IP ou hostname do coletor |
| `syslog_remote_port` | int | `514` | Porta UDP |
| `debug_minutes` | int | `0` | Janela de debug temporario |
| `protos_file` | string | auto | Caminho do ficheiro de protocolos custom |
| `interfaces` | array | `["lan"]` | Devices reais (ex: `em0`) |

### Campos de uma politica

| Campo | Obrigatorio | Descricao |
|-------|-------------|-----------|
| `id` | Sim | Identificador unico |
| `name` | Nao | Nome descritivo |
| `enabled` | Sim | `true`/`false` |
| `action` | Sim | `monitor`, `allow`, `block`, `tag` |
| `priority` | Sim | Numero (maior = primeiro) |
| `interfaces` | Nao | Array de devices. Vazio = todas. |
| `tag_table` | Se tag | Nome da tabela PF |
| `match.ndpi_app` | Nao | Array de nomes de app nDPI |
| `match.ndpi_category` | Nao | Array de categorias nDPI |
| `match.src_hosts` | Nao | Array de IPs v4 de origem |
| `match.src_cidrs` | Nao | Array de CIDRs de origem |

### Campos de uma excepcao

| Campo | Obrigatorio | Descricao |
|-------|-------------|-----------|
| `id` | Sim | Identificador unico |
| `enabled` | Sim | `true`/`false` |
| `priority` | Sim | Numero (maior = primeiro) |
| `action` | Sim | `allow`, `block`, `monitor`, `tag` |
| `hosts` | * | Array de IPs v4 |
| `cidrs` | * | Array de CIDRs |
| `interfaces` | Nao | Array de devices. Vazio = todas. |

(*) Pelo menos um host ou CIDR e obrigatorio.

---

## 12. Exemplos praticos de politicas

### Exemplo 1: Bloquear BitTorrent em toda a rede

```json
{
  "id": "p-block-bt",
  "name": "Bloquear BitTorrent",
  "enabled": true,
  "action": "block",
  "priority": 100,
  "match": {
    "ndpi_app": ["BitTorrent"]
  }
}
```

### Exemplo 2: Bloquear redes sociais apenas no WIFI

```json
{
  "id": "p-block-social-wifi",
  "name": "Sem redes sociais no WIFI",
  "enabled": true,
  "action": "block",
  "priority": 90,
  "interfaces": ["ath0"],
  "match": {
    "ndpi_app": ["Facebook", "Instagram", "TikTok", "Twitter"]
  }
}
```

### Exemplo 3: Bloquear YouTube so para alunos (por sub-rede)

```json
{
  "id": "p-block-yt-alunos",
  "name": "Sem YouTube para alunos",
  "enabled": true,
  "action": "block",
  "priority": 85,
  "interfaces": ["ath0"],
  "match": {
    "ndpi_app": ["YouTube"],
    "src_cidrs": ["192.168.10.0/24"]
  }
}
```

### Exemplo 4: Taggear trafego de streaming para analise

```json
{
  "id": "p-tag-streaming",
  "name": "Taggear streaming",
  "enabled": true,
  "action": "tag",
  "priority": 70,
  "tag_table": "layer7_streaming",
  "match": {
    "ndpi_category": ["Streaming"]
  }
}
```

### Exemplo 5: Bloquear VPN apenas para IPs especificos

```json
{
  "id": "p-block-vpn-users",
  "name": "Sem VPN para usuarios listados",
  "enabled": true,
  "action": "block",
  "priority": 80,
  "match": {
    "ndpi_app": ["OpenVPN", "WireGuard", "Tailscale"],
    "src_hosts": ["192.168.1.50", "192.168.1.51", "192.168.1.52"]
  }
}
```

### Exemplo 6: Excepcao para o administrador

```json
{
  "id": "ex-admin",
  "enabled": true,
  "priority": 1000,
  "action": "allow",
  "hosts": ["192.168.1.1"],
  "cidrs": ["10.0.0.0/24"]
}
```

> O administrador em `192.168.1.1` e toda a sub-rede `10.0.0.0/24` nunca serao
> bloqueados, independentemente das politicas.

### Exemplo 7: Excepcao por interface

```json
{
  "id": "ex-admin-iface",
  "enabled": true,
  "priority": 900,
  "action": "allow",
  "cidrs": ["172.16.0.0/16"],
  "interfaces": ["igb2"]
}
```

> Todo o trafego vindo da sub-rede `172.16.0.0/16` na interface `igb2` (ADMIN)
> e permitido sem avaliacao de politicas.

---

## 13. Linha de comando do daemon

O binario `layer7d` aceita as seguintes opcoes:

| Flag | Descricao |
|------|-----------|
| `-V` | Mostra a versao e sai |
| `-t` | Modo teste: le o JSON, valida e imprime um resumo detalhado com dry-run de politicas |
| `-c path` | Caminho do ficheiro de config (default: `/usr/local/etc/layer7.json`) |
| `-e IP APP [CAT]` | Modo enforce-once: avalia uma decisao para o IP/app dados e executa pfctl se aplicavel |
| `-n` | Com `-e`: nao executa pfctl (dry-run) |
| `--list-protos` | Imprime todos os protocolos e categorias nDPI em formato JSON |
| `-h` / `--help` | Mostra ajuda |

### Exemplos de uso

```bash
# Ver versao:
layer7d -V

# Testar configuracao:
layer7d -t -c /usr/local/etc/layer7.json

# Testar uma decisao sem executar pfctl:
layer7d -c /usr/local/etc/layer7.json -n -e 192.168.1.50 BitTorrent

# Testar com categoria:
layer7d -c /usr/local/etc/layer7.json -n -e 192.168.1.50 BitTorrent Download-FileTransfer-FileSharing

# Listar todos os protocolos nDPI:
layer7d --list-protos
# Saida: {"protocols":["FTP_CONTROL","HTTP","BitTorrent",...], "categories":["Web","Streaming",...]}
```

---

## 14. Sinais do daemon

| Sinal | Como enviar | O que faz |
|-------|-------------|-----------|
| **SIGHUP** | `kill -HUP $(pgrep layer7d)` | Relê o `layer7.json`, fecha e reabre capturas, aplica novas politicas |
| **SIGUSR1** | `kill -USR1 $(pgrep layer7d)` | Imprime estatisticas no syslog (pacotes, fluxos, adds PF, etc.) |
| **SIGTERM** | `kill $(pgrep layer7d)` | Encerra o daemon graciosamente |

### Exemplo de saida do SIGUSR1

```
layer7d[1234]: SIGUSR1 stats: ver=0.2.0 reload_ok=3 sighup=2 usr1=1
  loop_ticks=500 policies=5 exceptions=2 enforce_cfg=1 have_parse=1
  pf_add_ok=42 pf_add_fail=0 cap_pkts=15000 cap_classified=3200
  cap_expired=2800 captures=2
```

| Contador | Significado |
|----------|-------------|
| `reload_ok` | Reloads de config bem sucedidos |
| `policies` | Numero de politicas carregadas |
| `exceptions` | Numero de excepcoes carregadas |
| `enforce_cfg` | 1 se modo enforce, 0 se monitor |
| `pf_add_ok` | IPs adicionados a tabelas PF com sucesso |
| `pf_add_fail` | Falhas de pfctl |
| `cap_pkts` | Total de pacotes capturados |
| `cap_classified` | Fluxos classificados pelo nDPI |
| `cap_expired` | Fluxos expirados (inativos >120s) |
| `captures` | Numero de interfaces a capturar |

---

## 15. Protocolos customizados (protos file)

O nDPI vem com ~350 protocolos compilados. Para adicionar deteccoes extras
**sem recompilar**, edite o ficheiro `/usr/local/etc/layer7-protos.txt`.

### Formato

```
# Comentarios comecam com #

# Por dominio (SNI/DNS/HTTP Host):
host:"app-interna.empresa.local"@CustomApp
host:"vpn.provider.com"@VPN

# Por porta TCP/UDP:
tcp:9090@Prometheus
udp:51820@WireGuard

# Por IP:
ip:203.0.113.50@PartnerAPI

# Filtro nBPF:
nbpf:"host 10.0.0.1 and port 443"@SpecialTraffic
```

### Aplicar alteracoes

Apos editar o ficheiro, envie SIGHUP ao daemon:

```bash
kill -HUP $(pgrep layer7d)
```

Nao e necessario reiniciar o servico nem recompilar. As novas regras sao
carregadas em runtime.

### Verificar se foi carregado

Na pagina **Diagnostics**, verifique:
- "Ficheiro de protocolos custom: **Sim** (X regras)"

Ou envie SIGUSR1 e verifique no log.

---

## 16. Gestao de frota (fleet)

Para quem tem **multiplos firewalls** (ex: 52 unidades), existem dois scripts
para automatizar a distribuicao:

### 16.1 Ficheiro de inventario

Crie um ficheiro com um IP por linha:

```
# inventario.txt
# Matriz
192.168.0.1
# Filial Norte
10.10.1.1
# Filial Sul
10.10.2.1
# ... (ate 52 ou mais)
```

### 16.2 Atualizar protocolos custom (sem recompilacao)

Para atualizar as regras customizadas em **todos** os firewalls:

```bash
./scripts/release/fleet-protos-sync.sh \
  -i inventario.txt \
  -f /path/to/layer7-protos.txt
```

O que faz:
1. Copia o ficheiro `layer7-protos.txt` para cada firewall via SCP
2. Envia SIGHUP ao daemon em cada firewall
3. **Nao requer recompilacao**

Perfeito para: adicionar novos dominios, portas ou IPs ao protos file.

### 16.3 Atualizar o pacote completo (recompilacao)

Quando houver uma nova versao do pacote (ex: atualizacao core do nDPI):

```bash
./scripts/release/fleet-update.sh \
  -i inventario.txt \
  -p release/pfSense-pkg-layer7-0.2.0.pkg \
  --parallel 4
```

O que faz por firewall:
1. Copia o `.pkg` via SCP
2. Para o daemon
3. Instala o pacote (`pkg add -f`)
4. Reinicia o daemon
5. Verifica versao e PID

Opcoes:
- `--dry-run` — mostra o que faria sem executar
- `--parallel N` — atualiza N firewalls em paralelo
- `--user USER` — usuario SSH (default: root)

### 16.4 Cadencia recomendada

| Tipo de atualizacao | Frequencia | Recompilacao? | Script |
|---------------------|-----------|---------------|--------|
| Protocolos custom | Conforme necessidade | Nao | `fleet-protos-sync.sh` |
| Core nDPI | ~2-4x por ano | Sim (1x no builder) | `fleet-update.sh` |

### 16.5 Pre-requisito: SSH sem password

Configure autenticacao por chave SSH entre a maquina de gestao e todos os firewalls:

```bash
ssh-keygen -t ed25519
ssh-copy-id root@192.168.0.1
ssh-copy-id root@10.10.1.1
# ... para cada firewall
```

---

## 17. Troubleshooting

### O daemon nao inicia

```bash
# Verificar se o binario existe:
ls -la /usr/local/sbin/layer7d

# Verificar config:
layer7d -t -c /usr/local/etc/layer7.json

# Verificar se ja esta a correr:
pgrep layer7d

# Iniciar manualmente:
service layer7d onestart

# Ver logs:
grep layer7d /var/log/system.log | tail -20
```

### Politicas nao bloqueiam

1. Verifique que o modo e **enforce** (nao monitor)
2. Verifique que a politica esta **ativa** (enabled)
3. Verifique que as **tabelas PF existem**:
   ```bash
   pfctl -t layer7_block -T show
   ```
4. Verifique que ha uma **regra PF de block** usando a tabela
5. Envie SIGUSR1 e veja `pf_add_ok` vs `pf_add_fail`

### Excepcao nao esta a funcionar

1. Verifique que a excepcao esta **ativa**
2. Verifique que a **prioridade** e maior que a das politicas
3. Verifique o IP/CIDR esta correcto (IPv4 apenas)
4. Use `layer7d -e` para testar:
   ```bash
   layer7d -c /usr/local/etc/layer7.json -n -e 192.168.1.1 BitTorrent
   # Deve mostrar: action=allow reason=exception
   ```

### Lista de apps nDPI vazia na GUI

1. Verifique que o binario tem suporte nDPI:
   ```bash
   layer7d --list-protos | head -c 100
   ```
2. Se falhar, o pacote foi compilado sem nDPI
3. Limpe o cache: `rm /tmp/layer7-ndpi-protos.json`

### Daemon consome muita CPU

1. Reduza o numero de interfaces de captura
2. Verifique se a rede tem muito trafego broadcast/multicast
3. Use `debug_minutes: 0` (evite modo debug permanente)

---

## 18. Glossario

| Termo | Significado |
|-------|-------------|
| **nDPI** | Biblioteca open-source de Deep Packet Inspection. Classifica trafego por analise de padroes. |
| **DPI** | Deep Packet Inspection — inspecao profunda de pacotes para identificar aplicacoes. |
| **pfctl** | Ferramenta de linha de comando do PF (Packet Filter) do FreeBSD/pfSense. |
| **PF table** | Tabela de IPs no PF. Usada para block/tag dinamico. |
| **pcap** | Biblioteca de captura de pacotes (libpcap/BPF). |
| **SIGHUP** | Sinal Unix para recarregar configuracao sem reiniciar. |
| **SIGUSR1** | Sinal Unix para solicitar estatisticas ao daemon. |
| **SNI** | Server Name Indication — campo TLS que indica o dominio solicitado. |
| **CIDR** | Notacao de sub-rede (ex: `192.168.0.0/24` = 256 enderecos). |
| **Fleet** | Conjunto de multiplos firewalls geridos centralmente. |
| **protos file** | Ficheiro de protocolos customizados para nDPI (runtime, sem recompilacao). |
| **enforce** | Modo operacional onde o daemon executa acoes reais (pfctl). |
| **monitor** | Modo operacional onde o daemon apenas observa e regista. |
| **tag** | Acao que adiciona IP a uma tabela PF customizada (nao bloqueia). |

---

*Documento gerado para Layer7 v0.2.0 — pfSense CE*
