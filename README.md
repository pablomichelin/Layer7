# Layer7 para pfSense CE

Pacote **open source** para **pfSense CE**: classificação Layer 7 em tempo real via **nDPI** (~350 aplicações detectáveis), políticas granulares por interface/IP (`monitor`, `tag`, `allow`, `block`), enforcement via PF tables e GUI completa no ecossistema pfSense.

| | |
|--|--|
| **Repositório** | <https://github.com/pablomichelin/pfsense-layer7> |
| **Licença** | BSD-2-Clause (ver `LICENSE`) |
| **Versão actual** | **0.2.7** (enforcement PF integrado ao filtro pfSense) |
| **Compatibilidade** | pfSense CE 2.7.x / 2.8.x · FreeBSD 14/15 |

## O que faz

- **Identifica** aplicações e protocolos em tempo real (BitTorrent, YouTube, TikTok, redes sociais, VPN, streaming, etc.)
- **Bloqueia** tráfego por aplicação, categoria, interface e IP de origem
- **Monitoriza** sem interferir no tráfego
- **Tagga** IPs em tabelas PF customizadas para regras avançadas
- **Excepciona** IPs, sub-redes e interfaces específicas
- **Gestão de frota** para 50+ firewalls com scripts automatizados

## Funcionalidades v0.2.7

- **Políticas por interface** — regras separadas para LAN, WIFI, ADMIN, etc.
- **Listas de IPs/CIDRs** — bloquear apenas para IPs ou sub-redes específicos
- **Sites/hosts manuais** — adicionar domínios manualmente nas políticas, com match por host e subdomínio observado
- **Selecção de apps nDPI** — lista com pesquisa de ~350 aplicações e categorias
- **Selecção em massa** — botões para selecionar tudo/limpar interfaces e selecionar itens visíveis nas listas nDPI
- **Visualização das listas** — ação `Ver listas` para inspeccionar tudo o que a política cobre antes de editar
- **Excepções granulares** — múltiplos hosts/CIDRs por excepção, por interface
- **GUI completa** — 6 páginas (Estado, Definições, Políticas, Excepções, Events, Diagnostics)
- **`layer7d --list-protos`** — enumera protocolos/categorias nDPI em JSON
- **Protocolos customizados** — ficheiro de regras editável em runtime (sem recompilação)
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
fetch -o /tmp/layer7.pkg https://github.com/pablomichelin/pfsense-layer7/releases/download/v0.2.6/pfSense-pkg-layer7-0.2.6.pkg
IGNORE_OSVERSION=yes pkg add -f /tmp/layer7.pkg
sysrc layer7d_enable=YES
service layer7d onestart
layer7d -V   # deve mostrar: 0.2.6
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

Artefato **`.pkg`** via GitHub Releases. Ver [Guia Completo §3](docs/tutorial/guia-completo-layer7.md#3-instalacao).

Para frota (múltiplos firewalls): [`scripts/release/fleet-update.sh`](scripts/release/fleet-update.sh) e [`scripts/release/fleet-protos-sync.sh`](scripts/release/fleet-protos-sync.sh).

## CI

[![smoke layer7d](https://github.com/pablomichelin/pfsense-layer7/actions/workflows/smoke-layer7d.yml/badge.svg)](https://github.com/pablomichelin/pfsense-layer7/actions/workflows/smoke-layer7d.yml) — compila `layer7d` e corre smoke em Ubuntu (ver [`docs/tests/README.md`](docs/tests/README.md)).

## Contribuir

Um bloco por vez; PR com objectivo, teste mínimo, rollback e docs (template em [`.github/pull_request_template.md`](.github/pull_request_template.md)).
