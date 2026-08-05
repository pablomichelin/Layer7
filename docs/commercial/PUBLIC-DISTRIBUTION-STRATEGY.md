# Estratégia de distribuição pública vs. código privado

**Data:** 2026-08-04  
**Estado:** Aprovado para execução

---

## Situação actual

| Repositório | Visibilidade | Conteúdo |
|-------------|--------------|----------|
| `pablomichelin/pfsense-layer7` | **Privado** | Código-fonte, docs internas, SSOT de desenvolvimento |
| `pablomichelin/Layer7` | **Público** | Espelhava o repositório completo (código + receita) |

O remote `layer7` no Mac local aponta para `pablomichelin/Layer7`. O remote
`origin` aponta para `pfsense-layer7` (privado).

**Problema:** o repositório público `Layer7` expunha código-fonte, ADRs,
license server, planos de enforcement e documentação interna.

---

## Decisão recomendada (opção A — preferida)

**Não tornar `Layer7` privado.** Em vez disso:

1. Manter **`pfsense-layer7` privado** como SSOT de desenvolvimento (já está).
2. **Substituir o conteúdo** de `pablomichelin/Layer7` por uma árvore mínima
   (`public-dist/`) — só docs comerciais, MANUAL-INSTALL, changelog e scripts
   de instalação.
3. **Manter GitHub Releases** no mesmo repo `Layer7` — **sem quebrar URLs**
   existentes (`releases/download/v…/install.sh`, `.pkg`, etc.).
4. **Deixar de fazer push** do código completo para o remote `layer7`.

### Porquê não tornar `Layer7` privado?

Se `Layer7` passar a privado, **todas as releases deixam de ser públicas**
para quem não tem acesso ao repo. Clientes, lab e o botão «Verificar
actualização» da GUI deixariam de funcionar sem autenticação GitHub.

Para ter deploy público com repo privado, seria necessário:

- Criar um **segundo repo público** (ex. `Layer7-releases`);
- Migrar todas as releases;
- Actualizar `install.sh`, `MANUAL-INSTALL.md`, GUI updater e links históricos.

Isso é possível, mas é **migração com breaking change**. A opção A evita isso.

---

## Opção B (alternativa — só se quiseres repo privado com outro nome)

1. Tornar `pablomichelin/Layer7` **privado**.
2. Criar `pablomichelin/Layer7-releases` **público** (árvore mínima).
3. Republicar releases recentes no novo repo.
4. Actualizar `REPO_NAME` em `scripts/release/install.sh` e todo o
   `MANUAL-INSTALL.md` para o novo URL.
5. Publicar nova versão do pacote com URLs actualizadas.

**Custo:** quebra links antigos até clientes actualizarem.

---

## O que fica PÚBLICO (`Layer7` — opção A)

```
README.md
LICENSE
docs/commercial/          # evaluation pack, product overview, install guides
scripts/release/install.sh
scripts/release/uninstall.sh
scripts/release/common.sh
scripts/release/verify-release.sh
```

**Não publicar:** `MANUAL-INSTALL.md`, `MANUAL-USO-LICENCAS.md`, `CHANGELOG.md`
(com detalhes de license server), URLs de activação, nem qualquer documentação
de como funciona o licenciamento ou onde está o license server.

Mais **GitHub Releases** (assets por tag): `.pkg`, `.sha256`, `install.sh`,
manifesto, assinatura, chave pública.

## O que fica PRIVADO (`pfsense-layer7` — local + origin)

- `src/layer7d/` — daemon
- `package/` — port pfSense
- `license-server/` — backend de licenças
- `docs/03-adr/`, `docs/09-blocking/`, `docs/core/`, `CORTEX.md`, etc.
- Testes, runbooks internos, roadmap, backlog

---

## Pacote de avaliação (novo)

Ficheiros para enviar a prospects **sem NDA**:

- `docs/commercial/LAYER7-EVALUATION-PACK-EN.md`
- `docs/commercial/LAYER7-EVALUATION-PACK-PT.md`
- `docs/commercial/LAYER7-PRODUCT-OVERVIEW-EN.md` / `-PT.md`

---

## Execução

Script: `scripts/release/sync-public-distribution.sh`

```bash
# Simular (não envia):
./scripts/release/sync-public-distribution.sh --dry-run

# Aplicar no worktree e push para Layer7:
./scripts/release/sync-public-distribution.sh --push
```

**Atenção:** o push reescreve `main` do `Layer7` com árvore mínima. Releases
já publicadas no GitHub **permanecem** na aba Releases.

**Histórico git público:** commits antigos no `Layer7` ainda contêm o código
até serem removidos com `git filter-repo` ou repo novo. Para apagar histórico
público de forma definitiva, pedir limpeza de histórico num segundo passo.

---

## Remotes no Mac local

| Remote | Repo | Uso |
|--------|------|-----|
| `origin` | `pfsense-layer7` (privado) | `git push origin` — desenvolvimento |
| `layer7` | `Layer7` (público mínimo) | `sync-public-distribution.sh --push` apenas |

**Regra:** `git push layer7` manual do branch `main` completo **deixa de ser
feito**.
