# Auditoria adversária — GO teste MITM controlado `20260809T215442Z`

## Ataques considerados

| # | Hipótese | Resultado | Evidência |
|---|----------|-----------|-----------|
| A1 | Rdr `from any` / blast LAN | **Rejeitada** | `07-pf-audit.txt` — só `<layer7_mitm_src>`→`<layer7_mitm_dst>` |
| A2 | Anti-QUIC global / inet6 | **Rejeitada** | regra UDP/443 só tabelas MITM; sem `from any`/`to any`/`inet6` |
| A3 | Edge PASS só com `--disable-quic` | **Rejeitada** | harness sem flags; `EDGE_BLOCK_OK`; screenshot block page |
| A4 | Bypass TLS / interstitial como PASS | **Rejeitada** | `EDGE_INTERSTITIAL=NO`; política sem `--ignore-certificate-errors` |
| A5 | Peer = CA (KEY_USAGE) | **Rejeitada** | leaf `CA:FALSE` + serverAuth + SAN `mitm-lab.test` |
| A6 | Escopo alargado (outro host/domínio) | **Rejeitada** | tabelas só `.24` e `198.18.0.10`; `NEG_SCOPE_OK` |
| A7 | Trust CA sem necessidade (falso positivo) | **Rejeitada** | `09-negatives-ca.txt` — sem CA → sem block page limpa (`NEG_CA=PASS`) |
| A8 | Residuais pós-teste | **Rejeitada** | `93-post-state.txt` + reconfirm: EFF/GATE/RDR/QUIC/8443=0; CA appliance ausente; ATQ=0 |
| A9 | Activação permanente “por engano” | **Rejeitada** | decisão humana + teardown imediato; `enabled=false` |
| A10 | Segredos no git | **Rejeitada** | só `06-mitm-ca.crt` público; credenciais só `/tmp` local |
| A11 | Mutação `.234`/`.235` | **Rejeitada** | fora do escopo; não tocadas |
| A12 | Fail-safe ausente | **Rejeitada** | `at +15 min` armado antes da activação; limpo no fim |

## Limitações honestas

- Prova é de **janela controlada** (origem única + destino lab), não de MITM permanente em produção.  
- Destino `198.18.0.10` é lab via `.54`, não domínio real.  
- `quic_mode=block` + regra PF anti-QUIC escopo; não se reclama paridade NGFW universal.

## Veredicto auditoria

**PASS** para o GO de teste controlado.  
**NO-GO** para activação permanente MITM em `.254` (decisão humana mantida).
