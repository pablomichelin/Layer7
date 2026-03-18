# Plano de Implementação Passo a Passo

## Regra deste documento

A execução deve seguir a ordem abaixo.  
Não pular blocos.

**Regra de evidência:** tarefas que exigem pfSense/FreeBSD só podem ser marcadas `[x]` após execução no ambiente correto e registo em [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md) (ou anexo).

---

## Bloco 1. Preparação do projeto

### Objetivo
Criar a base documental e de repositório.

### Tarefas
- [x] criar repositório
- [x] subir estrutura inicial
- [x] adicionar `AGENTS.md`
- [x] adicionar `CORTEX.md`
- [x] adicionar docs-base (`docs/`, ADRs, changelog)
- [x] definir license (BSD-2-Clause)
- [x] definir convenção de versionamento (SemVer `0.x` até V1 — ver `README.md` / `docs/06-releases/`)

### Saída
Repositório limpo e organizado.

---

## Bloco 2. Laboratório e builder

### Objetivo
Montar ambiente reproduzível.

### Tarefas
- [x] documentar builder FreeBSD (`docs/08-lab/builder-freebsd.md`, `scripts/build/BUILDER.md`)
- [x] documentar lab pfSense + rede + cliente (`docs/08-lab/lab-topology.md`, `scripts/lab/LAB-SETUP.md`)
- [x] definir rede de teste (topologia e endereçamento template)
- [x] especificar host cliente (na topologia de lab)
- [x] documentar syslog remoto (`docs/08-lab/syslog-remote.md`)
- [x] documentar snapshots + gate Fase 1 (`docs/08-lab/snapshots-e-gate.md`)
- [ ] **Execução (operador):** VM builder ativa com `git`/`pkg`
- [ ] **Execução (operador):** pfSense lab + cliente + syslog validados + snapshot base

### Saída
- **Documental:** bancada especificada em `docs/08-lab/`.
- **Física:** checklist em `snapshots-e-gate.md` até todos os itens marcados.

---

## Bloco 3. PoC do engine

### Objetivo
Provar a classificação Layer 7.

### Tarefas
- [x] integrar nDPI em PoC simples (`src/poc_ndpi/layer7_ndpi_poc.c` + `scripts/build/build-poc-freebsd.sh`)
- [x] gerar eventos normalizados (JSONL v1 em stdout)
- [x] registrar confiança (campo `confidence`, valor `detected` no PoC)
- [ ] testar tráfego real (operador: PCAP no builder + preencher `docs/poc/resultados-poc.template.md`)
- [x] medir performance (resumo em stderr: pkts/s, tempo)

### Saída
PoC compilável no FreeBSD; documentação em `src/poc_ndpi/README.md` e `docs/poc/`.

### Critério de aceite
Existe lista clara de:
- apps detectados;
- apps mal detectados;
- limites conhecidos.

*(Preenchimento da lista: após testes reais no lab.)*

---

## Bloco 4. Modelagem do core

### Objetivo
Definir internamente como o pacote pensa.

### Tarefas
- [x] definir modelo de config (`docs/core/config-model.md`, `samples/config/layer7-minimal.json`)
- [x] definir modelo de evento (`docs/core/event-model.md`)
- [x] definir state runtime (`docs/core/runtime-state.md`)
- [x] definir policy matrix (`docs/core/policy-matrix.md`)
- [x] definir categories (`docs/core/categories.md`)
- [x] definir precedence (`docs/core/precedence.md`)
- [x] tipos C compartilhados (`src/common/layer7_types.h`)

### Saída
Core modelado em `docs/core/` + amostra JSON + `layer7_types.h`.

---

## Bloco 5. Package skeleton

### Objetivo
Criar esqueleto do pacote pfSense **no repositório**.

### Tarefas (código / artefactos — existem no Git)
- [x] diretório do port, Makefile, pkg-descr, pkg-plist
- [x] XML (`info.xml`, `layer7.xml`), `layer7.inc`, priv stub
- [x] página PHP informativa (`layer7_status.php` — sem persistência)
- [x] `rc.d/layer7d`, hooks `pkg-install` / `pkg-deinstall`, `layer7.json.sample`

