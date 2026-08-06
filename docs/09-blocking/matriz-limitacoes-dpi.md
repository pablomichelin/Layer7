# Matriz — limitações DPI (nDPI / captura passiva)

**Data:** 2026-07-29  
**Rev. alinhamento IPv6:** 2026-08-04 (passo **12.1** / GV0.4)  
**Engine:** nDPI 5.x (estático, `HAVE_NDPI=1`)  
**Modo V1:** monitor passivo — sem MITM TLS universal  
**Estado:** Produção enforce `1.9.0` (IPv4); trilha IPv6 **ABERTA** (sem claim dual-stack)

> **IPv6 (honestidade):** captura e decisão DPI são **IPv4-only** até ondas V2–V3.
> PF scoped emite `inet`+`inet6` desde 12.3 (REV-018 fechado); sem IPs v6 nas
> tabelas até o daemon V3. Não afirmar dual-stack completo até GV7.
> SSOT: [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md) ·
> [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md) ·
> arranque [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md).

---

## 1. Arquitectura de captura

| Parâmetro | Valor | Ficheiro |
|-----------|-------|----------|
| Método | libpcap promisc, snaplen 1536 | `capture.c:452-468` |
| Datalinks | `DLT_EN10MB`, `DLT_RAW` | `capture.c:465` |
| Protocolo L3 | **IPv4 only** (`ip_v != 4` descartado) | `capture.c:562-563` |
| Interfaces | Nomes **reais** (`vmx0`, não `lan`) | `layer7.inc` normalização |
| Fluxos máx. | 65536 slots | `capture.c:36` |
| Pacotes/flujo | 48 (`L7C_MAX_PKTS_PER_FLOW`) | `capture.c` |
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
| IPv6 captura / nDPI | **Parcial** (12.4: parse+fluxo+nDPI; DNS AAAA hint e métricas = 12.5+) | Extension headers conservadores; hint DNS ainda v4 | FP-010 |
| IPv6 PF scoped | **Sim** (`inet`+`inet6`, passo 12.3) | Tabelas ainda só populadas com v4 até V3; captura v6 ausente | REV-018 fechado |
| IPv6 dual-stack produto | **Não** (até GV7) | Trilha V0–V6 aberta; produção `1.9.0` inalterada | ADR-0024 |
| VLAN 802.1Q | Sim (parse Ethernet) | Interfaces `.10` dependem nome real | Teste PHP |
| CDN multi-host | Parcial | DNS hint 1 hostname/IP, TTL 600 s | FP-013 |
| App-only cold start | Parcial | Primeiros pacotes Unknown até classificar | Comportamento nDPI |
| MITM TLS | **Não** (V1; add-on **diferido** 20.7a) | Sem inspecção ClientHello própria; ADR-0026 implementação diferida; Identity-first PME | Squid rejeitado; reabrir só com novo GO |

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
| "Suporte dual-stack IPv6" / produto IPv6-completo | **Falso** em `1.9.0` — disclosure V0; implementação por ondas ADR-0024 | Claim incorreto se não disclosed (I1) |

---

## Referências

- [`ADR-0013`](../03-adr/ADR-0013-bloqueio-por-sni-via-ndpi.md) — SNI via nDPI
- [`ADR-0024`](../03-adr/ADR-0024-suporte-ipv6-ativacao-faseada.md) — IPv6 faseado V0–V6
- [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md) · [`plano-gates-ipv6.md`](plano-gates-ipv6.md)
- [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md) (M-01, M-23)
- [`matriz-unificada-rev-fp-aud.md`](matriz-unificada-rev-fp-aud.md) — FP-010, REV-018, AUD-007
- FP-010, FP-013, FP-014, FP-020
- `src/layer7d/capture.c`, `capture_flow_key.h`
