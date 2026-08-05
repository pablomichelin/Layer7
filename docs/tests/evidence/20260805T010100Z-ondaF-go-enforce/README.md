# Onda F — GO enforce produção `1.8.11_69`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260805T010100Z-ondaF-go-enforce` |
| Plano | passos **7.1** + **7.2** |
| Data | `2026-08-05` |
| Decisor | Operador (GO explícito no chat) |

## Pré-condições verificadas

| Onda / gate | Estado |
|-------------|--------|
| A–D | **PASS** |
| E | **LIMITAÇÃO** — aceite humano **ADR-0022** (sem VM CE) |
| G 8.1+8.2 | **PASS** |
| G2–G7 | **PASS** (`_69` / evidências Ondas A–D) |
| GitHub `releases/latest` | `v1.8.11_69` |

## Decisões registadas

1. **ADR-0022 aceite:** promoção GO com ressalva CE — validação física pfSense CE
   do build `_69` permanece **pendente**; evidência Plus/FB16 + histórico CE 2.8.1.
2. **Produção enforce (referência SSOT):** `1.8.11_24` → **`1.8.11_69`**
3. **Canal `latest`:** já `1.8.11_69` — **alinhado** (passo 7.2)
4. **Rollback imediato documentado:** `_68`
5. **Rollback histórico:** `_24`

## Artefacto

| Campo | Valor |
|-------|-------|
| Tag | `v1.8.11_69` |
| SHA256 | `b6d11ccdbb0b59209a501ee4240706e873153c2780c283721d904158f6b06764` |

## Não incluído neste bloco

- Activação física de `scoped_hybrid` em produção (continua decisão operacional)
- BG-028 trust chain pacote (Onda I)
- Onda J (R1–R12 completo)

## Veredicto

**Onda F — PASS** (GO enforce documental `1.8.11_69`)
