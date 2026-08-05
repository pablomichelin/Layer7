# F5 — checklist smoke repetível (Onda G / passo 8.2)

**SSOT:** este ficheiro + `tests/lab/run-f5-smoke-checklist.sh`  
**Objectivo:** um único roteiro repetível antes de cada release ou após mudança crítica.  
**Não substitui:** gates G2–G7 completos, two-client, nem validação CE física (ADR-0022).

---

## Pré-requisitos

- [ ] Backup/snapshot appliance (Veeam ou hypervisor) — **obrigatório** antes da fase **A3**
- [ ] SSH ao builder `192.168.100.12` e appliance `192.168.100.254`
- [ ] Candidato lab conhecido (ex.: `1.8.11_69`) — ver `CORTEX.md`
- [ ] Working tree limpo ou mudanças conscientes (`git status`)

---

## Fases (ordem fixa)

| Fase | Onde | Comando / script | Critério PASS |
|------|------|------------------|---------------|
| **L1** | macOS / CI | `sh tests/run-local.sh` | exit 0 |
| **L2** | macOS / CI | `sh scripts/package/check-port-files.sh` | exit 0 |
| **B1** | Builder FreeBSD | `sh scripts/package/smoke-layer7d.sh` | exit 0 |
| **B2** | Builder FreeBSD | `cd package/pfSense-pkg-layer7 && make -V PORTVERSION` | versão esperada |
| **A1** | Appliance | `sh diagnose-layer7-appliance.sh` (read-only) | pacote instalado, daemon vivo ou parado coerente |
| **A2** | Appliance | `layer7d -V`, `service layer7d onestatus`, `--license-status` | versão = candidato; licença válida ou grace documentado |
| **A3** | Appliance | `smoke-monitor-mode.sh` em **monitor** temporário | exit 0; **restaurar** config produção no fim |

**A3 opcional em produção enforce:** saltar se o operador não autorizar toggle monitor; registar `SKIP` com motivo.

---

## Execução automática (recomendada)

```sh
# Na raiz do repo (macOS com SSH ao lab):
export L7_BUILDER=root@192.168.100.12
export L7_APPLIANCE=root@192.168.100.254
export L7_CANDIDATE=1.8.11_69
export L7_RUN_A3_MONITOR=1   # 0 para saltar toggle monitor em produção

sh tests/lab/run-f5-smoke-checklist.sh
```

Saída e evidência: `docs/tests/evidence/<run_id>/`

---

## Rollback (appliance)

- Script A3 guarda backup de `layer7.json` e restaura no `EXIT`
- Se falhar manualmente: restaurar snapshot Veeam do pfSense
- `layer7-pfctl flush-all` + `/etc/rc.filter_configure` se PF inconsistente

---

## Ligação backlog / matriz

| Item | Referência |
|------|------------|
| BG-012 / BG-013 | `docs/tests/test-matrix.md` sec. 1, 3, 5 |
| Mapa F5 (8.1) | `docs/tests/evidence/20260805T005000Z-ondaG-f5-mapa/` |
| Gates completos | `docs/09-blocking/plano-gates-producao.md` |

---

## Histórico

| Data | run_id | Veredicto |
|------|--------|-----------|
| 2026-08-05 | `20260805T005500Z-ondaG-f5-smoke-82` | ver evidência |
