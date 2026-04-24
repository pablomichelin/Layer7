# pfSense-pkg-layer7

Port **FreeBSD/pfSense** com binário **`layer7d`** (`main.c` + `config_parse.c`), XML/PHP/rc.d de suporte.

## Estado honesto

| Aspeto | Situação |
|--------|----------|
| Artefactos no repositório | Sim (Makefile, `files/`, plist, daemon C) |
| `make package` validado | **Não** até correr no builder — ver abaixo |
| `pkg add` / GUI / serviço no pfSense | **Não validado** — ver [`../../docs/04-package/validacao-lab.md`](../../docs/04-package/validacao-lab.md) |

## Estrutura (código)

| Caminho | Função |
|---------|--------|
| `../../src/layer7d/main.c`, `config_parse.c` | Fontes do daemon |
| `files/usr/local/pkg/layer7.xml` | Metadados de menu/URL esperados pelo pfSense *(registo real só após install)* |
| `files/.../info.xml` | Manifesto do pacote |
| `files/.../layer7_status.php` | Diagnóstico (`layer7d -t`) |
| `files/.../layer7_settings.php` | Globais JSON + **interfaces[]** (CSV) |
| `files/.../layer7_policies.php` | Toggle + adicionar + **editar** (`?edit=N`) + remover |
| `files/.../layer7_exceptions.php` | Toggle + adicionar + **editar** + remover exceção |
| `files/.../layer7_events.php` | Events (orientação syslog / futuro event-model) |
| `files/.../layer7_diagnostics.php` | Estado serviço, logs, comandos (Diagnostics) |
| `files/.../layer7.inc` | load/save/CSRF/HUP; geração PF (tabelas blacklist, anti-QUIC por interface, inject NAT **force_dns**); **`layer7_daemon_version()`** |
| `files/.../layer7.json.sample` | Amostra estática |
| `files/.../rc.d/layer7d` | Script de serviço (`layer7d_enable` default **NO**) |
| `files/pkg-install.in` / `pkg-deinstall.in` | Hooks padrão pfSense |

## Build (só em host FreeBSD com `make` + toolchain)

1. Repo completo com `src/layer7d/main.c` e `config_parse.c`.
2. `nDPI` instalada no builder com header em `/usr/local/include/ndpi/ndpi_api.h` e archive estático em `/usr/local/lib/libndpi.a`.
3. `sh scripts/package/check-port-files.sh` (alinhamento `pkg-plist` / `files/`).
4. Opcional: `sh scripts/package/smoke-layer7d.sh` (valida compile + `-t`).
5. `cd package/pfSense-pkg-layer7 && make package` → artefacto `.pkg` (nome inclui `PORTVERSION`). Localização típica: subpastas de `work/pkg/` — usar `find . -maxdepth 5 -name 'pfSense-pkg-layer7*.pkg'`.

O build falha de propósito se `libndpi.a` não existir, para evitar gerar pacote que exija instalar `libndpi.so` separadamente no pfSense.

## Instalação no pfSense (após build)

```sh
pkg add ./pfSense-pkg-layer7-<versão>.pkg
```

Comandos de verificação e critérios de aceite: **`docs/04-package/validacao-lab.md`**.

## Rollback

`pkg delete pfSense-pkg-layer7` e, se necessário, reinstalar o `.pkg`
anterior. Menções a `.txz` nesta área devem ser tratadas como legado.

Documentação geral: [`../../09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md`](../../09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md).  
Validação GUI: [`../../docs/package/gui-validation.md`](../../docs/package/gui-validation.md).
