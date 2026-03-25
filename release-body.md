## Layer7 v1.4.13 — expansão visual dos blocos administrativos

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Blocos visuais expandidos** — as páginas `Politicas`, `Grupos`, `Events`, `Diagnostics` e `Blacklist` passam a usar blocos administrativos com cabeçalhos fortes, seguindo o padrão já aplicado em `Definicoes`
- **Leitura mais clara** — listagens, filtros, formulários e áreas operacionais ficam melhor segmentados para o utilizador final
- **PT/EN mantido** — o modo bilingue continua preservado, reutilizando as legendas existentes da interface
- **Sem alteração funcional** — a release é estritamente visual; persistência, relatórios, licenciamento, upgrade e enforcement continuam com o mesmo comportamento da `v1.4.12`

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
