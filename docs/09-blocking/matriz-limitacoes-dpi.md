# Matriz — limitações DPI (nDPI / captura passiva)

**Data original:** 2026-07-29  
**Rev.:** `2026-08-06` — alinhamento pós **trilha IPv6 FECHADA** + MITM **DEFER 20.7a**  
**Engine:** nDPI 5.x (estático, `HAVE_NDPI=1`)  
**Modo V1 base:** captura passiva + PF — **sem** MITM TLS (add-on diferido)  
**Estado produto:** produção enforce / baseline trilha Identity = **`1.9.8`**  
**IPv6:** trilha V0–V6 **FECHADA** (GV7.4 PASS) — dual-stack **no âmbito documentado**  
**MITM:** ADR-0026 **implementação diferida** (20.7a); Identity-first PME  

> **Honestidade (actual):** o produto `1.9.8` classifica e enforce IPv4 **e** IPv6
> (captura, fluxos, nDPI, PF scoped, DNS `rdr inet6`, portal HTTP, VIP ACL v6).
> Limites residuais (ECH, QUIC cifrado, extension headers IPv6 conservadores,
> CDN multi-host, classificação parcial até estado final) **permanecem**.
> **Não** afirmar MITM / decrypt TLS. **Não** reabrir IPv6 sem GO.
>
> SSOT estado: [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](../00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) ·
> [`CORTEX.md`](../../CORTEX.md) (Trilha IPv6) ·
> ADR-0024 · gates [`plano-gates-ipv6.md`](plano-gates-ipv6.md) ·
> plano histórico [`../archive/planos-fechados/plano-ipv6-completo.md`](../archive/planos-fechados/plano-ipv6-completo.md)
> ([stub](../02-roadmap/plano-ipv6-completo.md)).

---

## 1. Arquitectura de captura

| Parâmetro | Valor | Ficheiro / nota |
|-----------|-------|-----------------|
| Método | libpcap promisc, snaplen 1536 | `capture.c` |
| Datalinks | `DLT_EN10MB`, `DLT_RAW` | `capture.c` |
| Protocolo L3 | **IPv4 e IPv6** (`ip_ver` 4\|6; parse v6 + extension headers) | `capture.c`, `capture_flow_key.h` |
| Interfaces | Nomes **reais** (`vmx0`, não `lan`) | `layer7.inc` normalização |
| Fluxos máx. | 65536 slots | `capture.c` |
| Pacotes/flujo | 48 (`L7C_MAX_PKTS_PER_FLOW`) | `capture.c` |
| Idle fluxo | 120 s | `capture.c` |
| Estado nDPI final | `NDPI_STATE_CLASSIFIED` | `capture.c` |
| Fallback orçamento | `ndpi_detection_giveup()` | `capture.c` |

---

## 2. Matriz de capacidades vs limitações

| Capacidade | Suportado (`1.9.8`) | Limitação | Classificação |
|------------|---------------------|-----------|---------------|
| Classificação app nDPI | Sim | Resultado parcial até estado final | Bug histórico FP-020 corrigido |
| Categoria nDPI | Sim | Depende de classificação final | — |
| SNI TLS | Sim (opt-in `sni_inspection`) | TLS 1.3 **ECH** oculta SNI | Limitação arquitectural |
| Host HTTP | Sim (nDPI) | HTTPS sem SNI visível → só IP | Limitação |
| QUIC | Detectável | Payload cifrado; anti-QUIC PF separado | Limitação + toggle |
| DoH/DoT | Toggle anti-bypass | DoH hardcoded / DoT externo | FP-014 |
| IPv6 captura / nDPI | **Sim** (trilha V2–V3 / 12.4–12.5+) | Extension headers: caminho conservador; edge cases raros | FP-010 **fechado** no âmbito GV |
| IPv6 PF scoped | **Sim** (`inet`+`inet6`) | Mesmas regras de população de tabelas que v4 | REV-018 fechado |
| IPv6 dual-stack produto | **Sim** no âmbito GV7 / `1.9.8` | Não é “IPv6 infinito”; limites DPI acima aplicam-se a v4 e v6 | ADR-0024 **FECHADA** |
| VLAN 802.1Q | Sim (parse Ethernet) | Interfaces `.10` dependem nome real | Teste PHP |
| CDN multi-host | Parcial | DNS hint 1 hostname/IP, TTL 600 s | FP-013 |
| App-only cold start | Parcial | Primeiros pacotes Unknown até classificar | Comportamento nDPI |
| MITM TLS | **Não** (diferido 20.7a) | Sem terminação TLS; ADR-0017 (block page HTTP) vigente; ADR-0026 diferido | Squid rejeitado; reabrir só com novo GO |

---

