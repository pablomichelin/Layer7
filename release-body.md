## Layer7 v1.4.12 — ajuste visual da GUI Settings

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Settings em blocos** — a página `Definicoes` passa a usar blocos visuais separados com cabeçalhos fortes, no estilo administrativo do pfSense
- **Leitura mais clara** — definições gerais, logging/debug, captura/interfaces, licença, backup/restore, relatórios e actualização ficam visualmente isolados
- **PT/EN mantido** — os novos títulos dos blocos foram traduzidos para inglês sem alterar o selector de idioma
- **Sem alteração funcional** — a release é estritamente visual; persistência, relatórios, licenciamento e upgrade continuam com o mesmo comportamento da `v1.4.11`

### Inclui todas as funcionalidades anteriores

- Anti-bypass DNS multi-camada (DoH/DoT/DoQ/iCloud Private Relay)
- Bloqueio por destino (sites/apps) via DNS + nDPI
- ~350 apps nDPI detectaveis (YouTube, Facebook, TikTok, etc.)
- Politicas por interface, IP/CIDR, app e categoria
- GUI administrativa integrada ao pfSense CE

### Instalacao (um comando)

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/Layer7/main/install.sh && sh /tmp/install.sh
```

### Rollback

```sh
pkg delete pfSense-pkg-layer7
```

### Compatibilidade

- pfSense CE 2.7.x / 2.8.x / 25.x
- FreeBSD 14 / 15

### Documentacao

- [Guia Completo](docs/tutorial/guia-completo-layer7.md)
- [CHANGELOG](docs/changelog/CHANGELOG.md)
