# pfSense-pkg-layer7

Port no estilo **FreeBSD/pfSense** com binário **`layer7d`** compilado a partir de `src/layer7d/main.c`, mais XML/PHP/rc.d de suporte.

## Estado honesto

| Aspeto | Situação |
|--------|----------|
| Artefactos no repositório | Sim (Makefile, `files/`, plist, daemon C) |
| `make package` validado | **Não** até correr no builder — ver abaixo |
| `pkg add` / GUI / serviço no pfSense | **Não validado** — ver [`../../docs/04-package/validacao-lab.md`](../../docs/04-package/validacao-lab.md) |

## Estrutura (código)

| Caminho | Função |
|---------|--------|
| `../../src/layer7d/main.c` | Fonte do daemon (obrigatória no build; path relativo ao port) |
| `files/usr/local/pkg/layer7.xml` | Metadados de menu/URL esperados pelo pfSense *(registo real só após install)* |
| `files/.../info.xml` | Manifesto do pacote |
| `files/.../layer7_status.php` | Página **informativa** (sem gravação de config) |
| `files/.../layer7.json.sample` | Amostra estática |
| `files/.../rc.d/layer7d` | Script de serviço (`layer7d_enable` default **NO**) |
| `files/pkg-install.in` / `pkg-deinstall.in` | Hooks padrão pfSense |

## Build (só em host FreeBSD com `make` + toolchain)

1. Clonar o **repositório completo** de forma que exista `package/pfSense-pkg-layer7/../../src/layer7d/main.c`.
2. `cd package/pfSense-pkg-layer7 && make package`.
3. O nome do `.txz` segue `PORTVERSION` no `Makefile` (ex.: `0.0.3`).

## Instalação no pfSense (após build)

```sh
pkg add ./pfSense-pkg-layer7-<versão>.txz
```

Comandos de verificação e critérios de aceite: **`docs/04-package/validacao-lab.md`**.

## Rollback

`pkg delete pfSense-pkg-layer7` e, se necessário, reinstalar `.txz` anterior.

Documentação geral: [`../../09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md`](../../09-EMPACOTAMENTO-PFSENSE-E-DISTRIBUICAO.md).
