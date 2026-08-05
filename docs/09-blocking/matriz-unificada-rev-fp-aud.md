# Matriz unificada REV / FP / AUD / BG

**Data:** 2026-07-30  
**Versão:** candidato `1.8.11_31` (HEAD `2ba9b5f`)  
**Release pública:** `1.8.11_24`  
**Estado ledger:** CANDIDATO CONGELADO — NO-GO produção

Legenda de **estado:** `open` | `code_fixed` | `by_design` | `doc_only` | `appliance_pass` | `closed`  
Legenda de **evidência:** OBS | REP | LOCAL | BUILDER | APPLIANCE | N/C

---

## FP-001..020 (revisão funcional 2026-07-29)

| ID | Aliases | Tipo | Sev. | Fix versão | Estado | Local | Builder | Appliance | Gate |
|----|---------|------|------|------------|--------|-------|---------|-----------|------|
| FP-001 | — | DEFECT | Crítica | `_27` | code_fixed | LOCAL | BUILDER | N/C | G4.2 |
| FP-002 | — | DEFECT | Crítica | `_27` | code_fixed | LOCAL | BUILDER | N/C | G5.3 |
| FP-003 | AUD-011 | DEFECT | Crítica | `_27` | code_fixed | — | BUILDER | N/C | G5.5 |
| FP-004 | — | DEFECT | Alta | `_27`/`_28` | code_fixed | LOCAL | BUILDER | N/C | G5.6 |
| FP-005 | — | DEFECT | Alta | `_27` | code_fixed | — | BUILDER | N/C | G4 |
| FP-006 | — | DEFECT | Alta | `_27` | code_fixed | — | BUILDER | N/C | G3 |
| FP-007 | REV-042 | DEFECT | Média | `_27` | code_fixed | — | BUILDER | N/C | G5 |
| FP-008 | — | DEFECT | Média | `_27` | code_fixed | LOCAL | BUILDER | N/C | G4.2 |
| FP-009 | REV-001, AUD-005 | LIMITATION | Crítica | — | by_design | OBS | — | N/C | G5+ADR |
| FP-010 | REV-038, AUD-007, REV-018 (PF) | LIMITATION | Alta | — | open | OBS | — | N/A | Trilha IPv6: V1=REV-018; V2–V3+GV3–GV4=captura; ADR-0024 |
| FP-011 | AUD-004 | RISK | Alta | — | open | — | BUILDER | N/C | G2.5 |
| FP-012 | AUD-012 | RISK | Alta | `_30` | code_fixed | LOCAL | BUILDER | N/C | G4.5 |
| FP-013 | — | LIMITATION | Média | — | open | OBS | — | N/C | lab DNS |
| FP-014 | — | LIMITATION | Média | — | open | OBS | — | N/C | V2 |
| FP-015 | REV-021, AUD-006 | DEFECT | Média | — | open | LOCAL contrato | — | N/C | F5 |
| FP-016 | AUD-001 | UNVALIDATED | Alta | — | open | — | BUILDER | N/C | G5 |
| FP-017 | BG-056 | DEFECT | Crítica | `_28` | code_fixed | LOCAL parcial | BUILDER | N/C | G5.6 |
| FP-018 | AUD-010, BG-057 | DEFECT | Crítica* | `_29` | code_fixed | REP sintético | BUILDER | N/C | G3.2 |
| FP-019 | BG-058 | DEFECT | Crítica | `_30` | code_fixed | LOCAL | BUILDER | N/C | G4.5 |
| FP-020 | BG-059 | DEFECT | Crítica | `_31` | code_fixed | LOCAL | BUILDER | N/C | G4.2 TLS |

\* Crítica se anti-QUIC activo.

---

## AUD-001..015 (auditoria E2E 2026-07-29 + rodada 2026-07-30)

