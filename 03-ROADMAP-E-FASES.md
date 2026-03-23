# Roadmap e Fases

## Visão geral

O projeto deve ser executado em **fases numeradas** com entrada, saída e gate claro: **V1 (0–11)**, **transição (12)**, **V2+ (13–22)** — as fases 13–22 são *backlog direcionado*; só entram em execução após gate da Fase 11 e estabilização da Fase 12.

**Trilha complementar de enforcement/bloqueio:** ver
[`docs/09-blocking/blocking-master-plan.md`](docs/09-blocking/blocking-master-plan.md).

---

## Fase 0. Descoberta e congelamento do escopo

### Objetivo
Definir exatamente:
- o que a V1 faz;
- o que a V1 não faz;
- o que fica para V2.

### Entradas
- objetivo do produto
- limitações técnicas conhecidas
- restrições do pfSense/FreeBSD
- restrição de tudo open source

### Saídas
- escopo V1 fechado
- não objetivos documentados
- critérios de sucesso definidos

### Gate
Não avançar sem:
- [x] escopo aprovado
- [x] categorias iniciais aprovadas
- [x] ações iniciais aprovadas
- [x] modo monitor/enforce definidos

---

## Fase 1. Ambiente de desenvolvimento e laboratório

### Objetivo
Montar a bancada.

### Entregas
- VM/host FreeBSD builder
- pfSense CE de laboratório
- clientes de teste
- gerador de tráfego
- syslog remoto

### Gate
- [x] build host funcional
- [x] snapshot do lab pronto
- [x] acesso SSH confiável
- [x] rede de teste validada

---

## Fase 2. PoC do motor Layer 7

### Objetivo
Provar a classificação antes do pacote.

### Entregas
- daemon mínimo ou binário de PoC
- integração inicial com nDPI
- eventos JSON
- teste com tráfego real

### Gate
- [x] 20 casos de tráfego testados
- [x] baseline de CPU e RAM
- [x] acurácia inicial entendida
- [x] limitações iniciais anotadas

---

## Fase 3. Modelo de dados e política

### Objetivo
Definir como o pacote pensa.

### Entregas
- modelo de config
- modelo de evento
- categorias
- precedência
- defaults
- exceções

### Gate
- [x] policy matrix aprovada
- [x] esquema de evento definido
- [x] ordem de prioridade definida

---

## Fase 4. Esqueleto do pacote pfSense

### Objetivo
Transformar a PoC em pacote instalável.

### Entregas
- diretório do port
- Makefile
- pkg-plist
- XML
- páginas mínimas
- script rc

### Gate
- [x] pacote compila
- [x] instala
- [x] remove
- [x] não deixa lixo operacional grave

---

## Fase 5. Daemon de produção V1

### Objetivo
Ter um `layer7d` real.

### Entregas
- start/stop/reload
- leitura de config
- runtime state
- counters
- tratamento de erro

### Gate
- [x] daemon sobe após reboot
- [x] daemon aceita reload
- [x] daemon registra erro de forma útil

---

## Fase 6. Enforcement V1

### Objetivo
Aplicar decisões na prática.

### Entregas
- integração com aliases/tables
- bloqueio por categoria/app quando possível
- exceções
- allow/monitor/tag/block

### Gate
- [x] caso simples de block validado — 2026-03-22
- [x] whitelist validada — 2026-03-22
- [x] modo monitor não interfere

---

## Fase 7. GUI operacional mínima

### Objetivo
Dar cara de produto.

### Entregas
- Dashboard
- Settings
- Policies
- Exceptions
- Events
- Diagnostics

### Gate
- [x] salvar funciona
- [x] aplicar funciona
- [x] UI não quebra em erro de input

---

## Fase 8. Logging e observabilidade

### Objetivo
Tornar o pacote operável.

### Entregas
- logs locais mínimos
- syslog remoto
- counters
- diagnostics

### Gate
- [x] logs legíveis
- [x] exportação remota validada
- [x] debug temporário disponível

---

## Fase 9. Testes e hardening

### Objetivo
Parar de “achar” e começar a validar.

### Entregas
- matriz de testes
- instalação
- upgrade
- reboot
- rollback
- stress leve

### Gate
- [x] suite de testes executada — 58/58 OK
- [x] bugs críticos corrigidos
- [x] rollback validado — 2026-03-22

---

## Fase 10. Beta controlado

### Objetivo
Rodar em piloto.

### Entregas
- piloto em monitor mode
- tuning
- exceptions finas
- feedback operacional

### Gate
- [x] enforce end-to-end validado — 2026-03-23 (pf_add_ok=7)
- [ ] piloto estável 24h+ *(pendente: teste real)*
- [x] documentação ajustada

---

## Fase 11. Release 0.1.0 / V1

### Objetivo
Fechar primeira versão de verdade.

### Entregas
- pacote versionado
- changelog
- docs
- runbook
- release notes

### Gate
- [x] DoD completo
- [x] release publicada — v0.1.0 (2026-03-23), v0.2.0 (2026-03-18)
- [x] rollback documentado
- [x] roadmap V2 definido *(fases 13-22)*

---

## Fase 12. Pós-release e trilha V2

### Objetivo
Não perder o controle do produto depois da primeira entrega.

### Entregas
- backlog V2 priorizado
- bugs classificados
- ADRs complementares
- aprendizado do piloto

### Gate
- [ ] SSOT atualizado
- [ ] próximos blocos definidos
- [ ] dívida técnica mapeada

---

## Fase 13. Motor nDPI no daemon (produção)

### Objetivo
Fechar o loop **captura → classificação nDPI → policy → enforce** no appliance, com o mesmo binário entregue pelo pacote.

