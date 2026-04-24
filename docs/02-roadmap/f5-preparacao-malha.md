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
- `docs/tests/test-matrix.md` e
  [`../tests/README.md`](../tests/README.md) revistos; CI
  (`.github/workflows/smoke-layer7d.yml`) como mínimo de regressão
  de compilação do daemon.
- Nenhum refactor estrutural (F6) antes do tempo.

---

## 3. Ordem de trabalho sugerida (F5.1+)

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
