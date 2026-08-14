# Evidência BG-127 — GA5.9 controlado `20260814T143406Z`

**Run ID:** `20260814T143406Z-bg127-ga59`  
**GO:** repetir **só** GA5.9 no `.254` após API `30.13` live no `.244`  
**Janela:** `2026-08-14` 11:34–11:36 `-03`  
**Campanha deste bloco:** **PASS** GA5.9 campo — **não** fecha BG-127 (GA2.6 enforce / GA4.8 continuam DEFERRED)

| Campo | Valor |
|-------|--------|
| DUT | `192.168.100.254` (`systemupfw.system.up`) |
| Testemunha | `192.168.100.54` — **só leitura** (`NO_443_8443`) |
| Pacote | `pfSense-pkg-layer7-1.9.63` — **não** alterado |
| Modo | `monitor` · `layer7_enabled=true` |
| MITM | `enabled=false` `mitm_effective=false` `NO_8443` `NO_MITM_RDR` |
| Licença produção | id **13** Systemup `valid=1` `expiry=2030-01-09` — **nunca** revogada nem editada |
| Licença teste | id **15** cliente **9** `BG-127-TEST` expiry `2026-08-16` `base` — criada, activada no `.254` (FP exclusivo), **revogada** |
| Check-in config | opt-in `true` só na janela; restaurado a `absent` |
| Código / release / `.234` / `.235` | **intocados** |

## Veredictos

| Passo | Gate | Veredicto | Nota |
|-------|------|-----------|------|
| P0 | baseline `.254` | **PASS** | `1.9.63` monitor MITM OFF; hashes = produção |
| lic-create | portal | **PASS** | id **15**; id 13 intacto |
| activate | DUT | **PASS** | `customer=BG-127-TEST` FP `.254` |
| check-in 1 | pré-revoga | **PASS** | `check-in OK — license active` (envelope 30.13 aceite) |
| revoke | portal | **PASS** | só id **15** → `revoked`; id 13 `active` |
| check-in 2 | GA5.9 | **PASS** | `check-in denied — Licenca revogada.`; `valid=0`; daemon vivo; MITM OFF; zero block |
| final | restore | **RESTORED** | hashes = backup; Systemup `valid=1`; `check_in_enabled=absent` |

## Backup (fora do git)

| Onde | Path |
|------|------|
| Appliance | `/root/layer7-backup-bg127-20260814T143406Z.tgz` |
| Operador | `~/Documents/layer7-operator-backups/20260814T143406Z-bg127-ga59/` |
| SHA256 tgz | `0e1cdfda8ed4a18284a800305848f331a2698cdc5426ba4d454b50927ede93d2` |
| SHA256 `.lic` produção | `05fd6411aa3f1be9af2ceb7a05b4493c4b2be58c5b46e0cc8c3f0e2e3d8c629e` |
| SHA256 `layer7.json` | `f966fc92b622ad6391bf633b0b1567e0d87703a75526c02c86cf03eae5587b32` |

Nenhum `.lic` / token / chave / cookie no git.

## Residual BG-127

GA5.9 campo **PASS**. BG-127 permanece **aberto / PARTIAL** (GA2.6 enforce **DEFERRED**; GA4.8 **DEFERRED**). Sem outros gates neste bloco.
