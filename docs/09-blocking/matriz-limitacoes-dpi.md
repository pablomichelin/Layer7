# Matriz — limitações DPI (nDPI / captura passiva)

**Data:** 2026-07-29  
**Engine:** nDPI 5.x (estático, `HAVE_NDPI=1`)  
**Modo V1:** monitor passivo — sem MITM TLS universal  
**Estado:** CANDIDATO INTERNO EM VALIDAÇÃO

---

## 1. Arquitectura de captura

| Parâmetro | Valor | Ficheiro |
|-----------|-------|----------|
| Método | libpcap promisc, snaplen 1536 | `capture.c:452-468` |
| Datalinks | `DLT_EN10MB`, `DLT_RAW` | `capture.c:465` |
| Protocolo L3 | **IPv4 only** | `capture.c:562-563` |
| Interfaces | Nomes **reais** (`vmx0`, não `lan`) | `layer7.inc` normalização |
| Fluxos máx. | 65536 slots | `capture.c:36` |
| Pacotes/fluxo | 48 (`L7C_MAX_PKTS_PER_FLOW`) | `capture.c` |
| Idle fluxo | 120 s | `capture.c` |
| Estado nDPI final | `NDPI_STATE_CLASSIFIED` (`_31`) | `capture.c:624-628` |
| Fallback orçamento | `ndpi_detection_giveup()` | `capture.c:628` |

---

## 2. Matriz de capacidades vs limitações

| Capacidade | Suportado | Limitação | Classificação |
|------------|-----------|-----------|---------------|
| Classificação app nDPI | Sim | Resultado parcial até estado final (`_31`) | Bug corrigido FP-020 |
| Categoria nDPI | Sim | Depende de classificação final | — |
| SNI TLS | Sim (opt-in `sni_inspection`) | TLS 1.3 **ECH** oculta SNI | Limitação arquitectural |
| Host HTTP | Sim (nDPI) | HTTPS sem SNI visível → só IP | Limitação |
| QUIC | Detectável | Payload cifrado; anti-QUIC PF separado | Limitação + toggle |
| DoH/DoT | Toggle anti-bypass | DoH hardcoded / DoT externo | FP-014 |
| IPv6 | **Não** | Tráfego IPv6 ignorado na captura | FP-010 |
| VLAN 802.1Q | Sim (parse Ethernet) | Interfaces `.10` dependem nome real | Teste PHP |
| CDN multi-host | Parcial | DNS hint 1 hostname/IP, TTL 600 s | FP-013 |
| App-only cold start | Parcial | Primeiros pacotes Unknown até classificar | Comportamento nDPI |
| MITM TLS | **Não** (V1) | Sem inspecção ClientHello própria | Decisão congelada |

---

## 3. Matriz DNS hint vs SNI

| Fonte | Cache | Escopo | Limite | Risco |
|-------|-------|--------|--------|-------|
| Respostas DNS monitorizadas | Global 1024 entradas | 1 hostname/IP | TTL 600 s | FP-013 CDN |
| SNI nDPI | Por fluxo | Por conexão TLS | ECH bloqueia | FP-014 |
| Política host | `flow_decide` | Por cliente | Requer SNI ou hint | Intermitência pré-`_31` |

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

| Métrica | Comportamento `_31` | Gate pendente |
|---------|---------------------|---------------|
| `cap_active` | JSON stats | Validar sob carga |
| `cap_evicted` | Evicção LRU determinística | FP-012 |
| `cap_dropped` | Fluxo descartado janela cheia | FP-012 |
| `cap_classified` | Fluxos concluídos | Monitor passivo |
| `captures` | Interfaces libpcap abertas | Appliance |

---

## 6. Claims incorretos vs realidade

| Claim (docs/GUI) | Realidade código | Tipo |
|------------------|------------------|------|
| "Bloqueio por aplicação como UDM" | Funciona após classificação final; falha intermitente em `_24` | Claim parcial |
| "SNI real sem MITM" | Verdadeiro com limite ECH | Limitação documentada ADR-0013 |
| "Anti-bypass DNS completo" | NAT anchor + blacklists; não cobre DoH hardcoded | Limitação |
| "Suporte dual-stack" | **Falso** — IPv4-only | Claim incorreto se não disclosed |

---

## Referências

- `docs/03-adr/ADR-0013-bloqueio-por-sni-via-ndpi.md`
- FP-010, FP-013, FP-014, FP-020
- `src/layer7d/capture.c`, `capture_flow_key.h`
