# Evidência — instalação passiva `1.9.42` em produção `.254`

**Run-id:** `20260809T175111Z`  
**Host:** `192.168.100.254`  
**GO humano:** instalação passiva apenas (sem activar MITM / sem rdr)  
**Antes:** `1.9.38`  
**Depois:** `1.9.42`  
**SHA256:** `6bd6ba374b398ec82cd43ea2246f16a3774f4377d3cac6411265472d3d3a4c4b`  
**`.234/.235/.24`:** **não tocados**

## Veredicto

| Gate | Estado |
|------|--------|
| Backup `config.xml` + `layer7.json` | **PASS** |
| SHA pacote `1.9.42` | **PASS** |
| `pkg add -f` (ABI 15→16 com `-f`) | **PASS** |
| Versão instalada `1.9.42` | **PASS** |
| `layer7d` running | **PASS** (pid 88862) |
| GUI `:9999` HTTP 200 | **PASS** |
| SSH `:22` | **PASS** |
| LAN + Internet ping | **PASS** |
| DNS | **PASS** |
| `mitm` ausente / OFF | **PASS** (`has_mitm=no`) |
| `source_cidr`/`dest_cidr` | **PASS** (vazios) |
| gate / `mitm.effective` / `:8443` | **PASS** (ausentes) |
| rdr/tabelas MITM | **PASS** (none) |
| Activação MITM / CA cliente | **não executada** (fora de escopo) |

**SMOKE_PASS_PASSIVE**

## Rollback disponível (não usado)

- `/tmp/pfSense-pkg-layer7-1.9.38.pkg` (SHA `7c60f6b1…1dab`)
- `/tmp/pfSense-pkg-layer7-1.9.41.pkg` (staged)
- Backups **no appliance** (não no git):  
  `/tmp/config.xml.bak-pre-mitm-19242-20260809T175111Z`,  
  `/tmp/layer7.json.bak-pre-mitm-19242-20260809T175111Z`,  
  pasta `/tmp/l7-19242-passive-20260809T175111Z/`

## Descoberta destino HTTPS (read-only; **não configurado**)

Ver `13-target-discovery.txt` e secção no relatório do chat / CORTEX.
Recomendação: **destino /32 dedicado controlado pelo proprietário** — não CDN anycast público.
