# Redesenho integral da GUI Layer7 — análise e decisão

**Estado:** GUI0 documental concluído no commit `3563757` após gates PASS;
implementação GUI1–GUI7 bloqueada até GO humano explícito.
**Baseline auditada:** `main` em `4354aec`, pacote `1.9.79`, pfSense autenticado
em `https://45.238.165.144:9999`, inspeção exclusivamente de leitura em
`2026-08-31`. A tag leve `v1.9.79` aponta para `4d210d5` (commit do artefacto);
`4354aec` é o commit documental posterior que marcou a publicação como latest.
**Matriz técnica:**
[`../01-architecture/frontend-redesign-inventario-paridade.md`](../01-architecture/frontend-redesign-inventario-paridade.md).
**Plano governado:**
[`../02-roadmap/plano-redesign-frontend.md`](../02-roadmap/plano-redesign-frontend.md).
**Wireframes:**
[`frontend-redesign-wireframes.md`](frontend-redesign-wireframes.md).

## 1. Resumo executivo

A interface não sofre de um único defeito de CSS. A causa predominante é a
mistura, na mesma página, de tarefas com intenção e risco diferentes. O caso
mais grave é `Policies`: políticas aplicadas, catálogo de 105 perfis, criação,
edição, detalhes e alterações pendentes coexistem na mesma rota. O link
`?edit=N` recarrega a página e o formulário aparece depois de todo o catálogo,
reproduzindo exactamente a queixa de que «Editar não funcionou».

A observação do appliance confirmou também:

- `Devices`: 674 linhas, 1.351 campos num único `POST` e 33.774 px de altura;
- `Exceptions`: 11.418 px e 304 campos, misturando Lista VIP, reservas DHCP,
  edição em lote, import/export e excepções gerais;
- `Settings`: 83 campos em seis formulários, junto com licença, backup,
  importação, update e configurações de runtime/PF;
- navegação plana com 12 destinos, incluindo `Package removal` no mesmo nível
  das tarefas frequentes;
- `Policies`: 771 campos renderizados, vários botões sem texto visível e
  separação insuficiente entre rascunho, gravação JSON, reload do daemon e
  reload PF;
- filtros/contexto vivem sobretudo no DOM ou em links locais e não formam um
  contrato consistente de retorno, restauração de scroll ou alterações não
  salvas.

A recomendação é manter PHP renderizado no servidor, os privilégios e o CSRF
nativos do pfSense, e acrescentar JavaScript progressivo. Não há evidência que
justifique React, Vue ou uma SPA: isso acrescentaria build, CSP, dependências e
um segundo modelo de estado sem resolver os contratos de servidor já
existentes.

## 2. Evidência e método

Foram cruzadas três fontes:

1. GUI real autenticada no Chrome, navegada apenas com `GET`, sem submeter
   formulários nem accionar serviços;
2. handlers PHP, JavaScript, CSS inline, XML do package, `layer7.inc`, catálogos
   PT/EN/ES e dados de perfis;
3. documentação canónica, testes e runbooks de package/lab.

Nenhuma configuração foi gravada no pfSense. Não foram activadas políticas,
reiniciados serviços, limpos dados, verificadas actualizações, exportados dados
do cliente nem executadas acções de licença/removal.

## 3. Diagnóstico por severidade

| Severidade | Achado | Evidência | Consequência |
|---|---|---|---|
| S1 crítica UX | Editar política recarrega a própria página e abre o formulário abaixo do catálogo | `layer7_policies.php?edit=N`; formulário após perfis rápidos | operador interpreta ausência de feedback como falha |
| S1 crítica UX | Devices não escala | 674 linhas/1.351 inputs/33.774 px; sem paginação | procura lenta, POST enorme, foco e contexto frágeis |
| S1 crítica segurança operacional | Ações de efeitos distintos parecem equivalentes | save JSON, SIGHUP, `filter_configure`, restart, update e uninstall usam botões comuns | operador não antecipa impacto/reload/interrupção |
| S2 alta | Policies mistura estado aplicado, biblioteca e autores | 105 perfis + 6 políticas + criação/edição na mesma rota | carga cognitiva e risco de mudança acidental |
| S2 alta | Settings é um agregador de subsistemas | runtime, captura, anti-bypass, block page, logs, relatórios, licença, backup e update | opções críticas competem com tarefas frequentes |
| S2 alta | Exceptions mistura quatro fluxos | VIP directa, DHCP picker, bulk/import/export e excepções | página de 11.418 px e 304 campos |
| S2 alta | Navegação exige modelo interno | 10 tabs primárias + 2 secundárias; Identity/MITM/Removal expostos | operador precisa conhecer componentes, não objectivos |
| S2 alta | Estado de alterações não salvas é local e inconsistente | barra apenas em perfis rápidos; demais formulários sem dirty guard | perda silenciosa ao navegar |
| S2 alta | Feedback de aplicação não distingue fases | mensagens como “gravada; SIGHUP enviado se...” | não se sabe se persistiu, recarregou daemon ou aplicou PF |
| S3 média | Controles icon-only e labels ambíguos | vários botões vazios em perfis; Editar/Opções/Ver listas | acessibilidade e previsibilidade reduzidas |
| S3 média | Estados vazios/erro/loading não têm padrão comum | cada página implementa avisos próprios | manutenção e i18n inconsistentes |
| S3 média | Detalhe técnico domina páginas de operação | paths, PF, sinais, modelos internos | conteúdo essencial perde hierarquia |
| S3 média | Layout usa estilos inline e CSS monolítico do include | `layer7_render_styles()` + estilos por página | regressão visual e responsiva difícil de isolar |

