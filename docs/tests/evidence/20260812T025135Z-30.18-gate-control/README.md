# Gate control — 30.18 (bateria factual antes do FECHADO)

**UTC bateria:** `20260812T025135Z`  
**Impl. base:** `b36f2e3` (processo F1.2 já no git; fecho documental prematuro corrigido aqui)

## Sequência obrigatória (esta sessão)

| # | Passo | Estado |
|---|-------|--------|
| 0 | Marcação canónica prévia `FECHADO` sem gate-control | **corrigida** → tratado como **EM EXECUÇÃO / PENDENTE** até bateria |
| 1 | Rever diff da implementação (`b36f2e3`) | **FEITO** — `diff-review-stat.txt` + `diff-review-guards.txt` |
| 2 | Executar bateria do cartão (×2) | **PASS** — `battery-run1.txt` + `battery-run2.txt` (`exit:0`) |
| 3 | Sintaxe shell dos scripts tocados | **PASS** — `shell-syntax.txt` |
| 4 | Confirmar evidência factual | **FEITO** — este directório |
| 5 | Declarar FECHADO + commit/push | **após** este pack (commit de gate-control) |

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | Gate control: não manter FECHADO sem bateria + revisão de diff registadas |
| Impacto | Só docs/evidência de fecho; impl. 30.18 já em `b36f2e3` |
| Risco | Baixo (documental); residual A-10 campo/BG-028 inalterado |
| Teste | `test_release_signing_f12_30.18.sh` ×2 — `exit:0` / `PASS` |
| Rollback | Reverter este commit de gate-control; impl. 30.18 intacta em `b36f2e3` |

## Gates

| Gate | Resultado |
|------|-----------|
| GA6.5 | **PASS (processo)** — dry-run ×2; política F1.2; residual campo → BG-028 |
| GA6.6 | **PASS** — `MANUAL-INSTALL.md` com `1.9.54` + procedimento F1.2 (revisto no diff) |

## Não aberto

`30.19` · MITM · IPv6 · `.254` · CF/DNS · license-server · release/deploy
