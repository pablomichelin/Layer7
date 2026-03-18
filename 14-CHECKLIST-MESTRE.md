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
- [x] código C + integração no Makefile do port *(no repo)*
- [ ] **Validação lab:** serviço sobe, logs, stop limpo no pfSense
- [ ] leitura real de config (JSON) após validação do pacote
- [ ] runtime state
- [ ] counters
- [ ] error handling

## Fase 6 - Enforcement
- [ ] block básico
- [ ] monitor
- [ ] whitelist
- [ ] exceptions
- [ ] logs de ação

## Fase 7 - GUI
- [ ] settings
- [ ] policies
- [ ] exceptions
- [ ] events
- [ ] diagnostics

## Fase 8 - Observabilidade
- [ ] logs locais mínimos
- [ ] syslog remoto
- [ ] modo debug
- [ ] diagnostics úteis

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

## Documentação contínua
- [ ] CORTEX atualizado
- [ ] AGENTS atualizado
- [ ] ADRs atualizadas
- [ ] runbooks atualizados
