# Evidência — alinhamento produção / two-client `1.9.8`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T162500Z-prod-align-two-client-1.9.8` |
| Pacote | `pfSense-pkg-layer7-1.9.8` |
| Appliance | `192.168.100.254` |
| Cliente A | `192.168.100.234` (server) |
| Cliente B | `192.168.100.235` (zpro-aimirim) |
| Veredicto | **PASS** (32 PASS / 0 FAIL após recheck NAT) |

## Fases

| Fase | Resultado |
|------|-----------|
| 0 Saúde (`1.9.8`, licença, daemon, dual-listen 8099) | **PASS** |
| 0 NAT IPv6 Layer7 (DNS+HTTP) | **PASS** (recheck; 1.º grep truncado) |
| 1 Políticas actuais `legacy_global` + `force_dns` | **PASS** |
| 2 Gate two-client `scoped_hybrid` (YT só A) | **PASS** |
| 3 Restore config produção | **PASS** |

## Destaques runtime

- **Allow:** A e B → `example.com` HTTP 200 (antes, durante e após restore)
- **Block DNS:** `bet365.com` → sinkhole `192.168.100.254` nos dois clientes
- **Block HTTP:** ambos recebem sinal de blockpage Layer7
- **Two-client scoped:** A=`000` (bloqueado) / B=`200` (YouTube OK) com `pdst` scoped
- **Restore:** `legacy_global`, 4 políticas, `mode=enforce`

## Modelo de produção actual

`enforcement_model=legacy_global` (bloqueio global por destino). O gate scoped
foi validado **temporariamente** e a config de produção foi restaurada.

## Alinhamento produção

| Critério | Estado |
|----------|--------|
| Pacote = referência enforce `1.9.8` | OK |
| Licença válida | OK |
| Enforce operacional com bloqueios reais | OK |
| Dual-stack NAT (DNS/HTTP inet6) | OK |
| Two-client scoped (capacidade do pacote) | OK |
| CE físico (ADR-0022) | LIMITAÇÃO aceite (inalterada) |

**Conclusão:** runtime do `1.9.8` no lab está **apto** para alinhar com a
referência de produção enforce já promovida (GV7.4). Ressalva: lab = pfSense
Plus/FB16, não CE físico.
