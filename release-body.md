## Layer7 v1.6.3 — Scroll fix: âncoras HTML em todos os formulários

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Correção

- Adicionadas âncoras HTML a todos os formulários POST em todas as páginas
- Ao submeter um form, a página volta à secção relevante em vez de saltar para o topo
- Páginas afectadas: Settings, Blacklists, Policies, Diagnostics, Reports, Status, Groups, Exceptions, Test

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
