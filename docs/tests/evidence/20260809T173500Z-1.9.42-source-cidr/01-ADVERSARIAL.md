# Auditoria adversária — `1.9.42` source scope

**Antes de PASS/release.**

| ID | Ataque / falha | Resultado |
|----|----------------|-----------|
| A1 | Dest-only (como `1.9.41`) afecta toda a LAN | **Mitigado** — zero rdr sem `source_cidr` |
| A2 | `from any` no gerador | **Mitigado** — forma fixa + cinto por regra |
| A3 | `source=any` / `0.0.0.0/0` | **Mitigado** — rejeitado na normalização |
| A4 | IPv6 rdr sem listener | **Mitigado** — só inet IPv4 |
| A5 | Dest = IP/CIDR do appliance (lockout GUI) | **Mitigado** — exclusão self (F6) + source self |
| A6 | Listen port = webConfigurator | **Mitigado** — snippet vazio |
| A7 | Upgrade default | **Mitigado** — listas vazias ⇒ zero rdr |
| A8 | OFF/disable/uninstall deixa tabelas | **Mitigado** — flush src+dst em sync OFF, pfctl, deinstall |
| A9 | Comentário com texto “from any” anula snippet | **Mitigado** — check só nas linhas `rdr` |
| A10 | Activação acidental em `.254` neste bloco | **Mitigado** — sem escrita; runbook separado |

**Veredicto auditoria:** **PASS** — GO para release `v1.9.42`; **NO-GO** activação produção sem runbook+GO humano.
