# Evidência — IM9 / 20.33 homologação real `1.9.29`

| Campo | Valor |
|-------|-------|
| `run_id` | `20260808T174100Z-im9-20.33-homolog-1.9.29` |
| Passo | **20.33** (IM9) |
| Pacote | **1.9.29** |
| Appliance | `192.168.100.254` (`systemupfw`) |
| Cliente A | `192.168.100.234` (`server`) |
| Cliente B | `192.168.100.235` (`zpro-aimirim`) |
| Backup | Veeam nas 3 máquinas (autorização humana) |
| Data UTC | `2026-08-08T17:41Z`–`17:43Z` |

## Veredicto

| Critério | Resultado |
|----------|-----------|
| 20.33 homologação pacote two-client | **PASS** |
| GI9.3 GO humano (teste real autorizado) | **PASS** |
| Licença produção restaurada (`layer7.lic.veeam-backup`) | **PASS** (`valid=1`, Systemup) |
| Identity inerte com `features=full` (T1→base) | **PASS** (sem 8743/1813) |
| Restore pós-teste | **PASS** (`enabled=0`, `mode=monitor`) |

## Two-client scoped (YouTube só A)

| Cliente | example.com | google.com | youtube.com |
|---------|-------------|------------|-------------|
| A `.234` | 200 | 200 | **000** (bloqueado) |
| B `.235` | 200 | 200 | **200** (OK) |

- Regra PF: `block drop quick inet from 192.168.100.234 to <layer7_pdst_0> label "layer7:pdst:h33-yt-block-a"`
- Tabela `layer7_pdst_0` populada com IPs YouTube (IPv4/IPv6) via DPI em tráfego real
- Sem quarentena total (google/example OK em A e B)

## Notas operacionais

1. **Primeira tentativa no appliance falhou:** `root@254` **não** tem chave SSH para `.234`/`.235` — curls via appliance → sempre HTTP 000. Orquestração correcta: **Mac → clientes** + **sshpass → appliance**.
2. Scripts: `tests/lab/run-im9-20.33-homolog.sh` (appliance) e `…-orchestrator.sh` (Mac).
3. Licença `.lic` estava ausente antes do teste; restaurada de `/root/layer7.lic.veeam-backup`.
4. Config JSON pós-restore = estado pré-teste (`enabled=false`, 3 políticas monitor). **Não** restaurámos o enforce `legacy_global` histórico (`/tmp/layer7.json.pre-prod-align`) — fica decisão operacional humana.
5. Homologação **Identity ON** (RADIUS/DC/`ad_*`) exige reemissão `features=base,identity` (T1: `full` ≠ Identity).

## Residuais (honestos)

- Lab AD/LDAP/RADIUS físico com entitlement Identity (GI5.1, GI6, GI7 lab)
- Chave SSH appliance→clientes (opcional para automação só no pfSense)
- MITM DEFER (inalterado)

## Conclusão

Pacote **`1.9.29`** homologado em produção com two-client real e restore.
Add-on Identity de rede: código+docs+GI8/GI9 documentais; activação comercial
SKU Y = reemissão `base,identity` + GO de fecho de trilha.
