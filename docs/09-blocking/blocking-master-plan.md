# Plano Mestre — Bloqueio Total Layer7

## Objetivo

Definir a trilha de implementação para o Layer7 evoluir de:

- **classificação + decisão + enforcement parcial**

para:

- **bloqueio operacional real** de aplicações, sites, serviços e funções no
  pfSense CE, com GUI, rollback e observabilidade adequados.

Este plano serve como guia para os próximos blocos de execução.

---

## 1. Estado atual honesto

Hoje o produto já consegue:

- capturar tráfego em interfaces selecionadas;
- classificar fluxos com nDPI;
- aplicar políticas por interface, IP/CIDR, app e categoria;
- enriquecer eventos com `dst=` e `host=` quando houver correlação DNS;
- gravar logs locais e mostrar eventos em tempo real;
- adicionar o **IP de origem** a PF tables quando a decisão é `block` ou `tag`.

Hoje o produto **ainda não entrega**, de forma fechada e transparente, todos os
comportamentos esperados de um bloqueador Layer7 completo:

- bloqueio automático “instalou e bloqueou” sem depender de regra PF externa;
- bloqueio consistente por **domínio/site**;
- bloqueio por **função** de produto (ex.: GitHub completo, WhatsApp, YouTube);
- gestão explícita de limitações com QUIC, DoH, CDN e endpoints partilhados.

---

## 2. Princípios de implementação

### 2.1 Sem prometer magia

Bloqueio Layer7 em pfSense CE, sem MITM universal, tem limites reais:

- tráfego cifrado reduz visibilidade;
- domínio nem sempre corresponde a um IP exclusivo;
- CDNs podem partilhar destino entre serviços distintos;
- DoH/DoT/ECH reduzem a observabilidade DNS/SNI.

### 2.2 Entregar valor operacional cedo

A sequência deve priorizar:

1. bloqueio real e automático por PF para apps/categorias;
2. bloqueio real por domínio com tabelas de destino;
3. perfis compostos para “funções” e “serviços”.

### 2.3 Tudo precisa ser operável

Cada etapa deve sair com:

- GUI clara;
- logs suficientes;
- counters;
- rollback simples;
- limitações documentadas.

---

## 3. Modelo-alvo de bloqueio

O produto passa a ter **três modos de enforcement** complementares.

### 3.1 Bloqueio por origem do cliente

Uso:

- aplicações;
- categorias;
- tráfego detectado por assinatura nDPI;
- contenção rápida do host após detecção.

Exemplo:

- cliente usa BitTorrent;
- daemon classifica;
- cliente entra em tabela PF de quarentena;
- regra PF do pacote passa a bloquear esse host.

### 3.2 Bloqueio por destino

Uso:

- domínio/site;
- endpoints conhecidos;
- serviços com resolução DNS observável.

Exemplo:

- política inclui `github.com`;
- daemon observa DNS e popula tabela de destinos;
- regra PF bloqueia conexões para esses destinos na interface alvo.

### 3.3 Bloqueio por perfil/função

Uso:

- “bloquear GitHub”;
- “bloquear WhatsApp”;
- “bloquear redes sociais”;
- “bloquear updates”, “AI tools”, “cloud storage”.

Modelo:

- um perfil agrega múltiplos critérios:
  - app nDPI
  - categoria
  - domínios
  - IPs conhecidos
  - heurísticas opcionais

---

## 4. Capabilidades finais desejadas

## 4.1 Aplicações e protocolos

Bloquear:

- BitTorrent
- QUIC
- VPNs detectadas
- streaming
- redes sociais
- apps detectadas por nDPI

Estratégia principal:

- nDPI classifica
- PF aplica bloqueio por origem do cliente

## 4.2 Sites e domínios

Bloquear:

- `github.com`
- `youtube.com`
- `facebook.com`
- `openai.com`
- listas próprias por cliente/tenant

Estratégia principal:

- correlação DNS observada
- cache de domínio → IP com TTL
- PF bloqueando destinos

## 4.3 Funções/serviços

Bloquear:

