# F3.11 - Readiness Scorecard

## Finalidade

Pagina executiva de estado da F3.11 — responde se a campanha pode avancar.

---

## 1. Estado executivo

| Campo | Valor |
|-------|-------|
| Ultima actualizacao | `2026-04-14` |
| Readiness status geral | `GO condicional` |
| Campanha status geral | `GO condicional` |
| Fase | `F3 aberta` |
| Subtrilha | `F3.11 alinhada no license-server live` |
| Blockers F3 reais restantes | `DR-05` (appliance) |
| Drifts reclassificados como fora de F3 | `DR-07` |
| Drifts resolvidos | `DR-01` (schema/admin live alinhado), `DR-02` (403 vs 409 cosmetico), `DR-03` (sessao stateful + Bearer), `DR-04` (inventario obtido), `DR-06` (same-origin fail-closed) |

---

## 2. Resumo por insumo

| Insumo | Estado | Bloqueante para F3? |
|--------|--------|---------------------|
| acesso ao host `192.168.100.244` | `disponivel` — SSH confirmado, stack observada | nao |
| PostgreSQL live | `disponivel` — schema de licenciamento e tabelas admin presentes no ambiente activo | nao |
| credencial admin | `disponivel` — superficie admin alinhada; bootstrap e sessao observados no live | nao |
| appliance pfSense `192.168.100.254` | `parcial` — SSH funcional, baseline exportado, daemon vivo confirmado por processo/stats, mas cenarios mutaveis ainda pendentes | sim (DR-05) |
| inventario de licencas | `disponivel` — 4 licencas reais obtidas do live em 2026-04-03 | nao |

---

## 3. Inventario real obtido em 2026-04-03

| Slot | ID | Cliente | Status | Expiry | Cenario |
|------|----|---------|--------|--------|---------|
| LIC-A | 8 | Compasi | `active` | 2026-12-31 | activacao valida em producao |
| LIC-B | 7 | Systemup | `active` | 2033-10-24 | activacao longo prazo, mesmo hw que LIC-D |
| LIC-C | 6 | Lasalle Agro | `revoked` | 2026-04-30 | revogacao real |
| LIC-D | 5 | Lasalle | `active` (expirada por data) | 2026-03-31 | expiracao por data vs status |

---

## 4. Proximos passos para fechar a F3

1. executar cenarios locais do appliance: snapshot/restore, offline/online,
   NIC/UUID (fechar DR-05);
2. consolidar evidencias e preencher relatorio final de campanha;
3. decidir binariamente `F3 pode fechar` ou `F3 nao pode fechar`.

### Evidencia DR-05 parcial (obtida em 2026-04-14)

- SSH funcional ao appliance via utilizador temporario `codex`;
- `layer7d --fingerprint` = `e31560f5bc9894e92b9007d3e2e897a374f3d0b493b803c929d54acf51f8f826`;
- licenca local valida de `Systemup`, expiry `2033-10-24`, coerente com a
  `ID 7` do backend;
- runtime exportado com stats JSON validos e restart de `layer7d` por
  `pfSsh.php playback svc restart layer7d`;
- baseline `20260414T123526Z-appliance254-permissions` confirma que o falso
  negativo de `service layer7d status` vem da falta de leitura do pidfile
  `0600 root:wheel`; `pgrep -fl layer7d` e stats JSON confirmam daemon vivo;
- o run canónico `20260414T000000Z-appliance254-continue` confirma tambem
  que `export-appliance-evidence.sh` corre de ponta a ponta com `codex`,
  materializa `40-preflight-appliance.txt`, preserva a leitura do `.lic`
  actual e regista o hash local do artefacto;
- verificacao adicional de control plane confirma `codex` sem `sudo`, sem
  `doas` e sem prova actual de playback livre em `pfSsh.php` para mutacao
  arbitraria do `.lic`;
- inspeccao read-only do pacote instalado confirma via legitima de mutacao
  na GUI (`register_license` / `revoke_license` em `layer7_settings.php`),
  apoiada por `layer7_lic_path()` e `layer7_restart_service()`, mas essa
  trilha continua dependente de contexto autenticado ainda nao disponivel ao
  `codex`;
- observacao HTTP local da GUI confirma `301` de `http://127.0.0.1/` para
  `https://127.0.0.1:9999/`, emissao de `PHPSESSID`, injecao de
  `__csrf_magic`, devolucao da login page quando `layer7_settings.php` e
  aberto sem sessao e `HTTP 403 CSRF Error` quando o token nao casa;
- a trilha GUI autenticada passa a ter tambem o helper canónico
  `scripts/license-validation/run-pfsense-gui-license-flow.sh` para
  materializar `probe`, `register` e `revoke` com evidencias por `run_id`,
  inclusive a partir do proprio appliance com `--ssh-target` quando a GUI
  util so responde em `https://127.0.0.1:9999/`;
- cenarios que exigem reescrever `/usr/local/etc/layer7.lic` continuam
  pendentes porque `codex` nao tem permissao de escrita nesse ficheiro.

Leitura operacional actual: o proximo passo sensato para destravar o
`DR-05` e usar a trilha GUI autenticada descrita em
`f3-runbook-proxima-campanha-real.md`, incluindo a sequencia `curl` e o
helper `run-pfsense-gui-license-flow.sh`, inclusive no modo
`--ssh-target <utilizador@host>` + `--gui-base https://127.0.0.1:9999`,
sempre com `PHPSESSID` + `__csrf_magic` da mesma sessao viva.

### Evidencia DR-02 (obtida em 2026-04-03)

| Cenario | Licenca | Repo | Live | Logica |
|---------|---------|------|------|--------|
| hw diferente do binding | ID 8 | 409 | 403 | correcta (rejeita) |
| licenca revogada | ID 6 | 409 | 403 | correcta (rejeita) |
| licenca expirada | ID 5 | 409 | 403 | correcta (rejeita) |
| reactivacao legitima | ID 7 | 200 | 200 | correcta (aceita) |

Conclusao: drift cosmetico de codigo HTTP, logica de negocio correcta.

---

## 5. Objectivo, impacto, risco, teste e rollback

- **Objectivo:** reflectir estado real apos evidencia de 2026-04-03.
- **Impacto:** documental.
- **Risco:** baixo.
- **Rollback:** `git revert <commit-deste-bloco>`.
