# F3 - Organizacao Local e Fecho

## Finalidade

Este documento organiza a pasta local do MacBook apenas no que interessa para
fechar a F3 sem improviso.

Objectivo:

- reduzir a dispersao entre raiz, `docs/`, codigo, scripts e alvos reais;
- deixar claro o que e canónico, o que e apenas referencia local e o que nao
  serve como prova do ambiente live;
- fixar a ordem minima de leitura e de execucao para concluir a F3;
- explicitar o que ainda falta, por alvo, para um fecho honesto da fase.

Estado formal preservado:

- `F3 continua aberta`;
- `F3.11 alinhada no license-server live`;
- `readiness = GO condicional`;
- `campanha = GO condicional`;
- `DR-05 continua bloqueante para o fecho da F3`;
- `este documento nao fecha a F3`;
- `este documento existe apenas para ajudar a concluir a F3 com ordem`.

Nota operacional:

- este e um artefacto auxiliar canónico enquanto a F3 estiver aberta;
- depois do fecho real da F3, ele pode ser removido ou absorvido em
  documentacao permanente mais curta.

---

## 1. Como a pasta local esta organizada

### 1.1 Raiz do repositorio

Usar a raiz apenas para:

- `CORTEX.md` como SSOT operacional do projecto;
- `AGENTS.md` como regra de actuacao;
- documentos historicos `00-` a `16-` apenas como contexto preservado.

Nao usar a raiz para concluir a F3, excepto:

- `CORTEX.md`;
- `AGENTS.md`;
- consulta historica quando houver duvida documental explicita.

### 1.2 Directorio `docs/`

Este e o centro documental canónico.

Para fechar a F3, os documentos que importam estao sobretudo em:

- `docs/00-overview/`
- `docs/01-architecture/`
- `docs/05-runbooks/`
- `docs/tests/`
- `docs/10-license-server/`

### 1.3 Directorios de codigo

Leitura objectiva:

- `license-server/` descreve o contrato canónico esperado do servidor, mas
  nao prova o estado do live `192.168.100.244`;
- `package/`, `src/` e `webgui/` representam o produto pfSense, mas nao
  substituem evidencia recolhida no appliance real `192.168.100.254`;
- `scripts/license-validation/` contem helpers de apoio, mas nao substitui
  o runbook, o gate e a evidencia real da campanha.

### 1.4 Regra pratica

- `repo` define o contrato esperado;
- `live` define o estado observado;
- a F3 so fecha quando contrato e evidencia real ficam suficientemente
  alinhados nos cenarios obrigatorios.

---

## 2. Separacao obrigatoria dos alvos

| Alvo | Papel na F3 | O que prova | O que nao prova |
|------|-------------|-------------|-----------------|
| builder FreeBSD `192.168.100.12` | compilar pacote quando necessario | que o pacote pode ser gerado | nao prova licenciamento live |
| pfSense `192.168.100.254` | validar daemon, `.lic`, fingerprint e cenarios reais de appliance | comportamento real do cliente/appliance | nao prova schema/auth do license server |
| host live `192.168.100.244` | validar deploy, stack viva e superficie real do servidor | topologia e runtime observados | nao prova, sozinho, o estado do banco nem o inventario completo |
| PostgreSQL live no `192.168.100.244` | validar schema e pool real de licencas | tabelas, colunas, contagens, inventario | nao prova comportamento do appliance |
| credencial admin do painel | validar superficie administrativa real | login, acesso admin, listagens/download/revoke | nao prova contrato canónico de sessao se o live divergir |
| inventario `LIC-A` a `LIC-F` | reservar e mapear licencas reais por cenario | viabilidade pratica da campanha | nao fecha schema/auth por si so |

Regra:

- nunca usar um alvo para "provar" outro;
- nunca tratar o repo como substituto do live;
- nunca tratar o live como conforme ao repo sem prova tecnica objectiva.

---

## 3. Ordem minima de leitura para trabalhar na F3

### 3.1 Base obrigatoria

1. `CORTEX.md`
2. `docs/README.md`
3. `docs/02-roadmap/roadmap.md`
4. `docs/02-roadmap/backlog.md`
5. `docs/02-roadmap/checklist-mestre.md`
6. `docs/00-overview/document-classification.md`
7. `docs/00-overview/document-equivalence-map.md`

### 3.2 Trilha especifica da F3

1. `docs/00-overview/f3-11-start-here.md`
2. `docs/00-overview/f3-organizacao-local-e-fecho.md`
3. `docs/01-architecture/f3-11-execution-master-register.md`
4. `docs/01-architecture/f3-11-readiness-scorecard.md`
5. `docs/01-architecture/f3-11-drift-registry.md`
6. `docs/01-architecture/f3-runbook-proxima-campanha-real.md`
7. `docs/01-architecture/f3-gate-fechamento-validacao.md`
8. `docs/05-runbooks/f3-11-live-access-checklist.md`

