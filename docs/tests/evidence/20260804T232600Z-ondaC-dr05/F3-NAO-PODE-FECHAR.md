# Relatório Onda C — F3 não pode fechar

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T232600Z-ondaC-dr05` |
| Data | 2026-08-04 |
| Veredicto | **F3 não pode fechar** |
| Bloqueador principal | S07 FAIL — activação de licença expirada aceite pelo backend |

## Resumo executivo

A campanha DR-05 no appliance `192.168.100.254` executou baseline, S07 e S09
(read-only parcial). O cenário **S07 falhou** face ao contrato canónico da F3:
uma licença com `expiry` no passado (`ID 5`, Lasalle) foi activada com sucesso
via API pública (`HTTP 200`), o `layer7d` gravou `/usr/local/etc/layer7.lic` e
o `activations_log` regista `result=success`. Os cenários que exigem controlo
de relógio (S08, S12) e drift de identidade (S13) ficaram **BLOCKED** por
risco operacional no appliance de produção Systemup.

## Matriz de decisão

| Cenário | Classificação | Justificação |
|---------|---------------|--------------|
| BASELINE | PASS | Evidência appliance recolhida; fingerprint coerente |
| S07 | **FAIL** | Activacao aceite + `.lic` persistido para licença expirada |
| S08 | **PASS** | Run `20260804T233500Z-ondaC-dr05-veeam` — grace confirmada com relógio controlado |
| S09 | PARTIAL | Offline OK; online inconclusivo |
| S12 | **PASS** | Run `20260804T233500Z-ondaC-dr05-veeam` — transição grace validada |
| S13 | PARTIAL | Baseline capturado; drift NIC/UUID pendente |

## Acções recomendadas

1. **Produto/backend:** rever endpoint `/api/activate` para licenças com
   `expiry` efectivo no passado — deve devolver `409` e não emitir artefacto.
2. **Daemon:** não gravar `.lic` quando a verificação local falha por expiração;
   melhorar mensagem de erro quando o servidor responde HTTP ≠ 2xx.
3. **Lab:** agendar janela com snapshot Veeam para S08/S12/S13.
4. **Documentação:** registar achado em backlog (BG novo) antes de re-tentar
   fecho F3.

## Rollback aplicado

Licença Systemup restaurada; appliance operacional pós-campanha.