## 4. Mapa actual

```text
Services > Layer7
├── Status
├── Devices
├── Policies
│   ├── Policies
│   ├── Groups
│   ├── Exceptions
│   ├── nDPI Categories
│   └── Simulate test
├── Identity                         [entitlement; fila fechada]
├── MITM                             [entitlement; NO-GO permanente]
├── Blacklists
├── Allowlist
├── Events
├── Reports
├── Settings
├── Diagnostics
└── Package removal
```

O XML do package lista apenas parte dessas entradas; a navegação efectivamente
renderizada é construída por `layer7_nav_items()` e
`layer7_nav_secondary_items()` no `layer7.inc`.

## 5. Arquitecturas consideradas

| Alternativa | Vantagem | Limitação | Decisão |
|---|---|---|---|
| A — manter tabs por módulo | alteração pequena | preserva o modelo interno e excesso de destinos | rejeitada como arquitectura final |
| B — cinco áreas por tarefa | linguagem de operador, progressive disclosure, escala | exige mapa de rotas e migração faseada | **recomendada** |
| C — dashboard único + busca de comandos | acesso rápido para expert | descoberta e acessibilidade fracas; complexidade desnecessária | não usar como navegação principal |
| D — SPA completa | transições sem reload | novo build/CSP/estado/ataque/rollback; desalinhada ao pfSense | rejeitada sem ADR e prova específica |

## 6. Arquitectura recomendada

```text
Visão geral
└── Estado, modo pedido/efectivo, licença, saúde, políticas activas, alertas

Proteção
├── Políticas aplicadas
├── Biblioteca de perfis
├── Excepções e Lista VIP
├── Allowlist de destinos
├── Blacklists web
├── Simulador
└── Catálogo nDPI (referência)

Clientes
├── Dispositivos
├── Grupos
└── Identity (avançado, entitlement; funcionalidade congelada)

Atividade
├── Eventos
└── Relatórios

Sistema
├── Geral
├── Interfaces e captura
├── Bloqueios e anti-bypass
├── Página de bloqueio
├── Logs e relatórios
├── Licença e atualização
├── Backup e restauração
├── Diagnóstico
├── MITM (avançado; fila fechada/NO-GO)
└── Zona de perigo: remoção do pacote
```

Identity e MITM permanecem integralmente inventariados. A mudança é apenas de
encontrabilidade/isolamento visual; não reabre a trilha 20.37, não altera
entitlements e não autoriza MITM.

## 7. Redesenho de Policies

### Opções de edição

| Opção | Formulário longo | Teclado/leitor | Ecrã menor | Manutenção pfSense | Risco |
|---|---:|---:|---:|---:|---:|
| painel lateral | fraco | médio | fraco | médio | médio |
| modal | fraco | médio | fraco | conhecido, mas apertado | alto |
| página exclusiva | **forte** | **forte** | **forte** | **simples** | **baixo** |
| inline na lista | fraco | médio | médio | estado complexo | alto |

**Recomendação:** página exclusiva de criação/edição, ainda server-rendered e
com PRG. Deve abrir acima da dobra, mostrar nome/estado/contexto, oferecer
`Cancelar e voltar`, preservar filtros/scroll no URL de retorno e dividir o
formulário em `Âmbito`, `O que proteger`, `Resultado`, `Horário` e `Avançado`.
Modal permanece aceitável apenas para a configuração curta de activação de um
perfil pronto; nunca para a política completa.

A tela de Proteção separa:

- **Políticas aplicadas:** fonte do runtime; editar, activar/desactivar,
  detalhes e remover;
- **Biblioteca:** factory/custom/override/hidden, pesquisa e filtros;
- **Rascunho de perfis:** mudanças locais com barra fixa `Descartar` / `Aplicar`;
- **Criação personalizada:** fluxo dedicado, sem o catálogo acima dele.

