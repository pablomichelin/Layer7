## Layer7 v1.6.6 — Fix crítico: blacklists nunca carregavam no daemon

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Correção

- **BUG CRÍTICO**: blacklists (categorias web UT1) nunca bloqueavam — `layer7_bld_N` sempre vazia
- **Causa raiz**: `bl_config.c` — `match_key()` avançava o ponteiro além do `"` ao falhar comparação; todas as chaves do JSON (incluindo `"rules"`) eram ignoradas após `"enabled"`
- **Efeito**: `n_rules=0` → daemon operava sem blacklists → regras PF referenciavam tabelas sempre vazias
- **Fix**: `match_key()` agora salva e restaura o ponteiro em qualquer falha de validação

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
