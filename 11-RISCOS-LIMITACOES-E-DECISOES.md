# Riscos, Limitações e Decisões

## 1. Objetivo

Registrar de forma explícita o que pode dar errado, o que é limitação natural do problema e quais decisões arquiteturais foram tomadas desde o início.

---

## 2. Limitações técnicas naturais

## 2.1. Tráfego cifrado moderno
Parte da visibilidade de Layer 7 é naturalmente reduzida por:
- TLS moderno;
- QUIC;
- ECH;
- apps evasivos;
- multiplexação e encapsulamento.

### Implicação
A V1 não deve prometer visibilidade total.

---

## 2.2. Performance
DPI custa CPU e RAM.  
Hardware fraco pode exigir:
- menos interfaces;
- menos logging;
- menos agressividade;
- escopo reduzido.

---

## 2.3. Acurácia de classificação
Toda classificação terá:
- falso positivo;
- falso negativo;
- inconclusivo.

A V1 precisa conviver com isso e registrar bem.

---

## 2.4. Ecossistema pfSense
Upgrade de versão pode exigir ajuste no pacote.  
Nada deve ser tratado como imutável.

---

## 3. Riscos do projeto

### R1 - Escopo inflado
Querer reproduzir um NGFW enterprise inteiro.

### Mitigação
Congelar V1 e empurrar extras para backlog.

---

### R2 - Empacotamento frágil
Pular build organizado e improvisar instalação.

### Mitigação
Adotar `.txz` e processo controlado.

---

### R3 - GUI antes do core
Criar interface bonita antes do motor.

### Mitigação
Seguir faseamento.

---

### R4 - Falta de observabilidade
Não saber por que bloqueou ou não bloqueou.

### Mitigação
Eventos normalizados e diagnostics mínimos desde cedo.

---

### R5 - Falta de rollback
Release virar armadilha.

### Mitigação
Todo bloco relevante com plano de retorno.

---

### R6 - Dependência excessiva de um único maintainer
Conhecimento ficar preso na cabeça.

### Mitigação
CORTEX, AGENTS, ADRs, runbooks, changelog.

---

## 4. Decisões iniciais

### D1
A V1 será open source end-to-end no núcleo.

### D2
O pacote será focado em pfSense CE.

### D3
O motor principal recomendado será nDPI.

### D4
Suricata é opcional na V1.

### D5
A distribuição inicial será por artefato de pacote, não repositório alternativo.

### D6
A V1 prioriza monitoramento + enforcement simples, não TLS MITM.

### D7
Logs locais serão mínimos; retenção longa fora do firewall.

---

## 5. Decisões que ainda precisam ser fechadas no início do projeto

- linguagem/implementação principal do daemon;
- método exato de captura/integração;
- fallback default de classificação inconclusiva;
- lista final de categorias da V1;
- comportamento exato de block por app versus block por domínio;
- formato final do evento;
- política de whitelist precedence.

---

## 6. Itens proibidos na V1

- console central multi-firewall;
- analytics pesado embarcado;
- feed pago;
- marketing enganoso de “equivalência com Palo Alto”;
- TLS inspection universal;
- repo alternativo sem maturidade operacional.

---

## 7. Riscos de comunicação

O maior risco comercial do projeto é vender uma expectativa maior que a capacidade real da V1.

Mensagem correta:
- produto Layer 7 open source para pfSense CE;
- classificação e enforcement úteis;
- evolutivo;
- honesto quanto a tráfego moderno cifrado.