### Tarefas (lab — **não concluídas até haver evidência**)
- [ ] `make package` OK no builder
- [ ] `pkg add` + ficheiros instalados conforme `pkg-plist`
- [ ] serviço `layer7d` onestart/status/logs no pfSense
- [ ] URL/menu GUI verificados (registar OK/NOK)
- [ ] remove do pacote validado

### Saída
Artefactos em `package/pfSense-pkg-layer7/`. Validação: [`docs/04-package/validacao-lab.md`](docs/04-package/validacao-lab.md).

---

## Bloco 6. Daemon V1

### Objetivo
Substituir PoC solta por serviço real.

### Tarefas (repositório)
- [x] fonte C `src/layer7d/main.c` (mínimo: syslog, stat em path de config, loop, SIGHUP placeholder)
- [x] Makefile do port compila para `sbin/layer7d` *(lógica no repo; build não validado em lab)*

### Tarefas (lab + produto)
- [ ] binário instalado e executável no pfSense após `pkg add`
- [ ] start/stop/status via `service layer7d` **comprovado** no appliance
- [ ] parser JSON + reload SIGHUP real
- [ ] manter counters
- [ ] logar estado
- [ ] tratar falha de inicialização

### Saída
Daemon gerenciável **com evidência no lab**.

---

## Bloco 7. Policy engine

### Objetivo
Transformar classificação em decisão.

### Tarefas
- [ ] implementar matcher de exceções
- [ ] implementar regra por app
- [ ] implementar regra por categoria
- [ ] implementar default action
- [ ] adicionar reason code
- [ ] gerar decisão final

### Saída
Policy engine previsível.

---

## Bloco 8. Enforcement mínimo

### Objetivo
Aplicar block/allow/monitor/tag.

### Tarefas
- [ ] integrar aliases/tables
- [ ] implementar block básico
- [ ] implementar whitelist
- [ ] implementar monitor
- [ ] registrar ação aplicada
- [ ] validar sem quebrar tráfego geral

### Saída
Primeira ação real em campo.

---

## Bloco 9. GUI operacional

### Objetivo
Permitir operação pela GUI.

### Tarefas
- [ ] criar Settings
- [ ] criar Policies
- [ ] criar Exceptions
- [ ] criar Events
- [ ] criar Diagnostics
- [ ] validação de input

### Saída
Pacote operável sem shell na maioria dos casos.

---

## Bloco 10. Logging e exportação

### Objetivo
Consolidar observabilidade.

### Tarefas
- [ ] definir formato de logs
- [ ] implementar syslog remoto
- [ ] adicionar diagnostics
- [ ] counters básicos
- [ ] modo debug temporário

### Saída
Operação e troubleshooting possíveis.

---

## Bloco 11. Testes e hardening

### Objetivo
Tirar a V1 do terreno da sorte.

### Tarefas
- [ ] testar instalação
- [ ] testar upgrade
- [ ] testar reboot
- [ ] testar persistência
- [ ] testar block
- [ ] testar whitelist
- [ ] testar modo monitor
- [ ] testar falha do daemon
- [ ] testar rollback

### Saída
Evidência de estabilidade mínima.

---

## Bloco 12. Release

### Objetivo
Publicar de forma profissional.

### Tarefas
- [ ] testes de regressão mínimos
- [ ] documentação de operação + runbook rollback
- [ ] version bump
- [ ] changelog
- [ ] release notes
- [ ] build `.txz`
- [ ] checksum
- [ ] documentação de instalação
- [ ] notas de compatibilidade pfSense CE

### Saída
Release 0.1.0 utilizável.

---

## Ordem proibida de trabalho

Não fazer:
- GUI bonita antes do daemon;
- marketing antes de teste;
- TLS inspection antes do MVP básico;
- distribuição “elegante” antes de build confiável;
- V2 antes do fechamento da V1.

---

## Critério para encerrar cada bloco

Encerrar apenas quando existir:
- evidência de teste **quando o bloco envolver appliance ou build**;
- documentação atualizada;
- commit limpo;
- próximos passos claros em `CORTEX.md`.
