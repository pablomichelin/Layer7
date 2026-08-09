# layer7-tlsproxy (PoC lab)

**Estado:** PoC-3 (`0.0.3-poc3`) — SNI bypass/block + página HTTPS  
**Lab:** `root@192.168.100.54`

```bash
export LAYER7_TLSPROXY_LAB=1
./layer7-tlsproxy --lab-tls-listen 127.0.0.1:8443 \
  --cert lab-certs/server.crt --key lab-certs/server.key \
  --block-sni blocked.test --bypass-sni bank.example
curl -k --resolve blocked.test:8443:127.0.0.1 https://blocked.test:8443/
```

Regras: sem lab env → recusa; `mitm_effective` nunca true; nunca em `.254`/`.234`/`.235`.
