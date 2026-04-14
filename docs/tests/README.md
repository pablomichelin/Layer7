# Testes (referência)

## CI (GitHub Actions)

O workflow **[`.github/workflows/smoke-layer7d.yml`](../../.github/workflows/smoke-layer7d.yml)** em **push/PR** para `main` ou `master`:

- **`scripts/package/check-port-files.sh`** — `pkg-plist` alinhado a **`files/`**;
- instala toolchain no **Ubuntu**;
- executa **`scripts/package/smoke-layer7d.sh`** (compilação + `-t` + cenários **`-e -n`**).

**Limitações:** não compila o **port** `.pkg`, não corre no **pfSense**, não executa **pfctl**. Gate de pacote: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md).

## Local

- `sh scripts/package/smoke-layer7d.sh` (requer `cc` + `make`).
- `make -C src/layer7d check` após `make` no mesmo diretório.
- `cd license-server/backend && npm test` para smoke tests puros da trilha
  de sessao/Bearer do painel administrativo.
- `cd license-server/frontend && npm test` para smoke tests puros da camada
  `api.js` e do estado autenticado da SPA.
- `cd license-server/frontend && npm run build` para validar que a SPA ainda
  compila apos mudancas na trilha administrativa.
- `bash -n scripts/license-validation/export-license-evidence.sh` e
  `bash -n scripts/license-validation/export-appliance-evidence.sh` e
  `bash -n scripts/license-validation/export-live-preflight.sh` e
  `bash -n scripts/license-validation/export-schema-preflight.sh` e
  `bash -n scripts/license-validation/prepare-f3-preflight.sh` para smoke
  syntax dos helpers shell da campanha F3.

## Matriz de testes

[`test-matrix.md`](test-matrix.md) — 78 testes divididos por categoria
(build, instalação, daemon, config, policy engine, enforcement, GUI,
observabilidade, rollback e addendum de licenciamento/activação da F3).
Estado actual: 74 OK e 4 pendentes em appliance/lab. A F3.6 passa a decompor
esses 4 blocos pendentes numa matriz manual explicita de 13 cenarios,
pre-requisitos, comandos, evidencias minimas e criterios de
aprovacao/reprovacao, descrita em
[`../01-architecture/f3-validacao-manual-evidencias.md`](../01-architecture/f3-validacao-manual-evidencias.md).
A F3.7 operacionaliza essa matriz em
[`../01-architecture/f3-pack-operacional-validacao.md`](../01-architecture/f3-pack-operacional-validacao.md)
e acrescenta um template minimo em
[`templates/f3-scenario-evidence.md`](templates/f3-scenario-evidence.md). A
F3.8 acrescenta o gate oficial de fechamento em
[`../01-architecture/f3-gate-fechamento-validacao.md`](../01-architecture/f3-gate-fechamento-validacao.md)
e o relatorio final unico da campanha em
[`templates/f3-validation-campaign-report.md`](templates/f3-validation-campaign-report.md):
sem esse relatorio e sem todos os obrigatorios em `PASS`, a F3 nao fecha.
