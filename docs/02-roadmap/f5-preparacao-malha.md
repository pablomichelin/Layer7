# F5 - Preparacao da malha de testes (antes da execução plena)

## Finalidade

Documento curto de **ponte** entre a **F4** (runtime estável) e a **F5**
(malha canónica de não-regressão). Não substitui o
[`roadmap.md`](roadmap.md) nem o [checklist-mestre](checklist-mestre.md).

---

## 1. Objectivo da F5 (lembrete)

- Matriz de testes canónica; separação smoke / builder / appliance /
  operação; gates repetíveis; ligação backlog ↔ checklist ↔ changelog.

Itens de backlog: BG-012, BG-013, BG-014.

---

## 2. Pré-requisitos antes de "abrir" a F5 plena

- F4: trilha package/daemon/blacklists com comportamento previsível e docs
  alinhados (criterio de saída do roadmap F4).
- [`checklist-mestre.md`](checklist-mestre.md) com gates de teste
  assinaláveis para **F4** (paralelismo com a F3): **F4.1** / BG-009 (sec. **10a**,
  teste **3.8**), **F4.2** / BG-010 (sec. **10b**, **12.1–12.2**), **F4.3** /
  BG-011 (sec. **11**, **6.7**), com roteiros e pré-requisito de builder em
  [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (*Índice dos
  roteiros F4* + secções 10a / 10b / 11).
- `docs/tests/test-matrix.md` e
  [`../tests/README.md`](../tests/README.md) revistos; CI
  (`.github/workflows/smoke-layer7d.yml`) como mínimo de regressão
  de compilação do daemon.
- Nenhum refactor estrutural (F6) antes do tempo.

---

## 3. Ordem de trabalho sugerida (F5.1+)

0. Garantir que as subfases **F4** em curso têm **evidência mínima** e
   **rollback** coerentes com o
   [`f4-plano-de-implementacao.md`](f4-plano-de-implementacao.md) e com os
   itens de teste do [`checklist-mestre.md`](checklist-mestre.md) (não
   antecipar "malha F5" sem cumprir o que a F4 ainda exige: **3.8**, **12.1–12.2**,
   **6.7** / BG-009, BG-010, BG-011). O *Índice dos roteiros F4* em
   [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) consolida
   a ordem builder → appliance.
1. **F5.1** — Inventariar o que já existe: smoke, `make check`, `npm test`
  do license server, validação de lab.
2. **F5.2** — Definir "gate mínimo" por alteração (matriz: área do produto
  → teste obrigatório).
3. **F5.3** — Ligar itens de backlog a IDs de teste ou a secções do
  `test-matrix.md`.
4. **F5.4** — (Opcional posterior) ampliar CI para ficheiros críticos do
  port ou do updater de blacklists, sem substituir testes de appliance.

---

## 4. O que NÃO é F5

- Observabilidade e release engineering "pesados" → **F7** (em parte).
- Mover `docs/` ou limpar legado → **F6**.

---

## 5. Documentação a manter viva

- `docs/tests/README.md`
- `docs/tests/test-matrix.md`
- `CORTEX.md` e `backlog.md` ao mudar o estado da F5.
- `checklist-mestre.md` (gates **F4.1–F4.3**) e `validacao-lab.md` (índice +
  roteiros **10a** / **10b** / **11**) como fontes de critério antes de alargar
  a malha de regressão.
