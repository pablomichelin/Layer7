## Layer7 v0.2.7 — enforcement PF integrado ao filtro pfSense

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Regras do pacote no filtro ativo do pfSense** — o XML do pacote agora declara `<filter_rules_needed>` para que o pfSense CE inclua automaticamente as regras de bloqueio do Layer7 no ruleset ativo durante `filter reload`
- **Bloqueio operacional por origem** — IPs adicionados a `<layer7_block>` pelo daemon passam a ser bloqueados automaticamente sem necessidade de regra PF manual externa
- **Persistencia apos reload/reboot** — a regra do pacote reaparece automaticamente em cada `filter_configure()` do pfSense

### Instalacao (um comando)

```sh
fetch -o /tmp/install.sh https://raw.githubusercontent.com/pablomichelin/pfsense-layer7/main/scripts/release/install.sh && sh /tmp/install.sh
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
