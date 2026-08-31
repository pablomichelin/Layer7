# Wireframes de baixa fidelidade — GUI Layer7

Estes wireframes definem hierarquia e estados, não identidade visual final.
Todos assumem o shell do pfSense, navegação por teclado e largura mínima de
320 px. Em ecrã estreito, colunas empilham, filtros recolhem numa secção e cada
linha de tabela vira bloco sem ocultar ações.

**Invariante ADR-0037:** todos os desenhos vivem em `Services > Layer7`, sob o
header, breadcrumb e tabs/subtabs nativos. Caixas representam `panel`, listas
representam `table`/`table-responsive`, formulários seguem `Form_*`/Bootstrap e
mensagens seguem `print_info_box()`/`alert`. As cinco áreas são apenas taxonomia;
não existe sidebar Layer7 nem shell visual próprio.

## Convenções

`[primário]` acção principal; `[secundário]` acção reversível; `[perigo]`
acção destrutiva; `●` estado; `▾` detalhe recolhível; `!` aviso.

## 1. Dashboard

```text
pfSense > Services > Layer7
[Estado] [Dispositivos] [Políticas] [Identity] [MITM] [...] [Definições]
┌ Visão geral ───────────────────────────── [Ver diagnóstico] ┐
│ ● Proteção efectiva: MONITOR   Pedido: ENFORCE              │
│ ! Licença inválida impede o PF de armar   [Ver licença]     │
├ Saúde ─────────┬ Políticas ────────┬ Actividade ─────────────┤
│ daemon activo  │ 6 activas         │ 124 bloqueios / 24 h    │
│ PF não armado  │ 2 com aviso       │ último evento 2 min     │
├ Alertas operacionais ────────────────────────────────────────┤
│ Captive Portal detectado; página Layer7 não será aplicada.   │
├ Mais bloqueados ───────────────┬ Clientes com mais eventos ─┤
│ tabela curta / estado vazio    │ tabela curta / estado vazio │
└────────────────────────────────┴──────────────────────────────┘
```

Loading usa texto “A carregar estado”; erro mantém links para Settings e
Diagnostics; sucesso pós-acção mostra etapas daemon/PF separadas.

## 2. Lista de políticas

```text
pfSense > Services > Layer7 > Políticas
[Políticas] [Grupos] [Exceções] [Categorias nDPI] [Simular teste]
┌ Proteção / Políticas aplicadas ─────── [Criar política] ┐
│ [Buscar________________] [Estado▾] [Acção▾] [Limpar]    │
│ 6 políticas · filtros no URL                            │
├ Nome          Estado   Âmbito     Resultado    Ações ───┤
│ Pornografia   Activa   Todas LAN  Bloquear     [Editar] │
│ YouTube       Activa   Gestores   Monitorar    [Editar] │
│                              [Detalhes] [Mais ações▾]   │
├ Alterações pendentes: 2  [Descartar] [Aplicar mudanças]┤
└──────────────────────────────────────────────────────────┘
```

Empty: “Nenhuma política aplicada” + atalhos para Biblioteca/Criar. Erro não
substitui a lista já carregada. A remoção fica em `Mais ações`, com confirmação.

## 3. Edição de política

```text
pfSense > Services > Layer7 > Políticas > Editar
[Políticas] [Grupos] [Exceções] [Categorias nDPI] [Simular teste]
┌ ← Políticas / Editar Pornografia            ● Activa ┐
│ ID profile-pornografia (somente leitura)               │
├ Âmbito ────────────────────────────────────────────────┤
│ Nome [Pornografia____]  Prioridade [50]  [✓] Activa   │
│ Interfaces [LAN ✓] [Visitantes ]  Origens [Todas▾]    │
├ O que proteger ────────────────────────────────────────┤
│ [Apps 3] [Categorias 1] [Sites 12] [Ver seletores]    │
├ Resultado ─────────────────────────────────────────────┤
│ ( ) Monitorar  (●) Bloquear  ( ) Permitir  ( ) Tag   │
├ Horário ▾       Avançado ▾ (exclusões, AD, escopo)    │
├ ! Alterações não salvas       [Cancelar] [Salvar]     │
└────────────────────────────────────────────────────────┘
```

