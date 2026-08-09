# GO humano — produto MITM (passo 20.10)

**Data:** `2026-08-09`  
**Decisão:** **GO produto** — autoriza início do passo **20.10**  
**Operador:** confirmou neste chat (“GO para fazer o que deve ser feito baseado no nosso plano”)

## Autorizado

1. Registar GO nos SSOTs (CORTEX, plano, START-HERE, checklist).  
2. Empacotar `layer7-tlsproxy` no `pfSense-pkg-layer7` com **default OFF**.  
3. `mitm_runtime_available=true` **quando** o binário existir no sistema.  
4. Avançar **20.10** em blocos auditáveis (sem Squid; sem overclaim NGFW).

## Ainda proibido neste GO (até bloco respectivo PASS)

| Proibido | Até |
|----------|-----|
| Intercept / PF rdr / bind TLS de produto | **20.10b PASS** (`1.9.41`) — gated; lab only neste bloco |
| `mitm_effective=true` | gates + **intercept_ready** (não activar em `.254` sem GO) |
| Activar por defeito em upgrade | Nunca |
| Intercept em `.254`/`.234`/`.235` sem smoke S8 OFF + plano | Controlo explícito |
| Squid / `pfSense-pkg-squid` | Permanente |

## Ordem de execução (pós-GO)

```text
20.10a — runtime no .pkg + rc default OFF + runtime_available
       + intercept_ready=false (este bloco)
20.10b — listen selectivo + PF rdr + block page HTTPS
20.11  — GI2/GI3 lab
```

## Rollback

- `mitm.enabled=false`; `service layer7_tlsproxy stop` (se existir).  
- Pacote: voltar a **`1.9.38`** (sem helper) ou build com helper ausente.  
- Enforce pin: **`1.9.8`** (não misturar com lab MITM).
