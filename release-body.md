## Layer7 v0.2.0 — Motor Multi-Interface

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Politicas por interface** — regras separadas para LAN, WIFI, ADMIN, etc.
- **Listas de IPs/CIDRs** — bloquear apenas para IPs ou sub-redes especificos
- **Seleccao de apps nDPI** — ~350 aplicacoes e categorias com pesquisa na GUI
- **Excepcoes granulares** — multiplos hosts/CIDRs por excepcao, por interface
- **Instalacao automatica** — tabelas PF, servico e config criados automaticamente
- **Guia Completo** — tutorial com 18 seccoes

### Instalacao (um comando)

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/pfsense-layer7/main/scripts/release/install.sh && sh /tmp/install.sh
```

### Rollback

```sh
pkg delete pfSense-pkg-layer7
```

### Compatibilidade

- pfSense CE 2.7.x / 2.8.x
- FreeBSD 14 / 15

### Documentacao

- [Guia Completo](docs/tutorial/guia-completo-layer7.md)
- [CHANGELOG](docs/changelog/CHANGELOG.md)
