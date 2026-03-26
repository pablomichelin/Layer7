## Layer7 v1.6.1 — Blacklists simplificadas + Backup completo

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Principais mudanças

- **Blacklists: removida opção de editar categorias** — mantém criar novas e apagar; datalist de categorias UT1 removida
- **Backup completo** — export/import inclui configuração de blacklists (regras, whitelist, categorias personalizadas, definições de update)
- Permite restaurar TODAS as configurações do pacote após formatação da máquina

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
