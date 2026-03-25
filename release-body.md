## Layer7 v0.3.2 — actualizacao via GUI

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Actualizacao pela GUI** — novo botao "Verificar actualizacao" na pagina Definicoes. Consulta automaticamente o GitHub Releases, compara versoes, e permite actualizar com um clique. O daemon e parado/reiniciado e todas as configuracoes sao preservadas.

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
