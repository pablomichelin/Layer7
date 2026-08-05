# ADR-0024 — Suporte IPv6: activação faseada (trilha V0–V6)

**Estado:** Aceito (publicado; implementação por ondas; GV0 fecha disclosure V0)  
**Data:** 2026-08-04  
**Rev.:** 2026-08-04c (um só START-HERE; salvaguardas)  
**Decisores:** Operador + agente (governança pós-fecho plano mestre)

---

## Contexto

- O produto declara alvo **pfSense CE** em redes que frequentemente operam **dual-stack**.
- A V1 (`1.9.0`) validou gates G2–G7 em **IPv4**; limitação **FP-010** / **AUD-007**:
  `capture.c` ignora tráfego não-IPv4 (`ip_v != 4`).
- **REV-018:** regras PF `scoped_hybrid` emitidas só com `inet` — bypass IPv6 em políticas.
- PF global já inclui algumas regras `inet6` (anti-DoT/QUIC, `layer7_block*`), mas o daemon
  não popula tabelas com endereços IPv6.
- O plano mestre de fecho listava «IPv6 completo» como **fora** do R1–R12; pós-Onda J abre-se
  trilha dedicada [`plano-ipv6-completo.md`](../02-roadmap/plano-ipv6-completo.md).
- Arranque de chat **único:**
  [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md).
  Não criar `START-HERE` paralelo para IPv6.

---

## Decisão

Adoptar **implementação faseada** do suporte IPv6 em **sete ondas (V0–V6)** com passos
numerados **12.1–12.13**, sem reabrir o veredicto do plano de fecho (`1.9.0` produção).

**Nota de numeração:** passos 12.x desta trilha ≠ `test-matrix.md` §12 (blacklists F4.2).

### V0 — Honestidade (obrigatória primeiro)

- Publicar limitação na GUI e documentação canónica (**I1**).
- Mapa de rastreabilidade M-01..M-25 mantido vivo (incl. salvaguardas §8).
- **Não** prometer «dual-stack completo» até GV7.
- ADR **Aceito** desde a publicação; **GV0** mede o pacote completo V0 (banner +
  matriz + índices), não a mera existência deste ficheiro.

### V1 — Paridade PF scoped (obrigatória antes do daemon)

- Emitir pares `inet`/`inet6` em `pdst`, `psrc`, `pallow`, `pexc`, `exc_allow`.
- Fechar **REV-018**; gate **GV1**.
- **Salvaguardas obrigatórias no desenho (antes de merge V1):**
  - não quebrar **NDP / ICMPv6** necessário à LAN;
  - `!<localsubnets>` (e equivalentes) devem cobrir redes locais **IPv6**;
  - nunca popular tabelas de block com `fe80::/10`, `ff00::/8`, `::1`.

### V2–V3 — Pipeline daemon (núcleo)

- Captura Ethernet `0x86DD`, tabela de fluxos IPv6, nDPI, `policy.c` CIDR v6,
  `enforce.c` / `pfctl` com endereços v6, allowlist v6.
- Captura deve prever **extension headers / fragmentação** (não só EtherType).
- Gates **GV2–GV4**.
- **M-09 (blacklist AAAA):** popular tabelas v6 em V3; sinkhole/`rdr` AAAA só
  com decisão V5 — half-fix AAAA sem rdr deve ficar documentado se V5 adiar.

### V4 — Configuração e GUI

- Validação e persistência IPv6 em `layer7.inc` e páginas GUI.
- Até V4, lab V3 pode usar JSON manual (sem validação GUI v6).

### V5 — NAT / DNS / block page (gate humano)

- **Opção A:** implementar `rdr inet6` coerente com ADR-0017/0018/0020.
- **Opção B:** **ADIAR** com limite explícito em MANUAL/GUI (**I7** satisfeito por exclusão).
- Requer decisão humana registada antes do passo 12.10.

#### Decisão registada (`2026-08-05`) — Opção B **temporária** (não permanente)

**Operador:** Systemup / Pablo Michelin.  
**Escolha:** **Opção B** — adiar implementação de DNS forçado / block page /
VIP DNS em IPv6 (`rdr inet6`) **agora**.