O objectivo “Editar Pornografia em até três interações” fica: `Proteção` →
pesquisar/seleccionar `Pornografia` → `Editar política`.

## 8. Settings recomendada

| Secção | Conteúdo | Efeito a declarar antes de salvar |
|---|---|---|
| Geral | idioma, serviço, modo pedido | persistência + possível SIGHUP; modo efectivo depende de licença/daemon |
| Interfaces e captura | interfaces, SNI | mudança de captura/reload do daemon |
| Bloqueios e anti-bypass | QUIC por interface, DoT/DoQ, modelo | possível quebra de apps; PF reload; `scoped_hybrid` experimental |
| Página de bloqueio | portal, copy, host/policy, DNS/sinkhole | Unbound/NAT/blockpage; conflito Captive Portal antes da acção |
| Logs e relatórios | níveis, rotação, syslog, detalhe, retenção | armazenamento, cron e privacidade |
| Licença e atualização | estado, registo/revogação, check/update | restart/interrupção e gates de entitlement |
| Backup e restauração | export/import | import pode substituir config e aplicar PF |
| Avançado | debug temporário e detalhes técnicos | impacto e rollback explícitos |

## 9. Modelo de estado “Salvar e aplicar”

Cada acção mutável deve expor as etapas reais, sem as fundir:

```text
Alterado localmente → Validando → Configuração salva → Daemon recarregado
                                     └──────────────→ PF aplicado (se necessário)
                                     └──────────────→ Serviço reiniciado (só quando necessário)
```

O resultado deve dizer quais etapas ocorreram, quais foram dispensadas e onde
falhou. A lógica continua no servidor; JavaScript só melhora apresentação e
preserva contexto. Ações destrutivas permanecem `POST` + CSRF + confirmação.

## 10. Sistema de componentes

| Componente | Contrato |
|---|---|
| cabeçalho de página | título de operador, resumo, estado e acção primária única |
| navegação principal/secundária | cinco áreas + destinos locais; estado activo e teclado |
| cartão de estado | valor, significado e próxima acção; sem métricas decorativas |
| tabela/lista | busca, filtros, paginação, total, empty/error/loading, ações nomeadas |
| chips | app, categoria, host, interface; texto sempre disponível |
| badge | pedido/efectivo/licença/daemon distintos; cor nunca é o único sinal |
| painel de detalhe | leitura técnica sob demanda, sem esconder acções essenciais |
| secção avançada | `<details>` ou painel acessível, estado persistido por página |
| aviso/erro/confirmação | impacto, causa, recuperação e foco programático |
| barra de alterações | resumo do dirty state, `Descartar`, `Salvar`/`Aplicar` |
| resultado de aplicação | etapas salvar/reload/PF/restart com sucesso parcial explícito |
| estado vazio | explicação + uma próxima acção válida |
| loading/skeleton | apenas para AJAX real; texto `aria-live` e fallback sem JS |
| zona de perigo | separada da configuração normal; confirmação proporcional |

Implementação futura deve extrair componentes PHP partilhados, reutilizar o
Bootstrap/Form stack do pfSense e adoptar JS externo progressivo. Nenhuma
autorização, validação ou decisão de segurança pode existir apenas no browser.

## 11. Acessibilidade, responsividade e i18n

- labels `<label for>` reais; placeholders apenas como exemplo;
- foco visível e movido para mensagem/formulário após PRG/erro;
- botões icon-only proibidos quando a acção não for universal; `aria-label`
  obrigatório quando inevitável;
- alvos de toque adequados e tabelas com modo responsivo/lista em 320 px;
- ordem DOM igual à ordem visual; sem dependência de hover;
- mensagens dinâmicas em `aria-live`, erros com associação ao campo;
- títulos, help, mensagens, estados vazios e labels novos entram simultaneamente
  em PT/EN/ES;
- nenhuma chave pode depender do fallback para atingir paridade;
- filtros e paginação vivem no URL; retorno de edição preserva query e âncora;
- dirty guard cobre navegação interna, refresh e fechamento da aba, mas nunca
  impede o POST/PRG legítimo.

## 12. Decisões humanas exigidas

1. GO para adoptar as cinco áreas como IA alvo.
2. GO para página exclusiva de edição/criação de políticas.
3. Confirmar se Identity fica em `Clientes > Avançado` e MITM em
   `Sistema > Avançado`, mantendo ambos visíveis apenas conforme contrato
   actual de entitlement.
4. Confirmar estratégia de rollout: uma versão por bloco técnico, sem “big
   bang”, e manutenção temporária de links/rotas antigas.
5. Confirmar se o primeiro bloco técnico autorizado será apenas fundação
   compartilhada (GUI1) ou navegação + fundação (GUI1–GUI2).

Sem essas decisões, o estado permanece **analisado e planeado; código GUI não
autorizado**.
