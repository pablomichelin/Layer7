# Estrutura de Repositório para Cursor e GitHub

## Objetivo

Criar uma estrutura de repositório que ajude:
- desenvolvimento incremental;
- documentação viva;
- build reproduzível;
- organização por área;
- operação do Cursor sem bagunça.

---

## Estrutura sugerida

```text
layer7-pfsense/
├─ AGENTS.md
├─ CORTEX.md
├─ README.md
├─ LICENSE
├─ .gitignore
├─ docs/
│  ├─ 00-overview/
│  ├─ 01-architecture/
│  ├─ 02-roadmap/
│  ├─ 03-adr/
│  ├─ 04-tests/
│  ├─ 05-runbooks/
│  ├─ 06-releases/
│  ├─ 07-prompts/
│  └─ 08-lab/
├─ package/
│  └─ pfSense-pkg-layer7/
│     ├─ Makefile
│     ├─ pkg-descr
│     ├─ pkg-plist
│     └─ files/
├─ src/
│  ├─ layer7d/
│  ├─ policy/
│  ├─ classifier/
│  ├─ events/
│  ├─ runtime/
│  └─ common/
├─ webgui/
│  ├─ xml/
│  ├─ php/
│  └─ priv/
├─ scripts/
│  ├─ build/
│  ├─ release/
│  ├─ lab/
│  └─ diagnostics/
├─ tests/
│  ├─ functional/
│  ├─ traffic/
│  ├─ package/
│  ├─ lab/
│  └─ fixtures/
├─ samples/
│  ├─ config/
│  ├─ logs/
│  └─ policies/
└─ .github/
   ├─ workflows/
   ├─ ISSUE_TEMPLATE/
   └─ pull_request_template.md
```

---

## Função de cada área

## `AGENTS.md`
Instruções permanentes para IA no Cursor.

## `CORTEX.md`
SSOT operacional do projeto:
- fase atual;
- decisões;
- próximos passos;
- riscos;
- pendências.

## `docs/`
Documentação viva.

## `package/`
Arquivos de empacotamento do pacote pfSense.

## `src/`
Lógica principal do projeto.

## `webgui/`
GUI do pacote.

## `scripts/`
Automação local de build, release e lab.

## `tests/`
Testes funcionais e artefatos de validação.

## `.github/`
Higiene de colaboração, CI e templates.

---

## Regras de organização

1. Nenhum arquivo estratégico solto na raiz sem motivo.
2. Toda decisão importante vira ADR.
3. Toda fase relevante ganha documento próprio.
4. Nenhuma release sem changelog.
5. Nenhuma alteração grande sem atualizar `CORTEX.md`.

---

## Branching sugerido

### `main`
Somente estado estável e rastreável.

### `develop`
Integração controlada.

### `feature/*`
Blocos pequenos.

### `fix/*`
Correções pontuais.

### `release/*`
Preparação de release.

### `hotfix/*`
Somente após V1 em produção.

---

## Convenção de commits sugerida

- `docs:`
- `build:`
- `pkg:`
- `gui:`
- `core:`
- `policy:`
- `test:`
- `ops:`
- `refactor:`
- `fix:`

Exemplos:
- `core: add initial runtime state manager`
- `pkg: add pfSense package skeleton`
- `docs: define v1 policy precedence`
- `test: add reboot persistence checklist`

---

## Estrutura recomendada dentro de `docs/`

```text
docs/
├─ 00-overview/
│  ├─ product-charter.md
│  └─ scope-v1.md
├─ 01-architecture/
│  ├─ target-architecture.md
│  ├─ event-model.md
│  └─ policy-model.md
├─ 02-roadmap/
│  ├─ roadmap.md
│  ├─ milestones.md
│  └─ backlog.md
├─ 03-adr/
│  ├─ ADR-0001-choose-ndpi.md
│  ├─ ADR-0002-no-third-party-repo-in-v1.md
│  └─ ADR-0003-enforcement-via-pf-and-dns.md
├─ 04-tests/
│  ├─ test-plan.md
│  ├─ traffic-matrix.md
│  └─ release-checklist.md
├─ 05-runbooks/
│  ├─ install.md
│  ├─ rollback.md
│  └─ diagnostics.md
├─ 06-releases/
│  ├─ release-0.1.0.md
│  └─ changelog.md
├─ 07-prompts/
│  └─ cursor-prompts.md
└─ 08-lab/
   ├─ lab-topology.md
   └─ sample-captures.md
```

---

## Regra de ouro para trabalhar no Cursor

Sempre abrir o projeto com estes arquivos como contexto prioritário:
- `AGENTS.md`
- `CORTEX.md`
- `docs/00-overview/product-charter.md`
- `docs/01-architecture/target-architecture.md`
- `docs/02-roadmap/roadmap.md`

---

## Política de arquivos gerados

Nunca commitar:
- dumps de tráfego sensíveis;
- logs massivos sem sanitização;
- credenciais;
- captures reais com dados privados;
- artefatos temporários de build sem necessidade.

---

## Política para GitHub Releases

Cada release deve publicar:
- source tag;
- changelog;
- notas de compatibilidade;
- artefato `.txz` se houver build pronto;
- hash/checksum;
- instrução resumida de instalação.

