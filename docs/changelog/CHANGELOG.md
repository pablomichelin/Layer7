# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/).

## [Unreleased]

### Added
- Scaffold do port `package/pfSense-pkg-layer7/` (Makefile, plist, XML, PHP informativo, rc.d, sample JSON, hooks pkg) — **código no repo; lab não validado**.
- `src/layer7d/main.c` (daemon mínimo: syslog, stat em path de config, loop).
- `docs/04-package/package-skeleton.md`, `docs/04-package/validacao-lab.md`, `docs/05-daemon/README.md`.
- `package/pfSense-pkg-layer7/LICENSE` para build do port isolado.

### Changed
- Documentação alinhada: nada de build/install/GUI marcado como validado sem evidência de lab.
- Port compila `layer7d` em C (`PORTVERSION` conforme Makefile).

### Fixed (código)
- `rc.d/layer7d` usa `daemon(8)` para arranque em background.

## [0.0.1] - 2026-03-17

### Added
- Documentação-mestre na raiz (`00-`…`16-`, `AGENTS.md`, `CORTEX.md`) e primeiro push ao GitHub.
