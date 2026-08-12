# Gate control — 30.17 (bateria factual antes do FECHADO)

**UTC bateria:** `20260812T024419Z`  
**Impl. base:** `7f30b56` (código já no git; fecho documental prematuro corrigido aqui)

## Sequência obrigatória (esta sessão)

| # | Passo | Estado |
|---|-------|--------|
| 0 | Marcação canónica prévia `FECHADO` sem gate-control | **corrigida** → tratado como **EM EXECUÇÃO / PENDENTE** até bateria |
| 1 | Rever diff da implementação (`7f30b56`) | **FEITO** — `diff-review-stat.txt` + `diff-review-guards.txt` |
| 2 | Executar bateria do cartão | **PASS** — `unit-attribution.txt` + `regression-cs-update.txt` |
| 3 | Confirmar evidência factual | **FEITO** — este directório |
| 4 | Declarar FECHADO + commit/push | **após** este pack (commit de gate-control) |

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | Gate control: não manter FECHADO sem bateria registada |
| Impacto | Só docs/evidência de fecho; código 30.17 já em `7f30b56` |
| Risco | Baixo (documental) |
| Teste | attribution + regressão CS — `attr_rc:0` `cs_rc:0` |
| Rollback | Reverter este commit de gate-control; código 30.17 intacto em `7f30b56` |

## Gates

| Gate | Resultado |
|------|-----------|
| GA6.3 | **PASS** — marca + doc privacidade + teste |
| GA6.4 | **PASS** — `local_only_no_telemetry`; sem phone-home no caminho stamp |

## Não aberto

`30.18` · MITM · IPv6 · `.254` · CF/DNS · license-server · release/deploy
