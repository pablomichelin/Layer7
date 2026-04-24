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

- Requer **`cc`** no PATH; compila com chamada directa ao compilador (lista de fontes inline).
- Em **FreeBSD** (builder canónico) compila o `src/layer7d/license.c` real e linka **`-lcrypto`** — comportamento idêntico ao port.
- Em **Linux** (incl. `ubuntu-latest` no GitHub Actions) e em **Darwin (macOS)**, o script gera um **stub local de licenciamento** em `$SMOKE_VER/license_smoke_stub.c` (sempre `dev_mode=1` / `valid=1`, equivalente à chave Ed25519 zerada) e dispensa `-lcrypto`. Isto é necessário porque `license.c` usa headers e syscalls **FreeBSD-only** (`net/if_dl.h`, `sysctlbyname kern.hostuuid`, `sockaddr_dl`, `IFT_ETHER`, …). O stub **não** é instalado no pacote e **só** roda no smoke; o smoke **canónico** continua a ser no **builder FreeBSD**.
- O pacote instalável oficial (`.pkg`) é gerado com **`make package`** em `package/pfSense-pkg-layer7/` no builder — ver [`docs/04-package/validacao-lab.md`](../../docs/04-package/validacao-lab.md). Referências a `.txz` ficam apenas como legado histórico.