Erro move foco ao resumo e associa cada mensagem ao campo. Após salvar, a tela
mostra “config salva / daemon recarregado / PF aplicado” e oferece voltar à
lista no mesmo filtro/posição.

## 4. Biblioteca de perfis

```text
┌ Proteção / Biblioteca                    [Criar perfil] ┐
│ [Buscar Pornografia___] [Grupo▾] [Estado▾]             │
│ Aplicados 6 | Disponíveis 99 | Personalizados 2        │
├ Pornografia ────────────────────────────────────────────┤
│ Sites adultos · 3 apps · 1 categoria · 12 hosts        │
│ ● Aplicado   [Ver conteúdo] [Editar] [Desligar]         │
├ Streaming / YouTube ────────────────────────────────────┤
│ Disponível                         [Opções] [Activar]    │
└──────────────────────────────────────────────────────────┘
```

Cards não renderizam centenas de checkboxes até abrir edição. Loading existe
somente se a lista for carregada progressivamente; sem JS, tudo permanece
operável em páginas server-rendered.

## 5. Devices

```text
┌ Clientes / Dispositivos                         [Atualizar] ┐
│ [Buscar IP, MAC, nome____] [Interface▾] [Estado▾]         │
│ 674 encontrados · 1–50                     [50 por página▾]│
├ □  Dispositivo   IP/MAC        Interface  Estado  Ações ───┤
│ □  Recepção      10.0.0.21     LAN        Online  [Abrir]  │
│ □  Sem nome      10.0.0.22     Wi-Fi      Offline [Abrir]  │
├ 2 seleccionados  [Atribuir ao grupo▾]                       │
└ Página 1 de 14                         [Anterior] [Próxima] ┘
```

Alias é editado no detalhe/linha sob demanda, não como 674 inputs simultâneos.
Empty distingue “sem dispositivos” de “nenhum resultado para o filtro”.

## 6. Events

```text
┌ Atividade / Eventos                      ● Actualização live ┐
│ [Tráfego | Operacional] [Buscar____] [Resultado▾] [Pausar] │
├ 14:32  BLOQUEADO · Pornografia · Cliente Recepção          │
│ site.example · app/category         [Investigar] [Técnico▾]│
├ 14:31  MONITORADO · YouTube · 10.0.0.22                    │
│ youtube.com                         [Investigar] [Técnico▾] │
└ [Carregar anteriores]                                         ┘
```

`Limpar vista` explicita que não apaga logs. Erro de polling pausa live e
preserva eventos existentes. Raw técnico é disclosure, não texto dominante.

## 7. Settings

```text
┌ Sistema / Configurações                         [Salvar] ┐
│ Geral | Interfaces | Anti-bypass | Página de bloqueio   │
│ Logs e relatórios | Avançado                              │
├ Geral ────────────────────────────────────────────────────┤
│ Idioma [Português▾]  Serviço [✓] Activo                  │
│ Modo pedido ( ) Monitorar (●) Aplicar bloqueios          │
│ ! O modo efectivo continuará MONITOR até licença válida. │
├ Impacto desta mudança ────────────────────────────────────┤
│ Salva config; recarrega daemon; PF só se necessário.      │
├ ! 3 alterações não salvas     [Descartar] [Salvar mudanças]│
└────────────────────────────────────────────────────────────┘
```

Cada secção tem URL/âncora, resumo de impacto e erros próprios. Captive Portal
gera warning bloqueante/explicativo na secção Página de bloqueio.

## 8. Diagnostics

