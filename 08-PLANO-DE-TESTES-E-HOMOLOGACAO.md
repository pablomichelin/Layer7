# Plano de Testes e Homologação

## 1. Objetivo

Garantir que o pacote:
- instala;
- opera;
- persiste;
- bloqueia quando deve;
- não quebra o firewall quando não deve.

---

## 2. Ambientes

## 2.1. Lab obrigatório
- 1 pfSense CE limpo
- 1 builder
- 1 cliente LAN
- 1 gerador de tráfego
- 1 syslog server

## 2.2. Piloto
- ambiente controlado
- rollback disponível
- operador presente

---

## 3. Tipos de teste

### A. Teste de pacote
- install
- remove
- reinstall
- upgrade
- downgrade controlado

### B. Teste de daemon
- start
- stop
- restart
- reboot persistence
- config reload

### C. Teste funcional
- monitor
- block
- allow
- tag
- whitelist
- exceptions

### D. Teste de tráfego
- HTTP
- HTTPS
- QUIC
- streaming
- social
- VPN/proxy
- file transfer
- unknown traffic

### E. Teste operacional
- GUI save/apply
- diagnostics
- syslog remoto
- counters

### F. Teste de falha
- daemon crash
- config inválida
- target de syslog indisponível
- classificação inconclusiva
- remoção do pacote

---

## 4. Matriz de testes mínimos

## 4.1. Instalação
- [ ] pacote instala sem erro
- [ ] menu aparece
- [ ] serviço inicia
- [ ] config default válida

## 4.2. Reboot
- [ ] reboot preserva config
- [ ] serviço volta
- [ ] estado é consistente

## 4.3. Monitor mode
- [ ] classifica
- [ ] não bloqueia
- [ ] registra eventos

## 4.4. Enforce mode
- [ ] bloqueia caso esperado
- [ ] não bloqueia exceção
- [ ] mantém allow funcional

## 4.5. Whitelist
- [ ] exceção por IP
- [ ] exceção por host
- [ ] exceção por domínio quando aplicável

## 4.6. Diagnostics
- [ ] últimos eventos visíveis
- [ ] erro de daemon visível
- [ ] counters atualizam

## 4.7. Logging
- [ ] evento local visível
- [ ] syslog remoto funcionando
- [ ] debug não fica ligado por padrão

---

## 5. Tráfego de teste sugerido

### Categoria business/productivity
- Microsoft 365 web
- Google Workspace
- Slack/Teams

### Categoria streaming
- YouTube
- Spotify web

### Categoria social
- Facebook/Instagram web
- X/Twitter web

### Categoria remote_access
- AnyDesk
- TeamViewer
- RDP-related flows quando aplicável

### Categoria vpn_proxy
- OpenVPN
- WireGuard
- proxies web conhecidos
- Tor quando possível em lab

### Categoria ai_tools
- interfaces web de IA
- APIs quando disponível em ambiente controlado

### Categoria uncategorized
- tráfego pouco comum
- apps experimentais
- tráfego não mapeado

---

## 6. Registro de evidência

Cada teste deve registrar:
- data
- build/version
- ambiente
- passos
- resultado esperado
- resultado obtido
- evidência
- rollback necessário? sim/não

---

## 7. Formato sugerido para caso de teste

```md
## CT-001 - Install limpo

Ambiente:
Versão:
Pré-condição:
Passos:
Resultado esperado:
Resultado obtido:
Evidências:
Status:
Observações:
```

---

## 8. Critério de homologação da V1

A V1 só vai para release quando:
- [ ] todos os testes críticos passarem
- [ ] bugs críticos forem zerados
- [ ] bugs altos tiverem workaround documentado
- [ ] rollback funcionar
- [ ] docs estiverem atualizadas

---

## 9. Critérios de severidade de bug

### Crítico
- quebra navegação geral
- impede boot/serviço
- corrompe config
- impede rollback
- crash recorrente grave

### Alto
- bloqueio incorreto relevante
- GUI salva config errada
- daemon não reinicia em condições comuns

### Médio
- logs confusos
- counters incorretos
- UX ruim sem impacto direto

### Baixo
- texto
- layout
- clareza visual
- melhoria de conforto

---

## 10. Teste de release final

Antes da release:
- [ ] instalar do zero
- [ ] operar em monitor
- [ ] operar em block
- [ ] validar exceptions
- [ ] exportar log
- [ ] reboot
- [ ] rollback

