# Fase A — Opcao 1: regras do pacote no ciclo oficial do filtro pfSense

## Objetivo

Implementar o **enforcement PF automatico do pacote** usando o mecanismo mais
seguro para pfSense CE nesta trilha:

- o pacote Layer7 passa a **publicar regras PF** no ciclo oficial de
  montagem/reload do filtro do pfSense;
- o daemon continua a popular **PF tables** dinamicas;
- o pacote deixa de depender de regra externa manual para o bloqueio por origem
  do cliente.

Esta opcao corresponde a usar o pacote como participante do lifecycle normal do
filtro, em vez de carregar regras por fora com `pfctl` ad-hoc.

## Decisao adotada para a trilha

### Escolha

**Opcao 1**: callback de regras do pacote dentro do ciclo normal do filtro do
pfSense.

### Motivo

Entre as opcoes estudadas, esta foi a melhor no equilibrio entre:

1. seguranca e efetividade do bloqueio;
2. consistencia do pacote e menor chance de erro operacional;
3. menor janela de bypass por falha de integracao do ruleset.

### Limite honesto

Mesmo com esta opcao, o produto **nao promete bloqueio imburlavel**:

- sem MITM universal;
- sem controlo total de DNS/DoH/DoT/ECH;
- sem controlo do endpoint do utilizador.

O que esta fase fecha e:

- bloqueio automatico por origem do cliente;
- persistencia coerente em reload/reboot;
- integracao mais robusta com o pfSense CE.

## Estado atual antes desta implementacao

Hoje o repositorio ja tem:

- `layer7d` a classificar e decidir;
- `layer7d` a fazer `pfctl -t <table> -T add <ip>`;
- tabelas `layer7_block` e `layer7_tagged`;
- helper PF do pacote em `/usr/local/libexec/layer7-pfctl`;
- snippet PF gerado em `/usr/local/etc/layer7/pf.conf`;
- Diagnostics a mostrar tabelas e snippet.

Hoje ainda falta:

- ligar as regras do pacote ao ciclo oficial do filtro do pfSense;
- validar order/precedence no appliance;
- provar persistencia apos reload e reboot;
- atualizar rollback/test matrix/runbooks desta fase.

## Escopo fechado desta fase

### Dentro do escopo

- publicar regra PF do pacote para bloquear origem presente em
  `<layer7_block>`;
- integrar a publicacao dessas regras ao ciclo oficial do filtro do pfSense;
- expor diagnostics para confirmar se o ruleset do pacote foi gerado e carregado;
- validar appliance reload/reboot;
- documentar risco, rollback e testes.

### Fora do escopo

- bloqueio por destino/dominio;
- perfis compostos de servico/funcao;
- alteracao massiva do subsistema de firewall do pfSense;
- reescrita da GUI;
- estrategia anti-DoH/anti-ECH;
- bloqueio universal de bypass no endpoint.

## Arquitetura alvo deste bloco

```text
layer7d classifica fluxo
    ->
policy engine decide block/tag
    ->
layer7d adiciona IP de origem a <layer7_block>
    ->
regras PF publicadas pelo pacote ja fazem block quick para <layer7_block>
    ->
bloqueio operacional real sem regra manual externa
```

## Hipotese tecnica a validar no appliance

O pacote deve gerar regras via include PHP do pacote, no padrao esperado pelo
ecossistema pfSense.

Hipotese de implementacao:

- declarar no XML do pacote o hook de regras do filtro;
- expor em `layer7.inc` a funcao geradora de regras do pacote;
- fazer o pfSense incluir essas regras durante `filter reload`;
- validar em `pfctl -sr` e/ou `rules.debug`.

## Plano passo a passo

Cada passo deve sair pequeno, auditavel e com rollback simples.

### Passo 1 — Confirmar o hook real do pacote

#### Objetivo

Descobrir no appliance pfSense CE qual mecanismo efetivo o filtro usa para
carregar regras do pacote:

- `filter_rules_needed` no XML;
- nome esperado da funcao `*_generate_rules`;
- `ruletype` esperado;
- ponto de visibilidade em `rules.debug` e `pfctl -sr`.

#### Impacto

Sem este passo, o resto pode parecer correto no repositorio e ainda assim nao
entrar no ruleset real.

#### Risco

Medio. Existe historico de diferenca entre XML declarado e funcao realmente
esperada pelo pfSense.

#### Teste minimo

- appliance pfSense CE;
- pacote instalado;
- inspecionar comportamento apos `filter reload`;
- confirmar onde uma regra de pacote aparece.