| ID | Aliases | Tipo | Sev. | Estado | Notas |
|----|---------|------|------|--------|-------|
| AUD-001 | FP-016 | UNVALIDATED | Crítica | open | Gate two-client ausente |
| AUD-002 | — | DEFECT | Crítica | open | `_24` pública contém bugs |
| AUD-003 | — | UNVALIDATED | Alta | open | Install passivo `_31` pendente |
| AUD-004 | FP-011 | RISK | Alta | open | ABI FB15 vs FB16 |
| AUD-005 | FP-009 | CLAIM | Crítica | by_design | legacy_global default |
| AUD-006 | FP-015 | DEFECT | Média | open | Parser JSON frágil |
| AUD-007 | FP-010 | LIMITATION | Alta | open | IPv4-only captura; trilha IPv6 ADR-0024 (passo 12.x) |
| AUD-008 | — | DEFECT | Alta | open | Interface lógica no JSON |
| AUD-009 | BG-028 | DOC-DRIFT | Alta | open | Trust chain inactiva |
| AUD-010 | FP-018 | DEFECT | Crítica* | code_fixed | Fix `_29+` |
| AUD-011 | FP-003 | UNVALIDATED | Crítica | code_fixed | State kill físico pendente |
| AUD-012 | FP-012 | UNVALIDATED | Alta | code_fixed | Carga real pendente |
| AUD-013 | — | UNVALIDATED | Média | open | PHP SKIP macOS |
| AUD-014 | — | DOC-DRIFT | Média | open | Working tree sujo |
| AUD-015 | D-ENC-001 | DOC-DRIFT | Baixa | open | pf-enforcement parcial |

---

## Achados lifecycle (rodada 2026-07-30 — B-002/B-003/B-004)

| ID | Aliases | Tipo | Sev. | Fix | Estado | Teste |
|----|---------|------|------|-----|--------|-------|
| B-002 | — | DEFECT | Média | `_31` local | code_fixed | R-21 |
| B-003 | — | DEFECT | Média | `_31` local | code_fixed | R-21 |
| B-004 | BG-033 | DEFECT | Média | `_31` local | code_fixed | R-21 |
| B-001 | D-ENC-003 | RISK | Alta | — | by_design | ADR futuro |

---

## BG-061 (rodada 2026-07-30 — flush lifecycle)

| ID | Fix | Estado | Teste |
|----|-----|--------|-------|
| B-002 | exc_allow em flush PHP + pfctl | code_fixed local | R-21 |
| B-003 | bl_apply flush | code_fixed local | R-21 |
| B-004 | pkg-deinstall PRE flush-all | code_fixed local | R-21 |

**Nota:** exige rebuild `_31` no builder antes de gate appliance.

---

## REV críticos (amostra — baseline 2026-06-15)

| ID | Tema | Estado `_31` | Alias FP |
|----|------|--------------|----------|
| REV-001 | legacy_global default | by_design | FP-009 |
| REV-002 | flush licença inválida | closed `_24+` | — |
| REV-003 | CIDR `/0` allowlist | closed | — |
| REV-021 | parser JSON | open | FP-015 |
| REV-038 | IPv4-only captura | open | FP-010 |
| REV-018 | scoped PF só `inet` (bypass v6) | open | FP-010 / V1 (12.3) |
| REV-042 | CNAME QNAME | code_fixed | FP-007 |
| REV-050 | lacunas integração | parcial | FP-016 |

---

## Backlog `_25`→`_31`

| Rev | BG | Tema |
|-----|-----|------|
| `_25` | BG-053 | PID, interfaces reais |
| `_26` | BG-054 | Logging L1 |
| `_27` | BG-055 | Hash, pdst/psrc, state kill |
| `_28` | BG-056 | pallow + L7ALLOW |
| `_29` | BG-057 | Anti-QUIC syntax |
| `_30` | BG-058 | Flow probe + evicção |
| `_31` | BG-059 | NDPI_STATE_CLASSIFIED |

---

## Referências

- `revisao-funcional-pre-producao-2026-07-29.md` — SSOT FP
- `revisao-codigo-pre-install-2026-06-15.md` — SSOT REV
- `auditoria-end-to-end-2026-07-29.md` — SSOT AUD
- `diagnostico-multitask-2026-07-30.md` — relatório desta rodada
- [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md) — FP-010 / REV-018 (trilha IPv6)
- [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md)
- [`matriz-limitacoes-dpi.md`](matriz-limitacoes-dpi.md) — alinhamento GV0.4 (passo 12.1)
