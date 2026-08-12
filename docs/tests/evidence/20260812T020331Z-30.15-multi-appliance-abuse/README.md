# Evidência — 30.15 / BG-121 / GA5.12

**UTC:** `20260812T020331Z`  
**Passo:** alerta abuso multi-appliance (fase 1 = só alerta)  
**Decisão 7:** `max_activations` **não** introduzido (só alerta).

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | Tornar T1 (mesma chave em múltiplos `hardware_id`) visível no painel; corrigir A-08 |
| Impacto | License-server backend + dashboard UI; **sem** alterar package/daemon/`.254`/release |
| Risco | Baixo — só alerta; sem hard-limit; rebind autorizado filtrado |
| Teste | Unit `multi-appliance-abuse.test.js` (5/5) + `npm test` backend (133/133) |
| Rollback | Reverter commit; em deploy futuro, imagem anterior do portal/API |

## Política de detecção

- Lookback: **30 dias** em `activations_log` ∪ `check_ins_log`
- Alerta se ≥2 `hardware_id` distintos **e** pelo menos um **não** explicado por bind actual nem por audit `license_rebound` sucesso
- Rebind A→B com histórico A+B → **sem** alerta (anti falso positivo)
- Terceiro appliance C após rebind → alerta

## Fora de escopo neste passo

- Deploy live do license-server
- `max_activations` / bloqueio de activate
- `.254`, CF/DNS, GitHub Release, MITM, IPv6, AP4

## Ficheiros de saída

- `unit-multi-appliance.txt` — gate GA5.12 (cenário abuso + rebind)
- `backend-npm-test.txt` — regressão suite backend
