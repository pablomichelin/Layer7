# layer7-tlsproxy (PoC lab)

**0.0.5-poc5** — lab `root@192.168.100.54`  
`--lab-transparent` + Opção A (netns/REDIRECT). Nunca em produção.

```bash
make && make STUB=/path/to/poc-upstream-stub.py test test-poc3 test-poc4
N=50 sh ./measure-s1-inline.sh   # root; lab-inline-up/down
```

`--upstream` só aceita `127.0.0.1`. Sem `LAYER7_TLSPROXY_LAB=1` → exit 3.
