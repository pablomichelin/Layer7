# Roadmap e Fases

## Visão geral

O projeto deve ser executado em **12 fases**, cada uma com entrada, saída e gate claro.

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
- [ ] escopo aprovado
- [ ] categorias iniciais aprovadas
- [ ] ações iniciais aprovadas
- [ ] modo monitor/enforce definidos

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
- [ ] build host funcional
- [ ] snapshot do lab pronto
- [ ] acesso SSH confiável
- [ ] rede de teste validada

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
- [ ] 20 casos de tráfego testados
- [ ] baseline de CPU e RAM
- [ ] acurácia inicial entendida
- [ ] limitações iniciais anotadas

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
- [ ] policy matrix aprovada
- [ ] esquema de evento definido
- [ ] ordem de prioridade definida

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
- [ ] pacote compila
- [ ] instala
- [ ] remove
- [ ] não deixa lixo operacional grave

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
- [ ] daemon sobe após reboot
- [ ] daemon aceita reload
- [ ] daemon registra erro de forma útil

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
- [ ] caso simples de block validado
- [ ] whitelist validada
- [ ] modo monitor não interfere

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
- [ ] salvar funciona
- [ ] aplicar funciona
- [ ] UI não quebra em erro de input

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
- [ ] logs legíveis
- [ ] exportação remota validada
- [ ] debug temporário disponível

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
- [ ] suite de testes executada
- [ ] bugs críticos corrigidos
- [ ] rollback validado

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
- [ ] piloto estável
- [ ] sem incidentes graves
- [ ] documentação ajustada

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
- [ ] DoD completo
- [ ] release publicada
- [ ] rollback documentado
- [ ] roadmap V2 definido

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

