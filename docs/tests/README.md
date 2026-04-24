# Testes (referência)

## CI (GitHub Actions)

O workflow **[`.github/workflows/smoke-layer7d.yml`](../../.github/workflows/smoke-layer7d.yml)** em **push/PR** para `main` ou `master`:

- **`scripts/package/check-port-files.sh`** — `pkg-plist` alinhado a **`files/`**;
- instala toolchain no **Ubuntu**;
- executa **`scripts/package/smoke-layer7d.sh`** (compilação + `-t` + cenários **`-e -n`**).

**Limitações:** não compila o **port** `.pkg`, não corre no **pfSense**, não executa **pfctl**. Gate de pacote: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (início: *Gates oficiais F4*; indice em [`../04-package/README.md`](../04-package/README.md); contexto de lab em [`../08-lab/README.md`](../08-lab/README.md)).

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
  `bash -n scripts/license-validation/init-f3-validation-campaign.sh` e
  `bash -n scripts/license-validation/prepare-f3-preflight.sh` e
  `bash -n scripts/license-validation/run-appliance-activation-scenario.sh` e
  `bash -n scripts/license-validation/run-pfsense-gui-license-flow.sh`
  para smoke syntax dos helpers shell da campanha F3.
- `scripts/license-validation/run-pfsense-gui-license-flow.sh --help` para
  validar a interface minima do helper GUI, incluindo o modo
  `--ssh-target` para GUI no loopback do appliance
  (`https://127.0.0.1:9999/`).

## Matriz de testes

[`test-matrix.md`](test-matrix.md) — 82 testes divididos por categoria
(build, instalação, daemon, config, policy engine, enforcement **inclui F4.3
`force_dns` / anchor NAT e anti-QUIC opcional (ponto 6.7 / sec. 11)**, **blacklists F4.2 (12.1–12.2)**,
GUI, observabilidade, rollback e
addendum de licenciamento/activação da F3). Estado actual: 74 OK e **8**
pendentes (1 em daemon F4.1 ponto 3.8; 2 em blacklists F4.2; 1 em enforcement F4.3; 4 no addendum F3.6).
Roteiros de evidência **F4** no appliance (10a / 10b / 11 ↔ matriz; **6.7** com
cenário multi-interface / VLAN opcional na secção **11**):
parágrafo *Gates oficiais F4* e tabela *Índice dos roteiros F4* em
[`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (início;
secção 10+).
A F3.6 passa a decompor
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
