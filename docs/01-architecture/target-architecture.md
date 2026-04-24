# Arquitetura alvo (resumo)

## Blocos V1

1. Pacote pfSense (GUI + metadados)
2. Daemon `layer7d`
3. Engine de classificação (**nDPI** — ver [ADR-0001](../03-adr/ADR-0001-engine-classificacao-ndpi.md))
4. Policy engine (`allow` / `block` / `monitor` / `tag`)
5. Enforcement + logging (PF, DNS/host onde couber); o **pacote** (`layer7.inc`)
   gera também anti-QUIC por interface, inject NAT **`natrules/layer7_nat`** para
   **DNS forçado** (`force_dns`) e partilha a validação de nomes PF via
   **`layer7_pf_ifname_for_rules()`** — ver
   [`../05-daemon/pf-enforcement.md`](../05-daemon/pf-enforcement.md).

**Modelagem V1 (config/evento/policy):** [`../core/README.md`](../core/README.md).

## Documento expandido

[`../../02-ARQUITETURA-ALVO.md`](../../02-ARQUITETURA-ALVO.md)
