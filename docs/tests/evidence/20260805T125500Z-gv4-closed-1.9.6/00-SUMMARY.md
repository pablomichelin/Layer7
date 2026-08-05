# Evidência GV4 FECHADO — `1.9.6` (2026-08-05T125500Z)

## Veredicto

**GV4 PASS** (two-client YouTube: A bloqueado v4+v6, B livre; Google livre).

## Condições necessárias (descobertas neste run)

1. **`enforcement_model=scoped_hybrid`** — com `legacy_global`, o daemon
   escreve em `layer7_block_dst` mas políticas com `src_hosts` precisam de
   `layer7_pdst_N` + regras scoped.
2. **`filter_configure()` / resync PF** após mudar JSON — editar só
   `layer7.json` + reiniciar `layer7d` **não** actualiza o ruleset vivo.
3. **DNS A+AAAA via unbound local funciona** no pcap `vmx0` — a limitação
   «pcap unbound intermitente» era **diagnóstico incorrecto**; o gap real era
   modelo/resync/tabela.
4. **IPv6 `src_hosts`**: incluir **GUA estática e SLAAC** do cliente (ex.
   `::100b` **e** `…:fe9d:252c`). Não usar `prefix/64` inteiro (bloqueia B).

## Resultados

| Cliente | yt4 | yt6 | google |
|---------|-----|-----|--------|
| A `192.168.100.234` | 000 | 000 | 200 |
| B `192.168.100.235` | 200 | 200 | 200 |

Aprendizagem: `dig @192.168.100.254` → `dns_block` → `layer7_pdst_3` (A+AAAA).

## Pacote

- Versão appliance: `1.9.6`
- Produção enforce: permanece `1.9.0`
- Após teste: lab restaurado a políticas pré-GV (`legacy_global`); regra
  `pass inet6` LAN (tracker `1785929863`) mantida.

## Não é V5

DNS forçado / `rdr inet6` / block page v6 **não** foram tocados.
