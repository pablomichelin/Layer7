# Evidência BG-127 — campanha operacional `20260814T051611Z`

**Run ID:** `20260814T051611Z-bg127`  
**GO:** humano `2026-08-14` (só evidência; engenharia `30.19` fechada)  
**Janela:** `2026-08-14` 02:16–02:20 `-03` (fora do horário comercial)  
**Campanha:** **PARTIAL** — não é PASS BG-127; não é FAIL de mecanismo

| Campo | Valor |
|-------|--------|
| DUT | `192.168.100.254` (`systemupfw.system.up`) |
| Testemunha | `192.168.100.54` (`ubuntu`) — **só leitura** |
| Pacote | `pfSense-pkg-layer7-1.9.63` |
| Modo | `monitor` · `layer7_enabled=true` |
| MITM | `enabled=false` `mitm_effective=false` `NO_8443` `NO_MITM_RDR` |
| Licença produção | `valid=1` `customer=Systemup` `expiry=2030-01-09` — **não** revogada nem substituída |
| Check-in config | `check_in_enabled=absent` (upgrade preserva; **não** forçado ON) |
| Código / release | **intocados** |
| `.234` / `.235` | intocados |

## Veredictos

| Passo | Gate | Veredicto | Nota |
|-------|------|-----------|------|
| P0 | baseline | **PASS** | `.54` testemunha OK; `.254` = `1.9.63` monitor / MITM OFF |
| P1 | pré-flight `.54` | **PASS** | sem mutação; gates Layer7 neste host = **N/A** |
| P2 | GA2.6 (N1) | **PASS parcial** | modo actual inalterado; enforce activo **DEFERRED** (sem GO de janela) |
| P3 | GA2.7 (N2) | **ABORT** | sem licença de **teste**; não se toca na de produção |
| P4 | GA3.7 | **PASS** | NTP OK; `clock_suspect=0`; `valid=1`; modo = P0 |
| P5 | GA4.8 | **DEFERRED** | token real `ok` (~27 d); isolamento de rede **não** executado (risco de tráfego no soak) |
| P6 | GA5.9 | **ABORT** | sem licença de teste + check-in ausente na base; sem opt-in |

## Backup (fora do git)

| Onde | Path |
|------|------|
| Appliance | `/root/layer7-backup-bg127-20260814T051611Z.tgz` |
| Operador (fora do repo) | `~/Documents/layer7-operator-backups/20260814T051611Z-bg127/` |
| SHA256 tgz | `8022c0299cd5c0184af4c62b6068d775f4db78d72cd8c1193280ef18f557c33e` |
| SHA256 `.lic` | `05fd6411aa3f1be9af2ceb7a05b4493c4b2be58c5b46e0cc8c3f0e2e3d8c629e` |
| SHA256 `layer7.json` | `f966fc92b622ad6391bf633b0b1567e0d87703a75526c02c86cf03eae5587b32` |

Hashes finais = hashes do backup. **Nenhum** `.lic` / token / chave no git.

## Comandos mutantes

**Nenhum** no DUT nem na testemunha. Única escrita: tarball de backup em `/root/` (cópia, sem alterar `.lic`/JSON activos).

## Rollback

Não exigido (estado = P0). Confirmação final: `valid=1`, `mode=monitor`, MITM OFF, hashes iguais.
