# Evidência — validação controlada 30.10 em `.254`

**Run ID:** `20260811T020533Z`  
**Host:** `192.168.100.254` (produção Systemup)  
**Passo:** `30.10`  
**Veredicto:** **STOP / BLOCKED** para uso em campo + **ROLLBACK PASS**

## Objectivo

Validar o cliente `1.9.53` (token de subscrição) em janela controlada no
appliance de produção, com rollback imediato se bloqueio/regressão.

## Conteúdo

| Ficheiro | Conteúdo |
|----------|----------|
| `00-verdict.txt` | Veredicto consolidado |
| `01-baseline-state.txt` | Estado pré-teste (`1.9.47`; `license_key` redigido) |
| `02-install-and-rollback.txt` | Pós-install `1.9.53` + pós-rollback `1.9.47` |
| `runid.txt` | Identificador UTC do run |

Backup operacional no appliance (fora do git):
`/root/layer7-backup-30.10-20260811T020533Z.tgz`

## Resultados (reais)

| Item | Resultado |
|------|----------|
| Backup / baseline | OK — `1.9.47`, monitor, licença válida, snapshot `ut1-2026-04-25` |
| Install `1.9.53` | POSTINSTALL_PASS (serviço, mode, licença, rede, snapshot) |
| Check-in | OK (`status=active`) |
| `content_subscription` no servidor | **AUSENTE** — `license.systemup.inf.br` sem deploy 30.9 |
| `--check-subscription` | `invalid` / `missing` |
| Update sem token | PASS — hold-active; snapshot intacto; enforce/mode intactos |
| Update com token válido | **BLOCKED** (pré-requisito externo) |
| GUI helper | `status=missing` |
| Rollback | **PASS** → baseline real **`1.9.47`** |
| `30.11` | **não iniciado** |

## Impacto / risco / rollback

- **Impacto:** janela curta em `1.9.53`; produção restaurada a `1.9.47`.
- **Risco mitigado:** deixar `1.9.53` sem emissão 30.9 no license-server
  bloquearia updates automáticos de blacklists.
- **Rollback executado:** `pkg add -f` de `1.9.47` (baseline real; o plano
  nomeava `1.9.52` como rollback lab — não era o pacote pré-teste).

## Pré-requisito / decisão humana

1. Deploy controlado do **license-server** com código **30.9**
   (`content_subscription` no check-in activo).
2. Nova janela de validação em appliance (GO explícito) antes de considerar
   GA/e2e de campo concluído ou promover `1.9.53` em produção.
3. **Não** iniciar `30.11` até esse pré-requisito fechar.
