# Arquitetura alvo (resumo)

## Blocos V1

1. Pacote pfSense (GUI + metadados)
2. Daemon `layer7d`
3. Engine de classificação (**nDPI** — ver [ADR-0001](../03-adr/ADR-0001-engine-classificacao-ndpi.md))
4. Policy engine (`allow` / `block` / `monitor` / `tag`)
5. Enforcement + logging (PF, DNS/host onde couber)

**Modelagem V1 (config/evento/policy):** [`../core/README.md`](../core/README.md).

## Documento expandido

[`../../02-ARQUITETURA-ALVO.md`](../../02-ARQUITETURA-ALVO.md)
