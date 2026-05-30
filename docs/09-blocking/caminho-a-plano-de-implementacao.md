# Caminho A — Plano de implementacao (UX e eficacia tipo UDM Pro)

> **Estado:** plano (documental). Nenhum codigo do produto e alterado por este
> documento. Cada bloco abaixo so arranca quando puxado formalmente para a
> frente (regra do backlog) e entrega-se em **blocos pequenos, reversiveis**.

## Contexto

A **Fase 1 de estabilizacao** da V1 comercial foi entregue e validada na
release [`v1.8.11_18`](../changelog/CHANGELOG.md) (monitor passivo real,
allowlist de destinos, flush fiavel, CLI `--license-status`). Com a base
estavel, o **Caminho A** foca em aproximar a **UX e a eficacia** de um
UniFi UDM Pro, **sem MITM universal** e sem reescrever o que ja existe.

Este plano e o documento operacional do "Caminho A" e mapeia nas fases V2 ja
declaradas no roadmap historico [`03-ROADMAP-E-FASES.md`](../../03-ROADMAP-E-FASES.md):

- **Fase 15** — politica DNS / dominio / FQDN;
- **Fase 17** — identidade e contexto (dispositivos);
- **Fase 18** — TLS inspection seletiva / SNI (opt-in).

Referencia tecnica honesta de estado e limites: [`blocking-master-plan.md`](blocking-master-plan.md).

## Estado actual honesto (o que JA existe)

Levantamento read-only do codigo confirma que o produto **ja** entrega:

- 16 perfis de servico/funcao em `package/.../etc/layer7/profiles.json`
  (youtube, facebook, instagram, tiktok, whatsapp, twitter, linkedin, netflix,
  spotify, twitch, social, streaming, gaming, vpn-proxy, ai-tools,
  remote-access, anti-bypass-dns);
- politicas por **app/categoria nDPI + host (suffix) + IP/CIDR/grupo de
  origem + interface + schedule**;
- bloqueio real por **destino** via correlacao DNS->IP em `layer7_block_dst`
  (cache com TTL), e blacklists UT1 por categoria em `layer7_bld_N`;
- allowlist de destinos (`layer7_allow_dst`, `pass quick`) — base CDN-aware;
- toggles QUIC/DoT/DoQ; GUI completa (Estado, Politicas, Perfis rapidos,
  Grupos, Excepcoes, Blacklists, Allowlist, Eventos, Relatorios, Definicoes,
  Diagnosticos).

## Gaps reais face ao UDM Pro

1. **Identidade de dispositivo:** sem inventario IP<->MAC<->hostname<->vendor;
   politicas so por IP/CIDR/grupo, **nunca por MAC/dispositivo**.
2. **SNI real:** nao ha parser de TLS ClientHello; o "host" vem **so** de
   correlacao DNS->IP — frágil sob DoH/cache do cliente/QUIC.
3. **CDN:** so allowlist conservadora + limitacao documentada; sem decisao
   strict/permissive por SNI para destinos partilhados.
4. **UX:** activar um perfil cria uma politica estatica `profile-*`; falta
   toggle on/off directo e vista unificada de Apps & Categorias & Perfis.
5. **Higiene:** perfil **GitHub** prometido no plano mestre **nao existe**;
   limite de hosts diverge (GUI grava 64, daemon aceita 32 -> truncamento
   silencioso); docs antigas ainda dizem "block = quarentena de origem" quando
   o runtime nDPI bloqueia **destino**.

## Principios

- **Monitor-first:** todo bloco novo nasce observavel antes de impor.
- **Blocos pequenos e reversiveis:** build + smoke + lab + rollback por bloco.
- **Honestidade tecnica:** expor limites (ECH/encrypted SNI, IP partilhado)
  em vez de prometer magia.
- **Reutilizar:** estender perfis/grupos/allowlist existentes, nao recriar.

## Blocos (ordem segura — baixo risco primeiro)

