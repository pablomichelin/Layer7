# Aviso — Releases antigas e RR-3 (decisão 8 / `30.19`)

**Estado:** Em vigor (`2026-08-12`)  
**Decisão humana:** *Limitar apontadores (latest/docs); manter tags com aviso*
([`../09-blocking/decisoes-humanas-30.1.md`](../09-blocking/decisoes-humanas-30.1.md) §8).  
**Insumo:** inventário `30.3`
[`../tests/evidence/20260810T234552Z-ap0-baseline/`](../tests/evidence/20260810T234552Z-ap0-baseline/).  
**Proibido neste bloco:** apagar ou alterar tags/assets no GitHub Releases.

---

## Apontadores oficiais (pós-decisão 8)

| Canal | Versão | Nota |
|-------|--------|------|
| Lab / `latest` (comandos `MANUAL-INSTALL`) | **`1.9.54`** | Inclui anti-pirataria AP1+ relevante publicada |
| Produção enforce (pin) | **`1.9.8`** | Pin histórico GV7.4 — **não** é o build mais endurecido da trilha AP1 |
| Primeiro build publicado pós-remoção bypass dev (`30.4`) | **`1.9.49`** | Referência de higiene AP1 |

**Não recomendado** para nova instalação ou “última versão segura anti-bypass”:
qualquer tag **≤ `1.9.48`** (e anteriores da amostra `30.3`), mesmo que links
históricos existam no manual para arqueologia/rollback.

## Risco residual aceite (RR-3)

As tags antigas **permanecem publicamente descarregáveis**. Um actor pode
fixar-se num `.pkg` antigo. Isto **não** é resolvido por documentação.
Mitigação de valor: conteúdo actual exige token (AP2); builds novos sem
caminho `is_dev_key` de produção (AP1); check-in (AP3) quando activo.

## Execução neste passo

- [x] Confirmar que secção **Links da versão actual** aponta `1.9.54` (não ≤48)
- [x] Aviso explícito no `MANUAL-INSTALL.md` + este ficheiro
- [x] Tags **não** alteradas (cumprimento literal da decisão 8 + GO do chat)
- [ ] Despublicar assets — **fora de escopo** (exigiria GO novo)

## Gate

GA6.12 **PASS** — decisão 8 executada documentalmente; residual RR-3 aceite por escrito.
