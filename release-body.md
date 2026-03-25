## Layer7 v1.4.11 — controlo de versao e links sincronizados

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Release de controlo** — consolida a entrega funcional publicada na `v1.4.10` sob uma nova versao patch para manter o historico consistente
- **Documentacao sincronizada** — links, exemplos de install/upgrade e referencias publicas passam a apontar para `v1.4.11`
- **Mesmo comportamento funcional da v1.4.10** — relatorios estilo NGFW, log detalhado opcional, seleccao por interface e retencao separada permanecem incluidos

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
