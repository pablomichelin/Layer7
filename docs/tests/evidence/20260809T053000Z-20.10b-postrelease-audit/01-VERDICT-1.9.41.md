# Fecho correctivo — 20.10b / `1.9.41`

**Data:** `2026-08-09`  
**Base auditada (NO-GO):** `1.9.40` / commit `691d6e8`  
**Candidato correctivo:** `1.9.41`  
**SHA256:** `1518ad6825aad51bb97897335e441ac630be0ce6af74b80738ec06e77ca0c1f4`

## Correcções F1–F6

| ID | Correcção em `1.9.41` |
|----|------------------------|
| F1 | `layer7_generate_rules("nat")` emite `layer7_generate_mitm_rdr_snippet()` em monitor |
| F2 | CA generate/import/delete chama `layer7_mitm_sync_helper` + `filter_configure` |
| F3 | `pkg-deinstall` PRE/POST: stop tlsproxy, remove gate/flag, flush `layer7_mitm_dst*` |
| F4 | Rdr MITM só IPv4 → `127.0.0.1` (sem inet6/`::1` sem listener) |
| F5 | Daemon lê `/var/run/layer7/mitm.effective` (flag escrita pelo PHP sync) |
| F6 | Exclui IPs do appliance e CIDRs que os contenham de `dest_cidr` |

## Testes

- `php tests/functional/test_mitm_config.php` no builder → **PASS**
- `pfctl -nf` read-only no `.254` (snippet table+rdr) → **PASS** (evidência `06-…`)
- Build FreeBSD 15 → `pfSense-pkg-layer7-1.9.41.pkg`

## Veredicto para 20.11

**GO condicional:** avançar **20.11** só depois de:

1. Tag `v1.9.41` publicada em `pablomichelin/Layer7` com `.pkg` + `.sha256`
2. `releases/latest` = `v1.9.41`
3. MANUAL/CORTEX com SHA `1518ad68…c1f4`
4. Sem activar intercept em `.254`/`.234`/`.235` neste gate

`1.9.40` permanece **NO-GO** histórico (não promover).
