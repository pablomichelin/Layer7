# Lab-pair allow/block (`.24` → `.54` / `198.18.0.10`) — PASS → MONITOR

| Campo | Valor |
|-------|--------|
| `run_id` | `20260809T225533Z-labpair-54-24` |
| GO humano | Par LAB local; fonte `.24`; fecho MONITOR no `.254` |
| Veredicto | **PASS** |
| Pacote | `1.9.46` |
| Estado final `.254` | `enabled=true` / `mode=monitor` / MITM OFF / zero regras Layer7 |

---

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| **Objectivo** | Provar allow/block LAN com destinos internos; deixar `.254` em MONITOR sem bloqueio |
| **Impacto** | Mutações temporárias em `.54` + `.254` (revertidas); políticas enforce restauradas a monitor |
| **Risco** | Médio (produção `.254`); mitigado por backups, fail-safe rota, cleanup obrigatório |
| **Teste** | HTTP allow `.54:8080` + block `198.18.0.10:8080` a partir de `.24` |
| **Rollback** | `.54`: `labpair-http/control.sh rollback`; `.254`: bak `/tmp/l7-labpair-bak-*` + snap pré-G2; rota host removida |

---

## Par LAB

| Papel | Destino | Path |
|-------|---------|------|
| Allow | `http://192.168.100.54:8080/` | L2 (retorno on-link) |
| Block | `http://198.18.0.10:8080/` | via GW `.254` → `.54` (VIP `lo`) |
| Fonte | `192.168.100.24` | exclusiva |

Inventário inicial: alias/HTTP **ausentes** na `.54`; rota `198.18` **ausente** no `.254`.

---

## Sequência executada

1. **`.54`:** alias `198.18.0.10/32` + HTTP dual `:8080` + policy-routing retorno só `from 198.18.0.10`  
2. **`.254` rota:** `route add -host 198.18.0.10 192.168.100.54` (runtime) + bak JSON/XML  
3. **`.254` política:** `scoped_hybrid` + `lab-tmp-24-19818` (`src=.24` → `pdst` com `198.18.0.10`); MITM/QUIC OFF  
4. **Smoke `.24`:** ALLOW **200** + marker; BLOCK timeout / `TNC=False`; contador PF `lab-tmp` com packets  
5. **Cleanup:** remover política/rota; `flush` + `mode=monitor`; rollback `.54`

Nota: rota global `.24 via .254` na `.54` partia o allow L2 — corrigida para `ip rule from 198.18.0.10 table 198`.

---

## Estado final (prova)

| Check | Resultado |
|-------|-----------|
| JSON | `enabled=true` `mode=monitor` `legacy_global` `mitm=false` `block_quic=false` |
| Tabelas block/block_dst | **0** membros |
| Regras `layer7:` / rdr MITM / QUIC L7 | **0** |
| GUI `:9999` | HTTP 200 |
| SSH | listen `*:22` |
| `pfctl -nf` | 0 |
| Rota `198.18.0.10` | default WAN (host route removida) |
| `.54` | sem alias/HTTP/`ip rule` lab |

---

## Preservações

- `.234` / `.235` intocados  
- Abortados untracked e `CHANGELOG.md` de outra sessão fora do commit  
- Credenciais `.24` só em `/tmp` (removidas; não commitadas)

## Artefactos-chave

- Inventário: `remote/01*`  
- Mutações: `remote/02`…`04`  
- Smoke PASS: `remote/07*`  
- Cleanup/prova: `remote/08*`…`10b*`  
- `11-VERDICT.txt`
