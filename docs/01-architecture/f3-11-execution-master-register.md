# F3.11 - Registro Mestre de Execucao

## Finalidade

Painel central da F3.11 — estado real dos insumos e proximos passos.

---

## 1. Painel consolidado

| Campo | Estado actual |
|-------|---------------|
| Fase | `F3 aberta` |
| Subtrilha | `F3.11 alinhada no license-server live` |
| Ultima evidencia real | `2026-04-14` — live alinhado + baseline real/permissoes do appliance + run canónico `20260414T000000Z-appliance254-continue` |
| Blockers F3 restantes | `DR-05` (appliance) |
| Drifts fora do escopo F3 | `DR-07` |

---

## 2. Estado por insumo

| Insumo | Estado | Evidencia |
|--------|--------|-----------|
| host `192.168.100.244` | disponivel | SSH confirmado, stack viva observada com 4 containers |
| PostgreSQL live | disponivel | base `layer7_license` com `admin_sessions`, `admin_audit_log` e `admin_login_guards` presentes |
| credencial admin | disponivel | `pablo@systemup.inf.br` observado no live; bootstrap administrativo e superficie de sessao alinhados |
| appliance `192.168.100.254` | parcial | SSH funcional via `codex`; baseline `S13`, runtime `S07`, baseline de permissoes e run canónico `20260414T000000Z-appliance254-continue` exportados; daemon vivo confirmado por processo/stats; `codex` sem `sudo`/`doas`, sem via observada de playback livre para escrita no `.lic`, sem playback Layer7 adicional observado em `pfSsh.php`/`phpshellsessions` e com trilha mutavel legitima observada apenas na GUI autenticada do pacote via `layer7_lic_path()` + `layer7_restart_service()`, agora tambem materializavel pelo helper `scripts/license-validation/run-pfsense-gui-license-flow.sh` em modo `--ssh-target` para GUI no loopback; cenarios mutaveis ainda pendentes |
| inventario | disponivel | 4 licencas reais: IDs 5, 6, 7, 8 — cobrindo active, revoked, expired-by-date e coexistencia |

---

## 3. Proximos passos

1. **DR-05**: executar os cenarios mutaveis restantes do appliance
   (snapshot/restore, offline, grace, NIC/UUID, clone/restore), priorizando
   a trilha GUI autenticada do pacote ja mapeada no runbook canonico e o
   helper `scripts/license-validation/run-pfsense-gui-license-flow.sh`,
   inclusive em `--ssh-target <utilizador@host>` + `--gui-base https://127.0.0.1:9999`
   quando a GUI util estiver so no loopback do appliance;
2. consolidar evidencias no relatorio final;
3. decidir fecho da F3.

**DR-02 resolvido em 2026-04-03:** live usa `403` onde repo usa `409` em
todos os cenarios de rejeicao (hw diferente, revogada, expirada), mas
aceita reactivacao legitima com `200`. Drift cosmetico — logica de negocio
validada e correcta.

**Alinhamento live confirmado em 2026-04-14:** stack activa em
`/opt/layer7-license`, API reconstruida, tabelas administrativas presentes,
`/api/auth/session` funcional e `Origin` externo em `/api/auth/login`
rejeitado com `403`.

**Baseline do appliance confirmado em 2026-04-14:** acesso SSH funcional via
`codex`, fingerprint `e31560f5...f826`, hostuuid
`f44d4d56-4a12-95c7-1099-1ebd8b33f579`, licenca local valida de `Systemup`
com expiracao `2033-10-24`, evidencias exportadas em
`/tmp/layer7-f3-evidence/20260414T111500Z-appliance-baseline/S13`,
`/tmp/layer7-f3-evidence/20260414T113500Z-appliance-runtime/S07`,
`${TMPDIR:-/tmp}/layer7-f3-evidence/20260414T123526Z-appliance254-permissions/S07`
e `/tmp/layer7-f3-evidence/20260414T000000Z-appliance254-continue/S07`.
O ultimo run confirma tambem que `service layer7d status` sofre falso
negativo por permissao de pidfile, enquanto `pgrep -fl layer7d` e o stats
JSON confirmam o daemon vivo.
Na trilha GUI do pacote, ja esta observado tambem o redirect
`http://127.0.0.1/` -> `https://127.0.0.1:9999/`, a emissao de `PHPSESSID`,
o uso de `__csrf_magic`, a devolucao da login page sem sessao autenticada e
o `HTTP 403 CSRF Error` quando o token nao casa com a sessao.

---

## 4. Objectivo, impacto, risco, teste e rollback

- **Objectivo:** reflectir estado real apos evidencia de 2026-04-03.
- **Impacto:** documental.
- **Risco:** baixo.
- **Rollback:** `git revert <commit-deste-bloco>`.
