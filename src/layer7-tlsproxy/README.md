# layer7-tlsproxy (PoC lab)

**Estado:** PoC-1 IPC (lab-only)  
**NÃO** é runtime de produto / 20.10.

## Comandos

```bash
make
./layer7-tlsproxy --health
# IPC (exige LAYER7_TLSPROXY_LAB=1; socket só em /tmp):
LAYER7_TLSPROXY_LAB=1 ./layer7-tlsproxy --ipc-serve --sock /tmp/layer7-tlsproxy-poc.sock &
LAYER7_TLSPROXY_LAB=1 ./layer7-tlsproxy --ipc-ping --sock /tmp/layer7-tlsproxy-poc.sock
make test
```

## Regras

- Sem bind TCP / intercept / PF / CA.
- Sem `LAYER7_TLSPROXY_LAB=1` → IPC e bind recusados.
- Socket PoC **apenas** sob `/tmp/` (nunca `/var/run/layer7/mitm.sock`).
- Respostas IPC afirmam `mitm_effective:false`.
- **Proibido** em `.254` / `.234` / `.235` de produção.
- **Não** incluir no `pkg-plist` sem GO de empacotamento.

Ver: `docs/09-blocking/poc-layer7-tlsproxy-lab.md`
