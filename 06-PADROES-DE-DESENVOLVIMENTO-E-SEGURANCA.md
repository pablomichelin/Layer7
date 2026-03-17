# Padrões de Desenvolvimento e Segurança

## 1. Objetivo

Definir padrões técnicos e operacionais para evitar um projeto frágil, inseguro ou impossível de manter.

---

## 2. Padrões de desenvolvimento

## 2.1. Regra do bloco pequeno
Nenhum PR deve misturar:
- GUI grande;
- policy engine;
- empacotamento;
- refactor profundo;
- documentação ampla;

em uma única entrega confusa.

Cada bloco deve ter foco.

---

## 2.2. Regra da documentação no mesmo commit
Se uma decisão técnica muda:
- código muda;
- documentação muda;
- `CORTEX.md` muda.

---

## 2.3. Regra do fallback seguro
Se a classificação falhar, a decisão padrão da V1 deve ser conservadora e documentada.

Opções possíveis:
- `monitor`
- `allow + log`
- `uncategorized`

A escolha precisa ser única e registrada.

---

## 2.4. Regra da observabilidade mínima
Todo componente importante precisa ter:
- estado;
- log;
- erro legível.

---

## 2.5. Regra de compatibilidade
Nada deve ser assumido fora do que foi validado em:
- builder;
- lab;
- pfSense CE alvo.

---

## 3. Segurança de software

## 3.1. Entradas não confiáveis
Trate como não confiável:
- input da GUI;
- nomes de host;
- domínios;
- valores de config;
- output de ferramentas externas;
- arquivos de import.

---

## 3.2. Armazenamento de segredos
Se algum dado sensível for necessário no futuro:
- evitar plaintext sem necessidade;
- documentar tratamento;
- restringir exposição em logs e GUI.

---

## 3.3. Logging seguro
Nunca logar por padrão:
- credenciais;
- tokens;
- payloads sensíveis;
- dados pessoais desnecessários;
- conteúdo completo de tráfego.

---

## 3.4. Privilégios mínimos
A GUI do pacote deve expor apenas os privilégios necessários.
Separar:
- view
- edit
- diagnostics
- admin do pacote

---

## 4. Segurança operacional

## 4.1. Antes de instalar em qualquer ambiente
- backup da config;
- snapshot quando possível;
- rollback documentado;
- release notes lidas.

## 4.2. Antes de upgrade
- changelog lido;
- compatibilidade confirmada;
- export de config;
- janela de manutenção definida.

---

## 5. Padrões de código

### Regras gerais
- nomes claros;
- funções pequenas;
- retorno explícito;
- tratamento de erro;
- comentários úteis, não decorativos;
- sem lógica escondida em scripts soltos.

### Regra de módulos
Separar:
- classificação
- política
- runtime
- eventos
- GUI adapters
- integração com pfSense

---

## 6. Padrões de configuração

### Toda configuração deve:
- ter default;
- ser validada;
- ser persistida corretamente;
- ser auditável;
- ser exportável.

### Nunca aceitar:
- config ambígua;
- precedence implícita obscura;
- dependência de ordem visual da GUI.

---

## 7. Padrões de teste

Toda funcionalidade nova precisa de pelo menos um destes:
- teste funcional manual documentado;
- teste automatizado local;
- matriz de validação;
- evidência de lab.

---

## 8. Padrões de release

Nenhuma release sem:
- changelog;
- versão;
- nota de compatibilidade;
- instrução de rollback;
- documentação atualizada.

---

## 9. Padrões de troubleshooting

Sempre que surgir bug relevante:
1. reproduzir;
2. registrar;
3. isolar;
4. corrigir;
5. documentar causa raiz;
6. adicionar teste ou checklist para evitar regressão.

---

## 10. Lista de decisões técnicas que exigem ADR

Sempre criar ADR para:
- troca do engine de classificação;
- mudança no método de enforcement;
- mudança no formato de evento;
- mudança de fallback;
- mudança na distribuição do pacote;
- decisão de usar ou não Suricata;
- qualquer entrada em TLS inspection.

