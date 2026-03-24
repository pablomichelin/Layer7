# CORTEX.md

## Projeto
Layer7 para pfSense CE — por [Systemup](https://www.systemup.inf.br)

## Status atual
**Versão: 1.0.0 — Release V1 Comercial**

Primeira versao estavel e completa do Layer7 para pfSense CE. Pacote comercial com motor de politicas granulares por interface, listas de IPs/CIDRs, seleccao de apps nDPI, perfis de servico rapidos (15 built-in), pagina de categorias nDPI, dashboard com contadores em tempo real, agendamento por horario, grupos de dispositivos nomeados, bloqueio QUIC selectivo, teste de politica com simulacao completa, backup e restore de configuracao, licenciamento Ed25519 com fingerprint de hardware. EULA proprietaria. GUI com 10 paginas. Enforcement PF por destino e origem. Anti-bypass DNS multi-camada. Fleet management para 50+ firewalls.

**Validação lab (2026-03-23):** Enforce end-to-end funcional — pipeline nDPI → policy engine → pfctl comprovado:
- `pf_add_ok=7`, zero falhas — 6 IPs automaticamente adicionados à tabela PF
- Excepções respeitadas (IPs .195 e .129 não tagados)
- Decisões block/tag logadas a NOTICE
- CLI `-e` valida: BitTorrent→block, HTTP→monitor, IP excepcionado→allow

**Listas melhores e sites manuais (v0.2.6):**
- GUI de políticas com botões de seleção em massa para interfaces, apps e categorias
- novo campo `Sites/hosts` em políticas, gravado como `match.hosts[]`
- matching por host/subdomínio com base no `host=` inferido por DNS
- ação `Ver listas` para abrir o conteúdo completo de uma política existente

**Hostname e destino nos eventos (v0.2.5):**
- logs passam a incluir `dst=` do fluxo
- logs passam a incluir `host=` quando houver correlacao DNS

**Monitor ao vivo na GUI (v0.2.4):**
- aba `Events` com atualizacao automatica dos ultimos eventos
- botoes para pausar e atualizar manualmente

**Log local do daemon (v0.2.3):**
- `layer7d` grava eventos em `/var/log/layer7d.log`
- GUI Events/Diagnostics leem o log local diretamente

**Labels amigaveis de interface (v0.2.2):**
- GUI mostra a descricao configurada da interface no pfSense
- fallback seguro para label padrao quando nao houver descricao

**Empacotamento autocontido (v0.2.1):**
- `layer7d` linkado com `libndpi.a` no builder
- pacote `.pkg` sem dependência de `libndpi.so` no pfSense
- script de release valida `ldd` do binário staged

**Motor Multi-Interface (v0.2.0):**
- Políticas por interface (LAN, WIFI, ADMIN, etc.)
- Listas de IPs/CIDRs por política e excepção
- ~350 apps/categorias nDPI seleccionáveis na GUI
- Daemon `--list-protos` para enumeração dinâmica
- GUI completa com 6 páginas

## Fase atual
**V1 Comercial concluida.** Todas as fases e blocos do plano V1 completos. Release 1.0.0 pronta para build final e publicacao.

## Estado real do enforcement

**Classificação e decisão:** funcionais em pfSense real.

**Enforcement PF por destino (v0.3.0):** o daemon agora adiciona IPs de
**destino** a `layer7_block_dst` quando uma politica de bloqueio casa. Dois
caminhos complementares:

1. **DNS**: daemon observa respostas DNS; se o dominio casa com `match.hosts[]`
   de uma politica `block`, o IP resolvido entra em `layer7_block_dst`.
2. **nDPI**: quando o fluxo e classificado e a politica e `block`, o IP de
   destino do fluxo entra em `layer7_block_dst`.

A regra PF `block drop quick inet to <layer7_block_dst>` bloqueia o trafego
para esses IPs. Entradas expiram automaticamente com base no TTL DNS (minimo
5 min) para evitar crescimento indefinido da tabela.

O modelo anterior (quarentena por origem) permanece disponivel via
`layer7_block` para `action=tag` e cenarios de quarentena explicita.

**Plano mestre desta trilha:** [`docs/09-blocking/blocking-master-plan.md`](docs/09-blocking/blocking-master-plan.md) (todas as fases concluidas na v1.0.0)

## Ultima entrega
- **v1.0.0 — Release V1 Comercial (2026-03-23):**
  - Versao final com todas as funcionalidades V1
  - PORTVERSION 1.0.0, install.sh actualizado
  - CHANGELOG, README, CORTEX actualizados
  - Documentacao de blocking-master-plan marcada como concluida
  - Ficheiros obsoletos removidos (plano-v1-comercial.md, phase-a-option1)
- **v0.9.0 — licenciamento e proteccao (2026-03-23):**
  - hardware fingerprint: SHA256(kern.hostuuid + MAC) via nova funcao `layer7_hw_fingerprint()`
  - ficheiro de licenca `.lic` com JSON assinado Ed25519, verificado via OpenSSL EVP API
  - sem licenca valida: daemon opera em monitor-only (sem enforce/block)
  - verificacao no arranque + periodica cada 1h
  - grace period de 14 dias apos expiracao da licenca
  - CLI `--fingerprint` para mostrar hardware ID da maquina
  - CLI `--activate KEY [URL]` para activacao online (pronto para servidor futuro)
  - seccao de licenca na GUI (Definicoes) com estado, hardware ID, cliente, expiry
  - campos de licenca exportados no stats JSON para a GUI
  - script `generate-license.py` para gerar pares de chaves e assinar licencas
  - chave publica placeholder (all-zeros = dev mode, skip verificacao)
  - EULA proprietaria substitui BSD-2-Clause
  - link com `-lcrypto` (base system do FreeBSD)
- **v0.8.0 — teste de politica + backup/restore (2026-03-23):**
  - nova pagina "Teste" na GUI com formulario: dominio/IP destino, IP origem, app nDPI, categoria nDPI
  - simulacao completa em PHP: excepcoes, politicas, groups, schedule, matching hosts/subdominios
  - resolucao DNS automatica de dominios com exibicao dos IPs resolvidos
  - veredicto visual colorido (block=vermelho, allow=verde, monitor=azul)
  - tabela detalhada de cada politica avaliada com motivo de match/mismatch
  - botoes "Exportar configuracao" e "Importar configuracao" na pagina Definicoes
  - export gera JSON com definicoes, politicas, excepcoes e grupos (sem estado runtime)
  - import valida JSON, substitui config e envia SIGHUP + filter_configure
  - import de ficheiro invalido mostra erro sem perder config actual
  - GUI passa a ter 10 paginas
- **v0.7.0 — grupos de dispositivos + bloqueio QUIC (2026-03-23):**
  - nova seccao `groups[]` no JSON config com id, name, cidrs[], hosts[]
  - campo `match.groups` nas politicas para referenciar grupos em vez de CIDRs manuais
  - daemon expande grupos para CIDRs/IPs no parse (reutiliza logica existente de `src_cidrs`/`src_hosts`)
  - nova pagina GUI "Grupos" com CRUD completo
  - dropdown de grupos nos formularios de adicionar, editar e perfis rapidos
  - proteccao contra remocao de grupo em uso por politica
  - toggle "Bloquear QUIC (UDP 443)" na pagina Definicoes
  - regra PF anti-QUIC injectada dinamicamente via `layer7_generate_rules()`
  - `filter_configure()` chamado automaticamente ao alterar o toggle
  - GUI passa a ter 9 paginas
- **v0.6.0 — agendamento por horario (2026-03-23):**
  - campo `schedule` nas politicas JSON: `days` + `start` + `end`
  - daemon verifica hora/dia local antes de casar politica (rule_matches + domain_is_blocked)
  - suporte a overnight range (ex: 22:00-06:00)
  - GUI de politicas com checkboxes de dias e inputs de hora inicio/fim
  - "Ver listas" e tabela de politicas mostram horario configurado
  - politica sem schedule continua sempre activa (retrocompativel)
- **v0.5.0 — dashboard com contadores (2026-03-23):**
  - contadores no daemon: total classificados, total bloqueados, total permitidos
  - tracking de top 10 apps bloqueadas e top 10 IPs de origem
  - SIGUSR1 + escrita periodica (~60s) de `/tmp/layer7-stats.json`
  - pagina "Estado" redesenhada como dashboard operacional com cards de resumo
  - tabelas top 10 apps e top 10 clientes bloqueados
  - uptime do daemon calculado e exibido
  - helper `layer7_read_stats()` em layer7.inc
- **v0.4.0 — perfis de servico + categorias nDPI (2026-03-23):**
  - ficheiro `profiles.json` com 15 perfis built-in (YouTube, Facebook, Instagram, TikTok, WhatsApp, Twitter/X, LinkedIn, Netflix, Spotify, Twitch, Redes Sociais, Streaming, Jogos, VPN/Proxy, AI Tools)
  - seccao "Perfis rapidos" na pagina de politicas com cards visuais
  - modal para escolher accao, interfaces e CIDRs antes de aplicar
  - perfis expandem-se em politicas normais no JSON
  - helper `layer7_load_profiles()` em layer7.inc
  - nova pagina "Categorias" com todas as apps nDPI agrupadas por categoria
  - campo de pesquisa filtra apps e categorias em tempo real
  - accordion expansivel com contagem de apps por categoria
  - daemon `--list-protos` estendido com `protocols_by_category`
  - GUI passa a ter 8 paginas (Estado, Definicoes, Politicas, Categorias, Excecoes, Events, Diagnostics)
- **v0.3.2 — actualizacao via GUI (2026-03-23):**
  - botao "Verificar actualizacao" na pagina Definicoes
  - consulta GitHub Releases API para detectar versao mais recente
  - botao "Actualizar agora" faz download e instalacao do .pkg automaticamente
  - daemon parado e reiniciado durante a actualizacao
  - politicas, excecoes e configuracoes preservadas
- **v0.3.1 — anti-bypass DNS (2026-03-23):**
  - regras PF anti-DoT/DoQ (porta 853) no snippet do pacote
  - politica nDPI built-in `anti-bypass-dns` (DoH_DoT + iCloudPrivateRelay)
  - script Unbound anti-DoH com NXDOMAIN para dominios de bypass conhecidos
  - instalacao automatica do anti-DoH via install.sh
  - documentacao da estrategia multi-camada em pf-enforcement.md
- **v0.3.0 — bloqueio por destino (sites/apps) (2026-03-23):**
  - nova tabela PF `layer7_block_dst` + regra `block to` no snippet do pacote
  - DNS callback: daemon observa DNS e bloqueia IPs de dominios proibidos
  - enforcement nDPI: `block` agora adiciona IP de destino (nao mais de origem)
  - cache com TTL + sweep periodico para expirar entradas de destino
  - diagnostics na GUI com contadores da tabela de destino
- **v0.2.7 — enforcement PF integrado ao filtro pfSense (2026-03-23):**
  - XML do pacote declara `<filter_rules_needed>layer7_generate_rules</filter_rules_needed>`
  - regras de bloqueio entram no ruleset ativo via `discover_pkg_rules()`
  - bloqueio por origem automatico sem regra PF manual externa
- **v0.2.1 — Empacotamento autocontido (2026-03-23):**
  - build do port usa `/usr/local/lib/libndpi.a`
  - `update-ndpi.sh` aborta se o binário final ainda depender de `libndpi.so`
  - pacote validado em FreeBSD 15 lab sem dependência runtime de nDPI
- **v0.2.6 — listas melhores e sites manuais (2026-03-23):**
  - botões de seleção em massa nas listas da GUI
  - novo campo `Sites/hosts` nas políticas
  - `layer7d` passa a casar `match.hosts[]` com `host=` e subdomínios
  - nova ação `Ver listas` nas políticas
- **v0.2.5 — Hostname e destino nos eventos (2026-03-23):**
  - `flow_decide` passa a mostrar `dst=` e `host=`
  - `host=` e derivado por correlacao DNS observada na propria captura
- **v0.2.4 — Monitor ao vivo na GUI (2026-03-23):**
  - aba `Events` ganha monitor ao vivo com auto-refresh
  - filtro atual da pagina tambem se aplica ao monitor ao vivo
- **v0.2.3 — Log local do daemon (2026-03-23):**
  - `layer7d` passa a gravar eventos em `/var/log/layer7d.log`
  - GUI Events e Diagnostics deixam de depender do syslog do pfSense
- **v0.2.2 — Labels amigaveis de interface (2026-03-23):**
  - GUI Settings passa a mostrar a descricao configurada da interface
  - GUI Policies e Exceptions reutilizam o mesmo label amigavel
- **v0.2.0 — Motor Multi-Interface (2026-03-18):**
  - GUI Settings: checkboxes dinâmicos de interfaces pfSense
  - Políticas: `interfaces[]`, `match.src_hosts[]`, `match.src_cidrs[]`
  - Excepções: múltiplos `hosts[]`/`cidrs[]` + `interfaces[]`
  - `layer7d --list-protos`: JSON com protocolos/categorias nDPI
  - GUI Policies: multi-select com pesquisa para apps nDPI
  - Policy engine filtra por interface, IP e CIDR de origem
- **Documentação: Guia Completo** — `docs/tutorial/guia-completo-layer7.md` (18 secções)
- **Documentação GitHub actualizada** — README, CORTEX, CHANGELOG, checklist, roadmap

## Objetivo imediato
**V1 Comercial concluida.** Todos os 10 blocos do plano V1 executados. Proximo:
build final no FreeBSD lab, publicacao do GitHub Release v1.0.0 e piloto em producao.

## Proximos 3 passos
1. Build final no FreeBSD lab (192.168.100.12)
2. Criar GitHub Release v1.0.0
3. Piloto estavel 24h+ sem incidente + par de chaves Ed25519 de producao

## Gates pendentes para V1
- [x] Fase 6: block validado no appliance (`pfctl`) — OK 2026-03-22
- [x] Fase 6: whitelist validada no appliance — OK 2026-03-22
- [x] Fase 9: whitelist e fallback testados — OK 2026-03-22
- [x] Fase 10: nDPI integrado e a classificar trafego real — OK 2026-03-22
- [x] Fase 10: enforce end-to-end via nDPI validado — OK 2026-03-23
- [ ] Fase 10: piloto estavel 24h+ sem incidente
- [x] Fase 11: release V1 final (0.1.0) — publicada 2026-03-23
- [x] Motor multi-interface v0.2.0 — implementado 2026-03-18
- [x] Plano V1 Comercial completo (Blocos 1-10) — 2026-03-23
- [x] Release v1.0.0 preparada — 2026-03-23

## Decisoes congeladas
- foco em pfSense CE
- pacote proprietario (EULA)
- distribuição por artefacto `.pkg`
- lab distribution via GitHub Releases
- sem software pago obrigatório
- V1 sem TLS MITM universal
- V1 com modo monitor e enforce
- documentação viva obrigatória
- engine de classificação: nDPI (ADR-0001)
- actualização nDPI: compilar 1x no builder + `fleet-update.sh`; custom protocols em runtime via `fleet-protos-sync.sh`
- políticas granulares: por interface + por IP/CIDR + por app/categoria nDPI

## Riscos ativos
- assumir compatibilidade plena enquanto depende de `IGNORE_OSVERSION=yes`
- mexer na WebGUI base do pfSense fora do fluxo oficial
- primeiro teste real em produção pendente

## Itens adiados
- console central
- identidade avançada
- TLS inspection selectiva
- integração profunda com Suricata
- console multi-firewall

**Trilha pós-V1 (documental):** fases **13-22** em `03-ROADMAP-E-FASES.md`.

## Politica de trabalho
- um bloco por vez
- uma validação por vez
- nada marcado como feito sem evidência de lab
- docs no mesmo commit

## Definition of Done da V1
- [x] pacote instalavel com evidencia
- [x] daemon funcional com evidencia
- [x] GUI completa (10 paginas) com evidencia
- [x] policy engine (granular: interface/IP/grupo/horario/app/categoria/host)
- [x] enforcement completo (PF por destino + origem)
- [x] observabilidade (dashboard, contadores, logs, syslog remoto)
- [x] perfis de servico (15 built-in)
- [x] licenciamento Ed25519
- [x] backup e restore
- [x] teste de politica
- [ ] rollback validado em producao
- [x] docs completas
