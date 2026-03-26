## Layer7 v1.5.1 — Limpeza total de relatórios

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **Limpar todos os dados de relatórios** — novo botão na página de Relatórios permite apagar toda a base SQLite (eventos, identity_map, daily_kpi), histórico JSONL e cursor de ingestão com um clique
- **Resolve travamentos** em servidores com milhares de páginas de eventos acumulados (9000+ páginas)
- **Confirmação obrigatória** antes da limpeza (acção irreversível)

### Inclui todas as funcionalidades anteriores

- Auditoria de segurança e robustez completa (v1.5.0)
- Categorias customizadas de blacklist (v1.4.17)
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
