# F3 Validation Campaign Report — Onda C consolidada

## Identificacao da campanha

- Campaign ID: `DR-05` / Onda C (plano fecho produção passo 4.1–4.2)
- Run ID: `20260804T211500Z-ondaC-f3-report`
- Data/hora UTC: 2026-08-04 (consolidação pós BG-077)
- Operador(es): agente + operador Systemup (Veeam)
- Ambiente/lab: `192.168.100.254` (pfSense), `192.168.100.244` (license server)
- Versao do produto: **`1.8.11_68`**
- Directorio raiz das evidencias: `docs/tests/evidence/`

## Resumo executivo

- Objectivo: fechar F3 (licenciamento previsível no appliance) — campanha DR-05
- Cenarios obrigatorios DR-05: S07–S13 (+ S14 BG-077)
- Resultado agregado: **6/6 PASS** (S07–S09, S12–S14, S13)
- **Decisao final: `F3 pode fechar`** (addendum `20260804T212000Z` — S13 PASS)
- Bloqueador anterior S13: **resolvido** (`20260804T212000Z-ondaC-s13-drift-PASS`)

## Veredito por cenario (DR-05 + S14)

| ID | Classificacao | Resultado | Evidencias principais |
|----|---------------|-----------|------------------------|
| S07 | Obrigatorio | **PASS** | `20260804T234000Z-ondaC-s07-retest` |
| S08 | Obrigatorio | **PASS** | `20260804T233500Z-ondaC-dr05-veeam` |
| S09 | Obrigatorio | **PASS** | `20260804T211300Z-ondaC-s09-retest-PASS` (+ offline documentado) |
| S12 | Obrigatorio | **PASS** | `20260804T233500Z-ondaC-dr05-veeam` |
| S13 | Obrigatorio | **PASS** | `20260804T212000Z-ondaC-s13-drift-PASS` |
| S14 | Obrigatorio (BG-077) | **PASS** | `20260804T210500Z-ondaC-s14-checkin-PASS` |

Cenarios S01–S06: cobertos em campanhas anteriores F3.11 (`20260414*`); não reexecutados nesta onda.

## Detalhe S09 (pós BG-077)

- Revogação no servidor + `.lic` local: **continua válido offline** (by design).
- `layer7d --check-in` após revogação: **invalida imediatamente** (PASS comercial).
- Flag `check_in_enabled` default **OFF** — rollback ADR-0021.

## Detalhe S13

- Drift reversível: MAC `em0` (`00:50:56:88:e1:33` → `02:11:22:33:44:55`).
- Pós-drift: `valid=0`, `hardware mismatch`; activate → HTTP **409**.
- Restauro MAC: licença Systemup válida sem Veeam.
- Evidência: `20260804T212000Z-ondaC-s13-drift-PASS/`

## Riscos remanescentes

- `update-blacklists.sh --apply` regista `WARN: cannot read pidfile` (Onda D 10b).

## Pendencias nao bloqueantes

1. Alinhar cosmeticamente HTTP `403` vs `409` no activate (DR-02).
2. Reteste SIGHUP blacklists com pidfile (10b).

## Conclusao final

- **Decisao: `F3 pode fechar`**
- **Justificacao:** S07–S09, S12–S14 e **S13** PASS. S01–S06 em campanha F3.11 (`20260414*`).

## Proximos passos

1. Actualizar CORTEX/roadmap — F3 **FECHADA**.
2. Continuar **Onda D** passo 5.1.
