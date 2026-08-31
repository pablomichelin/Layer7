# Wireframes nativos pfSense — GUI Layer7

Estes wireframes definem hierarquia e estados, não uma nova identidade visual.
A referência normativa é o formulário WebGUI do pfSense entregue pelo humano
em `2026-08-31`.

## Contrato visual inviolável

- `Services > Layer7`, breadcrumb e tabs existentes;
- barra escura horizontal para o título de cada secção;
- corpo branco e plano, com uma linha horizontal por campo ou informação;
- label alinhada na coluna esquerda;
- controlo, valor e help text na coluna direita;
- tabelas nativas para colecções;
- botões com classes, cores, tamanho e posição do pfSense;
- `print_info_box()`/`alert` para aviso, erro, sucesso e dirty state;
- renderização pelos componentes/assets pré-carregados do pfSense, não por uma
  reprodução CSS destes wireframes;
- sem cards, grelhas de KPI, chips, sombras, skeleton cards, sticky bars,
  painéis laterais ou caixas arredondadas como estrutura principal.

Em ecrã estreito, a linha pode empilhar label sobre campo/help usando o
comportamento responsivo do pfSense; a ordem semântica permanece igual.

## 1. Estado

```text
pfSense > Services > Layer7
[Estado] [Dispositivos] [Políticas] [...] [Definições]

████ Estado do Layer7 █████████████████████████████████████
 Serviço             Activo · PID 1234
 Modo pedido         Aplicar bloqueios
 Modo efectivo       Monitorizar
                     A licença actual não permite aplicar o PF.
 Licença             Inválida                         [Ver licença]

████ Protecção e actividade ███████████████████████████████
 Políticas activas   6
 Último evento       há 2 minutos
 Mais bloqueado      Pornografia · 61 eventos

████ Ações ████████████████████████████████████████████████
 Daemon              [Reiniciar serviço]
                     Interrompe brevemente a classificação.
```

Sem cards de métricas. Loading aparece como texto discreto na linha afectada;
erro usa `print_info_box()` antes da primeira secção.

## 2. Políticas aplicadas

```text
pfSense > Services > Layer7 > Políticas
[Políticas] [Grupos] [Excepções] [Categorias nDPI] [Simular]

████ Filtros ███████████████████████████████████████████████
 Procurar            [Pornografia________________________]
 Estado              [Todos ▾]       Resultado [Todos ▾]

████ Políticas aplicadas █████████████████████ [Adicionar]
 Nome             Estado    Âmbito       Resultado     Ações
 Pornografia      Activa    Todas LAN    Bloquear      [Editar]
 YouTube          Activa    Gestores     Monitorizar   [Editar]

 Resultados          1–2 de 6                  [Anterior] [Próxima]
```

Empty state ocupa uma linha normal da tabela. Detalhes abrem abaixo da linha
ou numa página nativa, sem card.

## 3. Editar política

```text
pfSense > Services > Layer7 > Políticas > Editar
[Políticas] [Grupos] [Excepções] [Categorias nDPI] [Simular]

████ Identificação █████████████████████████████████████████
 ID                  profile-pornografia (somente leitura)
 Nome                [Pornografia________________________]
 Activa              [✓] Usar esta política
 Prioridade          [50___]

████ Âmbito ████████████████████████████████████████████████
 Interfaces          [✓ LAN] [ Visitantes]
 Origens             [Todas ▾]
                     Quem será abrangido pela política.

████ Conteúdo ██████████████████████████████████████████████
 Apps                [Selecionar…]  3 seleccionadas
 Categorias          [Selecionar…]  1 seleccionada
 Sites               [Editar lista…] 12 entradas

████ Resultado █████████████████████████████████████████████
 Acção               ( ) Monitorizar  (●) Bloquear  ( ) Permitir

[aviso nativo: Existem alterações não guardadas.]
                                             [Cancelar] [Guardar]
```

O formulário abre no topo. Erro move foco ao info box e à linha inválida.

## 4. Biblioteca de perfis

```text
████ Filtros da biblioteca █████████████████████████████████
 Procurar            [Pornografia________________________]
 Grupo               [Todos ▾]       Estado [Todos ▾]

████ Perfis disponíveis ███████████████████████ [Criar perfil]
 Perfil           Grupo        Conteúdo              Estado/Ações
 Pornografia      Segurança    3 apps · 1 cat · 12   Activo [Editar]
 YouTube          Streaming    2 apps · 8 sites      [Opções] [Activar]
```

Os 105 perfis são linhas paginadas/filtradas, não quadrados individuais.

## 5. Dispositivos

```text
████ Filtros ███████████████████████████████████ [Actualizar]
 Procurar            [IP, MAC, nome______________________]
 Interface           [Todas ▾]       Estado [Todos ▾]

████ Dispositivos ████████████████████████████████████████
 □  Nome          IP / MAC              Interface  Estado   Ações
 □  Recepção      10.0.0.21 / 00:…      LAN        Online   [Editar]
 □  Sem nome      10.0.0.22 / A2:…      Wi-Fi      Offline  [Editar]

 Seleccionados       2    Grupo [Escolher ▾] [Atribuir]
 Resultados          1–50 de 674             [Anterior] [Próxima]
```

