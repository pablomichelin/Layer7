# Testes (referência)

## CI (GitHub Actions)

O workflow **[`.github/workflows/smoke-layer7d.yml`](../../.github/workflows/smoke-layer7d.yml)** em **push/PR** para `main` ou `master`:

- **`scripts/package/check-port-files.sh`** — `pkg-plist` alinhado a **`files/`**;
- instala toolchain no **Ubuntu**;
- executa **`scripts/package/smoke-layer7d.sh`** (compilação completa, incluindo
  módulos Identity, + `-t` + cenários **`-e -n`**).

**Limitações:** não compila o **port** `.pkg`, não corre no **pfSense**, não executa **pfctl**, não cobre os roteiros **10a** / **10b** / **11** no appliance. Gate de pacote: [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md). Matriz de regressão: [`layer7-regression-matrix.md`](layer7-regression-matrix.md) (R-01..R-21; gates G0–G7 em [`../09-blocking/plano-gates-producao.md`](../09-blocking/plano-gates-producao.md)).

## Local

- **Harness MITM activação (hang legado + timeout fix + CA-as-peer; `.254` só RO):**
  [`tests/harness/mitm-activate-hang/README.md`](../../tests/harness/mitm-activate-hang/README.md) —
  `run-local-hang.sh`, `run-local-timeout-fix.sh`,
  `run-local-tls-ca-peer.sh`, `run-local-tls-leaf-fix.sh` (sem escrita na `.254`).
- **Regressões próximas ao código (`1.9.46`):**
  - `make -C src/layer7-tlsproxy test-regress` — leaf D1 + política sem bypass
  - `php package/pfSense-pkg-layer7/tests/test_mitm_regress.php` — scope/rdr/anti-QUIC/lifecycle/`filter_configure_safe`
  - Gate C lab: [`evidence/20260809T210753Z-phaseBD-d1-254/`](evidence/20260809T210753Z-phaseBD-d1-254/) (Edge sem `--disable-quic`)
  - GO teste controlado `.254`: [`evidence/20260809T215442Z-phaseBD-d1-254/`](evidence/20260809T215442Z-phaseBD-d1-254/) (`quic_mode=block`; rollback OK; permanente NO-GO)
- **Control-plane timeout:** `php tests/functional/test_ctrl_exec_timeout.php`.
- **Checklist F5 repetível (Onda G 8.2):** [`f5-smoke-checklist.md`](f5-smoke-checklist.md) —
  `sh tests/lab/run-f5-smoke-checklist.sh` (local + builder + appliance).
- `sh tests/lab/run-im9-20.31-identity-mesh.sh` — malha IM9 / 20.31 (Identity
  OFF + ADR-0029 GUI; não activa módulos). Evidência exemplo:
  `docs/tests/evidence/20260808T135500Z-im9-20.31-identity-mesh/`.
- `sh tests/lab/run-gi7-identity-policies.sh` — checklist lab GI7 (AD residual).
- `sh scripts/package/smoke-layer7d.sh` (requer `cc`; compila também
  `log_store.c`, como o port real, e usa `-d` para cobrir enforcement por
  destino).
- `sh tests/run-local.sh` — suite F5 mínima (ver tabela abaixo) + lint PHP/sh
  do pacote.

### Testes em `tests/run-local.sh` (Caminho B / pós-revisão)

| Ficheiro | Bloco | Cobertura |
|----------|-------|-----------|
| `tests/functional/test_allowlist.c` | Fase 1 | allowlist, rejeição `/0`, seed |
| `tests/functional/test_identity_map.c` | IM3 / 20.12–20.14 | API + multi_user + save/load snap + stale skip |
| `tests/functional/test_config_parse.c` | A3 / E0 / FP-015 | parse JSON; fragilidade `enabled` em policies (#12–15) |
| `tests/unit/test_flush_coverage.sh` | BG-061 | contract flush exc_allow, bl_apply, pkg-deinstall |
| `tests/unit/test_rc_pidfile.sh` | BG-053 | pidfile `daemon(8)` sem newline |
| `tests/functional/test_capture_flow_key.c` | BG-055/BG-058/BG-059 | hash bidireccional, probe sem duplicação e finalização nDPI sem aceitar parcial |
| `tests/functional/test_log_store.c` | BG-054 | rotação por tamanho e limite de cópias |
| `tests/functional/test_policy_decide.c` | E1/E5/BG-056 | decisão, escopo, app/host=`pdst`, quarentena=`psrc`, allow preserva índice |
| `tests/functional/test_enforce_scoped.c` | E3/BG-056 | runtime PF (`pdst_N` / `psrc_N` / `pallow_N`), exception block e cache TTL |
| `tests/unit/test_sinkhole_local_guard.sh` | BG-105 | destino local do portal/sinkhole é filtrado antes da decisão; DNS bloqueado mantém auditoria `outcome=sinkhole` |
| `tests/functional/test_bl_src_match.c` | pós-REV-007 | `except_ips` em `l7_bl_rule_matches_src()` |
| `tests/functional/test_scoped_pf_inc.php` | E2/E4/BG-056 | regras PF scoped, quarentena, allow por tag sem `pass quick` e flush |
| `tests/functional/test_interface_normalization.php` | BG-053 | `lan`/`optN` → interface real em todos os consumidores |
| `tests/functional/test_logging_reports.php` | BG-054 | parser de auditoria, sem dupla contagem e cursor através da rotação |

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

[`test-matrix.md`](test-matrix.md) — 120 testes divididos por categoria
(build, instalação, daemon, config, policy engine, enforcement **inclui F4.3
`force_dns` / anchor NAT e anti-QUIC opcional (ponto 6.7 / sec. 11)**, **blacklists F4.2 (12.1–12.2)**,
GUI, observabilidade, rollback e
addendum de licenciamento/activação da F3, estabilização `_25`, logs `_26`,
correcções `_27`, allow PF seguro `_28`, parser anti-QUIC `_29` e captura
resiliente `_30` e finalização nDPI `_31`).
Estado actual após build `_31`: 99 OK e **21** pendentes. A sintaxe
corrigida passa no parser PF read-only e o pacote extraído passou no builder
FreeBSD 15 (`SHA256 bea385dd…01840`); as regressões FP-019 passam localmente,
e o pacote `_30` passou no builder (`SHA256 3a54c667…e9b40`). FP-020 e o
pacote `_31` passaram no builder (`SHA256 dc5118dd…453e33`). Gates instalados
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
