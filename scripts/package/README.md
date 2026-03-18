# Scripts de pacote / daemon

## `check-port-files.sh` / `check-port-files.ps1`

Confirma que **`package/pfSense-pkg-layer7/pkg-plist`** tem ficheiro em **`files/`** para cada linha (exceto `@…` e **`sbin/layer7d`**, gerado no build).

```sh
sh scripts/package/check-port-files.sh
```

Em **Windows** (PowerShell):

```powershell
.\scripts\package\check-port-files.ps1
```

Corre no **CI** antes do smoke.

## `smoke-layer7d.sh`

Valida **compilação** (`smoke`), **`-V`**, **`-t`** nos dois JSONs, **`-e -n`** em **monitor** (sem PF) e em **enforce** (linha `dry-run: pfctl`).

```sh
sh scripts/package/smoke-layer7d.sh
```

- Requer **`cc`** e **`make`** no PATH; compila com **`src/layer7d/Makefile`** (`OUT=layer7d-smoke`, `version.str` temporário `"smoke"`).
- O pacote instalável (`.txz`) só é gerado com **`make package`** em `package/pfSense-pkg-layer7/` no builder — ver [`docs/04-package/validacao-lab.md`](../../docs/04-package/validacao-lab.md).
