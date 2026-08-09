# Auditoria adversária pós-release — 20.10b / `1.9.40`

**Data:** `2026-08-09T053000Z`  
**Commit auditado:** `691d6e8`  
**Release:** `v1.9.40` (`pablomichelin/Layer7`)  
**Veredicto:** **NO-GO** para fechar 20.10b / avançar 20.11  
**Acção:** reabrir 20.10b → candidato **`1.9.41`**

## O que passou

| Item | Resultado |
|------|-----------|
| Git limpo pós-push; `releases/latest` = `v1.9.40` | PASS |
| Assets `.pkg` + `.sha256`; SHA = `fbbf206d…1afe` | PASS |
| MANUAL/CORTEX apontam `1.9.40` + SHA | PASS |
| Defaults rc `enable=NO`; gate ausente ⇒ não arranca | PASS |
| Listen produto loopback-only; health `mitm_effective_claim=false` | PASS |
| Lab `.54` página HTTPS product | PASS (pré-release) |
| `pfctl -nf` snippet table+rdr e inline no `.254` (read-only) | **PASS** (EXIT 0; sem load) |
| Produção `.254` sem gate/listener MITM; pkg observado `1.9.38` | PASS (não tocada) |

## Falhas materiais (reabrem 20.10b)

| ID | Severidade | Falha |
|----|------------|-------|
| F1 | **Crítica** | `layer7_generate_rules("nat")` só emitia NAT se `layer7_pf_should_enforce` — em **monitor** o rdr MITM **nunca** era injectado mesmo com `mitm_effective=true` (helper podia subir; caminho partido). |
| F2 | **Alta** | Delete/import/generate CA **não** chamava `layer7_mitm_sync_helper` / `filter_configure` → gate/helper/rdr podiam ficar órfãos após CA delete. |
| F3 | **Alta** | `pkg-deinstall` **não** parava `layer7-tlsproxy` nem removia gate/flag nem flush `layer7_mitm_dst*`. |
| F4 | **Alta** | Rdr **IPv6** → `::1:8443` sem listener `::1` (produto só `127.0.0.1`) = blackhole IPv6. |
| F5 | **Média** | Daemon reportava sempre `mitm_effective=false` (claim falso OFF vs PHP). |
| F6 | **Média** | Anti-lockout webConfigurator incompleto (só comparava porta listen≠webgui; não excluía IPs do appliance em `dest_cidr`). |

## Evidências anexas

- `06-pfctl-nf-readonly-254.txt` — parser PF no appliance (sem `-f`)

## Próximo

Candidato **`1.9.41`** corrigiu F1–F6 — ver `01-VERDICT-1.9.41.md`.  
**Não** avançar 20.11 até `releases/latest` = `v1.9.41` + MANUAL/CORTEX alinhados.
