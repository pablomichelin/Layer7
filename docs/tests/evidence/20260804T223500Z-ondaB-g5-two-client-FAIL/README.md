# Evidência Onda B — G5 two-client (FAIL)

| Campo | Valor |
|-------|-------|
| `run_id` | `20260804T223500Z-ondaB-g5-two-client-FAIL` |
| Plano | passo **3.1** — G5 two-client |
| Appliance | `192.168.100.254` |
| Cliente A | `192.168.100.234` (`server`) |
| Cliente B | `192.168.100.235` (`zpro-aimirim`) |
| Veredicto | **FAIL** — rollback aplicado |

## O que foi testado

1. Configuração temporária: `scoped_hybrid` + `mode=enforce` + política block YouTube só para `192.168.100.234`
2. Regra PF gerada: `block drop quick inet from 192.168.100.234 to <layer7_pdst_0>`
3. Tabela `layer7_pdst_0` populada manualmente com IP YouTube (`142.251.156.4`)
4. `curl -4 https://www.youtube.com` nos clientes A e B

## Resultado

| Teste | Esperado | Observado |
|-------|----------|-----------|
| A bloqueado | timeout/FAIL | HTTP **200** |
| B permitido | HTTP 2xx | HTTP **200** |
| `layer7_block_dst` | vazia (scoped) | vazia ✓ |
| Regra `layer7:pdst` no ruleset | presente | presente ✓ |
| Contador PF na regra pdst | packets > 0 | **0 packets** (12541 evals) |

## Causa-raiz (read-only)

No `/tmp/rules.debug` do pfSense, existe regra **antes** das regras Layer7:

```text
pass in quick on $LAN inet from any to any   (ridentifier 1624548664, ~linha 617)
```

As regras Layer7 (`layer7:pdst:*`) estão na secção **Extra rules from packages** (~linha 813), **depois** do `pass any` da LAN. Com `quick`, o tráfego dos clientes LAN nunca chega ao block Layer7.

## Rollback

- Restaurado `/tmp/layer7.json.pre-g5`
- `filter_configure` + `layer7d restart`
- Estado final: `enabled=true`, `mode=monitor`, `legacy_global`
- Clientes A/B: YouTube HTTP 200 (normal)

## Próximo passo técnico sugerido

- Backlog: injetar regras Layer7 **antes** do pass global LAN, ou documentar requisito de não ter `pass any any` na LAN acima do pacote
- **Não** repetir enforce em produção até correcção ou regra LAN ajustada pelo operador