#### Rollback

Nenhum impacto permanente se ficar so em observacao.

#### Estado validado em 2026-03-23

- o repositorio passou a expor `layer7_generate_rules("filter")` em
  `/usr/local/pkg/layer7.inc`;
- o pacote passou a chamar `filter_configure()` no install/deinstall;
- Diagnostics passaram a mostrar hook, `rules.debug` e `pfctl -sr`;
- no appliance pfSense CE 25.11.1, o hook/local helper nao bastaram para fazer
  a regra `layer7:block:src` aparecer em `pfctl -sr`.

#### Causa raiz identificada em 2026-03-23

O XML do pacote (`layer7.xml`) **nao tinha o tag `<filter_rules_needed>`**.

O pfSense CE usa `discover_pkg_rules()` em `filter.inc` para montar regras de
pacotes durante o `filter reload`. Esta funcao itera os pacotes registados em
`config.xml` e, para cada um com `<filter_rules_needed>`, inclui o
`include_file` e chama a funcao geradora de regras. Sem o tag, o pacote era
simplesmente ignorado.

Evidencia cruzada: os pacotes oficiais HAProxy e Snort do pfSense usam este
mesmo tag nos seus XMLs para registar funcoes geradoras de regras.

#### Fix aplicado

Adicionado ao `layer7.xml`:

```xml
<filter_rules_needed>layer7_generate_rules</filter_rules_needed>
```

A funcao `layer7_generate_rules("filter")` ja existia em `layer7.inc` e estava
correta. O unico ajuste necessario foi o tag no XML.

#### Pendente de validacao no appliance

Instalar o pacote com o XML corrigido e confirmar:

1. `grep layer7 /tmp/rules.debug` — presenca em rules.debug;
2. `pfctl -sr | grep layer7` — regra no ruleset ativo;
3. bloqueio real de IP em `<layer7_block>`;
4. persistencia apos reload e reboot.

### Passo 2 — Publicar a regra minima de block

#### Objetivo

Criar a implementacao minima da regra do pacote:

- `block drop quick inet from <layer7_block> to any`
- opcionalmente a variante IPv6, documentando limitacao se ainda nao houver
  suporte efetivo no daemon.

#### Impacto

Primeiro bloqueio automatico real via pacote.

#### Risco

Medio. Regra com order errada pode nao bloquear; regra em lugar errado pode
interferir mais do que o necessario.

#### Teste minimo

- IP manualmente adicionado a `layer7_block`;
- trafego deixa de passar;
- regra visivel em `pfctl -sr`.

#### Rollback

- remover retorno da regra do pacote;
- recarregar filtro;
- flush opcional da tabela.

#### Estado validado em 2026-03-23

No appliance, o caminho abaixo ja foi provado:

- politica `block` em `mode=enforce` casou com `Github`;
- o daemon registou `action=block reason=policy_match policy=teste1`;
- o daemon registou `enforce_action: block src=10.0.85.165 table=layer7_block`;
- `pfctl -t layer7_block -T show` mostrou `10.0.85.165`.

Mas o bloqueio operacional ainda nao fechou porque:

- `pfctl -vsr | grep 'layer7:block:src'` nao retornou nada;
- `pfctl -ss` mostrou states ativos/pendentes mesmo apos `pfctl -k`;
- sem regra ativa no ruleset, a table sozinha nao bloqueia.

A causa raiz foi identificada no Passo 1 acima: o XML nao tinha
`<filter_rules_needed>`. Apos o fix no XML, este passo deve ser revalidado
no appliance.

Conclusao parcial:

- policy engine: OK;
- add em PF table: OK;
- regra PF ativa do pacote: fix aplicado (tag XML), pendente de revalidacao.

### Passo 3 — Amarrar diagnostics ao estado real do filtro

#### Objetivo

Mostrar na GUI:

- se o hook do pacote esta ativo;
- se a regra apareceu no ruleset atual;
- preview da regra efetiva;
- counters e tabelas relacionados.

#### Impacto

Reduz tempo de troubleshooting e evita “bloqueio nao funcionou” sem contexto.

#### Risco

Baixo.

#### Teste minimo

- pagina Diagnostics carrega;
- mostra “hook ativo” ou “nao confirmado”;
- mostra estado do ruleset.

#### Rollback

Remover apenas diagnostico adicional.

### Passo 4 — Fechar ciclo install/reload/reboot

#### Objetivo

Garantir que a regra do pacote:

- existe apos install;
- reaparece apos `filter reload`;
- reaparece apos reboot;
- continua alinhada com o modo/estado do pacote.

#### Impacto

Entrega consistencia operacional real.

#### Risco

Medio. Falhas aqui geram comportamento “instalou, funcionou uma vez e sumiu”.

#### Teste minimo

- install limpo;
- reload de filtro;
- reboot;
- validacao em `pfctl -sr`.

#### Rollback

- desabilitar geracao da regra do pacote;
- reload de filtro.

### Passo 5 — Validar com `layer7d` end-to-end

#### Objetivo

Provar o caminho completo:

- nDPI detecta;
- policy decide `block`;
- `layer7d` adiciona IP a `<layer7_block>`;
- regra do pacote bloqueia trafego real.

#### Impacto

Fecha a Fase A funcionalmente.

#### Risco

Medio. Pode haver diferenca entre teste manual da tabela e classificacao real
sob trafego.

#### Teste minimo

- politica `block` em `mode=enforce`;
- fluxo BitTorrent ou outro app conhecido;
- confirmar log + entrada em table + bloqueio real.

#### Rollback

- voltar a monitor;
- flush de `layer7_block`;
- remover regra do pacote se necessario.

### Passo 6 — Documentar operacao e rollback

#### Objetivo

Atualizar docs para o novo comportamento oficial do produto.

#### Impacto

Evita suporte baseado em estado antigo.

#### Risco

Baixo.

#### Teste minimo

- docs batem com o comportamento observado no appliance.

#### Rollback

Reverter docs do bloco se a validacao tecnica falhar.

## Ordem recomendada de implementacao no repositorio

1. validar hook/funcao real no appliance;
2. alterar `layer7.xml` e `layer7.inc` para a regra minima;
3. reforcar Diagnostics;
4. validar install/reload/reboot;
5. atualizar testes e runbooks;
6. so depois expandir comportamento.

## Arquivos provaveis deste bloco

- `package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.xml`
- `package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc`
- `package/pfSense-pkg-layer7/files/usr/local/www/packages/layer7/layer7_diagnostics.php`
- `package/pfSense-pkg-layer7/files/usr/local/etc/rc.d/layer7d`
- `package/pfSense-pkg-layer7/files/pkg-install.in`
- `docs/05-daemon/pf-enforcement.md`
- `docs/05-runbooks/rollback.md`
- `docs/tests/test-matrix.md`
- `docs/tutorial/guia-completo-layer7.md`

## Criterios de aceite

Este bloco so pode ser marcado como concluido quando houver evidencia de
appliance para todos os itens abaixo:

- regra do pacote visivel no ruleset ativo;
- bloqueio real quando IP entra em `layer7_block`;
- comportamento persistente apos `filter reload`;
- comportamento persistente apos reboot;
- rollback simples e documentado;
- Diagnostics coerente com o estado real.

## Evidencias esperadas

- saida de `pfctl -sr` com label/trecho da regra Layer7;
- saida de `pfctl -t layer7_block -T show`;
- screenshot ou log da pagina Diagnostics;
- log do `layer7d` com `enforce_action`;
- resultado de reboot/reload documentado.

## Riscos ativos desta opcao

- diferenca entre hook declarado e hook efetivo no pfSense CE;
- order/precedence da regra no filtro;
- interferencia com reload do pfSense se o pacote publicar regra invalida;
- falsa sensacao de “bloqueio imburlavel” fora do escopo real do produto.

## Rollback desta fase

Rollback tecnico minimo:

1. desabilitar retorno das regras do pacote;
2. recarregar filtro;
3. `pfctl -t layer7_block -T flush`;
4. confirmar ausencia da regra Layer7 em `pfctl -sr`;
5. manter daemon em monitor se necessario.

Rollback de pacote:

- `pkg delete pfSense-pkg-layer7`;
- confirmar remocao das regras/tabelas dinamicas restantes conforme runbook.

## O que o proximo chat deve assumir sem pedir contexto novamente

- o projeto e **Layer7 para pfSense CE**;
- o plano mestre obrigatorio esta em `docs/09-blocking/blocking-master-plan.md`;
- a decisao desta trilha e implementar a **Opcao 1**;
- a implementacao deve ser feita em blocos pequenos;
- nao deve haver reestruturacao grande;
- docs devem ser atualizadas no mesmo bloco;
- se houver duvida sobre compatibilidade real do pfSense CE, parar e registrar
  a incerteza antes de mexer em arquitetura maior.