### A0 — Higiene e quick wins (baixo risco, sem arquitectura)

- Adicionar perfil `github` em `profiles.json`.
- Alinhar limite de hosts GUI<->daemon (eliminar truncamento silencioso;
  avisar na GUI quando exceder).
- Clarificar nas docs que `block` em runtime = bloqueio por **destino**
  (`layer7_block_dst`), nao quarentena de origem
  (`docs/05-daemon/pf-enforcement.md`, `blocking-master-plan.md`).
- **Teste:** `php -l`, perfis carregam, `smoke-monitor-mode.sh` exit 0.
- **Rollback:** reverter `profiles.json` e limite; docs.

### A1 — Inventario de dispositivos (read-only / monitor) [Fase 17]

- Ler DHCP leases + tabela ARP do pfSense para construir inventario
  IP<->MAC<->hostname<->vendor (OUI), com alias editavel persistente.
- Nova pagina GUI **Dispositivos** (so leitura): lista, ultima actividade.
- **Sem** alterar enforcement. ADR por fonte de identidade (DHCP/ARP).
- **Teste:** inventario coincide com `Status > DHCP Leases` em lab.
- **Rollback:** remover pagina + cache de inventario.

### A2 — Politicas e grupos por dispositivo [Fase 17]

- Alvo de politica por **dispositivo** (MAC -> IP actual via leases) e grupos
  dinamicos baseados no inventario A1; resolucao no resync e em mudanca DHCP.
- Reutiliza a estrutura de `groups[]` existente.
- **Teste:** lab — politica por MAC bloqueia o dispositivo certo mesmo apos
  troca de IP por DHCP; rollback para IP/CIDR intacto.
- **Rollback:** politicas por IP/CIDR/grupo continuam a funcionar.

### A3 — SNI-aware / CDN (opt-in, strict/permissive) [Fase 18]

- Parser de **TLS ClientHello** no daemon para extrair SNI real (complementar
  ao DNS), usado quando o DNS nao esta disponivel.
- Modo CDN por politica: **permissive** (actual, DNS->IP) vs **strict**
  (exige match de SNI para bloquear destino partilhado -> menos falsos
  positivos em CDN).
- **Limite honesto:** nao e MITM; le apenas ClientHello em claro; ECH /
  encrypted SNI continua limite documentado.
- **Teste:** lab com destino CDN partilhado (ex. youtube vs outro servico no
  mesmo IP) sem falso positivo em destino critico.
- **Rollback:** desligar parser/modo strict -> volta ao comportamento DNS->IP.

### A4 — UX tipo UDM (toggles e vista unificada)

- Toggle de perfil **on/off** directo (sem gerir politica estatica
  `profile-*`), estado por perfil.
- Vista unificada **Apps & Categorias & Perfis** com hit counters por perfil
  e por dispositivo; icones e descricoes.
- **Teste:** activar/desactivar perfil reflecte-se nas tabelas PF e counters.
- **Rollback:** politicas explicitas continuam disponiveis.

### A5 — Hardening, F5 alargada e fleet

- Estender testes (policy decision BG-012/013, resolve de dispositivo, parse
  de SNI), matriz de lab, counters exportaveis, docs e rollout.
- **Gate:** suite minima de regressao do Caminho A executavel e repetivel.

## Definition of Done do Caminho A

- Instalar e ter UX onde se escolhem perfis/listas com toggle e se aplicam
  **por dispositivo**;
- eficacia melhorada com SNI onde tecnicamente possivel;
- limites (ECH, IP partilhado, DoH) expostos com honestidade;
- cada bloco com build + smoke + lab + rollback validados.

## Gate global

- Nenhum bloco avanca sem ser puxado no backlog (BG-039+).
- Cada bloco actualiza docs no mesmo bloco e mantem `smoke-monitor-mode.sh`
  em exit 0 (nao regressao da Fase 1).
- Decisoes de arquitectura (identidade, SNI/TLS) geram ADR antes de implementar.
