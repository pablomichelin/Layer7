# Scripts de pacote / daemon

## `check-port-files.sh`

Confirma que **`package/pfSense-pkg-layer7/pkg-plist`** tem ficheiro em **`files/`** para cada linha (exceto `@…` e **`sbin/layer7d`**, gerado no build).

```sh
sh scripts/package/check-port-files.sh
```

Corre no **CI Linux** antes do smoke. O antigo `check-port-files.ps1` fica
preservado apenas como legado ate F6; nao e fluxo vigente do projecto.

## `smoke-layer7d.sh`

Valida **compilação** (`smoke`), **`-V`**, **`-t`** nos dois JSONs, **`-e -n`** em **monitor** (sem PF) e em **enforce** (linha `dry-run: pfctl`).

```sh
sh scripts/package/smoke-layer7d.sh
```

- Requer **`cc`** no PATH; compila com chamada directa ao compilador (lista de fontes inline).
- Em **FreeBSD** (builder canónico) compila o `src/layer7d/license.c` real e linka **`-lcrypto`** — comportamento idêntico ao port.
- Em **Linux** (`ubuntu-latest` no GitHub Actions), o script e apenas apoio de
  CI e nao substitui builder/appliance.
- Em **macOS**, o script bloqueia por defeito. O Mac e workspace de
  edicao/git/docs, nao ambiente de validacao tecnica do produto.
- O pacote instalável oficial (`.pkg`) é gerado com **`make package`** em `package/pfSense-pkg-layer7/` no builder — ver [`docs/04-package/validacao-lab.md`](../../docs/04-package/validacao-lab.md). Referências a `.txz` ficam apenas como legado histórico.