**Ressalva obrigatória (não é abandono):**

1. V5 **não** fica cancelada. Os passos **12.10–12.11** e **BG-083**
   permanecem no plano como trabalho **a retomar**.
2. Retomar **da maneira certa** (Opção A completa, com salvaguardas NDP/ICMPv6
   e smoke lab) **depois** de: release lab com código 12.6–12.9 (`1.9.2`),
   gates appliance GV1/GV3/GV4 PASS (ou veredicto limitado documentado), e
   novo GO humano explícito para abrir 12.10.
3. Até lá: **não** afirmar «DNS forçado IPv6» / «block page IPv6» / «VIP DNS
   IPv6»; disclosure na GUI (Diagnostics) e docs (I7 por exclusão temporária).
4. Controlo L7 em IPv6 (captura, política, PF, allowlist, GUI host/CIDR)
   **já entregue** em 12.3–12.9 — a exclusão aplica-se **só** a NAT/DNS/portal.

**I7:** satisfeito **temporariamente** por esta exclusão formal; fecha-se de
forma definitiva quando 12.10–12.11 (Opção A) PASS ou quando uma emenda
futura declarar adiamento permanente (não é o caso agora).

**Status trilha (`2026-08-05`, 12.13/GV7):** núcleo dual-stack **FECHADO**
(I1–I6+I8; GV7.1–GV7.3). Esta Opção B **permanece temporária**. Produção
enforce **não** promovida (GV7.4 PENDENTE). Residual: retomar 12.10 com GO
Opção A, ou GO de promoção `1.9.6` separado.

### V6 — Fecho

- Campanha lab dual-stack; release; promoção na série patch `1.9.n`
  (`1.9.1`, `1.9.2`, …) só após **GV7**. Sem `PORTREVISION`/`_N`; sem salto
  automático a `1.10.0`.
- V6 pode avançar em paralelo com V5 **adiado** para fechar gates do núcleo
  dual-stack; **não** marcar trilha IPv6 “completa comercial” sem retomar V5
  ou emenda permanente.

---

## Alternativas consideradas

| Alternativa | Motivo de rejeição |
|-------------|-------------------|
| Big-bang IPv6 num único PR | Risco de regressão IPv4; viola AGENTS.md |
| Manter só docs sem código | Redes dual-stack continuam vulneráveis |
| Bloquear todo IPv6 no PF (`block inet6`) | Quebra clientes legítimos; fora do escopo comercial |
| Reabrir plano mestre P0–J | Perde rastreabilidade do fecho `1.9.0` |
| Segundo START-HERE só para IPv6 | Confunde handoff; **rejeitado** — arranque único no fecho |

---

## Consequências

### Positivas

- Trilha auditável; handoff em chat novo via `START-HERE-fecho-producao.md`.
- IPv4 permanece SSOT de produção até GV7.

### Negativas

- Esforço **G** (várias ondas, testes appliance).
- V5 (NAT/DNS v6) pode exigir decisões de design não triviais (NAT64, NPT).
- Allowlist por IP v6 frágil com Privacy Extensions (preferir CIDR; MAC→IP v6 diferido).

---

## Critérios de revisão desta ADR

- Emenda obrigatória se V5 adoptar Opção B **permanente** de DNS v6 (não é o
  caso da decisão `2026-08-05` — essa é adiamento **temporário** com retoma).
- Emenda ao reabrir 12.10 (GO humano Opção A) ou se mudar a ressalva.
- Emenda se mudar ordem V1↔V2 (não recomendado).
- Emenda se inventário DHCPv6/MAC→IPv6 entrar no escopo I1–I8.

---

## Referências

- FP-010, REV-018, AUD-007
- [`f4-ipv6-mapa-rastreabilidade.md`](../01-architecture/f4-ipv6-mapa-rastreabilidade.md)
- [`plano-gates-ipv6.md`](../09-blocking/plano-gates-ipv6.md)
- [`matriz-limitacoes-dpi.md`](../09-blocking/matriz-limitacoes-dpi.md)
- ADR-0017, ADR-0018, ADR-0020
- [`START-HERE-fecho-producao.md`](../00-overview/START-HERE-fecho-producao.md)
