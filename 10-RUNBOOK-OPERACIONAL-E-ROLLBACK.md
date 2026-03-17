# Runbook Operacional e Rollback

## 1. Objetivo

Ter um guia de operação realista para instalação, upgrade, rollback e troubleshooting básico.

---

## 2. Antes de instalar

Checklist:
- [ ] backup da config do pfSense
- [ ] snapshot da VM/appliance quando possível
- [ ] janela de teste definida
- [ ] release notes lidas
- [ ] versão alvo confirmada
- [ ] rollback preparado

---

## 3. Instalação inicial

### Fluxo
1. conferir compatibilidade;
2. copiar artefato;
3. instalar pacote;
4. habilitar em modo monitor;
5. validar daemon;
6. validar GUI;
7. validar logs;
8. só depois testar enforcement.

### Pós-instalação imediata
- [ ] pacote aparece na GUI
- [ ] daemon sobe
- [ ] config default válida
- [ ] diagnostics respondem
- [ ] logs mínimos existem

---

## 4. Primeira ativação

### Ordem recomendada
1. disabled
2. monitor
3. enforce parcial
4. enforce mais amplo

### Nunca fazer
Instalar e sair bloqueando categorias amplas sem baseline.

---

## 5. Operação diária

### O operador deve verificar
- status do serviço
- counters
- últimos eventos
- erros recentes
- syslog remoto

### O operador não deve fazer
- alterar 10 políticas de uma vez
- habilitar debug permanente
- misturar tuning com troubleshooting sem registrar

---

## 6. Upgrade

### Antes do upgrade
- [ ] ler changelog
- [ ] exportar config
- [ ] salvar artefato da versão anterior
- [ ] validar compatibilidade
- [ ] registrar janela

### Depois do upgrade
- [ ] verificar daemon
- [ ] verificar GUI
- [ ] verificar persistência
- [ ] verificar policy match
- [ ] verificar whitelist
- [ ] verificar logs

---

## 7. Rollback

### Disparadores de rollback
- perda de navegação geral
- bloqueio incorreto sem workaround rápido
- daemon instável
- config corrompida
- impacto operacional relevante

### Ordem sugerida
1. desabilitar enforcement
2. parar serviço se necessário
3. remover ou reverter pacote
4. restaurar config se aplicável
5. validar rede
6. documentar causa

---

## 8. Troubleshooting rápido

## 8.1. GUI não mostra pacote
- conferir instalação
- conferir XML e caminhos
- conferir permissões

## 8.2. Daemon não sobe
- conferir log
- conferir config
- conferir dependências
- testar start manual controlado

## 8.3. Não há eventos
- conferir modo
- conferir interfaces
- conferir pipeline de classificação
- conferir log level

## 8.4. Está bloqueando demais
- conferir default action
- conferir precedence
- conferir exceptions
- conferir categoria/app map

## 8.5. Não está bloqueando
- conferir enforcement ativo
- conferir alias/table
- conferir caminho da decisão
- conferir se o tráfego realmente foi classificado

---

## 9. Modo debug

### Regra
Debug deve ser:
- temporário;
- consciente;
- desabilitado depois.

### Debug não pode virar padrão
Logs massivos em firewall tendem a virar areia no motor.

---

## 10. Procedimento de incidente

Quando ocorrer incidente:
1. congelar mudanças;
2. coletar evidência;
3. identificar versão;
4. reproduzir;
5. aplicar workaround;
6. decidir rollback ou correção;
7. documentar pós-morte.

---

## 11. Pós-morte mínimo

Todo incidente relevante deve registrar:
- causa provável
- impacto
- gatilho
- mitigação
- correção definitiva
- como evitar repetição

