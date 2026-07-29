# Testes (referência)

## CI (GitHub Actions)

O workflow **[`.github/workflows/smoke-layer7d.yml`](../../.github/workflows/smoke-layer7d.yml)** em **push/PR** para `main` ou `master`:

- **`scripts/package/check-port-files.sh`** — `pkg-plist` alinhado a **`files/`**;
- instala toolchain no **Ubuntu**;
- executa **`scripts/package/smoke-layer7d.sh`** (compilação + `-t` + cenários **`-e -n`**).

**Limitações:** não compila o **port** `.pkg`, não corre no **pfSense**, não executa **pfctl**, não cobre os roteiros **10a** / **10b** / **11** no appliance. Gate de pacote: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (início: *Gates oficiais F4*; indice em [`../04-package/README.md`](../04-package/README.md); contexto de lab em [`../08-lab/README.md`](../08-lab/README.md)).

## Local

- `sh scripts/package/smoke-layer7d.sh` (requer `cc`; compila também
  `log_store.c`, como o port real, e usa `-d` para cobrir enforcement por
  destino).
- `sh tests/run-local.sh` — suite F5 mínima (ver tabela abaixo) + lint PHP/sh
  do pacote.

### Testes em `tests/run-local.sh` (Caminho B / pós-revisão)

| Ficheiro | Bloco | Cobertura |
|----------|-------|-----------|
| `tests/functional/test_allowlist.c` | Fase 1 | allowlist, rejeição `/0`, seed |
| `tests/functional/test_config_parse.c` | A3 / E0 | parse JSON (`sni_inspection`, `enforcement_model`) |
| `tests/functional/test_capture_flow_key.c` | BG-055 | hash bidireccional: ida/volta no mesmo fluxo nDPI |
| `tests/functional/test_log_store.c` | BG-054 | rotação por tamanho e limite de cópias |
| `tests/functional/test_policy_decide.c` | E1/E5/BG-056 | decisão, escopo, app/host=`pdst`, quarentena=`psrc`, allow preserva índice |
| `tests/functional/test_enforce_scoped.c` | E3/BG-056 | runtime PF (`pdst_N` / `psrc_N` / `pallow_N`), exception block e cache TTL |
| `tests/functional/test_bl_src_match.c` | pós-REV-007 | `except_ips` em `l7_bl_rule_matches_src()` |
| `tests/functional/test_scoped_pf_inc.php` | E2/E4/BG-056 | regras PF scoped, quarentena, allow por tag sem `pass quick` e flush |
| `tests/functional/test_interface_normalization.php` | BG-053 | `lan`/`optN` → interface real em todos os consumidores |
| `tests/functional/test_logging_reports.php` | BG-054 | parser de auditoria, sem dupla contagem e cursor através da rotação |
| `tests/unit/test_rc_pidfile.sh` | BG-053 | pidfile `daemon(8)` sem newline |
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

[`test-matrix.md`](test-matrix.md) — 108 testes divididos por categoria
(build, instalação, daemon, config, policy engine, enforcement **inclui F4.3
`force_dns` / anchor NAT e anti-QUIC opcional (ponto 6.7 / sec. 11)**, **blacklists F4.2 (12.1–12.2)**,
GUI, observabilidade, rollback e
addendum de licenciamento/activação da F3, estabilização `_25`, logs `_26`,
correcções `_27` e allow PF seguro `_28`).
Estado actual antes do build final `_28`: 89 OK e **19** pendentes. Os testes
C/PHP/shell do `_28` estão PASS; build, parser PF e gates no appliance
continuam explicitamente pendentes.
Roteiros de evidência **F4** no appliance (10a / 10b / 11 ↔ matriz; **6.7** com
anti-QUIC opcional e cenário multi-interface / VLAN na secção **11**):
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