### 3.3 Quando abrir o codigo

Abrir codigo apenas quando a pergunta for:

- o contrato esperado no repo e este mesmo?;
- a rota canónica existe ou nao?;
- a tabela esperada no repo e esta ou nao?;
- o helper/script de apoio faz exactamente o que o runbook diz?;
- a implementacao canónica do repo diverge ou nao do live observado?

---

## 4. O que fecha a F3 de verdade

O fecho real da F3 exige:

1. campanha real executada com os cenarios obrigatorios definidos na F3.8;
2. evidencias reais por `run_id`, com nomes padronizados e outputs brutos;
3. cenario obrigatorio fora de `PASS` a manter a F3 aberta;
4. ausencia de blocker estrutural que contamine a leitura dos cenarios;
5. relatorio final unico de campanha com conclusao binaria.

Nao fecha a F3:

- documentacao bonita;
- leitura optimista do repo;
- acessos parciais;
- login que responde mas nao respeita o contrato canónico;
- inventario presumido;
- "parece alinhado".

---

## 5. O que falta hoje para fechar a F3

### 5.1 pfSense / appliance

Ainda falta prova real de:

- snapshot/restore;
- offline/online controlado;
- controlo legitimo de NIC/UUID;
- clone/restore com reactivacao ou falha observada;
- cenarios locais pendentes da matriz F3.

### 5.2 host live `192.168.100.244`

Ja nao falta para a F3:

- SSH confirmado;
- stack viva observada com 4 containers;
- directorio activo observado em `/opt/layer7-license`.

Ainda permanece fora do escopo de fecho da F3:

- proveniencia exacta do deploy/revisao (`DR-07`), a tratar em governanca
  operacional/F7 sem bloquear os cenarios de licenciamento do appliance.

### 5.3 PostgreSQL live

Ja nao falta para a F3:

- base `layer7_license` observada;
- tabelas `licenses`, `activations_log`, `admin_sessions`,
  `admin_audit_log` e `admin_login_guards` presentes;
- drift de schema/admin (`DR-01`) reclassificado como resolvido no ambiente
  activo.

### 5.4 credencial admin / auth

Ja nao falta para a F3:

- login administrativo observado;
- `/api/auth/session` funcional no live;
- bridge Bearer administrativa funcional;
- `Origin` externo em `/api/auth/login` rejeitado com `403`.

### 5.5 inventario `LIC-A` a `LIC-F`

Ja nao falta para a F3:

- inventario real obtido do backend live com 4 licencas: IDs `5`, `6`, `7`
  e `8`;
- cobertura pratica dos estados `active`, `revoked`, expirada por data e
  reactivacao legitima no mesmo hardware.

Nota: a nomenclatura antiga `LIC-A` a `LIC-F` permanece util como linguagem
de campanha, mas nao deve reabrir burocracia quando o inventario real ja
esta provado e suficiente para a leitura actual da F3.

---

## 6. O que nao fazer para fechar a F3

- nao abrir trilha local paralela sem necessidade operacional real;
- nao subir stack local so porque ela existe no repo;
- nao usar builder para "provar" licenciamento;
- nao usar o repo para deduzir o inventario live;
- nao fechar drift por analogia;
- nao tratar login com JWT como se validasse sessao stateful;
- nao misturar appliance, host live, banco e credencial;
- nao chamar de progresso o que ainda e apenas preparacao.

---

## 7. Proximo fluxo seguro

Enquanto a F3 estiver aberta, o fluxo seguro e:

1. usar este mapa para escolher o documento certo;
2. manter `F3 aberta` ate evidencia real dos cenarios do appliance;
3. nao reabrir blockers ja saneados do live/admin/inventario;
4. executar apenas o bloco restante `DR-05` com control plane legitimo
   observado, snapshot/rollback e evidencias por `run_id`;
5. so depois consolidar relatorio final;
6. so depois decidir fecho da F3.

---

## 8. Destino deste artefacto depois da F3

Quando a F3 fechar de forma real:

- rever se este documento ainda acrescenta valor;
- se nao acrescentar, removê-lo;
- manter apenas a documentacao canónica permanente e funcional;
- evitar que "documento de ajuda" sobreviva sem necessidade.

---

## 9. Objectivo, impacto, risco, teste e rollback deste bloco

- **Objectivo:** dar um mapa unico da pasta local e do caminho real para
  concluir a F3 sem voltar ao caos operacional.
- **Impacto:** documental-operacional apenas; nenhum ficheiro de codigo foi
  alterado.
- **Risco:** baixo; o documento apenas reduz ambiguidade e nao muda gate,
  fase nem criterio de fecho.
- **Teste minimo:** coerencia cruzada com `docs/README.md`,
  `f3-11-start-here.md`, `f3-11-execution-master-register.md`,
  `f3-runbook-proxima-campanha-real.md` e `f3-gate-fechamento-validacao.md`.
- **Rollback:** `git revert <commit-deste-bloco>`.
