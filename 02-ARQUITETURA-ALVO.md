# Arquitetura Alvo

## 1. Visão arquitetural

A arquitetura proposta para a V1 é composta por cinco blocos:

1. **Pacote pfSense**
2. **Daemon Layer7**
3. **Engine de classificação**
4. **Motor de políticas**
5. **Camada de enforcement e logging**

---

## 2. Componentes principais

## 2.1. GUI do pacote
Responsável por:
- habilitar/desabilitar serviço;
- salvar configuração;
- exibir políticas;
- exibir eventos resumidos;
- exibir status do daemon;
- mostrar diagnósticos básicos.

Arquivos esperados:
- XML do pacote
- páginas PHP da GUI
- includes de privilégios
- páginas de diagnostics

---

## 2.2. `layer7d`
Daemon principal do projeto.

Responsabilidades:
- iniciar e parar corretamente;
- ler a configuração persistida;
- classificar fluxos;
- consultar policy engine;
- emitir ações;
- registrar eventos;
- exportar logs;
- sobreviver a reboot e reload.

---

## 2.3. Engine de classificação
Motor que consome metadados/fluxos e tenta classificar:
- aplicação;
- protocolo;
- categoria;
- hostname/SNI quando disponível;
- confiança;
- indicadores auxiliares.

A recomendação é usar **nDPI** como núcleo principal.

---

## 2.4. Policy engine
Transforma uma classificação em decisão.

Ações da V1:
- `allow`
- `block`
- `monitor`
- `tag`

Entradas:
- interface;
- origem;
- destino;
- categoria;
- aplicação;
- exceção;
- modo operacional.

---

## 2.5. Enforcement
Conjunto de mecanismos usados para aplicar a decisão.

Na V1:
- PF tables / aliases
- regras correlatas
- integração com políticas por host/domínio quando aplicável

---

## 2.6. Logging / eventos
Dois níveis:

### eventos operacionais
- start/stop/reload
- erro
- policy match
- bloqueio

### eventos diagnósticos
- classificador inconclusivo
- conflito de política
- timeout
- fallback

---

## 3. Fluxo de dados de alto nível

```text
Tráfego observado
    ↓
Coleta / normalização
    ↓
Classificação Layer7
    ↓
Policy engine
    ↓
Ação
    ├─ monitor
    ├─ tag
    ├─ allow
    └─ block
    ↓
Evento / log / exportação
```

---

## 4. Estrutura lógica da V1

## 4.1. Dentro do pfSense
- GUI do pacote
- arquivos de configuração
- daemon
- integração com aliases/tables
- logs locais mínimos
- exportação syslog

## 4.2. Fora do pfSense
- builder FreeBSD
- repositório GitHub
- servidor syslog
- ambiente de lab
- eventualmente CI de build

---

## 5. Camadas da solução

### Camada 1. Administração
GUI, config, privilégios, páginas de status.

### Camada 2. Core de serviço
`layer7d`, estado, reload, watchdog.

### Camada 3. DPI/classificação
nDPI e wrappers internos.

### Camada 4. Política
matriz de decisão.

### Camada 5. Enforcement
aliases/tables/DNS-related actions.

### Camada 6. Observabilidade
logs, counters, diagnostics, exportação.

---

## 6. Estado interno recomendado

Criar uma separação nítida entre:

- **configuração persistida**
- **estado runtime**
- **eventos**
- **counters**

### Configuração persistida
Guardada em `config.xml` via integração padrão do pacote.

### Estado runtime
Exemplos:
- status do daemon;
- interfaces monitoradas;
- timestamp do último reload;
- contadores recentes.

### Eventos
Formato normalizado para log e UI.

### Counters
- fluxos classificados;
- fluxos bloqueados;
- fluxos monitorados;
- erros;
- inconclusivos.

---

## 7. Modelo de decisão de política

A policy engine da V1 deve ser simples e explícita.

Ordem sugerida:
1. se pacote desabilitado → no-op
2. se interface fora do escopo → ignore
3. se exceção explícita → ação de exceção
4. se política específica por app → aplicar
5. se política por categoria → aplicar
6. se regra default → aplicar
7. se nada casar → monitor

---

## 8. Categorias mínimas sugeridas para V1

- business
- productivity
- social
- streaming
- gaming
- vpn_proxy
- remote_access
- ai_tools
- file_transfer
- adult
- malware_suspect
- uncategorized

---

## 9. Estrutura de evento recomendada

```json
{
  "ts": "2026-03-17T12:00:00Z",
  "iface": "lan",
  "src_ip": "192.168.10.50",
  "dst_ip": "142.250.0.10",
  "dst_host": "youtube.com",
  "proto_l4": "tcp",
  "proto_l7": "youtube",
  "category": "streaming",
  "confidence": 86,
  "decision": "block",
  "reason": "policy_category_streaming",
  "mode": "enforce"
}
```

---

## 10. Segurança da arquitetura

A arquitetura precisa assumir que:

- o daemon pode falhar;
- a classificação pode falhar;
- a GUI pode salvar config inválida;
- upgrades do pfSense podem exigir ajuste;
- tráfego moderno pode não ser totalmente visível.

Logo, a V1 deve ter:
- defaults conservadores;
- validação de config;
- modo monitor;
- fallback seguro;
- rollback fácil.

---

## 11. Decisão importante sobre distribuição

O GitHub deve armazenar:
- código-fonte;
- documentação;
- scripts;
- pipeline;
- releases;
- changelog;

Mas o **artefato de instalação no pfSense** deve ser tratado separadamente:
- build do pacote;
- release do `.txz`;
- instalação controlada no firewall.

---

## 12. Meta arquitetural da V1

Entregar algo com esta personalidade técnica:

- simples de entender;
- honesto nas limitações;
- estável;
- fácil de operar;
- fácil de remover se necessário;
- preparado para crescer.

