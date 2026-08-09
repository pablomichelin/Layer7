# layer7-tlsproxy (PoC lab)

**Estado:** PoC-2 TLS lab (`0.0.2-poc2`)  
**Lab autorizado:** `root@192.168.100.54`  
**NÃO** é runtime de produto / 20.10.

## Comandos

```bash
make && make test
# No .54, com certs em lab-certs/:
export LAYER7_TLSPROXY_LAB=1
./layer7-tlsproxy --lab-tls-listen 127.0.0.1:8443 --cert lab-certs/server.crt --key lab-certs/server.key
curl -k https://127.0.0.1:8443/
make test-tls-lab
```

## Regras

- `LAYER7_TLSPROXY_LAB=1` obrigatório para IPC/TLS.
- TLS default `127.0.0.1`; `0.0.0.0` só com `--lab-allow-any` (somente `.54`).
- Respostas afirmam `mitm_effective:false`.
- **Proibido** em `.254` / `.234` / `.235`.
- Chaves **nunca** no git (`lab-certs/` gitignored).

Ver: `docs/09-blocking/poc-layer7-tlsproxy-lab.md`
