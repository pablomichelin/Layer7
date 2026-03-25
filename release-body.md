## Layer7 v1.4.10 — relatorios estilo NGFW

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Relatorios estilo NGFW** — historico executivo separado do log detalhado para reduzir uso local
- **Log detalhado opcional** — operador pode activar/desactivar a gravacao detalhada em SQLite
- **Escopo por interface** — log detalhado pode ser limitado a uma ou mais interfaces
- **Retencao separada** — historico executivo e log detalhado passam a ter retenções independentes
- **Paginacao compacta** — a tela de relatorios deixa de renderizar milhares de paginas no HTML

### Inclui todas as funcionalidades anteriores

- Anti-bypass DNS multi-camada (DoH/DoT/DoQ/iCloud Private Relay)
- Bloqueio por destino (sites/apps) via DNS + nDPI
- ~350 apps nDPI detectaveis (YouTube, Facebook, TikTok, etc.)
- Politicas por interface, IP/CIDR, app e categoria
- GUI completa com 6 paginas

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
