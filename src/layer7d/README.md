# layer7d (fonte)

`main.c`: **`layer7_on_classified_flow`** (nDPI chamará após classificar; hoje só CLI **`-e`**). **`layer7_pf_enforce_decision`** em **`enforce.c`**. **`tag_table`**, **SIGHUP**, **SIGUSR1**. `-t` → dry-run; **`-e IP APP [CAT]`** → uma decisão + `pfctl -T add` (lab); **`-n`** com **`-e`** = dry sem pfctl.

Versão embutida: ficheiro **`version.str`** (uma linha, ex. `"dev"`). O port gera `${WRKSRC}/version.str` a partir de **PORTVERSION**.

```sh
# a partir da raiz do clone
# build local (recomendado no builder)
make -C src/layer7d
src/layer7d/layer7d -V
# ou, a partir de src/layer7d: make && ./layer7d -V

# build manual (equivalente)
cc -Wall -Wextra -O2 -I. -I../common -o layer7d main.c config_parse.c policy.c enforce.c
./layer7d -V
./layer7d -t -c ../../samples/config/layer7-minimal.json
./layer7d -n -c ../../samples/config/layer7-enforce-smoke.json -e 10.0.0.100 BitTorrent
./layer7d -h
```

Para ver **block** no dry-run na regra BitTorrent do sample, ative `enabled: true` em `p-blk-bt-001`.

Smoke (raiz do clone): `sh scripts/package/smoke-layer7d.sh` — usa este Makefile com **`OUT=layer7d-smoke`** e **`VSTR_DIR`** temporário. **Syslog remoto:** `syslog_remote` + `syslog_remote_host` + porta (UDP RFC 3164).
