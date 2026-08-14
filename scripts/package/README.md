# Scripts de pacote / daemon

**Ordem canónica** para gerar o **`.pkg`** do port (raiz do clone, no
**builder FreeBSD**): `check-port-files.sh` → `smoke-layer7d.sh` →
`cd package/pfSense-pkg-layer7 && make package`. Detalhe: [`docs/08-lab/builder-freebsd.md`](../../docs/08-lab/builder-freebsd.md).

## `check-port-files.sh`

Confirma que **`package/pfSense-pkg-layer7/pkg-plist`** tem ficheiro em **`files/`** para cada linha (exceto `@…` e **`sbin/layer7d`**, gerado no build).

```sh
sh scripts/package/check-port-files.sh
```

Corre no **CI Linux** antes do smoke. O antigo `check-port-files.ps1` fica
preservado apenas como legado ate F6; nao e fluxo vigente do projecto.

## `verify-prod-pubkey.sh`

Compara a pubkey Ed25519 embutida em `src/layer7d/license.c` **e** o PEM
do port (`license-signing-public-key.pem`) com o SoT fora do git no
builder (`/root/layer7-build-secrets/`). Usar **antes** de `make package`
(passo 30.2 / GA1.8 / P3-6). **FAIL** se C, PEM ou SoT divergirem
(PEM ausente, inválido ou outra Ed25519). A validação C vs SoT
mantém-se.

Selftest local com fixture (`L7_PROD_PUBKEY_HEX_FILE` + PEM
temporário; sem ler o SoT do builder):

```sh
sh tests/functional/test_verify_prod_pubkey.sh
```

```sh
sh scripts/package/verify-prod-pubkey.sh
```

## `audit-release-dev-bypass.sh`

Auditoria de um **`.pkg` publicado** quanto a marcadores do bypass de
desenvolvimento (A-01 / `is_dev_key`) — passo **30.3** / GA1.6 / GA1.7.

```sh
sh scripts/package/audit-release-dev-bypass.sh --selftest
sh scripts/package/audit-release-dev-bypass.sh --inventory path/to.pkg
sh scripts/package/audit-release-dev-bypass.sh --gate path/to.pkg   # exit 1 se marcadores
sh scripts/package/audit-release-dev-bypass.sh --check-source       # residual no fonte
```

`--gate` falha se o binário contiver a string `development key` ou o símbolo
`is_dev_key`. `--check-source` exige que `is_dev_key()` exista só sob
`#ifdef L7_DEV_BUILD` (passo **30.4** / BG-114).

## `test-prod-no-dev-bypass.sh`

Gate GA2.1–2.3 / passo **30.4**: port sem `-DL7_DEV_BUILD`; runtime FreeBSD com
pubkey all-zeros em build de produção ⇒ licença inválida; controlo com
`L7_DEV_BUILD` confirma que o bypass lab ainda funciona.

```sh
sh scripts/package/test-prod-no-dev-bypass.sh   # runtime completo no builder
```

## `test-prod-strip.sh`

Gate GA2.4 / GA2.5 / GA2.11 / passo **30.5**: `.pkg` com `layer7d` strippado;
`nm`/`strings` sem `is_dev_key` / `layer7_license_check`; em FreeBSD corre
`-t` e `--fingerprint`.

```sh
sh scripts/package/test-prod-strip.sh --gate path/to.pkg
```

## `smoke-layer7d.sh`

Valida **compilação** (`smoke`), **`-V`**, **`-t`** nos dois JSONs, **`-e -n`**
em **monitor** (sem PF) e em **enforce** com destino explícito
(`-d 203.0.113.10`, linha `dry-run: pfctl`).

```sh
sh scripts/package/smoke-layer7d.sh
```

- Requer **`cc`** no PATH; compila com chamada directa ao compilador (lista de fontes inline).
- Em **FreeBSD** (builder canónico) compila o `src/layer7d/license.c` real e linka **`-lcrypto`** — comportamento idêntico ao port.
- Em **Linux** (`ubuntu-latest` no GitHub Actions), o script e apenas apoio de
  CI e nao substitui builder/appliance.
- Em **macOS**, o script bloqueia por defeito. O Mac e workspace de
  edicao/git/docs, nao ambiente de validacao tecnica do produto.
- O pacote instalável oficial (`.pkg`) é gerado com **`make package`** em `package/pfSense-pkg-layer7/` no builder — ver [`docs/04-package/validacao-lab.md`](../../docs/04-package/validacao-lab.md) (início: *Gates oficiais F4*; secções **10a** / **10b** / **11**). Referências a `.txz` ficam apenas como legado histórico.