Aliases são editados numa linha/página sob demanda; não existem 674 inputs no
mesmo POST.

## 6. Eventos

```text
████ Filtros de eventos ████████████████████████████████████
 Fonte               (●) Tráfego  ( ) Operacional
 Procurar            [___________________________________]
 Actualização        Activa                              [Pausar]

████ Eventos █████████████████████████████████████████████
 Hora    Resultado   Cliente       Destino          Política     Ações
 14:32   Bloqueado   Recepção      site.example     Pornografia  [Detalhes]
 14:31   Monitorado  10.0.0.22     youtube.com      YouTube      [Detalhes]
```

Detalhe técnico expande uma linha. “Limpar vista” nunca parece apagar logs.

## 7. Definições

```text
[Geral] [Interfaces] [Anti-bypass] [Página de bloqueio]
[Logs e relatórios] [Licença/actualização] [Avançado]

████ Definições gerais █████████████████████████████████████
 Idioma              [Português ▾]
 Serviço             [✓] Activar Layer7
 Modo pedido         (●) Monitorizar  ( ) Aplicar bloqueios
                     O modo efectivo também depende da licença e do daemon.

████ Impacto operacional ███████████████████████████████████
 Ao guardar          Configuração será gravada e o daemon recarregado.
 PF                  Só será aplicado se os campos alterados exigirem.

[aviso nativo: 3 alterações ainda não foram guardadas.]
                                                        [Guardar]
```

As secções/abas organizam os 83 campos sem criar cartões.

## 8. Diagnósticos

```text
████ Estado dos componentes ████████████████████████████████
 Daemon              Activo
 Configuração        Válida
 PF                  Duas tabelas obrigatórias ausentes
 nDPI                Disponível

[aviso nativo: Foram detectadas tabelas PF ausentes.]

████ Acções de diagnóstico █████████████████████████████████
 Estatísticas        [Recolher agora]
 Configuração        [Recarregar daemon]
 Tabelas PF          [Ver evidência] [Reparar tabelas]
                     A reparação altera o PF e exige confirmação.
```

Leitura vem antes das reparações; não há grelha de status cards.

## 9. Licença e actualização

```text
████ Licença ███████████████████████████████████████████████
 Estado              Válida
 Cliente             Empresa
 Expiração           …
 Funcionalidades     Identity incluído
 Acções              [Registar nova] [Revogar…]

████ Actualização ██████████████████████████████████████████
 Versão instalada    1.9.79
 Última verificação  Ainda não executada
 Acções              [Verificar actualização]
                     A instalação interrompe o daemon temporariamente.
```

Progresso e resultado aparecem em info box/linhas, não em cards de etapas.

## 10. Remoção do pacote

```text
[alerta nativo: Esta operação remove o Layer7 e pode interromper protecção.]

████ Opções de remoção █████████████████████████████████████
 Preservar dados      (●) Configuração completa
                     ( ) Apenas licença
                     ( ) Não preservar
 Confirmação          [____________________]
                     Digite REMOVER para continuar.

                                              [Cancelar] [Remover pacote]
```

A separação de risco vem da ordem, título, alert e confirmação nativos — não
de uma caixa desenhada como aplicação externa.

## 11. Estados obrigatórios

| Estado | Representação pfSense |
|---|---|
| normal | secções, linhas e tabelas nativas |
| vazio | linha de tabela ou info box com próxima acção |
| loading | texto/ícone discreto na linha afectada |
| erro | `print_info_box()`/`alert-danger` + erro junto do campo |
| pendente | info box próximo do rodapé do formulário |
| sucesso | `print_info_box()` com etapas textuais realizadas |
| aviso | `alert-warning` antes da acção afectada |
| destrutivo | secção nativa + alerta + confirmação explícita |

## 12. Fluxos de aceitação

1. Pornografia: Políticas → procurar → Editar, no máximo três interações.
2. Perfil pronto: Políticas/Biblioteca → Activar → opções → confirmar.
3. Política personalizada: Políticas → Adicionar → formulário nativo dedicado.
4. Conteúdo de política: detalhes/linha sem perder filtros.
5. Excepção: Dispositivo → acções → criar excepção/VIP.
6. Dispositivo: busca paginada por alias/IP/MAC/hostname.
7. Bloqueio: Evento → Detalhes → política, cliente e evidência técnica.
8. Simular: subtab Simular; read-only inequívoco.
9. Monitor→enforce: Definições; pedido e efectivo separados.
10. Captive Portal: alert nativo na secção Página de bloqueio.
11. Licença/update: Definições → Licença/actualização.
12. Backup/restore: secção própria com confirmação de import.
13. Uninstall: Remoção → confirmação tipada.