### Entregas
- integração nDPI no `layer7d` (fora do PoC isolado);
- seleção de interface(s) / espelhamento ou hook acordado com limites pfSense;
- eventos alinhados a `docs/core/event-model.md`;
- degradar com segurança se recurso ou PCAP falhar.

### Gate
- [ ] classificação real em lab com políticas aplicadas;
- [ ] impacto CPU/RAM documentado;
- [ ] fallback (monitor-only) definido.

---

## Fase 14. GUI V1 completa e sync config

### Objetivo
Eliminar lacunas entre GUI e ficheiro JSON consumido pelo daemon.

### Entregas
- editor completo de policies, exceptions, events view, diagnostics;
- validação de input e mensagens de erro estáveis;
- apply/reload sem drift GUI↔disco.

### Gate
- [ ] operador cobre fluxos V1 sem shell;
- [ ] testes manuais/regressão GUI documentados.

---

## Fase 15. Política DNS / domínio / FQDN

### Objetivo
Aprofundar **host/domain policy** além de IP/CIDR onde o stack permitir.

### Entregas
- regras por domínio/FQDN (resolver + cache TTL definidos);
- interação com Unbound/dnsmasq ou caminho suportado em CE;
- documentação de limitações (TTL, DoH, etc.).

### Gate
- [ ] casos de teste DNS em lab;
- [ ] sem bloqueio errado de tráfego não alvo (falso positivo aceite mapeado).

### Documento complementar
- [`docs/09-blocking/blocking-master-plan.md`](docs/09-blocking/blocking-master-plan.md)

---

## Fase 16. Observabilidade e operações

### Objetivo
Operação em ambiente real sem depender só de syslog genérico.

### Entregas
- formato de logs estável + rotação;
- syslog remoto configurável (host, facility, severidade);
- counters exportáveis; modo debug temporário com guard-rails.

### Gate
- [ ] exportação validada contra coletor de teste;
- [ ] runbook de troubleshooting atualizado.

---

## Fase 17. Identidade e contexto de utilizador (onde viável)

### Objetivo
Enriquecer decisão com **identidade** quando tecnicamente possível no ecossistema (sem prometer paridade com UTM enterprise).

### Entregas
- mapeamento IP↔utilizador ou grupo (fonte definida: captive portal, LDAP sync leve, etc.);
- políticas condicionais a “grupo” quando suportado;
- ADR por fonte de identidade.

### Gate
- [ ] pelo menos um modo de identidade validado em lab;
- [ ] limitações explícitas na doc de produto.

---

## Fase 18. TLS inspection seletiva (opt-in)

### Objetivo
Piloto de **inspeção TLS não universal** (ex.: categorias/domínios específicos), alinhado a não objetivos da V1.

### Entregas
- desenho de trust chain e opt-in explícito;
- integração mínima com proxy ou mecanismo escolhido;
- auditoria e logging de exceções.

### Gate
- [ ] piloto interno/lab sem default-on;
- [ ] risco legal/operacional documentado.

---

## Fase 19. Correlação com IDS (ex. Suricata)

### Objetivo
**Enriquecer eventos Layer7** com contexto IDS quando disponível (item adiado do CORTEX).

### Entregas
- consumo de eventos ou logs estruturados (formato acordado);
- regras de correlação simples (app + alerta);
- sem obrigatoriedade de Suricata instalado.

### Gate
- [ ] demo de correlação em lab;
- [ ] degradação graciosa sem IDS.

---

## Fase 20. Escala, HA e performance

### Objetivo
Tuning para **múltiplas interfaces**, maior débito e notas de **HA/CARP** (sem console central).

### Entregas
- benchmarks e limites suportados;
- documentação HA (estado, reload, split-brain);
- filas e backpressure no daemon.

### Gate
- [ ] stress leve documentado;
- [ ] sem regressão em instalação single-node.

---

## Fase 21. Ciclo de vida nDPI / assinaturas

### Objetivo
Processo claro de **atualização de biblioteca/assinaturas** nDPI sem infra proprietária obrigatória.

### Entregas
- procedimento de rebuild/repackage documentado;
- versionamento de “rules bundle” se aplicável;
- compatibilidade entre versões pfSense/nDPI.

### Gate
- [ ] upgrade testado em lab;
- [ ] rollback de versão nDPI documentado.

---

## Fase 22. API local / automação / hooks

### Objetivo
Permitir automação **sem console central multi-firewall** (outro item adiado).

### Entregas
- API REST local ou hooks de script (decisão por ADR);
- autenticação mínima (token/rota local);
- exemplos Ansible/curl.

### Gate
- [ ] superfície de ataque revisada;
- [ ] exemplos e runbook.

---

## Cronograma sugerido em blocos

### Bloco 1
Fase 0 + Fase 1

### Bloco 2
Fase 2

### Bloco 3
Fase 3 + Fase 4

### Bloco 4
Fase 5

### Bloco 5
Fase 6

### Bloco 6
Fase 7

### Bloco 7
Fase 8 + Fase 9

### Bloco 8
Fase 10 + Fase 11

### Bloco 9 (pós-V1)
Fase 12

### Bloco 10 (V2 — sequência sugerida)
Fase 13 → 14 → 15; em paralelo documental: 16, 21

### Bloco 11 (V2 — ondas seguintes)
Fases 17–20 conforme prioridade de mercado/lab

### Bloco 12 (V2 — exposição programática)
Fase 22 após endurecimento de 13–16

---

## Política de avanço entre fases

Só avançar quando:
- o gate estiver completo;
- a documentação estiver atualizada;
- existir rollback;
- não houver bug crítico aberto da fase atual.

---

## Regras anti-caos

- não abrir 5 frentes em paralelo;
- não mexer no pacote e na policy engine e na GUI e no build sem fechamento de bloco;
- não trocar dependência central no meio do bloco;
- não aceitar “depois eu documento”.
