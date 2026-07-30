# ADR-0018 — Plano DNS forçado (anti-bypass) e precedência de bloqueio sobre allowlist

- **Estado:** Aceito; código `1.8.11_39`/`1.8.11_40`, gate appliance pendente
- **Data:** 2026-07-30
- **Fase:** Caminho B pré-produção / F4
- **Backlog:** BG-063 (este ADR), relacionado BG-036 (allowlist), BG-062 (block page)

## Contexto

Teste em lab (cliente `192.168.95.43`, política `profile-youtube` block, modo
`enforce`): o bloqueio funcionava por 2–3 minutos e depois o YouTube voltava a
carregar. A investigação revelou duas causas:

1. **`youtube.com` estava na allowlist-seed** (secção Google/Workspace). O gate
   de allowlist corria **antes** da decisão de política, pelo que a política
   block do administrador nunca era aplicada para esses destinos.
2. **IPs partilhados de CDN Google**: domínios legitimamente allowlisted
   (`google.com`, `googleapis.com`) resolvem para os mesmos IPs que o YouTube.
   Esses IPs entravam em `layer7_allow_dst` (tag `L7ALLOW` global) e furavam as
   regras block do Layer7 para qualquer cliente.

Comparação directa com o UniFi (UDM), pedida pelo dono do produto: o UniFi
funciona porque (a) o perfil do administrador é soberano — não existe lista de
fábrica capaz de anular um bloqueio configurado; (b) o controlo é primariamente
**no plano DNS** da rede (o gateway é o resolver e o cliente não consegue
contorná-lo facilmente); (c) DPI inline complementa. O Layer7 é passivo
(captura + tabelas PF por IP), pelo que o plano DNS é ainda mais decisivo.

## Decisão

### 1. Política manual do administrador prevalece sempre (implementado em `_39`)

Precedência efectiva no daemon:

```
excepções → políticas manuais (block/allow) → allowlist-seed → blacklist UT1 → default allow
```

- No caminho DNS (`layer7_on_dns_resolved`): a decisão de política é avaliada
  **antes** do gate de allowlist. Block de política aplica-se mesmo que o
  domínio/IP esteja na seed.
- No caminho de fluxo nDPI (`layer7_on_classified_flow`): o gate de allowlist
  não anula blocks com `reason=policy_match`.
- Ao aplicar um block PF, o IP é **revogado** de `layer7_allow_dst`
  (`allow_cache_revoke_ip`) para fechar o furo da tag `L7ALLOW` em IPs de CDN
  partilhados.

A allowlist-seed volta ao papel para que foi desenhada (BG-036): proteger
contra falsos positivos de **blacklists por categoria**, nunca contra a vontade
explícita do administrador. Consistente com a regra já documentada para a
whitelist de blacklists (`DIRETRIZES-IMPLEMENTACAO.md` §10.7).

`youtube.com` foi removido da seed (`_39`).

### 2. DNS forçado global opt-in na página de bloqueio (implementado em `_40`)

Novo campo `block_page.force_dns` (default **false**). Quando activo e com a
página de bloqueio em execução (enforce + toggle):

- **NAT rdr** em cada interface de captura: todo o tráfego UDP/TCP porta 53
  para destinos externos é redireccionado para o Unbound local (`127.0.0.1`).
  Clientes com `8.8.8.8`/`1.1.1.1` hardcoded passam a receber as respostas do
  sinkhole. Reutiliza o anchor `natrules/layer7_nat` existente.
- **Anti-DoH automático**: aplica os overrides Unbound já existentes
  (NXDOMAIN para resolvers DoH conhecidos + canário `use-application-dns.net`
  que desactiva DoH no Firefox), se ainda não configurados.
- Desactivar `force_dns` **não** remove o anti-DoH (também gerido em
  Diagnostics); a remoção é acção explícita do admin.

Combinado com os toggles já existentes — anti-QUIC por interface
(`block_quic_interfaces`) e DoT/DoQ porta 853 (`block_dot_doq`) — o plano DNS
fica fechado ao nível do que o UniFi entrega, sem MITM.

### 3. Trade-off declarado: IPs de CDN partilhados

Com enforcement por IP (tabelas PF), bloquear YouTube num IP partilhado com
`google.com` pode afectar o serviço allowlisted nesse mesmo IP enquanto a
entrada block existir (TTL 60–3600 s). Isto é **inerente** à arquitectura
passiva + PF e também existe no UniFi em graus diferentes. A mitigação
principal é deslocar o bloqueio de sites para o **plano DNS** (sinkhole), onde
a granularidade é por domínio e não por IP.

## Consequências

- O modelo comercial fica alinhado com a expectativa «tipo UniFi»: o admin
  bloqueia o que quiser (Google inteiro, bancos, M365) sem excepções de
  fábrica intransponíveis.
- A seed continua a proteger instalações que usam blacklist por categoria.
- `force_dns` global é opt-in e requer página de bloqueio activa — sem
  surpresas em upgrades.
- Rollback: desligar toggles na GUI (`force_dns`, block page) reverte NAT e
  Unbound; `_38` é o pacote anterior ao novo comportamento do daemon.

## Limitações conhecidas

- HTTPS para domínios sinkhole mostra erro de certificado (sem MITM; ADR-0017).
- DoH para IPs hardcoded (sem hostname conhecido) não é coberto; mitigado por
  anti-QUIC + lista NXDOMAIN + canário.
- IPv6: rdr emitido apenas inet (IPv4), coerente com o resto do enforcement.
- ECH e VPNs de cliente continuam fora do alcance (limitação declarada da
  categoria de produto, incl. UniFi).

**Invariante adicionado na `1.8.11_44`:** o daemon nunca adiciona IPs locais
das interfaces do firewall a tabelas block (`ip_is_local_iface_addr`,
getifaddrs, cache 60s). Sem este guard, a resposta sinkhole
`domínio-bloqueado → IP portal` reentrava no enforcement e o daemon bloqueava
o próprio IP do pfSense em `layer7_block_dst`, cortando GUI/SSH de todas as
redes (incidente de lab: `192.168.100.254` inacessível a partir da VLAN 95).

## Teste mínimo

1. Enforce + política block YouTube + block page ON + `force_dns` ON.
2. Cliente LAN com DNS manual `8.8.8.8`: `nslookup youtube.com 8.8.8.8` deve
   devolver o IP portal (rdr a funcionar).
3. `http://youtube.com` → página de bloqueio; vídeos não carregam de forma
   sustentada (>10 min).
4. `pfctl -a natrules/layer7_nat -s nat` mostra as regras rdr :53.
5. Desligar toggle → regras removidas no próximo filter reload.

## Rollback

Desligar `force_dns` e/ou página de bloqueio na GUI; `pfctl -a
natrules/layer7_nat -F Nat` em emergência; downgrade para `_38` se necessário.
