## Layer7 v1.5.0 — Auditoria de segurança e robustez

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Novidades

- **FIX CRITICO: blacklists no arranque** — daemon carrega blacklists UT1/custom automaticamente no startup (antes exigia SIGHUP manual)
- **FIX CRITICO: injecção em activação** — chaves com caracteres perigosos são rejeitadas antes de interpolar em JSON/shell
- **FIX CRITICO: password removida do seed.js** — admin password do license server lida de variável de ambiente
- **FIX ALTO: validação CIDR/PF** — octetos validados a 0-255, CIDRs/IPs sanitizados antes de regras PF
- **FIX ALTO: XSS/JS** — confirm() e labels Chart.js corrigidos com json_encode() em 6 páginas PHP
- **Varias correcções de robustez** — NULL safety no daemon, swap seguro de blacklists, lock atómico, validação de URL/whitelist, ordenação de políticas no simulador

### Inclui todas as funcionalidades anteriores

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