- “GitHub completo”
- “WhatsApp”
- “YouTube”
- “TikTok”
- “AI tools”
- “jogos”
- “cloud backup”

Estratégia principal:

- perfis compostos mantidos pelo pacote
- possibilidade de override local

---

## 5. Arquitetura alvo do enforcement

```text
captura pcap
    ->
nDPI / DNS observer / host correlator
    ->
policy engine
    ->
enforcement planner
    ->
PF manager
    ->
anchors + tables + rules
```

### Blocos internos novos

1. **PF manager do pacote**
- criar e manter anchors/tables/regras do Layer7

2. **Domain destination cache**
- mapear host observado para IPs de destino, com TTL/expiração

3. **Policy profile resolver**
- expandir “função/serviço” em múltiplos indicadores

4. **Enforcement planner**
- decidir se a ação é:
  - bloquear origem
  - bloquear destino
  - taggear
  - apenas monitorar

---

## 6. Fases de implementação

## Fase A — Enforcement PF automático do pacote ✅ (v0.2.7)

### Objetivo

Fazer o pacote instalar e gerir suas próprias regras PF, sem depender de
configuração manual escondida.

### Entregas

- anchor PF do Layer7;
- criação automática de regras e tables;
- persistência após reboot/reload;
- diagnostics mostrando ruleset ativo.

### Resultado esperado

Se a política é `block` e o modo é `enforce`, o host para de passar tráfego.

### Risco

- interferência com order de regras PF do pfSense.

### Rollback

- remover anchor;
- flush das tables;
- voltar a monitor.

---

## Fase B — Bloqueio real por app/categoria ✅ (v0.3.0)

### Objetivo

Fechar o bloqueio operacional de apps e categorias detectadas por nDPI.

### Entregas

- decisão `block` com ação imediata em PF;
- tabela de quarentena por origem;
- política de expiração/remoção;
- observabilidade do motivo do bloqueio.

### Testes mínimos

- BitTorrent bloqueado;
- QUIC bloqueado;
- categoria Web/Streaming bloqueada;
- rollback e expiração válidos.

---

## Fase C — Bloqueio real por domínio/site ✅ (v0.3.0)

### Objetivo

Transformar `Sites/hosts` em enforcement de destino, não apenas match lógico.

### Entregas

- cache de `host -> IP(s)` com TTL;
- tabela PF de destinos por política ou consolidada;
- suporte a subdomínio;
- expiração segura;
- logs `host_resolved`, `dst_table_add`, `dst_block_hit`.

### Testes mínimos

- `github.com`
- `youtube.com`
- `api.whatsapp.com`
- site com múltiplos IPs
- expiração TTL

### Limitações documentadas

- DoH/DoT
- cache local do cliente
- CDN e IP compartilhado

---

## Fase D — Perfis de serviço/função ✅ (v0.4.0)

### Objetivo

Permitir bloquear “coisas que o usuário entende”, não só indicadores isolados.

### Entregas

- perfis prontos:
  - GitHub
  - WhatsApp
  - YouTube
  - TikTok
  - Social
  - AI tools
- perfil = app + categoria + domínios + endpoints
- GUI para abrir o conteúdo do perfil

### Risco

- drift de endpoints ao longo do tempo.

### Mitigação

- versionamento do bundle;
- docs de atualização;
- overrides locais.

---

## Fase E — Tráfego evasivo e caminhos modernos ✅ (v0.3.1 + v0.7.0)

### Objetivo

Melhorar eficácia contra mecanismos que escondem o destino.

### Entregas

- opção de bloquear QUIC para forçar fallback TCP/TLS quando apropriado;
- políticas para DoH/DoT conhecidas;
- lista de provedores e endpoints críticos;
- eventos explícitos quando a visibilidade for insuficiente.

### Importante

Sem MITM universal, isto continua com limite técnico. O produto deve expor esse
limite, não escondê-lo.

---

## Fase F — UX, operação e troubleshooting ✅ (v0.5.0 + v0.8.0)

### Objetivo

Fazer o operador entender:

- o que foi detectado;
- o que foi bloqueado;
- por quê;
- por qual regra;
- se o bloqueio foi por origem ou por destino.

