# Evidência BG-127 — campanha residual `20260814T224213Z`

**Run ID:** `20260814T224213Z-bg127`  
**GO:** humano `2026-08-14` 19:40 `-03` (janela GA2.6 enforce + GA4.8; engenharia `30.19` fechada)  
**Janela:** `2026-08-14` 19:42–19:46 `-03` (fora do horário comercial)  
**Campanha:** **PASS** — fecha o residual de campo do BG-127

| Campo | Valor |
|-------|--------|
| DUT | `192.168.100.254` (`systemupfw.system.up`) |
| Testemunha | `192.168.100.54` (`ubuntu`) — **só leitura** |
| Pacote | `pfSense-pkg-layer7-1.9.63` |
| Modo final | `monitor` · `layer7_enabled=true` |
| MITM | `enabled=false` `mitm_effective=false` `NO_8443` `NO_MITM_RDR` |
| Licença produção | `valid=1` `customer=Systemup` `expiry=2030-01-09` — **não** revogada |
| Check-in config | `check_in_enabled=absent` (não forçado ON) |
| Código / release / `.244` | **intocados** |
| `.234` / `.235` | intocados |

## Veredictos

| Passo | Gate | Veredicto | Nota |
|-------|------|-----------|------|
| P0 | baseline | **PASS** | `.54` testemunha OK (ICMP filtrado; SSH OK); `.254` = `1.9.63` monitor / MITM OFF |
| P1 | pré-flight `.54` | **PASS** | sem mutação; gates Layer7 neste host = **N/A** |
| P2 | GA2.6 (N1) enforce | **PASS** | `mode=enforce` + `should_enforce=true`; `valid=1`; mesmo PID `98114`; regras PF `layer7:block:*` visíveis; MITM OFF; restore imediato a `monitor` |
| P5 | GA4.8 | **PASS** | token `ok`; corte do caminho de update → HTTP 000 primary+mirror; hold-active `ut1-2026-04-25`; modo/`valid`/PID intactos |

Gates já PASS em campanhas anteriores (não reexecutados): GA2.7, GA3.7, GA5.9.

## Backup (fora do git)

| Onde | Path |
|------|------|
| Appliance | `/root/layer7-backup-bg127-20260814T224213Z.tgz` |
| Operador (fora do repo) | `~/Documents/layer7-operator-backups/20260814T224213Z-bg127/` |
| SHA256 tgz | `63f08e04aa56e06388989c70f8e66165c186dd823c38126fed0af5f0d3e53d42` |
| SHA256 `.lic` | `05fd6411aa3f1be9af2ceb7a05b4493c4b2be58c5b46e0cc8c3f0e2e3d8c629e` |
| SHA256 `layer7.json` | `f966fc92b622ad6391bf633b0b1567e0d87703a75526c02c86cf03eae5587b32` |
| SHA256 `/etc/hosts` | `4d7a31a86a64e57a86b19e3d15513af7a34ec58ef7cbc32faeaa43c09e1df0fb` |

Hashes finais = hashes do backup. **Nenhum** `.lic` / token / chave no git.

## Limites honestos

- Lab = pfSense Plus / FreeBSD 16 — **não** prova CE (ADR-0022).
- `/etc/hosts` **não** isola `curl` no pfSense (resolver = Unbound). Tentativa inicial chegou à primary e activou o mesmo snapshot `ut1-2026-04-25` (LKG hash mudou; modo/licença intactos).
- Isolamento efectivo = proxy morto **só** no processo `update-blacklists.sh` (LAN/DNS dos clientes intocados). Duração real ~60 s (dois `--connect-timeout 30`), não 30 dias.
- Root no appliance pode contornar verificação local (RR-5 / R-A).

## Rollback

Executado no mesmo bloco do GA2.6 (`mode=monitor`) e do GA4.8 (`/etc/hosts` restaurado). Confirmação final: `valid=1`, `mode=monitor`, MITM OFF, hashes `.lic`/`json`/`hosts` iguais ao backup.
