## Layer7 v1.6.7 — Fix SIGSEGV: crash ao activar blacklists

Pacote Layer 7 para pfSense CE com classificacao em tempo real via nDPI.

### Correções (v1.6.7 + v1.6.6)

**v1.6.7 — SIGSEGV ao activar blacklists:**
- `blacklist.c`: cast inválido `(const char **)bl->cats` — `bl->cats` é `char[64][48]`, não `char**`
- SIGUSR1 para stats interpretava os primeiros 8 bytes de "adult" como ponteiro → SIGSEGV imediato
- Correcção: nova API `l7_blacklist_get_cat_name(bl, idx)` / `l7_blacklist_get_cat_hit_count(bl, idx)`

**v1.6.6 — Parser de blacklists nunca carregava regras:**
- `bl_config.c`: `match_key()` avançava `p` além do `"` ao falhar comparação; `rules[]` era ignorado
- Efeito: `n_rules=0` → daemon sem blacklists → tabelas `layer7_bld_N` sempre vazias
- Correcção: `match_key()` salva e restaura ponteiro em qualquer falha de validação

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
