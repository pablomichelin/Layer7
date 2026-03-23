# Checklist Mestre

*Itens de lab so marcar apos evidencia em [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md) (ou anexo).*

## Fase 0 - Escopo
- [x] nome do projeto definido
- [x] escopo V1 fechado
- [x] nao objetivos definidos
- [x] DoD V1 definido

## Fase 1 - Lab
- [x] builder criado
- [x] pfSense lab criado
- [ ] cliente de teste criado
- [ ] syslog remoto criado
- [ ] snapshots prontos

## Fase 2 - PoC
- [x] nDPI integrado em PoC *(src/poc_ndpi/ + ADR-0001)*
- [ ] eventos gerados
- [ ] trafego real testado
- [ ] performance medida
- [ ] limitacoes registradas

## Fase 3 - Modelagem
- [x] modelo de config definido *(docs/core/config-model.md)*
- [x] modelo de evento definido *(docs/core/event-model.md)*
- [x] categorias definidas *(docs/core/categories.md)*
- [x] precedence definida *(docs/core/precedence.md)*
- [x] fallback definido *(precedence.md: default allow em enforce, default monitor em monitor)*

## Fase 4 - Package skeleton (repositorio)
- [x] Makefile, pkg-descr, pkg-plist, XML, PHP informativo, rc.d, hooks pkg
- [x] Validacao lab: build pacote, `pkg add`, ficheiros, GUI/URL, remove - ver `validacao-lab.md`

## Fase 5 - Daemon
- [x] codigo C + parser minimo `enabled`/`mode` + `-t` + SIGHUP reload *(repo)*
- [x] Validacao lab: servico sobe, logs, stop limpo no pfSense
- [x] parser alargado *(policies[], exceptions[], match ndpi_app/ndpi_category, tag_table, syslog remoto, debug_minutes, interfaces)*
- [x] runtime state *(SIGUSR1 stats: reload_ok, snapshot_fail, sighup, loop_ticks, policies, exceptions, enforce_cfg, pf_add_ok/fail)*
- [x] counters (SIGUSR1 + snapshot_fail; lab operacao pendente)
- [x] error handling basico (degraded arranque; falha reload mantem snapshot anterior)

## Fase 6 - Enforcement
- [x] API block PF (`layer7_pf_exec_table_add`; integracao nDPI pendente)
- [x] monitor *(modo monitor: default_monitor, sem pfctl, safeguard em layer7_on_classified_flow)*
- [x] whitelist *(exception host 10.0.0.99 impede enforce; validado no appliance 2026-03-22)*
- [x] exceptions *(motor + GUI toggle)*
- [x] logs de acao *(enforce decisions logadas via L7_WARN em pfctl fail + L7_INFO em enforce_action)*

## Fase 7 - GUI
- [x] settings *(repo)*
- [x] policies *(toggle + adicionar + editar GUI + remover; id so JSON)*
- [x] exceptions *(lista + toggle + adicionar + editar + remover; id so JSON)*
- [x] events *(pagina basica; eventos estruturados seguem modelo event-model)*
- [x] diagnostics *(pagina GUI)*

## Fase 8 - Observabilidade
- [x] logs locais minimos *(formato documentado em docs/10-logging)*
- [x] syslog remoto *(codigo + GUI; validacao lab com coletor)*
- [x] modo debug *(debug_minutes 1-720 apos reload)*
- [x] diagnostics uteis *(pagina Diagnostics)*

## Fase 9 - Testes
- [x] instalacao *(pkg add do GitHub Release validado 2026-03-22)*
- [x] reboot
- [x] persistencia *(save em `Settings` confirmado com alteracao de `/usr/local/etc/layer7.json` no appliance)*
- [x] whitelist *(exception host impede enforce no appliance 2026-03-22)*
- [x] fallback *(config ausente: daemon sobe degradado; config invalido: snapshot anterior mantido)*
- [x] rollback *(pkg delete: pacote removido, config preservado, dashboard OK 2026-03-22)*

## Fase 10 - Piloto
- [ ] monitor mode validado *(requer appliance com trafego)*
- [ ] tuning inicial
- [ ] excecoes refinadas
- [ ] feedback coletado

## Fase 11 - Release
- [x] changelog *(docs/changelog/CHANGELOG.md)*
- [x] release notes *(docs/06-releases/release-notes-template.md)*
- [x] `.txz` *(v0.0.31-lab1 publicado no GitHub)*
- [x] checksum *(sha256 publicado no GitHub)*
- [x] install doc *(docs/04-package/deploy-github-lab.md + validacao-lab.md)*
- [x] rollback doc *(docs/05-runbooks/rollback.md + validacao-lab.md §10)*

## Fase 12 - Pos-release / trilha V2
- [ ] backlog V2 priorizado
- [ ] bugs classificados
- [ ] ADRs complementares
- [ ] aprendizado do piloto registado

## Fase 13 - nDPI no daemon (producao)
- [ ] loop captura -> classificacao -> policy -> enforce no appliance
- [ ] CPU/RAM documentados
- [ ] fallback monitor-only

## Fase 14 - GUI V1 completa
- [ ] policies/exceptions/events/diagnostics completos
- [x] validacao input *(syslog host + matriz em docs/package/gui-validation.md)*
- [ ] sync GUI<->JSON sem drift

## Fase 15 - Politica DNS/dominio
- [ ] regras FQDN/dominio (caminho CE definido)
- [ ] testes lab + limitacoes (DoH, TTL)

## Fase 16 - Observabilidade
- [ ] logs formato estavel + rotacao
- [ ] syslog remoto validado
- [ ] counters exportaveis

## Fase 17 - Identidade (onde viavel)
- [ ] fonte identidade + ADR
- [ ] um modo validado em lab

## Fase 18 - TLS inspection seletiva (opt-in)
- [ ] piloto lab, sem default-on
- [ ] risco legal/ops documentado

## Fase 19 - Correlacao IDS/Suricata
- [ ] demo correlacao lab
- [ ] degradacao sem IDS

## Fase 20 - Escala / HA
- [ ] stress leve documentado
- [ ] notas HA/CARP

## Fase 21 - Ciclo nDPI/assinaturas
- [ ] procedimento upgrade/rebuild
- [ ] rollback versao nDPI doc

## Fase 22 - API local / automacao
- [ ] ADR superficie API/hooks
- [ ] exemplos + runbook seguranca

## Documentacao continua
- [x] CORTEX atualizado
- [x] AGENTS atualizado
- [x] ADRs atualizadas *(ADR-0001 nDPI, ADR-0002 distribuicao .txz)*
- [x] runbooks atualizados *(validacao-lab, quick-start, webgui-safety, rollback)*
