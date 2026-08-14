# Evidência BG-127 — continuação `20260814T053905Z`

**Run ID:** `20260814T053905Z-bg127`  
**GO:** continuação humana autorizada após `20260814T051611Z` (só evidência; engenharia `30.19` fechada)  
**Janela:** `2026-08-14` 02:39–02:46 `-03` (fora do horário comercial)  
**Campanha:** **PARTIAL** — GA2.7 **PASS**; GA5.9 **FAIL campo** (API live pré-30.13); não fecha BG-127

| Campo | Valor |
|-------|--------|
| DUT | `192.168.100.254` (`systemupfw.system.up`) |
| Testemunha | `192.168.100.54` (`ubuntu`) — **só leitura** |
| Pacote | `pfSense-pkg-layer7-1.9.63` |
| Modo | `monitor` · `layer7_enabled=true` |
| MITM | `enabled=false` `mitm_effective=false` `NO_8443` `NO_MITM_RDR` |
| Licença produção | id **13** Systemup `valid=1` `expiry=2030-01-09` — **não** revogada nem editada |
| Licença teste | id **14** cliente **9** `BG-127-TEST` expiry `2026-08-16` features `base` — criada, activada no `.254`, **revogada** no GA5.9 |
| Check-in config | opt-in `true` só na janela GA5.9; restaurado a `absent` |
| Código / release | **intocados** |
| `.234` / `.235` | intocados |

## Veredictos

| Passo | Gate | Veredicto | Nota |
|-------|------|-----------|------|
| P0 | baseline | **PASS** | alinhado a `20260814T051611Z` / 20.36 |
| P1 | pré-flight `.54` | **PASS** | sem mutação; gates Layer7 neste host = **N/A** |
| lic-create | portal | **PASS** | cliente 9 + licença 14; id 13 intacto |
| P3 | GA2.7 (N2) | **PASS** | teste ausente ⇒ `valid=0`, daemon vivo, zero block; produção restaurada |
| P6 | GA5.9 | **FAIL** | `--check-in` oficial HTTP 400 (API rejeita `nonce`); `valid` ficou 1 (N3); legado sem nonce = `409 revoked` |
| final | restore | **RESTORED** | hashes = backup; MITM OFF |

## Backup (fora do git)

| Onde | Path |
|------|------|
| Appliance | `/root/layer7-backup-bg127-20260814T053905Z.tgz` |
| Operador | `~/Documents/layer7-operator-backups/20260814T053905Z-bg127/` |
| SHA256 tgz | `aed563952c7c019efb4773e594b0cae28f7d8f248ddb98114a48ca2740578558` |
| SHA256 `.lic` produção | `05fd6411aa3f1be9af2ceb7a05b4493c4b2be58c5b46e0cc8c3f0e2e3d8c629e` |
| SHA256 `layer7.json` | `f966fc92b622ad6391bf633b0b1567e0d87703a75526c02c86cf03eae5587b32` |

Nenhum `.lic` / token / chave / cookie no git.

## Residual

Deploy do API `30.13` (aceita `nonce` + envelope assinado) no live `.244` exige **GO próprio** — fora deste bloco. Sem isso GA5.9 no cliente `1.9.63` não corta enforce.