### Entregas

- Events com tipo de enforcement;
- Diagnostics com:
  - anchors
  - rules carregadas
  - tables e contagens
  - últimos adds/removes
- página de “hit counters” por política
- explicação de false positive / false negative

---

## 7. Modelo operacional proposto

## 7.1 Tipos de ação futuros

| Ação | Semântica alvo |
|------|-----------------|
| `monitor` | apenas observa |
| `allow` | exceção explícita |
| `block_src` | bloquear origem do cliente |
| `block_dst` | bloquear destinos associados ao domínio/serviço |
| `tag` | popular tabela para regras avançadas |
| `profile_block` | expandir perfil e aplicar múltiplos bloqueios |

Na GUI, isso pode continuar simplificado como `block`, mas o runtime precisa
decidir qual enforcement usar.

## 7.2 Policy engine enriquecido

Cada política poderá ter:

- app(s)
- categoria(s)
- site(s)
- perfil(s)
- interface(s)
- IP/CIDR de origem
- prioridade
- modo de enforcement

---

## 8. Estratégia de dados

## 8.1 Cache DNS

Necessário manter:

- domínio
- IP resolvido
- TTL observado ou TTL interno
- timestamp de expiração
- origem da observação

## 8.2 Bundle de perfis

Estrutura recomendada:

- versão do bundle
- perfis nomeados
- domínios
- apps nDPI
- categorias
- comentários

## 8.3 Runtime state

Persistir e expor:

- clientes bloqueados por origem
- destinos bloqueados por domínio
- causa do bloqueio
- política responsável
- tempos de expiração

---

## 9. Matriz de teste obrigatória

## 9.1 Apps

- BitTorrent
- QUIC
- VPN
- streaming
- social

## 9.2 Sites

- domínio simples
- subdomínio
- domínio com múltiplos IPs
- domínio com CDN
- domínio refeito após TTL

## 9.3 Funções

- GitHub completo
- WhatsApp
- YouTube
- TikTok

## 9.4 Condições adversas

- cliente com cache DNS
- DoH ativo
- troca de IP do destino
- reboot do pfSense
- reload do daemon
- rollback do pacote

---

## 10. Sequência recomendada de execução

### Bloco 1

PF automático do pacote

### Bloco 2

Bloqueio real por app/categoria

### Bloco 3

Bloqueio real por domínio/site

### Bloco 4

Perfis de serviço/função

### Bloco 5

Hardening, counters, UX e fleet rollout

---

## 11. Definition of Done da trilha de bloqueio

Esta trilha só fecha quando:

- instalar o pacote for suficiente para o bloqueio funcionar;
- `block` realmente parar tráfego sem regra PF manual escondida;
- `Sites/hosts` realmente bloquear destino quando houver observabilidade;
- perfis principais funcionarem em lab real;
- limitações técnicas estiverem expostas com honestidade;
- rollback estiver validado;
- a GUI mostrar “detectado”, “decidido” e “bloqueado” como estados distintos.

---

## 12. Decisões já tomadas

- pfSense CE continua como alvo primário;
- sem dependência obrigatória de software pago;
- sem MITM universal na base do produto;
- usar PF como enforcement principal;
- aproveitar nDPI + DNS observado antes de considerar técnicas mais invasivas.

---

## 13. Estado final

**Todas as fases concluidas na v1.0.0 (2026-03-23).**

| Fase | Descricao | Versao |
|------|-----------|--------|
| A | Enforcement PF automatico | v0.2.7 |
| B | Bloqueio real por app/categoria | v0.3.0 |
| C | Bloqueio real por dominio/site | v0.3.0 |
| D | Perfis de servico/funcao | v0.4.0 |
| E | Trafego evasivo (QUIC, DoH, DoT) | v0.3.1 + v0.7.0 |
| F | UX, operacao e troubleshooting | v0.5.0 + v0.8.0 |

O Layer7 atingiu todos os objetivos definidos neste plano mestre para a V1.
Evolucoes futuras estao documentadas no roadmap pos-V1 (fases 13-22).
