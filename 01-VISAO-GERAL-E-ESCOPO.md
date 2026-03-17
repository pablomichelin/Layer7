# Visão Geral e Escopo

## 1. Nome de trabalho do projeto

Sugestão de nome técnico do pacote:

- `pfSense-pkg-layer7`
- daemon: `layer7d`
- módulo de políticas: `layer7-policy`
- módulo de eventos: `layer7-events`

Sugestão de nome comercial interno:

- **SystemUp Layer7 for pfSense CE**
- ou simplesmente **Layer7 CE Package**

---

## 2. Problema que o produto resolve

O pfSense CE é uma plataforma forte de firewall, roteamento, VPN e serviços de rede, mas não nasce com uma camada nativa de **classificação Layer 7 orientada a produto**, com políticas amigáveis por aplicação/categoria e visão operacional própria.

O objetivo deste projeto é preencher esse espaço com um pacote open source que entregue:

- identificação de tráfego por aplicação/protocolo;
- políticas operacionais simples;
- experiência consistente na GUI;
- logs úteis;
- base de evolução para recursos mais avançados.

---

## 3. Objetivo principal da V1

Entregar um **pacote instalável** no pfSense CE com:

- engine de detecção/classificação Layer 7;
- GUI para configuração;
- ações por política:
  - `monitor`
  - `tag`
  - `allow`
  - `block`
- enforcement por:
  - aliases/tables do PF;
  - domínio/host quando aplicável;
- observabilidade básica;
- instalação, upgrade e rollback previsíveis.

---

## 4. Objetivos secundários

- criar base técnica limpa para V2;
- documentar tudo desde o início;
- evitar dependência de software pago;
- construir pipeline de build reproduzível;
- permitir publicação de código no GitHub com organização profissional.

---

## 5. Não objetivos da V1

A V1 **não deve** tentar resolver:

- inspeção TLS universal;
- MITM em massa;
- console central multi-tenant multi-firewall;
- billing/licenciamento;
- feeds pagos;
- analytics pesado embarcado;
- ML autoral;
- UI “enterprise” super polida antes do motor funcionar;
- cobertura total de QUIC/ECH/TLS moderno;
- detecção perfeita de apps evasivos.

---

## 6. Princípios do projeto

### 6.1. Princípio da verdade técnica
Nunca vender internamente a V1 como algo “equivalente a Palo Alto”.  
A V1 deve ser tratada como:

- produto open source;
- forte para monitoramento e enforcement seletivo;
- limitado por tráfego moderno cifrado;
- evolutivo.

### 6.2. Princípio da previsibilidade
Toda etapa precisa ter:
- entrada;
- saída;
- critérios de aceite;
- rollback.

### 6.3. Princípio da documentação viva
Se uma decisão mudou, a doc muda no mesmo commit.

### 6.4. Princípio do escopo congelado
Durante a V1, toda ideia nova entra em backlog.  
Não entra direto no desenvolvimento.

---

## 7. Personas do produto

### 7.1. Operador técnico
Quer instalar, habilitar e aplicar políticas sem virar desenvolvedor do pacote.

### 7.2. Administrador de rede
Quer saber:
- o que o pacote vê;
- o que bloqueia;
- o que fica cego;
- qual impacto de performance.

### 7.3. Desenvolvedor/maintainer
Quer:
- estrutura limpa;
- build confiável;
- docs consistentes;
- troubleshooting previsível.

---

## 8. Escopo funcional da V1

### 8.1. Entradas
- fluxos de rede observados;
- configuração do pacote;
- exceções por IP/host/domínio;
- categorias ativas;
- interfaces monitoradas.

### 8.2. Processamento
- classificação Layer 7;
- normalização do evento;
- decisão de política;
- emissão de ação e log.

### 8.3. Saídas
- evento local;
- log operacional;
- atualização de alias/table quando aplicável;
- bloqueio/allow/tag/monitor;
- exportação remota opcional.

---

## 9. Modo de operação da V1

A V1 deve suportar três modos:

### 9.1. Disabled
Pacote instalado, mas sem interferência.

### 9.2. Monitor only
Classifica e registra sem bloquear.

### 9.3. Enforce
Aplica políticas de bloqueio/allow/tag.

---

## 10. Dependências open source recomendadas

### Obrigatórias
- pfSense CE
- nDPI
- PF / aliases / tables
- Unbound / DNS policy quando aplicável
- syslog remoto opcional

### Opcionais
- Suricata
- ferramentas de geração de tráfego
- servidor de coleta de logs

---

## 11. Métricas de sucesso da V1

### Funcionais
- instala sem erro;
- sobe automaticamente;
- salva/restaura configuração;
- classifica tráfego útil;
- bloqueia casos previstos;
- rollback funciona.

### Operacionais
- operador entende a política;
- logs são legíveis;
- erros são rastreáveis;
- documentação permite reinstalar do zero.

### Técnicas
- CPU/RAM dentro do esperado para o hardware-alvo de teste;
- daemon não degrada a navegação quando desabilitado;
- reboot não corrompe estado.

---

## 12. Definição de pronto da V1

A V1 estará pronta quando todos estes pontos forem verdadeiros:

- [ ] pacote instalável em pfSense CE limpo;
- [ ] daemon funcional;
- [ ] GUI básica pronta;
- [ ] classificação útil de tráfego real;
- [ ] enforcement básico funcionando;
- [ ] exceções funcionando;
- [ ] logs locais e remotos funcionando;
- [ ] documentação operacional completa;
- [ ] testes mínimos repetíveis;
- [ ] rollback documentado e validado.

