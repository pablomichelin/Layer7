# Layer7 para pfSense CE

Pacote **open source** para **pfSense CE**: classificação Layer 7 (motor baseado em **nDPI**), políticas (`monitor`, `tag`, `allow`, `block`), enforcement via PF e integração DNS/host onde aplicável, GUI no ecossistema pfSense.

**Estado:** início de execução — estrutura de repo e documentação; daemon/pacote instalável ainda não.

| | |
|--|--|
| **Repositório** | <https://github.com/pablomichelin/pfsense-layer7> |
| **Licença** | BSD-2-Clause (ver `LICENSE`) |
| **Versão** | `0.x` até fechar V1 (SemVer) |

## Leitura rápida

1. [`00-LEIA-ME-PRIMEIRO.md`](00-LEIA-ME-PRIMEIRO.md)
2. [`CORTEX.md`](CORTEX.md) · [`AGENTS.md`](AGENTS.md)
3. Charter resumido: [`docs/00-overview/product-charter.md`](docs/00-overview/product-charter.md)
4. ADRs: [`docs/03-adr/`](docs/03-adr/) · Core V1: [`docs/core/`](docs/core/)

Os documentos numerados `01-`…`16-` na raiz são o **planejamento mestre** detalhado; `docs/` concentra SSOT operacional e decisões.

## PoC nDPI (Bloco 3)

No **FreeBSD** (builder): `./scripts/build/build-poc-freebsd.sh` → `build/poc-ndpi/layer7_ndpi_poc arquivo.pcap`. Ver `src/poc_ndpi/README.md`.

## Estrutura do repositório

```text
docs/           # charter, arquitetura, roadmap, ADRs, changelog, runbooks…
docs/poc/       # registro de resultados do PoC nDPI
package/        # pfSense-pkg-layer7 (port — esqueleto)
src/            # layer7d, classifier, policy, poc_ndpi…
webgui/         # XML / PHP / priv (futuro)
scripts/        # build, release, lab, diagnostics
tests/          # functional, traffic, package, lab, fixtures
samples/        # exemplos de config/log/política
```

## Distribuição (V1)

Artefato **`.txz`** + releases no GitHub; não instalar “direto do clone” em produção. Ver [ADR-0002](docs/03-adr/ADR-0002-distribuicao-artefato-txz.md).

## Contribuir

Um bloco por vez; PR com objetivo, teste mínimo, rollback e docs (template em [`.github/pull_request_template.md`](.github/pull_request_template.md)).
