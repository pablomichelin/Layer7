# F3.11 - Start Here

## Estado actual (actualizado em 2026-08-04)

- **`F3 FECHADA`** — veredito `F3 pode fechar` (Onda C)
- Campanha DR-05 concluída: S07–S09, S12–S14 e **S13** em `PASS`
- BG-077 (check-in online / revogação remota): **implementado** (`1.8.11_68`; S14 PASS)
- Relatório final: `docs/tests/evidence/20260804T211500Z-ondaC-f3-report/F3-PODE-FECHAR.md`
- Evidência S13 (drift NIC reversível): `20260804T212000Z-ondaC-s13-drift-PASS`
- Pré-requisito Veeam: **PASS** (`20260804T211800Z-veeam-prerequisite-PASS`)

---

## O que foi provado (campanha 2026-08-04)

1. Login e sessão administrativa no live (`192.168.100.244`)
2. `POST /api/activate` rejeita hw divergente, revogada e expirada
3. Appliance `192.168.100.254`: activação, offline, revogação (S08/S09/S12)
4. Check-in online e revogação remota (S14 — BG-077)
5. Drift de hardware NIC reversível com bloqueio e restauro (S13)
6. Reteste S07/S09 pós-BG-077: **PASS**

---

## Documentos de referencia

- [`../tests/evidence/20260804T211500Z-ondaC-f3-report/F3-PODE-FECHAR.md`](../tests/evidence/20260804T211500Z-ondaC-f3-report/F3-PODE-FECHAR.md)
- [`../tests/evidence/20260804T211500Z-ondaC-f3-report/F3-VALIDATION-CAMPAIGN-REPORT.md`](../tests/evidence/20260804T211500Z-ondaC-f3-report/F3-VALIDATION-CAMPAIGN-REPORT.md)
- [`../01-architecture/f3-fecho-operacional-restante.md`](../01-architecture/f3-fecho-operacional-restante.md) (histórico)
- [`../01-architecture/f3-gate-fechamento-validacao.md`](../01-architecture/f3-gate-fechamento-validacao.md)
- [`../03-adr/ADR-0021-check-in-online-e-revogacao-remota.md`](../03-adr/ADR-0021-check-in-online-e-revogacao-remota.md)

---

## Próximo passo (pós-F3)

Continuar **Onda D** (passo 5.1 — F4 lab) no plano mestre de fecho de produção.
Ver [`START-HERE-fecho-producao.md`](START-HERE-fecho-producao.md).