## 3. Matriz DNS hint vs SNI

| Fonte | Cache | Escopo | Limite | Risco |
|-------|-------|--------|--------|-------|
| Respostas DNS monitorizadas | Global 1024 entradas | 1 hostname/IP | TTL 600 s | FP-013 CDN |
| SNI nDPI | Por fluxo | Por conexão TLS | ECH bloqueia | FP-014 |
| Política host | `flow_decide` | Por cliente | Requer SNI ou hint | Intermitência pré-classificado |

---

## 4. Matriz de defeitos DPI por versão

| Defeito | `_24` | `_27` | `_30` | `_31` | Impacto |
|---------|-------|-------|-------|-------|---------|
| FP-001 Hash não bidireccional | **Sim** | Corrigido | — | — | Classificação dividida |
| FP-020 Classificação parcial prematura | **Sim** | Parcial | Parcial | Corrigido | Falso allow/block intermitente |
| FP-019 Probe flow table | **Sim** | Parcial | Corrigido | — | Classificação intermitente |
| FP-008 Expire idle em fluxo classificado | **Sim** | Corrigido | — | — | Slots ocupados |
| FP-007 QNAME CNAME | **Sim** | Corrigido | — | — | Blacklist/policy errada |

---

## 5. Matriz pressão / escala

| Métrica | Comportamento (pós-`_31` / linha `1.9.x`) | Nota |
|---------|------------------------------------------|------|
| `cap_active` | JSON stats | Validar sob carga em lab |
| `cap_evicted` | Evicção LRU determinística | FP-012 |
| `cap_dropped` | Fluxo descartado janela cheia | FP-012 |
| `cap_classified` | Fluxos concluídos | Monitor/enforce |
| `captures` | Interfaces libpcap abertas | Appliance |

---

## 6. Claims incorretos vs realidade

| Claim (docs/GUI) | Realidade | Tipo |
|------------------|-----------|------|
| "Bloqueio por aplicação como UDM" | Funciona após classificação final; falha intermitente em `_24` histórico | Claim parcial |
| "SNI real sem MITM" | Verdadeiro com limite ECH | Limitação documentada ADR-0013 |
| "Anti-bypass DNS completo" | NAT anchor + blacklists; não cobre DoH hardcoded | Limitação |
| "Sem IPv6 / só IPv4" (textos antigos) | **Obsoleto** — `1.9.8` é dual-stack no âmbito ESTADO-PRODUTO | Claim desactualizado se ainda aparecer |
| "Suporte dual-stack IPv6" | **Verdadeiro** em `1.9.8` no âmbito GV7 documentado; não apaga limites DPI (ECH/QUIC/…) | OK se disclosure de limites |
| "MITM / inspecção HTTPS completa" | **Falso** — MITM diferido; block page = ADR-0017 | Overclaim proibido (PME Identity-first) |

---

## 7. Histórico desta matriz

| Data | Evento |
|------|--------|
| 2026-07-29 | Criação (baseline pré-fecho IPv6; produção `1.9.0` IPv4) |
| 2026-08-04 | Nota alinhamento passo 12.1 / GV0.4 (trilha ainda aberta) |
| 2026-08-05 | Trilha IPv6 **FECHADA**; produção **`1.9.8`** dual-stack |
| 2026-08-06 | Rev. documental: cabeçalho + linhas IPv6/MITM alinhados ao ESTADO-PRODUTO + ADR-0026 DEFER |

---

## Referências

- [`ESTADO-PRODUTO-E-PLANOS-FECHADOS.md`](../00-overview/ESTADO-PRODUTO-E-PLANOS-FECHADOS.md) — capacidades `1.9.8`
- [`ADR-0013`](../03-adr/ADR-0013-bloqueio-por-sni-via-ndpi.md) — SNI via nDPI
- [`ADR-0017`](../03-adr/ADR-0017-pagina-bloqueio-utilizador-dns-sinkhole.md) — block page sem MITM
- [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md) — IPv6 faseado V0–V6 (**fila fechada**)
- [`ADR-0026`](../03-adr/ADR-0026-mitm-tls-inspection-opt-in.md) — MITM opt-in (**implementação diferida**)
- [`plano-gates-ipv6.md`](plano-gates-ipv6.md) · [`../archive/planos-fechados/plano-ipv6-completo.md`](../archive/planos-fechados/plano-ipv6-completo.md)
- [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md)
- [`matriz-unificada-rev-fp-aud.md`](matriz-unificada-rev-fp-aud.md) — FP-010, REV-018, AUD-007
- [`posicionamento-pme-identity-first.md`](../00-overview/posicionamento-pme-identity-first.md) — anti-overclaim
- `src/layer7d/capture.c`, `capture_flow_key.h`
