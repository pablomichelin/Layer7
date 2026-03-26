# Layer7 para pfSense CE

> **Um produto [Systemup](https://www.systemup.inf.br)**

Pacote comercial para **pfSense CE**: classificacao Layer 7 em tempo real via **nDPI** (~350 aplicacoes detectaveis), politicas granulares por interface/IP/grupo/horario (`monitor`, `tag`, `allow`, `block`), enforcement via PF tables e GUI completa com 10 paginas no ecossistema pfSense.

| | |
|--|--|
| **Desenvolvido por** | [Systemup](https://www.systemup.inf.br) Solucao em Tecnologia |
| **Desenvolvedor principal** | Pablo Michelin |
| **Website** | <https://www.systemup.inf.br> |
| **Distribuicao publica** | <https://github.com/pablomichelin/Layer7> |
| **Licenca** | EULA (ver `LICENSE`) |
| **Versao actual** | **1.6.3** |
| **Compatibilidade** | pfSense CE 2.7.x / 2.8.x - FreeBSD 14/15 |

## O que faz

- **Identifica** aplicacoes e protocolos em tempo real (BitTorrent, YouTube, TikTok, redes sociais, VPN, streaming, etc.)
- **Bloqueia** trafego por aplicacao, categoria, interface, IP de origem, grupo de dispositivos e horario
- **Monitoriza** sem interferir no trafego
- **Tagga** IPs em tabelas PF customizadas para regras avancadas
- **Excepciona** IPs, sub-redes e interfaces especificas
- **Gestao de frota** para 50+ firewalls com scripts automatizados
- **Protege** com licenciamento Ed25519 e fingerprint de hardware

## Funcionalidades v1.0

- **Perfis de servico rapidos** — 15 perfis built-in (YouTube, Facebook, Instagram, TikTok, WhatsApp, Twitter/X, LinkedIn, Netflix, Spotify, Twitch, Redes Sociais, Streaming, Jogos, VPN/Proxy, AI Tools) com criacao de politica por 1 clique
- **Politicas por interface** — regras separadas para LAN, WIFI, ADMIN, etc.
- **Listas de IPs/CIDRs** — bloquear apenas para IPs ou sub-redes especificos
- **Grupos de dispositivos** — grupos nomeados (ex: "Funcionarios", "Visitantes") reutilizaveis em politicas
- **Sites/hosts manuais** — adicionar dominios manualmente nas politicas, com match por host e subdominio
- **Agendamento por horario** — politicas com dias da semana e faixa horaria (suporte overnight)
- **Seleccao de apps nDPI** — lista com pesquisa de ~350 aplicacoes e categorias
- **Seleccao em massa** — botoes para selecionar tudo/limpar interfaces e selecionar itens visiveis
- **Dashboard operacional** — contadores em tempo real, top 10 apps bloqueadas, top 10 clientes
- **Pagina de categorias nDPI** — todas as apps organizadas por categoria com pesquisa
- **Teste de politica** — simulacao completa na GUI com veredicto visual (block/allow/monitor)
- **Backup e restore** — export/import de configuracao completa em JSON
- **Bloqueio QUIC selectivo** — toggle para forcar fallback TCP/TLS e melhorar visibilidade SNI
- **Anti-bypass DNS** — bloqueio automatico de DoT/DoQ (porta 853), deteccao nDPI de DoH, e NXDOMAIN via Unbound para dominios de bypass conhecidos
- **Licenciamento** — verificacao Ed25519 offline, fingerprint de hardware, grace period 14 dias, CLI de activacao
- **Actualizacao pela GUI** — botao em Definicoes para verificar e instalar a ultima versao
- **GUI bilingue PT/EN** — principais páginas administrativas organizadas em blocos visuais separados, alinhados ao estilo administrativo do pfSense
- **Blacklists com categorias customizadas** — na mesma tela de Blacklists, permite criar categorias locais com sites proprios e estender categorias UT1 existentes com dominios adicionais
- **Relatorios estilo NGFW** — historico executivo separado do log detalhado, com retencao propria e seleccao de interfaces para reduzir uso local
- **Excepcoes granulares** — multiplos hosts/CIDRs por excepcao, por interface
- **GUI completa** — 10 paginas (Estado, Definicoes, Politicas, Grupos, Categorias, Teste, Excepcoes, Events, Diagnostics)
- **Protocolos customizados** — ficheiro de regras editavel em runtime (sem recompilacao)
- **Fleet management** — scripts para actualizar 50+ firewalls por SSH

## Guia de utilização

**[Guia Completo Layer7](docs/tutorial/guia-completo-layer7.md)** — tutorial detalhado com 18 secções: instalação, configuração, todos os menus da GUI, formato JSON, exemplos práticos, CLI, gestão de frota, troubleshooting e glossário.

## Leitura rápida

1. [`docs/tutorial/guia-completo-layer7.md`](docs/tutorial/guia-completo-layer7.md) — **tutorial completo**
2. [`CORTEX.md`](CORTEX.md) — estado do projecto e decisões
3. [`docs/changelog/CHANGELOG.md`](docs/changelog/CHANGELOG.md) — histórico de alterações
4. [`docs/core/ndpi-update-strategy.md`](docs/core/ndpi-update-strategy.md) — estratégia nDPI para frota

## Instalação rápida

```bash
# No pfSense (SSH como root):
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh
```

Depois aceda a **Services > Layer 7** na GUI do pfSense.

Ver instruções completas no [Guia Completo](docs/tutorial/guia-completo-layer7.md#3-instalacao).

## Estrutura do repositório

```text
docs/              # documentação completa (tutorial, ADRs, changelog, runbooks)
docs/tutorial/     # guia completo de utilização
package/           # pfSense-pkg-layer7 (port FreeBSD + GUI PHP)
src/layer7d/       # daemon C (main, capture nDPI, policy engine, enforce PF)
src/common/        # tipos partilhados
scripts/release/   # fleet-update.sh, fleet-protos-sync.sh
scripts/lab/       # automação de lab (sync, build, install, test)
scripts/package/   # smoke tests, check port files
samples/config/    # exemplos de layer7.json
```

## Distribuição

Artefato **`.pkg`** via GitHub Releases do repositório público de distribuição `pablomichelin/Layer7`. Ver [Guia Completo §3](docs/tutorial/guia-completo-layer7.md#3-instalacao).

Para frota (múltiplos firewalls): [`scripts/release/fleet-update.sh`](scripts/release/fleet-update.sh) e [`scripts/release/fleet-protos-sync.sh`](scripts/release/fleet-protos-sync.sh).

## CI

[![smoke layer7d](https://github.com/pablomichelin/pfsense-layer7/actions/workflows/smoke-layer7d.yml/badge.svg)](https://github.com/pablomichelin/pfsense-layer7/actions/workflows/smoke-layer7d.yml) — compila `layer7d` e corre smoke em Ubuntu (ver [`docs/tests/README.md`](docs/tests/README.md)).

## Contribuir

Um bloco por vez; PR com objectivo, teste mínimo, rollback e docs (template em [`.github/pull_request_template.md`](.github/pull_request_template.md)).

---

**Layer7 para pfSense CE** e um produto da [Systemup](https://www.systemup.inf.br) Solucao em Tecnologia.

Desenvolvedor principal: **Pablo Michelin**

Layer7 para pfSense CE NAO e afiliado com Netgate ou o projecto pfSense. pfSense e uma marca registada da Electric Sheep Fencing LLC d/b/a Netgate.
