# Evidência — P3-9 opção A: «404 esperado» (BG-150)

**RUNID:** `20260814T204500Z`  
**Chat:** BG-150  
**Modo:** só documental — **sem** contacto GitHub, hosts, builder ou runtime.  
**HEAD de partida:** `82c69d6` (P3-8). Fecho deste bloco: commits `f18f658` + `b1f1b85` + este.

## Veredicto

**P3-9 AVALIADO** no git — **FEITO documental** (opção A).  
URLs do espelho **não** foram removidos.

| Afirmação | Estado |
|-----------|--------|
| Tag `blacklists-ut1-current` cortada em `30.11` | Sim (prova P3-8) |
| Quatro URLs GitHub de *download* = **404 esperado** | Sim (não reexecutado aqui) |
| Primary exige token (401 sem token) | Sim (prova P3-8 / `20260812T003214Z`) |
| **Não** é o canal do pacote | Sim (`releases/latest` / `v1.9.63`) |
| **Não** é motivo para reupload GA4.11 | Sim |
| Espelho = legado / fallback de runtime | Sim — **não** removido |

Prova de rede do cut: [`../20260814T200900Z-p38-cut-recheck/`](../20260814T200900Z-p38-cut-recheck/).  
Nota canónica: [`../../../09-blocking/nota-404-esperado-cut-30.11.md`](../../../09-blocking/nota-404-esperado-cut-30.11.md).
