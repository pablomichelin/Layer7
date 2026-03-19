# Checklist Mestre

*Itens de lab so marcar apos evidencia em [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md) (ou anexo).*

## Fase 0 - Escopo
- [ ] nome do projeto definido
- [ ] escopo V1 fechado
- [ ] nao objetivos definidos
- [ ] DoD V1 definido

## Fase 1 - Lab
- [x] builder criado
- [x] pfSense lab criado
- [ ] cliente de teste criado
- [ ] syslog remoto criado
- [ ] snapshots prontos

## Fase 2 - PoC
- [ ] nDPI integrado em PoC
- [ ] eventos gerados
- [ ] trafego real testado
- [ ] performance medida
- [ ] limitacoes registradas

## Fase 3 - Modelagem
- [ ] modelo de config definido
- [ ] modelo de evento definido
- [ ] categorias definidas
- [ ] precedence definida
- [ ] fallback definido

## Fase 4 - Package skeleton (repositorio)
- [x] Makefile, pkg-descr, pkg-plist, XML, PHP informativo, rc.d, hooks pkg
- [x] Validacao lab: build pacote, `pkg add`, ficheiros, GUI/URL, remove - ver `validacao-lab.md`

## Fase 5 - Daemon
- [x] codigo C + parser minimo `enabled`/`mode` + `-t` + SIGHUP reload *(repo)*
- [x] Validacao lab: servico sobe, logs, stop limpo no pfSense
- [ ] parser alargado (policies, GUI->ficheiro) quando necessario
- [ ] runtime state
- [x] counters (SIGUSR1 + snapshot_fail; lab operacao pendente)
- [x] error handling basico (degraded arranque; falha reload mantem snapshot anterior)

## Fase 6 - Enforcement
- [x] API block PF (`layer7_pf_exec_table_add`; integracao nDPI pendente)
- [ ] monitor
- [ ] whitelist
- [x] exceptions *(motor + GUI toggle)*
- [ ] logs de acao

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
- [x] instalacao
- [ ] reboot
- [ ] persistencia
- [ ] whitelist
- [ ] fallback
- [x] rollback

## Fase 10 - Piloto
- [ ] monitor mode validado
- [ ] tuning inicial
- [ ] excecoes refinadas
- [ ] feedback coletado

## Fase 11 - Release
- [ ] changelog
- [ ] release notes
- [ ] `.txz`
- [ ] checksum
- [ ] install doc
- [ ] rollback doc

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
- [ ] AGENTS atualizado
- [ ] ADRs atualizadas
- [ ] runbooks atualizados
