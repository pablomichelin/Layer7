# Release — Deploy lab via GitHub Releases

Scripts para publicar o pacote Layer7 como artefato em **GitHub Releases** e instalar no pfSense de lab com um único comando.

**Objetivo:** distribuição de artefato `.txz` para lab/teste. **Não** substitui o Package Manager oficial do pfSense. **Não** usa repositório pkg alternativo.

---

## Pré-requisitos

### Builder (FreeBSD)

- FreeBSD com toolchain (`cc`, `make`)
- **git**
- **gh** (GitHub CLI) — [instalação](https://cli.github.com/manual/installation)
- **sha256** (comando nativo FreeBSD)
- **find**, **awk**

### Autenticação GitHub CLI

```sh
gh auth login
```

Para CI/automação: `gh auth status` deve indicar autenticado. Token com scope `repo` para criar releases.

### Repositório público para lab

O fluxo funciona com repositório público. O `install-lab.sh` baixa o `.txz` e `.sha256` via URLs públicas do GitHub Releases. Não é necessário configurar repositório pkg no pfSense.

---

## Como rodar deployz.sh

**No builder FreeBSD**, a partir da raiz do clone:

```sh
sh scripts/release/deployz.sh \
  --repo-owner pablomichelin \
  --repo-name pfsense-layer7 \
  --version 0.0.31
```

### Parâmetros

| Parâmetro     | Obrigatório | Descrição                                      |
|---------------|-------------|------------------------------------------------|
| `--repo-owner`| Sim         | Dono do repositório (ex: `pablomichelin`)      |
| `--repo-name` | Sim         | Nome do repo (ex: `pfsense-layer7`)             |
| `--version`   | Sim         | Versão (ex: `0.0.31`); tag será `v0.0.31`      |
| `--port-dir`  | Não         | Default: `package/pfSense-pkg-layer7`          |
| `--skip-tag`  | Não         | Não criar tag git                              |
| `--skip-push` | Não         | Não fazer `git push` nem `git push --tags`     |

### Exemplo com opções

```sh
# Só build + release, sem push (tag já existe)
sh scripts/release/deployz.sh \
  --repo-owner pablomichelin \
  --repo-name pfsense-layer7 \
  --version 0.0.31 \
  --skip-push
```

### Validações do script

- Working tree limpo (commit ou stash antes)
- Execução em FreeBSD
- Dependências presentes
- Template `install-lab.sh.template` existe

---

## Instalação no pfSense (comando único)

Em **Diagnostics > Command Prompt** do pfSense:

```sh
fetch -o /tmp/install-lab.sh https://github.com/pablomichelin/pfsense-layer7/releases/download/v0.0.31/install-lab.sh && sh /tmp/install-lab.sh
```

Substituir `pablomichelin`, `pfsense-layer7` e `v0.0.31` conforme o release.

O script baixa o `.txz`, valida checksum (se disponível), instala com `pkg add -f` e mostra os próximos passos.

---

## Limitações

- **Build real** do pacote deve ocorrer em **builder FreeBSD** ou runner self-hosted. GitHub Actions não oferece host FreeBSD nativo; automação no GitHub exige runner FreeBSD próprio.
- Não é repositório pkg oficial do pfSense — artefato para lab/teste apenas.
- Instalação manual: um comando, mas executado pelo operador.

---

## Rollback

No pfSense:

```sh
pkg delete pfSense-pkg-layer7
```

Para instalar versão anterior, usar o `install-lab.sh` do release desejado (ex: `v0.0.30`).

---

## Fleet: múltiplos firewalls

Para ambientes com vários firewalls (10, 50, 100+):

### Atualizar pacote (requer compilação prévia no builder)

```sh
# Compilar 1x no builder:
./scripts/release/update-ndpi.sh

# Distribuir para todos:
./scripts/release/fleet-update.sh -i firewalls.txt -p pfSense-pkg-layer7-0.1.0.pkg --parallel 4
```

### Atualizar regras custom (sem recompilação)

```sh
# Editar regras localmente:
vim layer7-protos.txt

# Sincronizar para todos + SIGHUP:
./scripts/release/fleet-protos-sync.sh -i firewalls.txt -f layer7-protos.txt
```

Ver [`docs/core/ndpi-update-strategy.md`](../../docs/core/ndpi-update-strategy.md) para detalhes.

---

## Arquivos

| Ficheiro                   | Descrição                                      |
|----------------------------|------------------------------------------------|
| `deployz.sh`               | Build + publish GitHub Release (builder)       |
| `install-lab.sh.template`  | Template do script de instalação (1 firewall)  |
| `update-ndpi.sh`           | Atualiza nDPI no builder e reconstrói pacote   |
| `fleet-update.sh`          | Distribui `.pkg` para N firewalls via SSH      |
| `fleet-protos-sync.sh`     | Sincroniza `protos.txt` para N firewalls       |
| `README.md`                | Este documento                                 |

Documentação formal: [`docs/04-package/deploy-github-lab.md`](../../docs/04-package/deploy-github-lab.md).
