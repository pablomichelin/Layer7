# Checklist Mestre

*Itens de lab só marcar após evidência em [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md) (ou anexo).*

## Fase 0 - Escopo
- [ ] nome do projeto definido
- [ ] escopo V1 fechado
- [ ] não objetivos definidos
- [ ] DoD V1 definido

## Fase 1 - Lab
- [ ] builder criado
- [ ] pfSense lab criado
- [ ] cliente de teste criado
- [ ] syslog remoto criado
- [ ] snapshots prontos

## Fase 2 - PoC
- [ ] nDPI integrado em PoC
- [ ] eventos gerados
- [ ] tráfego real testado
- [ ] performance medida
- [ ] limitações registradas

## Fase 3 - Modelagem
- [ ] modelo de config definido
- [ ] modelo de evento definido
- [ ] categorias definidas
- [ ] precedence definida
- [ ] fallback definido

## Fase 4 - Package skeleton (repositório)
- [x] Makefile, pkg-descr, pkg-plist, XML, PHP informativo, rc.d, hooks pkg
- [ ] **Validação lab:** build `.txz`, `pkg add`, ficheiros, GUI/URL, remove — ver `validacao-lab.md`

## Fase 5 - Daemon
- [x] código C + parser mínimo `enabled`/`mode` + `-t` + SIGHUP reload *(repo)*
- [ ] **Validação lab:** serviço sobe, logs, stop limpo no pfSense
- [ ] parser alargado (policies, GUI→ficheiro) quando necessário
- [ ] runtime state
- [x] counters (SIGUSR1 + snapshot_fail; lab operação pendente)
- [x] error handling básico (degraded arranque; falha reload mantém snapshot anterior)

## Fase 6 - Enforcement
- [x] API block PF (`layer7_pf_exec_table_add`; integração nDPI pendente)
- [ ] monitor
- [ ] whitelist
- [x] exceptions *(motor + GUI toggle)*
- [ ] logs de ação

## Fase 7 - GUI
- [x] settings *(repo)*
- [x] policies *(toggle + adicionar + editar GUI + remover; id só JSON)*
- [x] exceptions *(lista + toggle + adicionar + editar + remover; id só JSON)*
- [x] events *(página básica; eventos estruturados seguem modelo event-model)*
- [x] diagnostics *(página GUI)*

## Fase 8 - Observabilidade
- [x] logs locais mínimos *(formato documentado em docs/10-logging)*
- [x] syslog remoto *(código + GUI; validação lab com coletor)*
- [x] modo debug *(debug_minutes 1–720 após reload)*
- [x] diagnostics úteis *(página Diagnostics)*

## Fase 9 - Testes
- [ ] instalação
- [ ] reboot
- [ ] persistência
- [ ] whitelist
- [ ] fallback
- [ ] rollback

## Fase 10 - Piloto
- [ ] monitor mode validado
- [ ] tuning inicial
- [ ] exceções refinadas
- [ ] feedback coletado

## Fase 11 - Release
- [ ] changelog
- [ ] release notes
- [ ] `.txz`
- [ ] checksum
- [ ] install doc
- [ ] rollback doc

## Fase 12 - Pós-release / trilha V2
- [ ] backlog V2 priorizado
- [ ] bugs classificados
- [ ] ADRs complementares
- [ ] aprendizado do piloto registado

## Fase 13 - nDPI no daemon (produção)
- [ ] loop captura → classificação → policy → enforce no appliance
- [ ] CPU/RAM documentados
- [ ] fallback monitor-only

## Fase 14 - GUI V1 completa
- [ ] policies/exceptions/events/diagnostics completos
- [x] validação input *(syslog host + matriz em docs/package/gui-validation.md)*
- [ ] sync GUI↔JSON sem drift

## Fase 15 - Política DNS/domínio
- [ ] regras FQDN/domínio (caminho CE definido)
- [ ] testes lab + limitações (DoH, TTL)

## Fase 16 - Observabilidade
- [ ] logs formato estável + rotação
- [ ] syslog remoto validado
- [ ] counters exportáveis

## Fase 17 - Identidade (onde viável)
- [ ] fonte identidade + ADR
- [ ] um modo validado em lab

## Fase 18 - TLS inspection seletiva (opt-in)
- [ ] piloto lab, sem default-on
- [ ] risco legal/ops documentado

## Fase 19 - Correlação IDS/Suricata
- [ ] demo correlação lab
- [ ] degradação sem IDS

## Fase 20 - Escala / HA
- [ ] stress leve documentado
- [ ] notas HA/CARP

## Fase 21 - Ciclo nDPI/assinaturas
- [ ] procedimento upgrade/rebuild
- [ ] rollback versão nDPI doc

## Fase 22 - API local / automação
- [ ] ADR superfície API/hooks
- [ ] exemplos + runbook segurança

## Documentação contínua
- [ ] CORTEX atualizado
- [ ] AGENTS atualizado
- [ ] ADRs atualizadas
- [ ] runbooks atualizados
