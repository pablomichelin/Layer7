## Layer7 v1.5.2 — Fix limpeza de relatórios

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Correcção

- **Fix crítico na limpeza de relatórios** — ao clicar "Limpar todos os dados", o cursor de ingestão agora é posicionado no fim do ficheiro de log actual, evitando que todo o histórico seja reimportado imediatamente na mesma carga da página (causava o efeito de "dados não foram apagados")

### Inclui todas as funcionalidades anteriores

- Botão "Limpar todos os dados" na página de Relatórios (v1.5.1)
- Auditoria de segurança e robustez completa (v1.5.0)
- Categorias customizadas de blacklist (v1.4.17)
- Anti-bypass DNS multi-camada (DoH/DoT/DoQ/iCloud Private Relay)
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
