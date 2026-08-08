# Governação Documental — Portal de Licenças

## Finalidade

Definir as regras **invioláveis** de documentação, versionamento visual e
acompanhamento desta trilha. Todo agente, maintainer ou chat deve seguir
este ficheiro antes de alterar `license-server/` ou o deploy em `244`.

## 1. Hierarquia de verdade

Ordem de prevalência (portal):

1. `CORTEX.md` (global Layer7)
2. `docs/README.md` + roadmap/backlog/checklist mestre (global)
3. `docs/10-license-server/README.md`
4. `docs/10-license-server/portal/README.md` + **este** `GOVERNANCE.md`
5. `VERSION.md` / `CHANGELOG.md` / `ESTADO.md`
6. Plano activo em `planos/`
7. `MANUAL-USO-LICENCAS.md` / arquitectura F2–F3 / ADRs de licenciamento
8. Código em `license-server/`

**Conflito:** se um plano do portal contradisser CORTEX/ADR/F3, vence o
documento global; o plano do portal deve ser emendado.

## 2. Versionamento visual (obrigatório)

O portal tem versão **própria**, independente do pacote pfSense
(`PORTVERSION` / GitHub Release `v1.9.x`).

| Conceito | Onde vive | Exemplo |
|----------|-----------|---------|
| Versão visual do portal | `VERSION.md` + `CHANGELOG.md` | `0.0.1`, `0.1.0`, `1.0.0` |
| Versão do pacote Layer7 | `MANUAL-INSTALL.md` / CORTEX | `1.9.30` |
| Deploy live | `ESTADO.md` | imagem Docker / data |

### Política SemVer do portal

- **0.0.x** — baseline e correcções mínimas enquanto o portal ainda é
  “CRUD mínimo”
- **0.x.0** — blocos de completude (P0/P1 do plano de melhoria), ainda
  sem escala comercial
- **1.0.0** — portal considerado “completo para operador único” (critérios
  em `OBJECTIVOS.md`)
- **Major ≥ 2** — mudanças incompatíveis de UX/API admin ou abertura de
  escala (só com GO explícito)

### Quando obrigar bump

| Mudança | Bump mínimo |
|---------|-------------|
| Só docs da trilha portal | sem bump (registar em `ACOES.md`) |
| Fix UI/bug sem feature | `PATCH` |
| Feature no painel (filtro, rebind UI, auditoria…) | `MINOR` |
| Redesign/quebradura de fluxos admin | `MAJOR` (GO) |

A versão visual deve aparecer de forma **visível** no painel (sidebar ou
rodapé) a partir da primeira release pós-baseline que mexa no frontend
(meta do plano P0).

## 3. Ficheiros obrigatórios a manter vivos

| Ficheiro | Actualizar quando |
|----------|-------------------|
| `VERSION.md` | bump de versão |
| `CHANGELOG.md` | toda versão / bloco entregue |
| `ESTADO.md` | deploy live, inventário relevante, drift |
| `ACOES.md` | **cada** bloco executado (mesmo só docs) |
| `IDEIAS.md` | ideia aceite, diferida ou rejeitada |
| `OBJECTIVOS.md` | mudança de objectivo de produto do portal |
| `planos/*` | abertura, progresso ou fecho de plano |
| `checklist.md` | gates do bloco actual |
| `historico/*` | fecho de baseline ou campanha |

## 4. Fluxo de entrega de um bloco

1. Declarar objectivo, impacto, risco, teste, rollback (padrão AGENTS).
2. Trabalhar **o próximo bloco na ordem do plano activo** (não saltar
   funções; P0→P1a→P1b→P1c→P1d→P1e).
3. Código em `license-server/` + docs desta pasta **no mesmo bloco**.
4. Actualizar `ACOES.md` com data, versão-alvo, ficheiros, teste, resultado.
5. Se subir versão: `VERSION.md` + `CHANGELOG.md` + versão na UI.
6. Deploy em `244` só quando o bloco o exigir; actualizar `ESTADO.md`
   (inclui `restart nginx` após recreate web/api).
7. Commit local quando o utilizador pedir; push para `origin` privado.

**Proibido nesta trilha (até GO):**

- portal cliente / MSP / self-service
- multi-admin com papéis de vendas
- faturação / billing
- mover/apagar/renomear docs fora da pasta `portal/` sem F6 / GO

**Permitido e esperado:**

- completar CRUD e ciclo de vida para operador único
- rebind governado, auditoria UI, check-in UI, SKU, renovação
- rebuild/redeploy frontend/API do compose isolado (sem tocar Zabbix/Grafana/etc.)

## 5. Planos

- Todo trabalho não trivial abre ou actualiza um ficheiro em `planos/`.
- Um plano tem estados: `RASCUNHO` | `ACTIVO` | `PAUSADO` | `CONCLUIDO` | `CANCELADO`.
- Só **um** plano de execução principal do portal deve estar `ACTIVO`
  (outros podem ser rascunho/futuro).
- Ao concluir: mover resumo para `historico/` e marcar plano `CONCLUIDO`
  (não apagar o ficheiro do plano).

## 6. Ideias

Em `IDEIAS.md` cada ideia tem:

- ID (`IDEA-XXX`)
- estado: `ACEITE` | `FUTURA` | `DIFERIDA` | `REJEITADA`
- origem (chat/data)
- ligação a plano ou versão, se existir

Ideias de **escala/vendas** ficam `FUTURA` ou `DIFERIDA` até GO explícito.

## 7. Segurança operacional do host `244`

Herdado do plano F2 / `PLANO-LICENSE-SERVER.md`:

- **Não tocar** Apache/Zabbix/Grafana/MySQL/monitor-pfsense
- Rede Docker isolada `layer7-license-net`
- Canal público só `443` via ISPConfig; origin `8445` privado

## 8. Relação com o pacote Layer7

Alterações **só no portal** não exigem bump de `PORTVERSION`.

Se a mudança exigir contrato novo no daemon (activate/check-in/SKU),
tratar como bloco conjunto: portal + pacote + manuais + CORTEX, com gates
próprios.

## 9. Handoff entre chats

Prompt mínimo:

```text
Continuar trilha portal license-server.
Ler: CORTEX.md → docs/10-license-server/portal/README.md →
GOVERNANCE.md → ESTADO.md → plano ACTIVO em portal/planos/.
Versão visual actual em VERSION.md.
Não abrir escala/MSP sem GO.
```

Ver também: `docs/00-overview/handoff-chat-novo.md`.
