# layer7-tlsproxy (PoC lab)

**0.0.4-poc4** — lab `root@192.168.100.54`

```bash
make && make STUB=/path/to/poc-upstream-stub.py test test-poc3 test-poc4
```

`--upstream` só aceita `127.0.0.1`. Nunca em produção.
