# F4 - Plano de implementacao (package / daemon / blacklists)

## Finalidade

Este documento organiza a **F4 — Confiabilidade package/daemon/blacklists** em
subfases pequenas, com gates, risco, teste minimo e rollback, alinhado ao
[`roadmap.md`](roadmap.md) e ao [backlog](backlog.md).

Referencias obrigatorias:

- [`../10-license-server/MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md)
- [`../11-blacklists/PLANO-BLACKLISTS-UT1.md`](../11-blacklists/PLANO-BLACKLISTS-UT1.md)
- [`../11-blacklists/DIRETRIZES-IMPLEMENTACAO.md`](../11-blacklists/DIRETRIZES-IMPLEMENTACAO.md)
- [`../01-architecture/f1-arquitetura-de-confianca.md`](../01-architecture/f1-arquitetura-de-confianca.md) (F1.3 blacklists, F1.4 fallback)
- [`../01-architecture/target-architecture.md`](../01-architecture/target-architecture.md)
- [`../05-daemon/README.md`](../05-daemon/README.md) e [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md)
- `package/pfSense-pkg-layer7/`, `src/layer7d/`, scripts de update de blacklists em `scripts/`

---

## 0. Politica de paralelismo com a F3

O roadmap declara **dependencia** da F3 concluida. Em **2026-04-24** ficou
aceite no projecto a seguinte **regra de execução pragmática**:

- A F3 continua **aberta** enquanto o **DR-05** (cenários mutáveis no
  appliance) e o **relatório** de campanha não cumprirem o gate F3.8.
- A **F4.1+** pode avançar em **trabalho de código e documentação** no
  pacote, no daemon e na trilha de blacklists, **sem declarar a F3 fechada** e
  **sem** alterar o contrato de licenciamento (comportamento do `.lic`,
  `layer7d` face à licença) salvo bloco aprovado e documentado.
- Cada entrega F4 declara: objectivo, impacto, risco, teste, rollback e docs
  afectadas, como em qualquer fase técnica.

Isto evita parar melhoria operacional de runtime na espera de janela de
laboratório, mantendo o **fecho formal da F3** condicionado à evidência real.

---

## 1. Pré-requisitos técnicos mínimos

- F0, F1, F2 concluidas; cadeia de confiança e fallback F1.4 conhecidos.
- F3: contratos documentais (F3.1–F3.5) e matriz de validação (F3.6–F3.8)
  disponíveis; **ou** excepção explícita acima para arranque da F4.1.
- Nenhuma reorganização física do repositório (isso é **F6**).

---

## 2. Mapeamento backlog → F4

| ID | Tema F4 | Subfase sugerida |
|----|---------|------------------|
| BG-009 | Confiabilidade de package/daemon (boot, reload, upgrade, rollback) | F4.1 |
| BG-010 | Trilha blacklists UT1: download, cron, reload, fallback, except, forcing DNS | F4.2 |
| BG-011 | Forcing DNS / anti-bypass (VLAN, interfaces, tabelas PF) | F4.3 |
| BG-020 / BG-021 | (Operacional) Integridade e fallback pós-F1.3 — reforço em runtime | F4.2 |

---

## 3. Ordem segura de subfases

### F4.0 — Governação (este plano + alinhamento canónico)

**Estado:** aberta em `2026-04-24`.

**Inclui:** `f4-plano-de-implementacao.md`, actualização de `CORTEX`, roadmap,
backlog, changelog; **não** exige `PORTVERSION` sozinha.

### F4.1 — Service e integração package/daemon

**Objectivo:** alinhar `rc.d`, arranque, reload e resincronização com
comportamento documentado; reduzir estados "metade activos" após upgrade.

**Checkpoint `2026-04-24`:** `rc.d/layer7d` aplica `chmod 0644` ao pidfile apos
arranque (evita falso negativo de `status` quando o pidfile era `0600`);
`pkg-install` passa a `onestop` antes de `onestart` no `POST-INSTALL` para
upgrade carregar o binario novo; `layer7_signal_reload()` alinha-se ao
`reload` do rc.d (HUP se vivo, senão `layer7_ensure_daemon_running()`);
`layer7_restart_service` / `layer7_read_stats` endurecem leitura do pidfile e
`USR1` só com processo vivo; `pkg-deinstall` para o servico em
`PRE-DEINSTALL` e limpa pid/rcvar em `POST-DEINSTALL`; `layer7_status.php`
alinha `kill -0`.
**Bloco adicional (cron / relatorios):** `layer7-stats-collect.sh` alinha a
leitura de `/var/run/layer7d.pid` a `update-blacklists.sh` (`read -r`, trim,
PID numerico); `PORTREVISION` `4` (`1.8.11_4`).
**Bloco adicional (rc.d / servico):** `rc.d/layer7d` — `layer7d_pid_from_file`
(`read -r`, trim, PID numerico) em `start`/`stop`/`status`/`reload`;
`PORTREVISION` `5` (`1.8.11_5`).
**Bloco adicional (PHP / pidfile):** `layer7.inc` — `layer7_daemon_pid_from_file`
(primeira linha, trim, só dígitos) partilhado por
`layer7_ensure_daemon_running`, `layer7_restart_service`, `layer7_signal_reload`,
`layer7_read_stats`, `layer7_status.php`, `layer7_diagnostics.php`;
`PORTREVISION` `6` (`1.8.11_6`).

**Liga a:** BG-009.

**Exclusões:** mudança estrutural de directórios; observabilidade pesada
(F7).

**Teste mínimo:** smoke do daemon (CI e/ou
`scripts/package/smoke-layer7d.sh`); no appliance, roteiro objectivo em
[`validacao-lab.md`](../04-package/validacao-lab.md) secção **10a** (BG-009:
pidfile, `rc.d`, permissões e alinhamento com scripts).

### F4.2 — Blacklists: updater, estado e tabelas PF

**Objectivo:** robustez do consumo pós-F1.3, escrita de
`.state/fallback.state`, tabelas PF e reload seguro; GUI coerente com o
diretório e feeds.

**Checkpoint `2026-04-24`:** `update-blacklists.sh` — função `send_sighup`
valida o conteúdo do pidfile e só envia `HUP` se `kill -0` confirmar processo
vivo; `do_restore_lkg` adquire o mesmo lock que `do_download`; `layer7-pfctl`
invoca `/sbin/pfctl` em todos os ramos; `PORTVERSION` `1.8.11`.
**Bloco adicional:** `send_sighup` normaliza espaços em branco em volta do
PID lido; `PORTREVISION` `3` (`1.8.11_3`).

**Liga a:** BG-010, aspectos de BG-020/021 no runtime do consumidor.

**Teste mínimo:** simulação de feed indisponível e verificação de
degradação/fail-closed; `layer7-pfctl ensure` e regras presentes após
reload; no appliance, roteiro objectivo em
[`validacao-lab.md`](../04-package/validacao-lab.md) secção **10b** (BG-010:
log do updater, `send_sighup`, `fallback.state`).

### F4.3 — Enforcement: forcing DNS e excepções

**Objectivo:** reduzir bypass em combinações reais (VLAN, nomes d'interface,
excepções) alinhado a [`layer7.inc`](../../package/pfSense-pkg-layer7/files/usr/local/pkg/layer7.inc) e regras geradas.

**Liga a:** BG-011.

**Checkpoint `2026-04-24` (primeiro bloco):** `layer7_generate_rdr_rules_snippet`
deduplica interfaces; valida cada CIDR/IP de origem antes de emitir `rdr`;
`layer7_get_pfsense_interfaces` deixa de assumir sempre API pfSense;
`PORTREVISION` incrementado (rebuild `1.8.11_1`).

**Bloco `2026-04-24` (documentação operacional):** `MANUAL-INSTALL.md` com
addendum F4.3 (anchor `natrules/layer7_nat`, verificação `pfctl`, validade
de CIDR, dedupe, nota **inet** vs IPv6).

**Bloco `2026-04-24` (código):** `layer7_pf_ifname_for_rules` + filtro de
interfaces; `layer7_inject_nat_to_anchor` com diagnóstico em falha de
`pfctl` / temp; `PORTREVISION` `2` (`1.8.11_2`).

**Teste mínimo:** matriz alargada de interfaces (cfr. ADR/changelog
históricos de `rdr` e `get_real_interface`); roteiro de inspecção do anchor
em [`../04-package/validacao-lab.md`](../04-package/validacao-lab.md) (secção
**11. Roteiro F4.3**); evidência em lab quando possível.

---

## 4. Critérios de saída da F4 (fase)

- Cenários listados no roadmap F4 com **evidência mínima** e **rollback** claro
  para as áreas tocadas.
- `MANUAL-INSTALL` e runbooks afectados actualizados na mesma entrega.
- Riscos remanescentes no `CORTEX.md` e próxima fase (F5) visíveis no backlog.

---

## 5. Próxima fase

Após a F4: **F5 — Malha de testes e regressão**; ver
[`f5-preparacao-malha.md`](f5-preparacao-malha.md).
