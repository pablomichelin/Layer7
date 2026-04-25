# Handoff para um chat novo (contexto longo)

## Finalidade

Preservar continuidade do trabalho no Layer7 quando a conversa no Cursor se torna
**demasiado longa** para o modelo manter detalhe fiável. O SSOT do projecto
continua a ser o repositório (`CORTEX`, roadmap, backlog); este documento
define **quando** e **como** mudar para um chat novo e **o que colar** no
primeiro turno.

---

## Quando considerar que o chat "esta grande demais"

Nao existe um numero fixo visivel para o utilizador em todos os casos. Usa
**combinacao de sinais**:

- **Muitas trocas** (dezenas de mensagens) com decisões, caminhos de ficheiros
  e estados intermedios que ja nao cabem confortavelmente na memoria do turno.
- **Tarefas grandes concluidas em serie** (varios commits, multiplas areas)
  sem um unico resumo escrito no repositório.
- **Respostas mais genericas** ou repeticao de perguntas ja respondidas.
- **Lentidão** ou avisos de limite de contexto no produto (se aparecerem).

**Regra prática:** em trabalho continuo de varios dias, abre um **chat novo
por bloco** (ex.: por fase, por PR, ou por semana) e usa o prompt abaixo.

**O agente** pode sugerir handoff quando perceber que o resumo compacto do
estado actual deixa de ser suficiente para agir sem reler o repo; o
**utilizador** pode pedir handoff a qualquer momento.

---

## O que fazer (passos)

1. Garantir que o **último estado importante** esta no Git ou em `CORTEX` /
   backlog / changelog (commit ou decisão documental).
2. Abrir **novo chat** no mesmo workspace (Layer7).
3. **Colar** na primeira mensagem o bloco [Prompt de continuação](#prompt-de-continuacao-copiar-e-colar), preenchendo os campos entre `<>`.
4. Opcional: anexar ou mencionar ficheiros abertos relevantes.
5. No chat antigo, **nao** apagar; serve de arquivo informal.
6. Opcional: na **raiz do clone**, `sh scripts/package/check-port-files.sh` — confirma
   que as entradas do `pkg-plist` (excepto o binario gerado no build) existem em
   `package/pfSense-pkg-layer7/files/` (qualquer `sh` Unix; saida `OK`). O
   `sh scripts/package/smoke-layer7d.sh` e para **FreeBSD** (builder) ou **Linux**
   (CI com stub de licenciamento); em **macOS** o script **termina com codigo 2** de
   proposito (workspace de edicao/git/docs, nao gate de fase; ver
   `CORTEX.md` e [`docs/08-lab/README.md`](../08-lab/README.md)). O fecho **F3**
   (**DR-05** no appliance) e a evidencia **F4** (seccoes **10a** / **10b** / **11** de
   [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md)) exigem **pfSense**
   e, quando aplicavel, o builder.

---

## Prompt de continuação (copiar e colar)

Substitui apenas o que estiver entre `<>`; remove as linhas de instrução se
preferires uma mensagem mais curta.

```text
Contexto: continuo o desenvolvimento do Layer7 (pfSense CE) no repo local.
Acabei de abrir um chat novo porque o anterior ficou longo.

Le obrigatoriamente primeiro: CORTEX.md, depois docs/README.md, docs/02-roadmap/roadmap.md e docs/02-roadmap/backlog.md (e AGENTS.md se fores agente).

Estado que quero que assumes ate verificar no repo:
- Branch: <main / outro>
- Ultimo foco do chat anterior: <ex.: F4.1 package, DR-05 appliance, license-server, etc.>
- Pendencias explicitas: <lista curta>
- Proximo passo desejado: <um frase>

Regras: ordem de fases F0-F7; um subsistema critico por vez; documentacao junto da mudanca; ver docs/00-overview/handoff-chat-novo.md para politica de handoff.
Se a fase for F4: gates e roteiros de evidencia no inicio e nas seccoes 10a/10b/11 de docs/04-package/validacao-lab.md (sec. 11: force_dns/NAT, anti-QUIC opcional, cenario opcional multi-interface/VLAN para BG-011 / teste 6.7; checklist-mestre, test-matrix).

Executa o proximo passo seguro e diz o que alteraste.
```

---

## O que nunca substitui o repositório

- Este prompt **não** é fonte de verdade sobre fase ou prioridade: quem manda
  é `CORTEX.md` + roadmap + backlog após `git pull`.
- Depois de colar o prompt, o agente **deve** confirmar o estado lendo os
  ficheiros canónicos.

---

## Ligacao com outras politicas

- Continuidade geral: `CORTEX.md` — secção "Politica de continuidade entre chats".
- Hierarquia de leitura do agente: `AGENTS.md`.
- Sobreposicao CORTEX / este handoff / prompts antigos em `docs/07-prompts`:
  [`docs/00-overview/document-equivalence-map.md`](../00-overview/document-equivalence-map.md)
  (tabela *Sobreposicoes internas* e ponto 4 na lista *Conflitos documentais*).
- Fluxo de lab, builder e o papel do macOS: [`docs/08-lab/README.md`](../08-lab/README.md);
  gates e roteiros F4: inicio de [`docs/04-package/validacao-lab.md`](../04-package/validacao-lab.md).
