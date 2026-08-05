# Onda E — Compatibilidade pfSense CE (`20260804T234500Z-ondaE-ce-parity`)

| Campo | Valor |
|-------|-------|
| Plano | passo **6.1** — Onda E |
| Candidato | `1.8.11_69` (`SHA256=b6d11ccdbb0b59209a501ee4240706e873153c2780c283721d904158f6b06764`) |
| Appliance proxy | `192.168.100.254` — **pfSense Plus 26.03.1** / FreeBSD 16 |
| VM CE dedicada | **INDISPONÍVEL** na malha `192.168.100.0/24` |
| Veredicto Onda E | **LIMITAÇÃO** (não CE PASS) |

## Objectivo do passo

Validar o claim comercial **pfSense CE** com install passivo + smoke monitor mínimo numa VM CE dedicada (`matriz-compatibilidade-ce-plus-freebsd.md`).

## Resultado

| Critério | CE real | Paridade Plus/FB16 (proxy) |
|----------|---------|----------------------------|
| VM CE acessível | **BLOCKED** — ver `network-probe.txt` | N/A |
| Install `_69` / ABI | **PENDENTE** (sem CE) | **PASS** — `layer7d -V`, `ldd` OK |
| G3 `pfctl -nf` | **PENDENTE** | **PASS** — `rules.debug` |
| G2 passivo (zero block PF) | **PENDENTE** | **FAIL** nesta sessão — resíduo regras G5 (`g5-test-bl`) + skeleton block após `filter_configure` com `enabled=false` |
| `smoke-monitor-mode.sh` | **PENDENTE** | **FAIL** (PF block presente); daemon/tabelas OK |
| Evidência histórica CE | `validacao-lab.md` — CE 2.8.1 `@192.168.0.195` (2026-03-22, 57/58 testes) — **rede inacessível**; versão ≠ `_69` |

## Veredicto formal

**Onda E = LIMITAÇÃO** — não satisfaz R1/R6 para claim CE-only exclusivo no candidato `_69`.

- **Não bloqueia** continuidade técnica Plus/FB16 (Ondas A–D já PASS no mesmo host).
- **Bloqueia GO enforce Onda F** até: (a) CE PASS físico, ou (b) decisão humana explícita aceitando proxy Plus + ADR-0022.

## Ficheiros

| Ficheiro | Descrição |
|----------|-----------|
| `network-probe.txt` | Sonda rede / builder / VM CE |
| `ce-parity-appliance.txt` | Inventário plataforma + ABI |
| `ce-parity-monitor-smoke-v2.txt` | Teste monitor temporário (restore OK) |
| `ce-passive-g2.txt` | G2 passivo `enabled=false` |
| `ONDA-E-LIMITACAO.md` | Relatório canónico do passo |

## Rollback

- Config appliance restaurada a `mode=enforce`, `enabled=true`, `legacy_global` após cada teste.
- Script: `tests/lab/run-ondaE-ce-parity-appliance.sh` (trap `EXIT` restaura backup).

## Próximo passo autorizado

1. **Humano:** provisionar VM pfSense CE na malha lab (snapshot + IP dedicado) e reexecutar passo 6.1, **ou**
2. **Humano:** aceitar ADR-0022 e decidir se Onda F prep pode avançar com ressalva CE, **ou**
3. **Onda G** (F5 malha) — se Onda F ficar bloqueada por CE.

**Não avançar Onda F (GO enforce) sem pedido explícito.**
