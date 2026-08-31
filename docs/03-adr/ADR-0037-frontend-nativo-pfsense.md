# ADR-0037 — Frontend Layer7 nativo ao pfSense

## Status

**Aceito** (`2026-08-31`) por decisão humana. Registo documental implementado,
pendente de gates/commit neste bloco. Aplica-se à trilha BG-174 / GUI1–GUI7.

## Contexto

O diagnóstico GUI0 propôs cinco áreas conceptuais para reduzir a carga
cognitiva. Essa hipótese podia ser interpretada como um shell de navegação
próprio, visualmente distinto da WebGUI do pfSense, ou como autorização para
reorganizar rotas e tabs já conhecidas pelos operadores.

O Layer7 é um package do pfSense CE. Uma interface paralela criaria surpresa,
drift de tema, manutenção adicional, comportamento responsivo divergente e
risco de incompatibilidade com futuras versões da WebGUI.

## Decisão

1. O Layer7 permanece em `Services > Layer7`, dentro do header, menu,
   breadcrumb, largura de conteúdo e tema fornecidos pelo pfSense.
2. A organização actual de páginas, rotas e tabs é a baseline. Mudanças de
   agrupamento exigem necessidade provada, paridade, compatibilidade de deep
   links e GO específico; o redesign não autoriza reorganização ampla.
3. As cinco áreas `Visão geral`, `Proteção`, `Clientes`, `Atividade` e
   `Sistema` são apenas uma taxonomia de análise e linguagem. Não constituem
   sidebar, dashboard-app ou shell alternativo.
4. Navegação usa tabs/subtabs no padrão pfSense. Formulários, painéis, tabelas,
   mensagens, botões, badges e confirmações reutilizam os componentes e
   classes disponíveis na WebGUI: `Form_*` quando adequado, Bootstrap nativo,
   `panel`, `table`, `nav-tabs`, `alert`, `btn` e `print_info_box()`.
5. Componentes Layer7 serão wrappers funcionais mínimos sobre esses primitivos,
   não um design system visual concorrente. CSS próprio só corrige layout ou
   comportamento que o pfSense não ofereça, sempre escopado a `.layer7-page`.
6. Cores, tipografia, espaçamento, ícones e estados devem acompanhar o tema do
   pfSense. Não fixar paleta que substitua o tema nem usar cor como único sinal.
7. A página exclusiva de edição de política continua permitida porque resolve
   um problema funcional, mas preserva as tabs, o breadcrumb, os botões e o
   formulário nativos, além das rotas/deep links anteriores.
8. PHP server-rendered, PRG, CSRF, privilégios e validação de servidor continuam
   obrigatórios. JavaScript é apenas melhoria progressiva.

## Consequências

- O operador continua a sentir que está no pfSense, não noutra aplicação.
- O trabalho prioriza hierarquia, divisão de páginas, foco, contexto e feedback,
  sem redesenhar a identidade visual do produto hospedeiro.
- A hipótese de shell próprio das cinco áreas fica rejeitada.
- GUI1 deve começar por inventariar e reutilizar os primitivos nativos antes de
  extrair qualquer helper visual.
- Wireframes e testes devem mostrar o chrome/tabs/painéis do pfSense e comparar
  temas suportados, desktop e larguras menores.

## Riscos e mitigação

- **Limitação do Bootstrap antigo:** usar melhoria progressiva e fallback
  server-rendered.
- **CSS existente já demasiado customizado:** reduzir por blocos, nunca em
  refactor transversal único.
- **Tabs numerosas:** melhorar subordinação e conteúdo dentro da organização
  actual; qualquer mudança estrutural futura volta ao gate humano.

## Rollback

Reverter o bloco documental desta decisão. Em implementação, cada onda mantém
rotas e handlers anteriores e permite rollback pelo package anterior de arquivo
interno, sem republicar a mesma versão.

## Relações

- Backlog: BG-174.
- Análise: [`../00-overview/frontend-redesign-analise.md`](../00-overview/frontend-redesign-analise.md).
- Plano: [`../02-roadmap/plano-redesign-frontend.md`](../02-roadmap/plano-redesign-frontend.md).
- Paridade: [`../01-architecture/frontend-redesign-inventario-paridade.md`](../01-architecture/frontend-redesign-inventario-paridade.md).
