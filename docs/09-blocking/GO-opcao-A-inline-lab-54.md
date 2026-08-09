# GO humano — Opção A (S1 inline lab)

**Data:** `2026-08-09`  
**Decisão:** **GO Opção A** — path inline **só** no lab `192.168.100.54`  
**Operador:** confirmou rede privada (só operador+agent), sem DHCP no `.54`, ninguém usa `.54` como gateway.

## Escopo autorizado

1. `iptables`/`nft` REDIRECT (e forwarding) **apenas** no host `.54`.  
2. Cliente de teste via **network namespace** no próprio `.54` (veth) **ou** VM descartável futura — **não** `.234`/`.235`.  
3. Medir S1/S2 no path redirect → `layer7-tlsproxy` → upstream lab.  
4. IPC lab / mock DECIDE; `mitm_effective` continua **false** no produto.  

## Proibido

| Proibido | Motivo |
|----------|--------|
| `.254` / `.234` / `.235` | Produção |
| Empacotar / `mitm_runtime_available=true` / GO produto 20.10 | Exige GO separado |
| Squid | Rejeitado |
| CA lab em clientes de produção | S7 / trust |

## Rollback

```bash
# no .54
/opt/layer7-poc/src/lab-inline-down.sh
# sysctl / iptables / netns removidos; processos mortos
```

## Risco residual

Baixo: blast radius = `.54` + ns local. Sem impacto no pfSense de borda se regras ficarem só no `.54`.

## Resultado (mesmo dia)

**PASS** — `N=50 sh ./measure-s1-inline.sh` em `.54`:

- S2 inline p95 ≈ 15.6 ms, errors=0  
- `orig_dst=1.1.1.1:443` (SO_ORIGINAL_DST)  
- S1 CPU busy ≈ 13%  
- Evidência: `docs/tests/evidence/20260809T045500Z-s1-inline-opcao-a-54/`

Nota técnica: REDIRECT entrega no IP da iface de entrada (`10.67.67.1:8443`); o PoC escuta `0.0.0.0:8443` com `--lab-allow-any` **só** neste path lab.
