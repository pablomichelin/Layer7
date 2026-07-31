# ADR-0020 — Isenção VIP no caminho DNS (sinkhole e DNS forçado)

- **Estado:** Aceito; candidato `_59` (Bloco D Lista VIP global)
- **Data:** 2026-07-31
- **Fase:** Caminho B / F4.3 (Lista VIP global)
- **Backlog:** BG-073
- **Relacionado:** ADR-0016 (PF `L7ALLOW`), ADR-0017 (sinkhole Unbound), ADR-0018 (`force_dns`)

## Contexto

A excepção canónica `vip-isentos` (BG-064, ADR-0016) isenta origens VIP de
**todo o enforcement PF e daemon** do Layer7: políticas (perfis e manuais),
blacklists UT1, anti-DoT/DoQ e anti-QUIC. O daemon avalia excepções **antes**
de todas as políticas (`layer7_decide_for_client`, `policy.c`); no PF, todas
as regras de block Layer7 usam `! tagged L7ALLOW`.

**GAP DNS verificado:** o sinkhole da block page escreve `local-zone` /
`local-data` **globais** no Unbound via
`layer7_blockpage_unbound_block()` — o resolver responde igual a todos os
clientes; não há discriminação de origem. O DNS forçado (anti-bypass,
ADR-0018) redirecciona a porta 53 **sem excepção de origem**:

- global `block_page.force_dns`: `from any` (`layer7.inc`, rdr :53);
- por blacklist (`force_dns` na regra UT1): `from <cidr>` sem isenção VIP.

Um IP na `vip-isentos` continua sujeito ao sinkhole e ao rdr :53. Consultas
DNS a domínios bloqueados resolvem para o IP portal; o cliente VIP cai na
página de bloqueio mesmo com isenção PF/daemon activa.

**Decisões fechadas (não re-litigar):**

- **D1:** `vip-isentos` permanece SSOT da isenção global — sem mecanismo
  paralelo (regra de ouro, `modelo-conceptual-gui.md`).
- **D2:** descrições por entrada em `layer7["vip_meta"]["labels"]`, **fora**
  do objecto da excepção; o daemon **nunca** lê `vip_meta`.

## Decisão

### 1. Escopo deste ADR

Este ADR cobre **apenas** a isenção VIP nos caminhos DNS (Unbound sinkhole e
NAT rdr :53). Não altera a semântica da excepção global nem duplica
`layer7_exc_allow_N` como segundo SSOT — a configuração DNS/PF deriva sempre
dos IPs/CIDRs expandidos da `vip-isentos`.

### 2. Opção (a) — PREFERIDA: view Unbound sem `view-first`

Atribuir aos IPs VIP uma **view Unbound dedicada** (sem `view-first`) via
`access-control-view`, de forma que essas origens **não herdem** as
`local-zone` / `local-data` globais injectadas pelo sinkhole da block page
(`layer7_blockpage_unbound_block`).

Implementação prevista:

- novo bloco marcado em `custom_options` do Unbound (padrão dos markers
  `L7_BLOCKPAGE_MARKER_*`: strip idempotente antes de reaplicar);
- IPs/CIDRs VIP lidos da excepção `vip-isentos` (mesma expansão que alimenta
  `layer7_exc_allow_N`);
- regeneração em `layer7_blockpage_sync()` / resync PF, alinhada ao ciclo de
  vida da block page;
- validação obrigatória com `unbound-checkconf` antes de aplicar.

**Vantagem:** isenta VIPs do sinkhole DNS por domínio — alinhado com a
expectativa «director isento de tudo», incluindo domínios bloqueados.

**Riscos a validar no lab (gate Bloco D / Bloco E):**

- efeito sobre **host overrides nativos** do pfSense/Unbound na view VIP;
- coexistência com anti-DoH global (`layer7_configure_unbound_anti_doh`);
- ordem e precedência entre views quando o cliente VIP usa resolver externo
  **e** `force_dns` está activo (pode exigir combinação com §3).

### 3. Opção (b) — FALLBACK: isentar VIPs só do rdr DNS forçado

Se (a) falhar no lab ou for incompatível com overrides nativos, implementar
isenção **parcial** e **honesta**:

- alterar regras rdr :53 (global `force_dns` e por CIDR de blacklist) para
  excluir origens VIP: `from !<layer7_exc_allow_N>` (ou tabela consolidada
  de todas as excepções allow activas);
- **obrigatório:** validar sintaxe PF com `pfctl -nf -` no ruleset completo
  antes de assumir (padrão FP-018 / BG-057);
- documentar limitação explícita: VIP que use o **resolver local** (Unbound)
  continua a receber respostas sinkhole — só escapa ao redireccionamento
  forçado da porta 53.

**Não declarar** isenção total se apenas (b) estiver activo.

### 4. Ordem de implementação

1. Tentar (a) no lab com cenário «director isento de tudo» (`validacao-lab`,
   secção prevista no Bloco E).
2. Se (a) = FAIL ou regressão inaceitável em host overrides → implementar (b)
   e actualizar GUI/help-block (modal Perfis rápidos, Lista VIP) com aviso
   sobre sinkhole local.
3. Qualquer tabela PF nova ou alteração de rdr entra em flush, self-heal e
   `layer7-pfctl flush-all` desde o primeiro commit (lição BG-061).

### 5. O que este ADR não resolve

- Isenção de anti-DoH NXDOMAIN global (continua global; VIP já isento no PF
  via `L7ALLOW` para tráfego não-DNS).
- IPv6 (rdr e enforcement IPv4-only, coerente com ADR-0018).
- Backup do `layer7.json` no `config.xml` (export/import coberto em BG-071).

## Consequências

- BG-073 fecha o gap «director isento incluindo domínios sinkhole» quando
  (a) passar no lab; caso contrário, (b) com limitação documentada.
- A help-block actual «nunca bloqueados por nenhum perfil Layer7» fica
  incompleta até Bloco D — corrigir na GUI Lista VIP (BG-071).
- Dependência: limites daemon alinhados (BG-072) para listas >8 directores
  antes de validar isenção DNS ponta a ponta.

## Limitações

- (b) não isenta do sinkhole Unbound local — apenas do rdr :53.
- (a) pode afectar resolução de overrides nativos para IPs VIP — trade-off
  a medir no appliance.
- ECH, DoH para IPs hardcoded e VPN de cliente permanecem fora do alcance
  (limitação de categoria, ADR-0018).
- NO-GO produção inalterado: candidatos `_59+` são canal teste/lab; referência
  enforce continua `1.8.11_24` até gates G2–G7.

## Teste

- Lab Bloco E: enforce + perfil block + blacklist UT1 + block page ON +
  `force_dns` ON; IP VIP navega domínios sinkhole; cliente não-VIP bloqueado
  com página.
- (a): `unbound-checkconf`; consulta DNS de IP VIP vs não-VIP para domínio
  sinkhole; verificar host override nativo.
- (b): `pfctl -nf -` com snippet rdr `from !<layer7_exc_allow_N>`; cliente
  VIP com DNS 8.8.8.8 não redireccionado; sinkhole local documentado como
  limitação.
- Builder: testes PHP do snippet Unbound/PF gerado; suite existente PASS.

## Rollback

Reinstalar pacote anterior ao Bloco D (`_58` ou `_56`); strip dos markers
Unbound VIP remove a view; regras rdr revertem no filter reload. Desactivar
block page / `force_dns` na GUI reverte NAT e Unbound (ADR-0018). Produção
enforce: manter `_24` passivo.
