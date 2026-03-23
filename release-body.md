## Layer7 v0.2.2 — Labels amigaveis de interface

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Labels amigaveis na GUI** — Interfaces de captura, politicas e excecoes agora mostram a descricao configurada da interface no pfSense, em vez de `OPT1`, `OPT2`, etc., quando houver descricao definida
- **Pacote autocontido** — `layer7d` passa a usar `libndpi.a` no build, evitando erro de `libndpi.so` ausente no pfSense
- **Install de um comando mantido** — o `install.sh` continua instalando direto do GitHub Releases, sem compilacao manual
- **Validacao de release** — o processo de build agora falha se o binario final ainda depender de `libndpi.so`
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
