# layer7-tlsproxy (20.10b + PoC lab)

**0.1.1** — produto: `--product-listen` + `LAYER7_TLSPROXY_PRODUCT=1` (só loopback).  
Lab `.54`: `LAYER7_TLSPROXY_LAB=1`. Nunca afirma `mitm_effective=true`.

```bash
make && make test
# produto (loopback):
LAYER7_TLSPROXY_PRODUCT=1 ./layer7-tlsproxy --product-listen 127.0.0.1:8443 \
  --cert /path/ca.crt --key /path/ca.key --block-sni blocked.test
# lab PoC:
make STUB=/path/to/poc-upstream-stub.py test test-poc3 test-poc4
```

PF rdr selectivo e gate rc vivem no pacote PHP (`layer7.inc` / `rc.d`).
