# Decisão S5 — QUIC / HTTP3 (pré-runtime)

| Campo | Valor |
|-------|--------|
| Critério | **S5** — caminho QUIC/HTTP3 definido por escrito |
| Data | `2026-08-09` |
| Pacote referência | **`1.9.38`** |
| Runtime | **AUSENTE** — sem prova de intercept QUIC |
| Evidência | este ficheiro + `01-code-quic-refs.txt` |

## Decisão canónica

| Opção | Veredicto |
|-------|-----------|
| **`bypass` (default)** | **ACEITE** — MVP / PME |
| `block` | Permitido como opção de operador; **não** default |
| `downgrade` (forçar TCP) | Permitido como opção avançada; **não** default; exige PoC |

**Valor de schema / GUI / contrato:** `layer7.mitm.quic_mode` default = **`bypass`**.

## Motivação (PME)

1. QUIC ubiquo (Chrome/HTTP3): **block** por defeito quebra UX e suporte.
2. **Downgrade** agressivo = segundo produto (PF/NAT complexos) antes de runtime.
3. **Bypass** = honestidade: sem `layer7-tlsproxy`, QUIC **não** é inspeccionável;
   não fingir MITM onde não há.
4. Alinha ao posicionamento Identity-first / sem overclaim NGFW.

## O que fica pendente (não bloqueia fecho documental S5)

| Item | Estado |
|------|--------|
| Prova lab com runtime (tráfego QUIC sob cada modo) | **PENDENTE** — exige PoC + GO lab |
| Comportamento ECH (S6) | **PENDENTE** — ortogonal; não reabre S5 |

## Critério de fecho S5 (pré-runtime)

- [x] Decisão escrita (este documento)
- [x] Default `bypass` no schema (`layer7.inc`)
- [x] GUI expõe as 3 opções com default S5
- [x] Contrato IPC §5 documenta `quic_mode`
- [ ] Prova lab sob runtime — **diferida** (não autoriza 20.10 sozinha)

**Veredicto S5 (fase pré-runtime):** **PASS documental**  
**Não autoriza `20.10`.**
