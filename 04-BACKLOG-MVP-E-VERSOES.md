# Backlog MVP e Versões

## Estratégia de backlog

Separar backlog por:
- épicos;
- histórias;
- tarefas técnicas;
- dívidas técnicas;
- itens adiados para V2.

---

## V0 - Descoberta técnica

### Épico V0.1 - Prova técnica do engine
- [ ] escolher wrapper/forma de integração com nDPI
- [ ] capturar fluxos em lab
- [ ] classificar tráfego real
- [ ] medir custo
- [ ] documentar limitações

### Critério de aceite
Existe prova de que o motor escolhido:
- vê algo útil;
- é reproduzível;
- é empacotável.

---

## V0.1 - Esqueleto do pacote

### Épico V0.1.1 - Package skeleton
- [ ] criar diretório do port
- [ ] criar Makefile
- [ ] criar XML básico
- [ ] criar menu na GUI
- [ ] criar script rc
- [ ] criar install/remove testável

### Critério de aceite
Pacote instala e aparece na GUI.

---

## V0.2 - Daemon mínimo

### Épico V0.2.1 - Runtime básico
- [ ] start
- [ ] stop
- [ ] status
- [ ] pidfile
- [ ] log inicial
- [ ] leitura de config

### Critério de aceite
Daemon sobe, para e registra estado corretamente.

---

## V0.3 - Policy engine mínimo

### Épico V0.3.1 - Policy matrix
- [ ] definir objeto policy
- [ ] definir order of precedence
- [ ] definir exceções
- [ ] implementar decisão

### Critério de aceite
Dado um evento sintético, a decisão é previsível.

---

## V0.4 - Enforcement mínimo

### Épico V0.4.1 - Block e monitor
- [ ] integrar com alias/table
- [ ] aplicar block
- [ ] aplicar monitor
- [ ] manter allow no-op
- [ ] implementar whitelist

### Critério de aceite
Um caso simples de bloqueio funciona sem quebrar o restante.

---

## V0.5 - GUI operacional

### Épico V0.5.1 - Settings
- [ ] enable/disable
- [ ] interfaces
- [ ] mode
- [ ] log level
- [ ] syslog target

### Épico V0.5.2 - Policies
- [ ] lista de categorias
- [ ] ações por categoria
- [ ] ordem visual clara

### Épico V0.5.3 - Exceptions
- [ ] host
- [ ] domínio
- [ ] IP/rede

### Épico V0.5.4 - Events
- [ ] últimos eventos
- [ ] filtro básico
- [ ] counters

### Critério de aceite
Operador consegue habilitar, configurar e verificar comportamento sem shell.

---

## V0.6 - Logging e diagnostics

### Épico V0.6.1 - Logging
- [ ] eventos operacionais
- [ ] exportação syslog
- [ ] modo debug temporário
- [ ] pagina diagnostics

### Critério de aceite
Problemas comuns podem ser diagnosticados sem adivinhação.

---

## V0.7 - Testes

### Épico V0.7.1 - Teste funcional
- [ ] install
- [ ] uninstall
- [ ] reboot
- [ ] config persist
- [ ] policy match
- [ ] whitelist
- [ ] fallback

### Épico V0.7.2 - Teste de tráfego
- [ ] web comum
- [ ] streaming
- [ ] social
- [ ] VPN/proxy
- [ ] QUIC
- [ ] tráfego inconclusivo

### Critério de aceite
Suite mínima executada e registrada.

---

## V1.0.0 - Primeiro release real

### Entrega esperada
- pacote instalável;
- daemon estável;
- GUI básica;
- enforcement básico;
- docs completas;
- rollback.

---

## Backlog V1.1

### Melhorias pós-release
- [ ] filtros melhores na tela de eventos
- [ ] mais counters
- [ ] tuning de performance
- [ ] categorias refinadas
- [ ] documentação de troubleshooting ampliada

---

## Backlog V1.5

### Evolução controlada
- [ ] associação IP ↔ usuário
- [ ] integração mais forte com LDAP/RADIUS
- [ ] tagging por dispositivo
- [ ] dashboards melhores

---

## Backlog V2

### Fora do escopo inicial
- [ ] inspeção TLS seletiva
- [ ] fingerprint mais avançado
- [ ] múltiplos perfis por cliente/site
- [ ] console central
- [ ] feed próprio de categorias
- [ ] atualização inteligente de signatures
- [ ] correlação de eventos
- [ ] integração mais profunda com Suricata

---

## Dívida técnica esperada

Já aceite desde o início que existirão dívidas técnicas em:
- cobertura de apps;
- performance em hardware fraco;
- limites de tráfego cifrado;
- heurísticas de classificação;
- ergonomia da GUI.

A meta não é zerar dívida técnica.  
A meta é **não esconder** a dívida técnica.

