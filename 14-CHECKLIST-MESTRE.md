# Checklist Mestre

*Itens de lab só marcar após evidência em [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md) (ou anexo).*

**Versão actual:** 0.2.0 (motor multi-interface)

## Fase 0 - Escopo
- [x] nome do projeto definido
- [x] escopo V1 fechado
- [x] não objectivos definidos
- [x] DoD V1 definido

## Fase 1 - Lab
- [x] builder criado
- [x] pfSense lab criado
- [x] cliente de teste criado *(lab topology validada)*
- [x] syslog remoto criado *(syslog config validado 2026-03-22)*
- [x] snapshots prontos

## Fase 2 - PoC
- [x] nDPI integrado em PoC *(src/poc_ndpi/ + ADR-0001)*
- [x] eventos gerados *(event-model validado)*
- [x] tráfego real testado *(nDPI classificando tráfego real 2026-03-22)*
- [x] performance medida *(CPU/RAM aceitáveis em lab)*
- [x] limitações registadas *(docs/core/, ADRs)*

## Fase 3 - Modelagem
- [x] modelo de config definido *(docs/core/config-model.md)*
- [x] modelo de evento definido *(docs/core/event-model.md)*
- [x] categorias definidas *(docs/core/categories.md)*
- [x] precedência definida *(docs/core/precedence.md)*
- [x] fallback definido *(default allow em enforce, default monitor em monitor)*

## Fase 4 - Package skeleton (repositório)
- [x] Makefile, pkg-descr, pkg-plist, XML, PHP, rc.d, hooks pkg
- [x] Validação lab: build pacote, `pkg add`, ficheiros, GUI/URL, remove

## Fase 5 - Daemon
- [x] código C + parser mínimo `enabled`/`mode` + `-t` + SIGHUP reload
- [x] Validação lab: serviço sobe, logs, stop limpo no pfSense
- [x] parser alargado *(policies[], exceptions[], match ndpi_app/ndpi_category, tag_table, syslog remoto, debug_minutes, interfaces)*
- [x] runtime state *(SIGUSR1 stats)*
- [x] counters (SIGUSR1 + snapshot_fail)
- [x] error handling básico (degraded arranque; falha reload mantém snapshot anterior)

## Fase 6 - Enforcement
- [x] API block PF (`layer7_pf_exec_table_add`) — validado 2026-03-22
- [x] monitor *(modo monitor: default_monitor, sem pfctl)*
- [x] whitelist *(exception host impede enforce; validado 2026-03-22)*
- [x] exceptions *(motor + GUI toggle)*
- [x] logs de acção *(enforce decisions logadas)*

## Fase 7 - GUI
- [x] settings *(checkboxes dinâmicos de interfaces pfSense)*
- [x] policies *(multi-select nDPI + interfaces + IPs/CIDRs)*
- [x] exceptions *(múltiplos hosts/CIDRs + interfaces)*
- [x] events *(página de eventos estruturados)*
- [x] diagnostics *(página GUI)*
- [x] status *(página de estado)*

## Fase 8 - Observabilidade
- [x] logs locais mínimos *(formato documentado em docs/10-logging)*
- [x] syslog remoto *(código + GUI; validação lab)*
- [x] modo debug *(debug_minutes 1-720 após reload)*
- [x] diagnostics úteis *(página Diagnostics)*

## Fase 9 - Testes
- [x] instalação *(pkg add do GitHub Release validado 2026-03-22)*
- [x] reboot
- [x] persistência *(save em Settings confirmado)*
- [x] whitelist *(exception host impede enforce 2026-03-22)*
- [x] fallback *(config ausente: daemon sobe degradado)*
- [x] rollback *(pkg delete: pacote removido, config preservado)*

## Fase 10 - Piloto
- [x] enforce end-to-end validado *(pf_add_ok=7, 2026-03-23)*
- [ ] monitor mode validado em produção *(pendente: teste real)*
- [ ] tuning inicial *(pendente: teste real)*
- [ ] excepções refinadas *(pendente: teste real)*
- [ ] feedback colectado *(pendente: teste real)*

## Fase 11 - Release
- [x] changelog *(docs/changelog/CHANGELOG.md)*
- [x] release notes *(docs/06-releases/)*
- [x] `.pkg` *(v0.2.0 compilado)*
- [x] checksum *(sha256)*
- [x] install doc *(docs/tutorial/guia-completo-layer7.md)*
- [x] rollback doc *(docs/05-runbooks/)*

## Fase 12 - Pós-release / trilha V2
- [ ] backlog V2 priorizado
- [ ] bugs classificados
- [ ] ADRs complementares
- [ ] aprendizado do piloto registado

## Motor Multi-Interface (v0.2.0)
- [x] GUI Settings: checkboxes dinâmicos de interfaces
- [x] Daemon: `--list-protos` enumera nDPI protocols/categories
- [x] GUI Policies: multi-select com pesquisa para apps nDPI
- [x] Daemon: políticas com campo `interfaces[]`
- [x] Daemon: `match.src_hosts[]` e `match.src_cidrs[]`
- [x] Daemon: callback de captura com nome da interface
- [x] Daemon: `flow_decide` com filtragem por interface/IP/CIDR
- [x] GUI Policies: selector de interfaces
- [x] GUI Policies: textareas para IPs/CIDRs de origem
- [x] Daemon: excepções com múltiplos `hosts[]`/`cidrs[]`
- [x] GUI Exceptions: textareas multi-host/CIDR + interfaces
- [x] Compilação e instalação validada em lab
- [x] Guia Completo Layer7 (docs/tutorial/)
- [ ] Teste em pfSense real de produção

## Documentação
- [x] CORTEX actualizado
- [x] AGENTS actualizado
- [x] ADRs actualizadas *(ADR-0001 nDPI, ADR-0002 distribuição .txz)*
- [x] runbooks actualizados
- [x] README.md actualizado para v0.2.0
- [x] Guia Completo *(docs/tutorial/guia-completo-layer7.md)*
- [x] CHANGELOG actualizado
