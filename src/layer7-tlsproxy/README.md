# layer7-tlsproxy (PoC)

Helper MITM **opt-in**, processo separado do `layer7d`.

| Fase | Estado |
|------|--------|
| PoC-0 idle | **este código** — sem bind / sem intercept |
| Empacotamento `.pkg` | **não** |
| Produção `.254` | **proibido** bind |

Documentação: [`../../docs/09-blocking/poc-layer7-tlsproxy-lab.md`](../../docs/09-blocking/poc-layer7-tlsproxy-lab.md)

```sh
cd src/layer7-tlsproxy && make test
```