```text
┌ Sistema / Diagnóstico                    [Atualizar estado] ┐
│ ● Daemon  ● Config  ! PF  ● nDPI  ● Logs                 │
├ Problema detectado ────────────────────────────────────────┤
│ Duas tabelas PF obrigatórias estão ausentes.               │
│ [Ver evidência técnica]              [Reparar tabelas PF]  │
├ Ações no daemon ────────────────────────────────────────────┤
│ [Recolher estatísticas] [Recarregar configuração]          │
├ Suporte ▾  [Copiar URL] [Abrir issue no GitHub]            │
└─────────────────────────────────────────────────────────────┘
```

Reparação pede confirmação e depois mostra before/after. Read-only vem antes
das mutações. Loading/erro por componente não bloqueia os demais diagnósticos.

## 9. Licença e atualização

```text
┌ Sistema / Licença e atualização                         ┐
├ Licença ────────────────────────────────────────────────┤
│ ● Válida · Empresa · expira em … · Identity incluído   │
│ [Registar nova licença]                    [Revogar…]    │
├ Atualização ─────────────────────────────────────────────┤
│ Instalada 1.9.79  ·  Verificação ainda não executada    │
│ [Verificar atualização]                                 │
│ Resultado: disponível 1.9.80                            │
│ ! O daemon será interrompido durante a instalação.      │
│ [Ver notas]                              [Atualizar…]    │
└──────────────────────────────────────────────────────────┘
```

Verificação tem loading/erro/up-to-date/no-asset. Update exige confirmação e
mostra etapas stop/download/install/start; falha nunca aparece como sucesso.

## 10. Remoção do pacote

```text
┌ Sistema / Zona de perigo / Remover Layer7                ┐
│ ! Remove daemon, GUI, blacklists, cron e tabelas PF.      │
│ Preservar: (●) configuração completa                     │
│            ( ) apenas licença  ( ) nada                  │
│ Digite REMOVER [__________]                               │
│ [Cancelar]                         [perigo Remover pacote]│
└────────────────────────────────────────────────────────────┘
```

Se o job já iniciou, o formulário desaparece e entra estado “remoção em
curso”, com log/caminho de verificação. A precedência das opções actuais é
preservada, mas apresentada como escolhas mutuamente exclusivas.

## 11. Matriz de estados obrigatórios

| Estado | Lista/tabela | Formulário | Acção assíncrona | Destrutiva |
|---|---|---|---|---|
| normal | total, filtros, resultados | valores + ajuda | botão disponível | isolada |
| vazio | causa + próxima acção | N/A | N/A | N/A |
| loading | skeleton curto + texto | bloqueia só campos dependentes | progresso/etapa | nunca simula conclusão |
| erro | preserva contexto + retry | resumo + erro por campo | etapa falhada + recuperação | confirmação permanece |
| pendente | filtros/contexto mantidos | barra dirty fixa ao conteúdo | aguardando confirmação | texto digitado |
| sucesso | lista actualizada | etapas de aplicação | conclusão inequívoca | job iniciado/concluído |
| aviso | limitação/impacto | antes do submit | risco operacional | consequências completas |
| destrutivo | acção fora da linha principal | confirmação proporcional | sem auto-retry | cor + texto + ícone, não só cor |

## 12. Fluxos de aceitação nos wireframes

1. Pornografia: Proteção → buscar → Editar, no máximo três interações.
2. Perfil pronto: Biblioteca → Activar → opções curtas → confirmar.
3. Política personalizada: Políticas → Criar → página dedicada.
4. Sites/apps/categorias: `Ver conteúdo`/`Detalhes`, sem perder filtro.
5. Excepção cliente: Dispositivo → acções → criar excepção/VIP com contexto.
6. Dispositivo: busca paginada por alias/IP/MAC/hostname.
7. Bloqueio: Evento → Investigar → política, cliente e evidência técnica.
8. Simular: Proteção → Simulador; read-only inequívoco.
9. Monitor→enforce: Settings Geral; pedido e efectivo separados.
10. Captive Portal: warning na Página de bloqueio com conflito e resolução.
11. Licença/update: Sistema → Licença e atualização.
12. Backup/restore: Sistema → Backup e restauração, confirmação de import.
13. Uninstall: Sistema → Zona de perigo → confirmação tipada.
