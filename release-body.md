## Layer7 v1.6.5 — Fix CI smoke layer7d

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Correção

- **GitHub Actions (smoke)** falhava no job Linux com `Makefile:20: *** missing separator`
- **Causa raiz**: o script usava `make` (GNU make no Ubuntu), mas o `src/layer7d/Makefile` usa sintaxe BSD make (`.if`)
- **Fix**: `scripts/package/smoke-layer7d.sh` agora detecta e prioriza `bmake` (fallback para `make`)
- **Fix**: workflow `.github/workflows/smoke-layer7d.yml` agora instala `bmake` no runner Ubuntu

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
