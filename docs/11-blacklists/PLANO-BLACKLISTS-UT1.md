# Plano Completo: Integração de Blacklists UT1 (Categorias Web)

> **DOCUMENTO AUTO-SUFICIENTE** — Este documento contém TODO o contexto
> necessário para implementar a integração de blacklists externas no Layer7.
> Não é necessário contexto anterior. Leia este documento do início ao fim
> antes de começar.

> **ADDENDUM NORMATIVO F1.3 (2026-04-01)** — o consumo automático no
> appliance deixou de usar UT1 directo em HTTP/FTP/rsync. A trilha oficial de
> consumo agora é:
> `manifesto assinado HTTPS -> snapshot validada -> cache local -> last-known-good`.
> O upstream UT1 continua apenas como **autoridade de conteúdo** na fase de
> aquisição controlada fora do firewall do cliente.

> **ADDENDUM NORMATIVO F1.4 (2026-04-01)** — a degradação desta trilha deixa
> de ser implícita. O updater passa a materializar:
> `healthy -> degraded (hold-active ou last-known-good) -> fail-closed`
> em `/usr/local/etc/layer7/blacklists/.state/fallback.state`, sempre sem
> promover conteúdo novo não validado.

> **ADDENDUM F4.3 (2026-04-24)** — a trilha de **DNS forçado** (`force_dns`)
> por regra de blacklist injecta `rdr` no anchor NAT `natrules/layer7_nat`
> (ver `layer7.inc` / plano
> [`docs/02-roadmap/f4-plano-de-implementacao.md`](../02-roadmap/f4-plano-de-implementacao.md)).
> Evidencia em laboratório: secção 11 de
> [`docs/04-package/validacao-lab.md`](../04-package/validacao-lab.md)
> (incl. cenário sugerido multi-interface / VLAN para **BG-011**);
> teste **6.7** em [`docs/tests/test-matrix.md`](../tests/test-matrix.md);
> addendum F4.3 em
> [`docs/10-license-server/MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md).
> Ver backlog **BG-011** (em curso na F4.3).

> **ADDENDUM F4.2 (BG-010)** — roteiro de evidência no appliance para updater,
> `fallback.state` e `send_sighup`: secção **10b** de
> [`docs/04-package/validacao-lab.md`](../04-package/validacao-lab.md); testes
> **12.1–12.2** em [`docs/tests/test-matrix.md`](../tests/test-matrix.md).
> Bloco F4.2 adicional (`1.8.11_7`): reload falhado preserva a blacklist
> anterior e as tabelas activas ate a nova carga ser valida; DNS/SNI validam a
> regra e a origem antes de popular `layer7_bld_N`; dominios em multiplas
> categorias passam a casar com a categoria seleccionada pela regra.

---

## Estado actual da trilha F4

### Melhorias pós-V1 implementadas (2026-03-31)

- [x] **Melhoria A — DNS Forçado via PF `rdr`**: campo `force_dns` por regra; regras `rdr pass` geradas dinamicamente por `layer7_inject_nat_to_anchor()` no anchor `natrules/layer7_nat`; checkbox na GUI
- [x] **Melhoria B — Bloqueio por DNS/SNI**: DNS e SNI validam categoria da regra, origem (`src_cidrs`) e dominios sobrepostos antes de adicionar IP à tabela `layer7_bld_N` correcta
- [x] **Melhoria C — Estatísticas DNS vs SNI**: contadores `bl_dns_hits` e `bl_sni_hits` no stats JSON

---

## Estado anterior (v1.4.17)

Implementado no produto:

- criacao de categorias locais com dominios proprios na mesma pagina de Blacklists;
- extensao de categorias UT1 existentes (mesmo ID da categoria) com dominios adicionais;
- seletor de regras com lista combinada (UT1 + custom), sem tela adicional.

Persistencia operacional:

- `config.json` passa a guardar `category_custom`;
- overlays locais por categoria sao sincronizados em `blacklists/_custom/<categoria>.domains`;
- daemon carrega dominios UT1 e overlays locais por categoria ativa.

---

## 1. Contexto do projecto Layer7

Layer7 é um produto comercial da **Systemup Solução em Tecnologia**
(www.systemup.inf.br), desenvolvido por **Pablo Michelin**.

É um daemon (C + nDPI) para pfSense CE que classifica tráfego de rede na
camada 7 e aplica políticas de bloqueio/monitoramento. O produto inclui
uma GUI PHP integrada ao pfSense com 10 páginas.

**Referência publica actual: 1.8.3** — V1 Comercial concluída e publicada.
**Branch de trabalho:** `1.8.11_12` no port, ainda sujeito aos gates F4 em
builder FreeBSD e appliance pfSense antes de release.

O daemon já suporta bloqueio por domínio/site via observação DNS:
quando uma resposta DNS é capturada e o domínio pertence a uma política
de bloqueio, o IP resolvido é adicionado à tabela PF `layer7_block_dst`.

**O que falta**: suporte a **listas externas massivas de domínios**
organizadas por categoria, como a blacklist UT1 da Université Toulouse
Capitole (sucessora da Shalla List usada pelo SquidGuard).

---

## 2. Objectivo

Integrar a **blacklist UT1** (Université Toulouse Capitole) no Layer7 para
que o operador possa:

1. Configurar a URL da blacklist e fazer download com progresso visível
   (fluxo inspirado no SquidGuard)
2. Ver categorias auto-descobertas do arquivo e seleccionar acção
   (`---` ou `deny`) por categoria
3. O daemon carregue os domínios das categorias com `deny` numa hash table
   eficiente, com whitelist global de domínios isentos
4. Quando uma resposta DNS for observada com domínio presente na blacklist,
   o IP resolvido entre automaticamente em `layer7_block_dst`
5. IPs excepcionados (tabela PF `layer7_bl_except`) possam aceder a
   destinos bloqueados
6. As listas sejam actualizadas periodicamente (cron) ou manualmente (GUI)
7. Tudo funcione integrado com o sistema de políticas existente

**Resultado esperado**: o Layer7 passa a funcionar como um
**"SquidGuard moderno"** para pfSense CE — classificação nDPI +
categorias web UT1 (80+ categorias, milhões de domínios), com GUI
integrada e enforcement PF. O nDPI detecta **aplicações** (YouTube,
BitTorrent), a UT1 categoriza **conteúdo de sites** (pornografia,
gambling, phishing) — juntos, cobrem dimensões complementares do
tráfego.

---

## 3. O que é a blacklist UT1

### 3.1 Origem

A blacklist é mantida pela **Université Toulouse Capitole** (França), gerida
por **Fabrice Prigent**. É a mesma família de listas usada historicamente
pelo SquidGuard, DansGuardian e E2Guardian.

- **Site**: https://dsi.ut-capitole.fr/blacklists/index_en.php
- **Mirror GitHub**: https://github.com/olbat/ut1-blacklists
- **Licença**: Creative Commons Attribution-ShareAlike 4.0 (CC-BY-SA 4.0)
- **Manutenção**: 50-300 URLs adicionados diariamente
- **Idade**: mantida há mais de 15 anos

### 3.2 Como obter

```
# Via HTTP (arquivo completo ~20MB comprimido)
http://dsi.ut-capitole.fr/blacklists/download/blacklists.tar.gz

# Via FTP
ftp://ftp.ut-capitole.fr/blacklist/

# Via rsync (mais eficiente para actualizações incrementais)
rsync -arpogvt rsync://ftp.ut-capitole.fr/blacklist .
```

### 3.3 Formato dos ficheiros

O arquivo `blacklists.tar.gz` extrai para:

```
blacklists/
├── adult/
│   ├── domains        ← um domínio por linha (4.6M+ entradas)
│   └── urls           ← URLs completas (não usaremos)
├── social_networks/
│   ├── domains
│   └── urls
├── gaming/
│   ├── domains
│   └── urls
├── vpn/
│   ├── domains
│   └── urls
├── streaming/
│   ├── domains
│   └── urls
├── ai/
│   ├── domains
│   └── urls
├── phishing/
│   ├── domains
│   └── urls
├── malware/
│   ├── domains
│   └── urls
└── ... (80+ categorias)
```

Cada ficheiro `domains` contém um domínio por linha, sem protocolo, sem
barra, sem espaços:

```
example.com
subdomain.example.com
another-site.org
```

**Para o Layer7, usaremos apenas os ficheiros `domains`** — o ficheiro
`urls` contém caminhos HTTP completos que não são aplicáveis ao nosso
modelo de bloqueio por DNS.

### 3.4 Categorias principais

| Categoria | Entradas (aprox.) | Descrição |
|-----------|-------------------|-----------|
| adult | 4.600.000+ | Pornografia (a maior categoria) |
| agressif | 396 | Racismo, ódio, antisemitismo |
| ai | 74 | Ferramentas de IA (ChatGPT, etc.) |
| audio-video | 600+ | Streaming de áudio e vídeo |
| bank | 6.646 | Sites de bancos online |
| bitcoin | 200+ | Criptomoedas |
| blog | 1.500 | Plataformas de blog |
| child | 77 | Whitelist para crianças < 10 anos |
| cleaning | 80+ | Sites de "limpeza de PC" |
| cooking | 300+ | Receitas e culinária |
| dating | 2.000+ | Sites de encontros |
| download | 500+ | Sites de download directo |
| drugs | 200+ | Drogas |
| educational_games | 100+ | Jogos educativos |
| filehosting | 500+ | Alojamento de ficheiros |
| financial | 1.000+ | Serviços financeiros |
| forums | 2.000+ | Fóruns |
| gambling | 8.000+ | Apostas e jogos de azar |
| games | 1.500+ | Jogos online |
| hacking | 1.000+ | Hacking e exploits |
| jobsearch | 300+ | Sites de emprego |
| lingerie | 500+ | Lingerie e roupa íntima |
| malware | 50.000+ | Malware e sites perigosos |
| manga | 1.000+ | Manga e anime |
| marketingware | 2.000+ | Adware e marketing agressivo |
| mixed_adult | 5.000+ | Conteúdo misto adulto |
| mobile-phone | 200+ | Conteúdo mobile |
| phishing | 90.000+ | Phishing |
| porn | 500.000+ | Pornografia explícita |
| press | 3.000+ | Imprensa e notícias |
| proxy | 5.000+ | Proxies web |
| radio | 500+ | Rádio online |
| reaffected | 200+ | Domínios reaproveitados |
| redirector | 2.000+ | Redireccionadores |
| remote-control | 300+ | Acesso remoto |
| sect | 500+ | Seitas e cultos |
| sexual_education | 100+ | Educação sexual |
| shopping | 10.000+ | Compras online |
| shortener | 500+ | Encurtadores de URL |
| social_networks | 500+ | Redes sociais |
| sports | 1.000+ | Desporto |
| stalkerware | 100+ | Software de espionagem |
| strict_redirector | 100+ | Redireccionadores estritos |
| strong_redirector | 100+ | Redireccionadores fortes |
| translation | 50+ | Tradutores online |
| update | 500+ | Servidores de actualização |
| vpn | 300+ | Serviços VPN |
| warez | 5.000+ | Software pirata |
| webmail | 200+ | Webmail |

**Nota**: os números são aproximados e mudam diariamente.

---

## 4. Arquitectura actual relevante

### 4.1 Como o bloqueio por domínio funciona hoje

```
                    ┌─────────────────────────────┐
                    │ capture.c                   │
                    │                             │
Resposta DNS ──►    │ observe_dns_response()      │
(porta 53)         │   └─ qname + resolved_ip    │
                    │      └─ dns_cb() callback   │
                    └──────────┬──────────────────┘
                               │
                    ┌──────────▼──────────────────┐
                    │ main.c                      │
                    │                             │
                    │ layer7_on_dns_resolved()    │
                    │   └─ layer7_domain_is_blocked() ◄── policy.c
                    │      └─ se bloqueado:       │
                    │         pfctl add IP a      │
                    │         layer7_block_dst    │
                    └─────────────────────────────┘
```

### 4.2 Limites da V1 para este caso de uso

| Componente | Limite actual | Impacto |
|------------|---------------|---------|
| `L7_MAX_HOSTS_PER_POLICY` | 16 domínios | Impossível ter milhares |
| `L7_MAX_POLICIES` | 24 políticas | Impossível ter 80+ categorias |
| Estrutura de busca | Array linear O(n) | Inviável com milhões de entradas |
| Import externo | Nenhum | Sem mecanismo de download/parse |
| Actualização | Manual (GUI) | Não suporta cron/rsync |

### 4.3 Ponto de integração chave

A função `layer7_on_dns_resolved()` em `main.c` é chamada para **cada
resposta DNS observada**. Hoje, ela consulta `layer7_domain_is_blocked()`
que itera linearmente pelas 24 políticas × 16 hosts cada.

**A integração consiste em adicionar uma segunda consulta** neste mesmo
ponto: além de verificar as políticas existentes, verificar também a
hash table de blacklists carregada em memória.

---

## 5. Arquitectura proposta

### 5.1 Visão geral (ACTUALIZADA v2)

```
┌─────────────────────────────────────────────────────────────────────┐
│                         pfSense CE                                  │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ /usr/local/etc/layer7/blacklists/                           │   │
│  │                                                              │   │
│  │  config.json              ← config blacklists (GUI gerida)   │   │
│  │  discovered.json          ← auto-descoberta (script gera)    │   │
│  │  adult/domains            ← ficheiros de domínios            │   │
│  │  gambling/domains         ← (TODAS as categorias extraídas)  │   │
│  │  malware/domains          │                                  │   │
│  │  ...                      │                                  │   │
│  └──────────────┬───────────────────────────────────────────────┘   │
│                 │ carga ao startup + SIGHUP                         │
│  ┌──────────────▼───────────────────────────────────────────────┐   │
│  │ layer7d (daemon)                                             │   │
│  │                                                              │   │
│  │  ┌──────────────────────────────────────┐                    │   │
│  │  │ blacklist.c (NOVO)                   │                    │   │
│  │  │                                      │                    │   │
│  │  │  Hash table: domínio → categoria     │                    │   │
│  │  │  Whitelist interna: domínios isentos │                    │   │
│  │  │  l7_blacklist_lookup(domain)         │                    │   │
│  │  │  l7_blacklist_load(dir, cats[], wl[])│                    │   │
│  │  │  l7_blacklist_free()                 │                    │   │
│  │  └──────────────┬───────────────────────┘                    │   │
│  │                 │                                             │   │
│  │  ┌──────────────┴───────────────────────┐                    │   │
│  │  │ bl_config.c (NOVO)                   │                    │   │
│  │  │  Parse de config.json separado       │                    │   │
│  │  │  (NÃO altera config_parse.c)         │                    │   │
│  │  └──────────────────────────────────────┘                    │   │
│  │                                                              │   │
│  │  layer7_on_dns_resolved():                                   │   │
│  │    1. layer7_domain_is_blocked() → políticas (V1, inalterado)│   │
│  │    2. l7_blacklist_lookup()      → blacklists (NOVO)         │   │
│  │       (whitelist verificada internamente antes do lookup)     │   │
│  │    Se qualquer um retornar bloqueado → pfctl add dst         │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ PF Tables                                                    │   │
│  │                                                              │   │
│  │  layer7_block_dst    ← IPs de destino bloqueados             │   │
│  │  layer7_bl_except    ← IPs de origem excepcionados (NOVO)    │   │
│  │                                                              │   │
│  │  pass quick from <layer7_bl_except> to <layer7_block_dst>    │   │
│  │  block drop quick to <layer7_block_dst>                      │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ GUI PHP (NOVA PÁGINA — estilo SquidGuard)                    │   │
│  │                                                              │   │
│  │  layer7_blacklists.php                                       │   │
│  │    Sec1: URL + Download com log                              │   │
│  │    Sec2: Categorias auto-descobertas (dropdown ---/deny)     │   │
│  │    Sec3: Excepções (whitelist domínios + IPs except)         │   │
│  │    Sec4: Definições e estado                                 │   │
│  └──────────────────────────────────────────────────────────────┘   │
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │ Cron job (NOVO)                                              │   │
│  │                                                              │   │
│  │  /usr/local/etc/layer7/update-blacklists.sh                  │   │
│  │  - fetch blacklists.tar.gz                                    │   │
│  │  - extrair TODAS as categorias                                │   │
│  │  - gerar discovered.json (auto-descoberta)                    │   │
│  │  - SIGHUP ao daemon para recarregar                           │   │
│  └──────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
```

### 5.2 Subsistema de blacklists (novo módulo C)

O novo módulo `blacklist.c` / `blacklist.h` será um componente **paralelo
e independente** do policy engine existente. Não altera nenhuma estrutura
da V1.

**Nota v2**: as interfaces são **globais** (todas as categorias afectam
as mesmas interfaces). O parâmetro `iface` foi removido da API.
A whitelist de domínios é passada no `l7_blacklist_load()` e verificada
internamente no `l7_blacklist_lookup()` antes da consulta à hash table.

#### Estrutura de dados: Hash table de domínios

```c
#define L7_BL_HASH_SIZE    (1 << 20)  /* 1M buckets — ajustável */
#define L7_BL_DOMAIN_MAX   256
#define L7_BL_CAT_MAX      32

struct l7_bl_entry {
    char domain[L7_BL_DOMAIN_MAX];
    char category[L7_BL_CAT_MAX];
    struct l7_bl_entry *next;       /* chaining para colisões */
};

struct l7_blacklist {
    struct l7_bl_entry **buckets;   /* array de ponteiros (hash table) */
    int n_entries;                   /* total de domínios carregados */
    int n_categories;                /* categorias activas */
    char categories[64][L7_BL_CAT_MAX]; /* lista de categorias carregadas */
};
```

**Escolha da hash table com chaining** (em vez de open addressing):
- Suporta melhor densidades altas (milhões de entradas)
- Inserção O(1) amortizado, lookup O(1) médio
- Memória alocada via `calloc`/`malloc`, libertada com `free`
- Função de hash: FNV-1a (rápida, boa distribuição para strings)

#### Suffix matching (subdomínios)

Para que `video.adult-site.com` case com uma entrada `adult-site.com`
na blacklist, a lookup deve fazer **suffix matching**:

```
Input: "video.adult-site.com"
Tentativa 1: lookup("video.adult-site.com") → não encontrado
Tentativa 2: lookup("adult-site.com")       → encontrado! → bloqueado
```

Implementação: o lookup tenta o domínio completo primeiro; se não
encontrar, remove o primeiro label (até ao primeiro `.`) e tenta de novo,
repetindo até não haver mais labels.

### 5.3 Configuração JSON

A configuração de blacklists usa um **ficheiro separado** em vez de
modificar o parser JSON existente do daemon. Isto evita alterar
`config_parse.c` e simplifica o parse.

**Ficheiro de config das blacklists** (`/usr/local/etc/layer7/blacklists/config.json`):

Gerido pelo PHP (GUI). Lido pelo daemon no startup e SIGHUP.

```json
{
  "enabled": true,
  "source_url": "https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt",
  "mirror_urls": [
    "https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt"
  ],
  "auto_update": true,
  "update_interval_hours": 24,
  "categories": ["adult", "gambling", "malware", "phishing"],
  "whitelist": ["google.com", "microsoft.com"],
  "except_ips": ["192.168.10.50", "192.168.10.51"]
}
```

- `categories[]`: lista de IDs das categorias com acção `deny` (bloqueio)
- `whitelist[]`: domínios que NUNCA são bloqueados pela blacklist (global)
- `except_ips[]`: IPs de origem que podem aceder a destinos bloqueados
  (populam a tabela PF `layer7_bl_except`)

**Ficheiro de auto-descoberta** (`/usr/local/etc/layer7/blacklists/discovered.json`):

Gerado automaticamente pelo script de download após extrair o arquivo.

```json
{
  "source": "https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt",
  "snapshot_id": "ut1-20260401T030000Z",
  "discovered_at": "2026-03-24T03:00:00Z",
  "categories": [
    {"id": "adult", "domains_count": 4623451},
    {"id": "agressif", "domains_count": 396},
    {"id": "ai", "domains_count": 74},
    {"id": "gambling", "domains_count": 8234},
    {"id": "malware", "domains_count": 52340},
    {"id": "phishing", "domains_count": 91203}
  ]
}
```

**Estado e persistencia F1.3 no appliance:**

- origem oficial primaria:
  `https://downloads.systemup.inf.br/layer7/blacklists/ut1/current/layer7-blacklists-manifest.v1.txt`
- mirror controlado:
  `https://github.com/pablomichelin/Layer7/releases/download/blacklists-ut1-current/layer7-blacklists-manifest.v1.txt`
- chave publica pinned:
  `/usr/local/share/pfSense-pkg-layer7/blacklists-signing-public-key.pem`
- cache local:
  `/usr/local/etc/layer7/blacklists/.cache/<snapshot_id>/`
- estado activo:
  `/usr/local/etc/layer7/blacklists/.state/active-snapshot.state`
- last-known-good:
  `/usr/local/etc/layer7/blacklists/.last-known-good/`
- restauro da ultima versao valida:
  `/usr/local/etc/layer7/update-blacklists.sh --restore-lkg`

A GUI lê este ficheiro para listar categorias disponíveis. Se não existir
(primeiro uso), mostra mensagem "Faça o download da lista primeiro".

### 5.4 Ficheiros no disco

```
/usr/local/etc/layer7/
├── layer7.json              ← config existente (INALTERADO)
├── profiles.json            ← perfis existentes (INALTERADO)
└── blacklists/
    ├── config.json          ← config das blacklists (gerido pela GUI)
    ├── discovered.json      ← categorias auto-descobertas (gerado pelo script)
    ├── last-update.txt      ← timestamp da última actualização
    ├── adult/
    │   └── domains          ← ficheiro de domínios (um por linha)
    ├── agressif/
    │   └── domains
    ├── gambling/
    │   └── domains
    ├── malware/
    │   └── domains
    ├── phishing/
    │   └── domains
    └── ... (TODAS as categorias extraídas; só as com deny são carregadas)
```

**Nota**: o script de download extrai TODAS as categorias do arquivo
(para auto-descoberta e contagem), mas o daemon só carrega as que
estão na lista `categories[]` do `config.json`.

---

## 6. Blocos de implementação

### Bloco 1: Script de download e extracção (ACTUALIZADO v2)

**Objectivo**: criar o script shell que descarrega a blacklist UT1,
extrai TODAS as categorias para auto-descoberta, gera metadados, e
prepara os ficheiros para o daemon.

**Ficheiro**: `/usr/local/etc/layer7/update-blacklists.sh`

**Modos de operação**:

- `--download`: descarrega o arquivo e extrai tudo para auto-descoberta
- `--apply`: copia apenas as categorias com acção `deny` para o directório final e envia SIGHUP
- Sem argumentos: executa `--download` seguido de `--apply` (fluxo cron)

**Lógica**:

```bash
#!/bin/sh
# update-blacklists.sh — descarregar e gerir blacklists UT1
# Fluxo SquidGuard: download → auto-descoberta → selecção na GUI → apply

BL_DIR="/usr/local/etc/layer7/blacklists"
CONFIG="$BL_DIR/config.json"
DISCOVERED="$BL_DIR/discovered.json"
TMP_DIR="/tmp/layer7-bl-update"
TARBALL="$TMP_DIR/blacklists.tar.gz"
LOCK="/tmp/layer7-bl-update.lock"
LOG="/var/log/layer7-bl-update.log"

# 1. Verificar lock (evitar execuções simultâneas)
# 2. Ler URL do config.json (ou usar default UT1)
# 3. Criar directório temporário
# 4. fetch -o $TARBALL $URL (FreeBSD fetch, não wget/curl)
# 5. Verificar integridade (tamanho mínimo, ex: > 1MB)
# 6. Extrair TUDO para $TMP_DIR/
# 7. Auto-descoberta: listar categorias + contar domínios
# 8. Gerar discovered.json com metadados
# 9. Copiar TODAS as pastas de categorias para $BL_DIR/
# 10. Se modo --apply: ler categories[] do config.json
#     (o daemon carrega apenas estas, mas os ficheiros ficam todos)
# 11. Gravar timestamp em last-update.txt
# 12. Enviar SIGHUP ao daemon: a implementacao em `update-blacklists.sh`
#     (`send_sighup`) le o pidfile com `read -r`, aplica trim, rejeita PID
#     nao numerico e exige `kill -0` antes de `HUP`; nao usar
#     `kill -HUP $(cat /var/run/layer7d.pid)` (fragil a espacos/corrompido).
#     Em operacao manual: `service layer7d reload` (F4.1, `DIRETRIZES`).
# 13. Limpar temporários
# 14. Log do resultado com progresso
```

**Auto-descoberta (passo 7-8)**:

O script percorre todos os subdirectórios extraídos, identifica os que
contêm um ficheiro `domains`, conta as linhas (domínios), e gera o
`discovered.json`:

```sh
# Pseudo-código da auto-descoberta
echo '{"source":"'"$URL"'","discovered_at":"'"$(date -u +%Y-%m-%dT%H:%M:%SZ)"'","categories":[' > "$DISCOVERED"
first=1
for catdir in "$TMP_DIR"/blacklists/*/; do
    cat=$(basename "$catdir")
    domfile="$catdir/domains"
    if [ -f "$domfile" ]; then
        count=$(wc -l < "$domfile")
        [ $first -eq 0 ] && printf ',' >> "$DISCOVERED"
        printf '{"id":"%s","domains_count":%d}' "$cat" "$count" >> "$DISCOVERED"
        first=0
    fi
done
echo ']}' >> "$DISCOVERED"
```

**Progresso para a GUI**: o script escreve progresso num ficheiro
temporário (`/tmp/layer7-bl-progress.txt`) que a GUI lê via AJAX
para mostrar o estado do download ao operador.

**Dependências FreeBSD**: `fetch` (base system), `tar` (base system),
`kill` (base system), `wc` (base system). Nenhuma dependência adicional.

**Cron job**: registado via pfSense cron API ou crontab directo:
```
0 3 * * * /usr/local/etc/layer7/update-blacklists.sh >> /var/log/layer7-bl-update.log 2>&1
```

**Entregas**:
- [ ] Script `update-blacklists.sh` funcional com modos `--download` e `--apply`
- [ ] Auto-descoberta de categorias com geração de `discovered.json`
- [ ] Progresso escrito em ficheiro temporário para a GUI
- [ ] Teste manual: download + auto-descoberta + verificação de discovered.json
- [ ] Log de resultado legível
- [ ] Lock file para evitar execuções paralelas

---

### Bloco 2: Módulo C de blacklists (hash table)

**Objectivo**: criar `blacklist.c` e `blacklist.h` com uma hash table
eficiente para milhões de domínios, com suffix matching.

**Ficheiros**:
- `src/layer7d/blacklist.h`
- `src/layer7d/blacklist.c`

**API pública**:

```c
/* blacklist.h */
#ifndef LAYER7_BLACKLIST_H
#define LAYER7_BLACKLIST_H

#define L7_BL_DIR "/usr/local/etc/layer7/blacklists"

struct l7_blacklist;  /* opaco */

/*
 * Carrega domínios das categorias listadas em cats[].
 * Lê ficheiros $dir/$cat/domains para cada categoria.
 * Retorna ponteiro para a blacklist carregada, ou NULL em caso de erro.
 * O chamador deve libertar com l7_blacklist_free().
 *
 * whitelist[]/n_whitelist: domínios que NUNCA são bloqueados.
 * O módulo guarda uma cópia interna da whitelist.
 */
struct l7_blacklist *l7_blacklist_load(const char *dir,
    const char **cats, int n_cats,
    const char **whitelist, int n_whitelist);

/*
 * Verifica se um domínio está na blacklist (com suffix matching).
 * Verifica a whitelist ANTES do lookup na hash table.
 * Retorna o nome da categoria se bloqueado, ou NULL se permitido.
 */
const char *l7_blacklist_lookup(const struct l7_blacklist *bl,
    const char *domain);

/*
 * Liberta toda a memória da blacklist (incluindo whitelist interna).
 */
void l7_blacklist_free(struct l7_blacklist *bl);

/*
 * Retorna estatísticas da blacklist carregada.
 */
int l7_blacklist_count(const struct l7_blacklist *bl);
int l7_blacklist_cat_count(const struct l7_blacklist *bl);

#endif
```

**Nota v2**: interfaces removidas da API (decisão: global). A whitelist
é passada no load e verificada internamente no lookup, antes da consulta
à hash table.

**Implementação interna (blacklist.c)**:

1. **Hash function**: FNV-1a 32-bit sobre os bytes do domínio (lowercase)
2. **Tabela**: array de ponteiros `struct l7_bl_entry *` (chaining)
3. **Tamanho**: `1 << 20` (1.048.576 buckets) — bom para até ~5M entradas
4. **Alocação**: `calloc` para o array; `malloc` para cada entry
5. **Lowercase**: domínios normalizados para minúsculas no load
6. **Validação**: linhas vazias, comentários `#`, domínios inválidos ignorados
7. **Suffix matching**: no lookup, tentar domínio completo, depois remover
   labels da esquerda progressivamente

**Estimativa de memória** (pior caso — todas as categorias):

| Item | Cálculo | Total |
|------|---------|-------|
| Hash table array | 1M × 8 bytes (ponteiros) | ~8 MB |
| Entradas (5M domínios) | 5M × (256 + 32 + 8) bytes | ~1.4 GB |
| **Optimizado** (domínio médio ~25 chars) | 5M × (25 + 32 + 8 + overhead) | ~400 MB |

**IMPORTANTE**: com todas as categorias (~5M domínios), o uso de memória
será significativo. **Estratégias de mitigação**:

1. **Carregar apenas categorias activas** — cenário típico: 5-10 categorias,
   ~100K-500K domínios → **~30-150 MB** (aceitável)
2. **Domínio como ponteiro para pool** — em vez de `char domain[256]`, usar
   alocação exacta com pool contíguo
3. **Categoria como índice** — em vez de `char category[32]`, usar `uint8_t
   cat_id` (0-255)
4. **Versão optimizada posterior** — suffix trie ou DAFSA se necessário

**Estimativa realista (5-10 categorias, ~200K domínios)**:

| Item | Total |
|------|-------|
| Hash table array | ~8 MB |
| Entradas (200K × ~70 bytes) | ~14 MB |
| **Total** | **~22 MB** |

Perfeitamente aceitável para pfSense (mínimo 1GB RAM recomendado).

**Entregas**:
- [ ] `blacklist.h` com API pública
- [ ] `blacklist.c` com hash table FNV-1a + chaining
- [ ] Suffix matching funcional
- [ ] Testes: carregar ficheiro de exemplo, lookup, free, sem leaks
- [ ] Makefile actualizado com `blacklist.c`

---

### Bloco 3: Integração no daemon (DNS callback) (ACTUALIZADO v2)

**Objectivo**: integrar o módulo de blacklists no daemon `layer7d`,
adicionando a consulta à blacklist no callback de DNS.

**Ficheiros alterados**:
- `src/layer7d/main.c`
- `src/layer7d/bl_config.c` (NOVO — parse do `config.json` das blacklists)

**Parse de configuração** (ACTUALIZADO v2):

O daemon lê a configuração de blacklists de um ficheiro **separado**
`/usr/local/etc/layer7/blacklists/config.json` em vez de modificar o
parser existente `config_parse.c`. Isto preserva o parser V1 intacto.

```c
/* bl_config.c — parse do config.json das blacklists */
struct l7_bl_config {
    int enabled;
    char categories[64][L7_BL_CAT_MAX];
    int n_categories;
    char whitelist[256][L7_BL_DOMAIN_MAX];
    int n_whitelist;
    char except_ips[64][48];
    int n_except_ips;
};

int l7_bl_config_load(const char *path, struct l7_bl_config *cfg);
```

**Alterações em `main.c`**:

```c
static struct l7_blacklist *s_blacklist;
static unsigned long long s_bl_hits;
static unsigned long long s_bl_lookups;

static void
layer7_on_dns_resolved(const char *domain, const char *resolved_ip,
    uint32_t ttl)
{
    int r;
    const char *bl_cat;

    if (!s_have_parse || !s_ge)
        return;

    /* Verificação existente: políticas manuais (V1 — inalterado) */
    if (layer7_domain_is_blocked(s_rules, s_np, domain)) {
        r = layer7_pf_exec_table_add(L7_PF_TABLE_BLOCK_DST, resolved_ip);
        if (r == 0) {
            s_pf_dst_add_ok++;
            dst_cache_add(resolved_ip, ttl);
            L7_INFO("dns_block: domain=%s ip=%s ttl=%u table=%s",
                domain, resolved_ip, ttl, L7_PF_TABLE_BLOCK_DST);
        } else {
            s_pf_dst_add_fail++;
        }
        return;
    }

    /* NOVO: verificação na blacklist UT1 */
    /* Whitelist verificada DENTRO de l7_blacklist_lookup() */
    if (s_blacklist) {
        s_bl_lookups++;
        bl_cat = l7_blacklist_lookup(s_blacklist, domain);
        if (bl_cat) {
            s_bl_hits++;
            r = layer7_pf_exec_table_add(L7_PF_TABLE_BLOCK_DST,
                resolved_ip);
            if (r == 0) {
                s_pf_dst_add_ok++;
                dst_cache_add(resolved_ip, ttl);
                L7_INFO("bl_block: domain=%s cat=%s ip=%s ttl=%u",
                    domain, bl_cat, resolved_ip, ttl);
            } else {
                s_pf_dst_add_fail++;
            }
        }
    }
}
```

**Carga e recarga (SIGHUP)**:

Na rotina de reload de configuração (já existente no main.c), adicionar
após o parse de políticas:

```c
/* Recarregar blacklists do config.json separado */
if (s_blacklist) {
    l7_blacklist_free(s_blacklist);
    s_blacklist = NULL;
}

struct l7_bl_config bl_cfg;
if (l7_bl_config_load(L7_BL_DIR "/config.json", &bl_cfg) == 0
    && bl_cfg.enabled && bl_cfg.n_categories > 0) {

    const char *cats[64], *wl[256];
    for (int i = 0; i < bl_cfg.n_categories; i++)
        cats[i] = bl_cfg.categories[i];
    for (int i = 0; i < bl_cfg.n_whitelist; i++)
        wl[i] = bl_cfg.whitelist[i];

    s_blacklist = l7_blacklist_load(L7_BL_DIR, cats, bl_cfg.n_categories,
        wl, bl_cfg.n_whitelist);

    if (s_blacklist)
        L7_NOTE("blacklists: loaded %d domains in %d categories",
            l7_blacklist_count(s_blacklist),
            l7_blacklist_cat_count(s_blacklist));
    else
        L7_WARN("blacklists: failed to load");

    /* Popular tabela PF de excepções */
    for (int i = 0; i < bl_cfg.n_except_ips; i++)
        layer7_pf_exec_table_add("layer7_bl_except",
            bl_cfg.except_ips[i]);
}
```

**Integração com excepções** (ACTUALIZADO v2):

Duas camadas de excepções:
1. **Excepções V1** (por IP de origem, `layer7_allow_src`): funcionam
   como antes, avaliadas em `layer7_flow_decide()`
2. **Excepções de blacklist** (por IP, `layer7_bl_except`): nova tabela
   PF com regra `pass` antes do `block to <layer7_block_dst>` — avaliada
   pelo PF antes da regra de bloqueio

**Contadores por categoria**: para o top hits, manter um array de
contadores indexado pelo ID da categoria, incrementado no lookup.

**Entregas**:
- [ ] `bl_config.c` com parse do `config.json` separado
- [ ] Carga de blacklists no startup do daemon
- [ ] Recarga via SIGHUP (incluindo tabela PF de excepções)
- [ ] Integração no callback DNS com whitelist interna
- [ ] Contadores `s_bl_hits` e `s_bl_lookups` no stats JSON
- [ ] Contadores por categoria para top hits
- [ ] Log `bl_block:` para eventos de blacklist
- [ ] Teste: domínio em blacklist → IP bloqueado em PF
- [ ] Teste: domínio na whitelist → não bloqueado

---

### Bloco 4: GUI — Página de Blacklists (REESCRITO v2)

**Objectivo**: criar a página PHP para gestão de blacklists na GUI do
pfSense, com layout inspirado no SquidGuard (download → auto-descoberta
→ selecção → exceções).

**Ficheiro novo**: `layer7_blacklists.php`

**Layout da página (4 secções)**:

```
┌─────────────────────────────────────────────────────────────┐
│  Layer7 > Categorias Web (Blacklists)                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌ SECÇÃO 1 — URL e Download ─────────────────────────────┐ │
│  │                                                       │ │
│  │ URL da blacklist:                                     │ │
│  │ [http://dsi.ut-capitole.fr/blacklists/download/bl...] │ │
│  │                                                       │ │
│  │ [Download]                                            │ │
│  │                                                       │ │
│  │ ┌─ Log de download ──────────────────────────────────┐│ │
│  │ │ [2026-03-24 03:00:01] Downloading blacklists...   ││ │
│  │ │ [2026-03-24 03:00:15] Download complete (18.2 MB) ││ │
│  │ │ [2026-03-24 03:00:16] Extracting archive...       ││ │
│  │ │ [2026-03-24 03:00:22] Discovered 82 categories    ││ │
│  │ │ [2026-03-24 03:00:22] Generated discovered.json   ││ │
│  │ └────────────────────────────────────────────────────┘│ │
│  └────────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌ SECÇÃO 2 — Categorias (auto-descobertas) ──────────────┐ │
│  │                                            [Pesquisar] │ │
│  │                                                       │ │
│  │ Categoria       │ Domínios    │ Acção                  │ │
│  │ ─────────────────┼─────────────┼──────────────────────  │ │
│  │ adult            │ 4.623.451  │ [--- ▾] ⚠ RAM ~400MB  │ │
│  │ agressif         │       396  │ [deny ▾]               │ │
│  │ ai               │        74  │ [--- ▾]                │ │
│  │ gambling         │     8.234  │ [deny ▾]               │ │
│  │ malware          │    52.340  │ [deny ▾]               │ │
│  │ phishing         │    91.203  │ [deny ▾]               │ │
│  │ social_networks  │       523  │ [--- ▾]                │ │
│  │ vpn              │       312  │ [--- ▾]                │ │
│  │ ... (82 categorias auto-descobertas)                   │ │
│  │                                                       │ │
│  │ Nota: ⚠ = categoria com mais de 1M domínios           │ │
│  │       Impacto significativo em RAM.                    │ │
│  │                                                       │ │
│  │                        [Guardar categorias]            │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌ SECÇÃO 3 — Excepções ─────────────────────────────────┐ │
│  │                                                       │ │
│  │ Whitelist de domínios (nunca bloqueados):              │ │
│  │ ┌────────────────────────────────────────────────────┐│ │
│  │ │ google.com                                        ││ │
│  │ │ microsoft.com                                     ││ │
│  │ │ (um domínio por linha)                            ││ │
│  │ └────────────────────────────────────────────────────┘│ │
│  │                                                       │ │
│  │ IPs excepcionados (acedem a destinos bloqueados):     │ │
│  │ ┌────────────────────────────────────────────────────┐│ │
│  │ │ 192.168.10.50                                     ││ │
│  │ │ 192.168.10.51                                     ││ │
│  │ │ (um IP ou CIDR por linha)                         ││ │
│  │ └────────────────────────────────────────────────────┘│ │
│  │                                                       │ │
│  │                         [Guardar excepções]            │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌ SECÇÃO 4 — Definições e Estado ────────────────────────┐ │
│  │                                                       │ │
│  │ Actualização automática: [☑]                          │ │
│  │ Intervalo (horas):       [24]                         │ │
│  │                                                       │ │
│  │ Última actualização:     2026-03-24 03:00             │ │
│  │ Categorias activas:      5 / 82 disponíveis           │ │
│  │ Domínios carregados:     152.173                      │ │
│  │ Hits de blacklist:       1.247                        │ │
│  │                                                       │ │
│  │ Fonte: Université Toulouse Capitole (CC-BY-SA 4.0)    │ │
│  │                        [Guardar definições]            │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                             │
│  Atribuição: Listas mantidas pela Université Toulouse      │
│  Capitole (dsi.ut-capitole.fr). Licença CC-BY-SA 4.0.     │
└─────────────────────────────────────────────────────────────┘
```

**Funcionalidades**:

1. **URL editável + Download com log**: botão executa `update-blacklists.sh --download`,
   log visível via AJAX polling de `/tmp/layer7-bl-progress.txt`
2. **Auto-descoberta**: tabela lê `discovered.json` — se não existir, mostra
   "Faça o download da lista primeiro"
3. **Dropdown por categoria**: `---` (ignorar) ou `deny` (bloquear) — sem checkbox,
   estilo consistente com SquidGuard Target Rules
4. **Aviso para categorias grandes**: ícone ⚠ e texto para categorias com > 1M domínios
5. **Pesquisa de categorias**: filtro de texto JavaScript para a tabela
6. **Whitelist global**: textarea, um domínio por linha
7. **IPs excepcionados**: textarea, um IP/CIDR por linha → populam tabela PF `layer7_bl_except`
8. **Guardar**: cada secção tem botão próprio que actualiza `config.json`,
   e executa `update-blacklists.sh --apply` + SIGHUP
9. **Atribuição CC-BY-SA 4.0**: rodapé obrigatório

**Helpers em `layer7.inc`**:

```php
function layer7_bl_config_load() { ... }
function layer7_bl_config_save($config) { ... }
function layer7_bl_discovered_load() { ... }
function layer7_bl_get_stats() { ... }
function layer7_bl_download_start() { ... }
function layer7_bl_download_status() { ... }
function layer7_bl_apply() { ... }
function layer7_bl_pf_sync_except($ips) { ... }
```

**Entregas**:
- [ ] `layer7_blacklists.php` com layout de 4 secções
- [ ] Download com log visível (AJAX polling)
- [ ] Auto-descoberta de categorias (leitura de `discovered.json`)
- [ ] Dropdown `---`/`deny` por categoria
- [ ] Aviso para categorias com > 1M domínios
- [ ] Secção de excepções (whitelist domínios + IPs)
- [ ] Helpers em `layer7.inc`
- [ ] Integração no menu (layer7.xml — nova entrada)
- [ ] Campo de pesquisa para categorias
- [ ] GUI passa a ter 11 páginas

---

### Bloco 5: Cron job e actualização automática

**Objectivo**: registar o cron job no pfSense para actualização periódica
das blacklists.

**Mecanismo**: usar a API do pfSense (`install_cron_job()`) para registar
o cron via PHP, disparado pela GUI quando o auto-update é activado.

**Ficheiros alterados**:
- `layer7.inc` (função de setup/teardown do cron)
- `install.sh` (registar cron na instalação)

**Fluxo**:

1. Ao activar auto-update na GUI → `install_cron_job()`
2. Ao desactivar → `install_cron_job()` com remoção
3. `update-blacklists.sh` corre no horário configurado
4. Script descarrega, extrai, e envia SIGHUP
5. Daemon recarrega a hash table
6. GUI mostra timestamp da última actualização

**Entregas**:
- [ ] Cron job registado via pfSense API
- [ ] Remoção do cron quando auto-update desactivado
- [ ] Teste: verificar cron activo com `crontab -l`
- [ ] Log de actualização em `/var/log/layer7-bl-update.log`

---

### Bloco 6: Excepções PF e precedência (ACTUALIZADO v2)

**Objectivo**: implementar excepções por IP via tabela PF dedicada e
estabelecer a precedência final de bloqueio.

**Nova tabela PF: `layer7_bl_except`**

IPs/CIDRs nesta tabela podem aceder a destinos bloqueados pela blacklist
(e por políticas manuais). A regra `pass` precede a regra `block`.

**Regras PF** (em `layer7_pf_default_rules_text()` no `layer7.inc`):

```
pass quick inet from <layer7_bl_except> to <layer7_block_dst> label "layer7:bl:except"
block drop quick inet to <layer7_block_dst> label "layer7:block:dst"
```

A ordem é crítica: a regra `pass` DEVE vir antes da regra `block`.

**Gestão da tabela PF**:

- Na GUI (secção Excepções): textarea com IPs/CIDRs, um por linha
- Ao guardar, o PHP executa:
  1. `pfctl -t layer7_bl_except -T flush` (limpar tabela)
  2. Para cada IP: `pfctl -t layer7_bl_except -T add <ip>`
- No daemon (SIGHUP): recarrega `except_ips[]` do `config.json` e
  repopula a tabela PF

**Regras de precedência final**:

```
                     Resposta DNS observada
                              │
                   ┌──────────▼──────────────┐
                   │ IP origem em             │
                   │ layer7_bl_except?        │
                   └──────────┬──────────────┘
                    Sim │           │ Não
                   ┌────▼────┐     │
                   │ PERMITE │     │
                   │ (PF     │     │
                   │  pass)  │     │
                   └─────────┘     │
                   ┌───────────────▼──────────┐
                   │ Domínio em política       │
                   │ manual block?             │
                   └──────────┬──────────────┘
                    Sim │           │ Não
                   ┌────▼────┐     │
                   │ BLOQUEIA│     │
                   │ (dst)   │     │
                   └─────────┘     │
                   ┌───────────────▼──────────┐
                   │ Domínio na whitelist?     │
                   └──────────┬──────────────┘
                    Sim │           │ Não
                   ┌────▼────┐     │
                   │ PERMITE │     │
                   │ (ignora │     │
                   │  BL)    │     │
                   └─────────┘     │
                   ┌───────────────▼──────────┐
                   │ Domínio em categoria      │
                   │ deny da blacklist?        │
                   └──────────┬──────────────┘
                    Sim │           │ Não
                   ┌────▼────┐ ┌───▼─────┐
                   │ BLOQUEIA│ │ PERMITE │
                   │ (dst)   │ │ (default│
                   └─────────┘ └─────────┘
```

A excepção por IP funciona a nível PF (pass rule antes de block rule),
**não no daemon**. Isto é mais eficiente e fiável.

**Ficheiros afectados**:

- `layer7.inc` — `layer7_pf_default_rules_text()`: adicionar regra
  `pass quick from <layer7_bl_except> to <layer7_block_dst>`
- `layer7_blacklists.php` — secção de IPs excepcionados
- `config.json` de blacklists — campo `except_ips[]`
- `main.c` — popular tabela PF no reload

**Entregas**:
- [ ] Nova tabela PF `layer7_bl_except` criada nas regras
- [ ] Regra `pass quick` adicionada em `layer7_pf_default_rules_text()`
- [ ] Whitelist de domínios implementada no módulo C (verificada no lookup)
- [ ] IPs excepcionados editáveis na GUI (textarea)
- [ ] Sincronização GUI → tabela PF (flush + add)
- [ ] Teste: IP excepcionado acede a destino bloqueado pela blacklist
- [ ] Teste: IP normal é bloqueado pela blacklist
- [ ] Teste: domínio na whitelist não é bloqueado mesmo com categoria activa
- [ ] Documentação de precedência actualizada

---

### Bloco 7: Estatísticas e observabilidade

**Objectivo**: expor métricas de blacklist no dashboard e nos logs.

**Métricas novas no stats JSON** (`/tmp/layer7-stats.json`):

```json
{
  "blacklists": {
    "enabled": true,
    "categories_active": 5,
    "domains_loaded": 145230,
    "lookups_total": 52340,
    "hits_total": 1247,
    "hit_rate_pct": 2.38,
    "last_update": "2026-03-23T03:00:00Z",
    "top_categories_hit": [
      {"category": "adult", "hits": 890},
      {"category": "malware", "hits": 210},
      {"category": "phishing", "hits": 95},
      {"category": "gambling", "hits": 32},
      {"category": "social_networks", "hits": 20}
    ]
  }
}
```

**Integração no dashboard** (página Estado):
- Card adicional: "Blacklists: X categorias / Y domínios / Z hits"
- Tabela top categorias com hits

**Logs**:
- `bl_block: domain=xxx cat=adult ip=1.2.3.4 ttl=300`
- `bl_load: loaded 145230 domains in 5 categories (22MB)`
- `bl_update: download OK, 3 categories updated`

**Entregas**:
- [ ] Contadores no stats JSON
- [ ] Card no dashboard
- [ ] Top categorias hit
- [ ] Logs estruturados para blacklist events

---

### Bloco 8: Documentação e testes

**Objectivo**: documentar a funcionalidade e validar end-to-end.

**Documentação**:
- [ ] `docs/11-blacklists/PLANO-BLACKLISTS-UT1.md` (este documento)
- [ ] `docs/11-blacklists/MANUAL-BLACKLISTS.md` (manual de uso)
- [ ] Actualizar `CORTEX.md` com a nova funcionalidade
- [ ] Actualizar `docs/tutorial/guia-completo-layer7.md`
- [ ] Actualizar `README.md` do repositório

**Testes mínimos** (ACTUALIZADO v2):

| Teste | Esperado |
|-------|----------|
| Download da blacklist | `blacklists.tar.gz` descarregado e extraído |
| Auto-descoberta | `discovered.json` gerado com categorias e contagens |
| GUI sem discovered.json | Mensagem "Faça o download da lista primeiro" |
| Download com progresso | Log visível na GUI durante o download |
| Carga no daemon | Log `bl_load` com contagem correcta |
| Lookup de domínio da lista | `bl_block` log emitido |
| Lookup de domínio fora da lista | Nenhum bloqueio |
| Suffix matching | `sub.listed-domain.com` → bloqueado |
| Whitelist global | Domínio na whitelist → não bloqueado |
| Excepção por IP (PF) | IP em `layer7_bl_except` → acede a destino bloqueado |
| IP normal vs destino bloqueado | IP NÃO em `layer7_bl_except` → bloqueado |
| Regra PF pass antes de block | `pfctl -sr` mostra pass antes de block |
| Tabela PF flush + add | Após guardar excepções, tabela contém IPs correctos |
| Dropdown `---`/`deny` | Categoria com `---` → não carregada; `deny` → carregada |
| Aviso categoria grande | ⚠ exibido para categorias com > 1M domínios |
| Config.json separado | Daemon lê config.json sem modificar layer7.json |
| Actualização via cron | Download + discovered.json + reload + log |
| Actualização via GUI | Botão funciona, log visível, daemon recarrega |
| SIGHUP reload | Nova categoria adicionada → carregada |
| Memória | Uso aceitável (< 100MB para cenário típico) |
| Performance | Lookup < 1µs médio |
| Categoria `---` (ignorada) | Domínios não carregados |
| Top hits por categoria | Stats JSON mostra contadores por categoria |

---

## 7. Ficheiros afectados (resumo) (ACTUALIZADO v2)

### Ficheiros novos

| Ficheiro | Descrição |
|----------|-----------|
| `src/layer7d/blacklist.h` | Header do módulo de blacklists |
| `src/layer7d/blacklist.c` | Hash table FNV-1a + suffix matching + whitelist |
| `src/layer7d/bl_config.c` | Parse do `config.json` separado |
| `package/.../layer7_blacklists.php` | GUI com 4 secções (estilo SquidGuard) |
| `package/.../layer7/update-blacklists.sh` | Script de download + auto-descoberta |
| `docs/11-blacklists/PLANO-BLACKLISTS-UT1.md` | Este plano |
| `docs/11-blacklists/MANUAL-BLACKLISTS.md` | Manual de uso |

### Ficheiros alterados

| Ficheiro | Alteração |
|----------|-----------|
| `src/layer7d/main.c` | Integração blacklist no DNS callback + reload |
| `src/layer7d/Makefile` | Adicionar `blacklist.c` e `bl_config.c` ao build |
| `package/.../layer7.inc` | Helpers PHP + regra PF pass para `layer7_bl_except` |
| `package/.../layer7.xml` | Nova entrada no menu |
| `package/.../pkg-plist` | Novos ficheiros no pacote |
| `package/.../install.sh` | Setup do cron + directório blacklists |
| `CORTEX.md` | Status actualizado |

### Ficheiros NÃO alterados (v2)

| Ficheiro | Motivo |
|----------|--------|
| `src/layer7d/config_parse.c` | Config das blacklists é ficheiro separado |
| `/usr/local/etc/layer7.json` | Sem nova secção — config.json dedicado |
| `src/layer7d/policy.c` | Políticas V1 inalteradas |

---

## 8. Estimativas

### Complexidade por bloco

| Bloco | Descrição | Complexidade | Estimativa |
|-------|-----------|--------------|------------|
| 1 | Script de download | Baixa | 1 sessão |
| 2 | Módulo C (hash table) | Média-Alta | 2-3 sessões |
| 3 | Integração no daemon | Média | 1-2 sessões |
| 4 | GUI PHP | Média | 2 sessões |
| 5 | Cron job | Baixa | 1 sessão |
| 6 | Precedência e whitelist | Média | 1 sessão |
| 7 | Estatísticas | Baixa | 1 sessão |
| 8 | Documentação e testes | Baixa | 1 sessão |

**Total estimado**: 10-12 sessões de trabalho.

### Impacto no pacote

| Métrica | Antes | Depois |
|---------|-------|--------|
| Páginas GUI | 10 | 11 |
| Módulos C no daemon | ~6 | ~7 |
| Tamanho do .pkg | ~800KB | ~810KB (script é pequeno) |
| Dependências runtime | nenhuma nova | `fetch` (base FreeBSD) |
| RAM do daemon (típico) | ~5MB | ~25-30MB (com 5-10 categorias) |
| RAM do daemon (máximo) | ~5MB | ~400MB (todas as categorias) |

---

## 9. Riscos e mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| Memória excessiva com todas as categorias | Média | Alto | Limitar categorias activas; avisar na GUI; threshold |
| Download falha (servidor UT1 offline) | Baixa | Baixo | Retry; manter última versão; log do erro |
| Over-blocking (falsos positivos) | Média | Médio | Whitelist de domínios; excepções existentes |
| Performance do lookup | Baixa | Médio | Hash table O(1); benchmark no lab |
| Espaço em disco no pfSense | Baixa | Baixo | Extrair só categorias activas (~50MB max típico) |
| Licença CC-BY-SA 4.0 conflito | Muito baixa | Baixo | A lista são dados, não código; atribuição no About |
| UT1 muda formato dos ficheiros | Muito baixa | Médio | Validação no parse; fallback gracioso |
| Incompatibilidade com pfSense futuro | Baixa | Médio | Cron via API pfSense; script simples |

---

## 10. Rollback

O rollback é seguro e simples porque o subsistema é **paralelo e
independente**:

1. **Remover cron job**: `crontab -e` ou desactivar na GUI
2. **Remover ficheiros de blacklist**: `rm -rf /usr/local/etc/layer7/blacklists/`
3. **Desactivar na config**: `"blacklists": {"enabled": false}` no JSON
4. **SIGHUP**: daemon liberta a hash table e volta a funcionar como antes
5. **Reinstalar versão anterior**: o .pkg antigo não tem o módulo de blacklists

**Nenhuma funcionalidade V1 é alterada ou removida.** O policy engine,
os perfis, as políticas manuais, as excepções — tudo continua a funcionar
exactamente como antes.

---

## 11. Posicionamento comercial

Esta funcionalidade posiciona o Layer7 como:

> **"O SquidGuard moderno para pfSense CE"**

- Classificação nDPI (que o SquidGuard nunca teve)
- Categorias web UT1 (que o SquidGuard usava via Shalla/UT1)
- Enforcement PF nativo (sem proxy HTTP)
- GUI integrada no pfSense (sem interface separada)
- Actualizações automáticas (como o SquidGuard com cron)

**Diferencial competitivo**:
- pfBlockerNG faz bloqueio por listas de IPs/domínios, mas **não tem
  classificação L7** nem categorias web organizadas
- SquidGuard/E2Guardian precisam de proxy HTTP (Squid) e **não funcionam
  com HTTPS moderno** sem MITM
- Layer7 combina **nDPI + categorias web + PF** sem proxy

---

## 12. Sequência de execução recomendada (ACTUALIZADA v2)

```
Bloco 1: Script de download       ─┐
  + auto-descoberta                 ├── podem ser paralelos
  + discovered.json                 │
Bloco 2: Módulo C (hash table)    ─┘
  + whitelist interna

Bloco 3: Integração no daemon     ← depende do Bloco 2
  + bl_config.c (config.json)
  + contadores por categoria

Bloco 4: GUI PHP (SquidGuard)     ← depende do Bloco 1 (discovered.json)
  + 4 secções
  + download com log

Bloco 5: Cron job                  ← depende dos Blocos 1 e 4

Bloco 6: Excepções PF             ← depende dos Blocos 3 e 4
  + tabela layer7_bl_except
  + regra pass em layer7.inc

Bloco 7: Estatísticas             ← depende dos Blocos 3 e 4

Bloco 8: Documentação e testes    ← depende de todos
  + testes de excepção PF
  + testes de auto-descoberta
```

**Recomendação**: começar pelos Blocos 1 e 2 em paralelo, depois seguir
sequencialmente 3 → 4 → 5 → 6 → 7 → 8.

---

## 13. Pré-requisitos

Antes de iniciar a implementação:

- [x] V1 Comercial concluída e publicada
- [x] License server operacional
- [x] Versão do pacote: **v1.1.0** (confirmado)
- [x] Interfaces: **global** (confirmado)
- [x] Download: **HTTP via `fetch`** (confirmado)
- [x] Whitelist: **global** (confirmado)
- [ ] Confirmar disponibilidade de RAM no pfSense lab (mínimo 1GB)
- [ ] Verificar acesso HTTP ao servidor UT1 a partir do pfSense lab

---

## 14. Notas de compatibilidade

### Licença CC-BY-SA 4.0

A blacklist UT1 é distribuída sob **CC-BY-SA 4.0**. Isto significa:
- **Atribuição obrigatória**: mencionar a Université Toulouse Capitole
  como fonte das listas
- **Share-alike**: se redistribuirmos as listas modificadas, devem manter
  a mesma licença
- **Uso comercial permitido**: CC-BY-SA permite uso comercial

**Implementação**: nota de atribuição na GUI (rodapé da página de
blacklists) e na documentação.

### Compatibilidade com pfSense CE

- `fetch` disponível no base system FreeBSD (sem dependência adicional)
- `tar` disponível no base system FreeBSD
- Cron via `crontab` ou API pfSense
- Directórios sob `/usr/local/etc/layer7/` (já existente)
- PF tables e regras já em uso pelo Layer7

---

## 15. Decisões confirmadas (2026-03-24)

| Decisão | Escolha | Justificação |
|---------|---------|--------------|
| Interfaces | **Global** | Todas as categorias afectam as mesmas interfaces configuradas; por categoria fica para V2 |
| Versão do pacote | **v1.1.0** | Feature significativa (novo subsistema) |
| Download | **HTTP via `fetch`** | Base system FreeBSD; rsync pode não estar no pfSense |
| Hash table | **1M buckets** (`1 << 20`) | Suficiente para ~5M domínios; ajustável no header |
| Categoria adult | **Incluir mas avisar** | Aviso na GUI sobre RAM (~400MB com 4.6M domínios) |
| Whitelist | **Global** | Uma lista única de domínios nunca bloqueados por nenhuma categoria |
| Fluxo GUI | **Inspirado no SquidGuard** | URL configurável → Download com progresso/log → Categorias auto-descobertas → dropdown `---`/`deny` |
| Excepções por IP | **Nova tabela PF `layer7_bl_except`** | Regra `pass quick from <layer7_bl_except> to <layer7_block_dst>` antes da regra de bloqueio |

---

## 16. Valor complementar: nDPI + UT1

O nDPI e a blacklist UT1 são **complementares** — cobrem dimensões
diferentes do tráfego.

### O que o nDPI detecta (Layer7 V1)

O nDPI trabalha por **assinaturas de protocolo/aplicação**: reconhece
~350 apps pelo padrão do tráfego (TLS fingerprint, certificados, SNI).

Exemplos: YouTube, Facebook, WhatsApp, BitTorrent, Netflix, Steam,
OpenVPN, WireGuard, Tor.

### O que o nDPI NÃO consegue detectar

O nDPI **não sabe o que é o conteúdo de um site**. Para o nDPI, um site
de pornografia e um site de notícias são ambos "HTTPS" se não tiver
assinatura específica.

### Categorias que SÓ a UT1 oferece

| Categoria | nDPI | UT1 |
|-----------|------|-----|
| Pornografia/adulto (4.6M sites) | Não detecta | Sim |
| Apostas/gambling (8K sites) | Não detecta | Sim |
| Phishing (90K sites) | Limitado | Sim |
| Malware (50K domínios) | Limitado | Sim |
| Drogas | Não detecta | Sim |
| Violência/ódio | Não detecta | Sim |
| Warez/pirataria | Não detecta | Sim |
| Hacking/exploits | Não detecta | Sim |
| Dating/encontros | Não detecta | Sim |
| Seitas/cultos | Não detecta | Sim |
| Stalkerware | Não detecta | Sim |
| Armas | Não detecta | Sim |
| Lingerie | Não detecta | Sim |
| Encurtadores de URL | Não detecta | Sim |
| Proxy web / anonymizers | Parcial (VPN) | Sim (domínios) |

**Resumo**: nDPI detecta **aplicações** (YouTube, BitTorrent), UT1
categoriza **conteúdo de sites** (pornografia, gambling, phishing).
Juntos, cobrem tanto aplicações como conteúdo web, sem proxy HTTP,
sem MITM.

---

*Documento criado em 2026-03-23. Actualizado em 2026-03-24 (v2: decisões confirmadas, fluxo SquidGuard, excepções PF, auto-descoberta, valor nDPI vs UT1). Projecto Layer7 — Systemup Solução em Tecnologia.*
