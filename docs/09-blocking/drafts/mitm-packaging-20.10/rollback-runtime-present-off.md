# Rollback — runtime presente e OFF (prep 20.10 / S8)

**Estado:** canónico para checklist item 7  
**Lab:** `192.168.100.54` (PoC) — **não** `.254`  
**Evidência:** [`../../../tests/evidence/20260809T050000Z-s8-runtime-present-off-54/`](../../../tests/evidence/20260809T050000Z-s8-runtime-present-off-54/)

## Princípio

Ter o binário `layer7-tlsproxy` no disco **não** pode activar intercept.
Com serviço OFF / inline down:

- sem bind de intercept;
- sem REDIRECT/netns;
- `mitm_effective_claim=false` no health do PoC;
- no produto: `mitm_effective=false` e `mitm_runtime_available=false` até GO.

## Rollback lab (`.54`)

```bash
ssh root@192.168.100.54 'cd /opt/layer7-poc/src && sh ./lab-inline-down.sh'
# opcional: matar só o binário PoC (NÃO pkill -f layer7… numa sessão SSH remota)
# pgrep -a layer7-tlsproxy && kill <pid>
```

## Rollback futuro (após empacotar — ainda sem GO)

| Nível | Acção |
|-------|--------|
| rcvar | `sysrc layer7_tlsproxy_enable=NO` + `service layer7_tlsproxy stop` |
| Config | `mitm.enabled=false` + reload `layer7d` |
| Pacote | Build com `WITH_LAYER7_TLSPROXY=no` (default deste draft) |
| Release | Rollback para `1.9.38` / `1.9.37` sem helper |
| Enforce | Pin `1.9.8` — **não** misturar com lab MITM |

## Critérios PASS S8 (runtime presente OFF)

| # | Critério |
|---|----------|
| 1 | Binário existe |
| 2 | Nenhum processo tlsproxy a correr |
| 3 | Sem listener 443/8443 do helper |
| 4 | Sem netns/REDIRECT Opção A |
| 5 | Health PoC: `mitm_effective_claim=false`, `intercept=false` |
| 6 | Tráfego directo (ex.: `http://example.com`) sem path MITM |

**FAIL:** qualquer intercept, REDIRECT activo, ou claim `mitm_effective=true`.
