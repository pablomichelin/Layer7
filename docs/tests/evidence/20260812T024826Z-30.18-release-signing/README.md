# Evidência — 30.18 / BG-123 (cadeia F1.2 de release)

**UTC bateria:** `20260812T024826Z`  
**Escopo:** processo + docs + dry-run offline (chave **efémera** em TMP).  
**Não feito:** GitHub Release · `.254` · CF/DNS · license-server · chave de produção.

## Sequência

| # | Passo | Estado |
|---|-------|--------|
| 1 | Cartão Composer §8.2 | `card-8.2.txt` |
| 2 | Dry-run sign→verify + negativo tamper (×2 + evidência) | **PASS** — `test_release_signing_f12_30.18.txt` (`exit:0`) |
| 3 | `publish-release.sh` exige `verify-release.sh` | **PASS** — `publish-calls-verify.txt` |
| 4 | `mktemp` respeita `TMPDIR` (sign/verify) | **PASS** — `mktemp-tmpdir-hardening.txt` |
| 5 | Sem private key no working tree deste bloco | **PASS** — `no-private-key-in-status.txt` |
| 6 | FECHADO + commit/push | **após** este pack |

## Objectivo / impacto / risco / teste / rollback

| Campo | Valor |
|-------|--------|
| Objectivo | Activar/obrigar cadeia F1.2 (manifesto+`.sig`+pubkey) no processo de release; provar sign/verify; alinhar MANUAL |
| Impacto | Docs + teste funcional; **sem** alteração de `layer7d` / PORTVERSION / produção |
| Risco | Baixo (documental/processo); residual A-10 em tags já publicadas até 1ª publish Fase 1 (BG-028) |
| Teste | `tests/functional/test_release_signing_f12_30.18.sh` — exit 0 |
| Rollback | Reverter commit 30.18; canal `1.9.54` (`.pkg`+`.sha256`) inalterado |

## Gates

| Gate | Resultado |
|------|-----------|
| GA6.5 | **PASS (processo)** — dry-run + política; **residual campo** até 1ª release F1.2 (ADR-0023 Fase 1 / BG-028) |
| GA6.6 | **PASS** — `MANUAL-INSTALL.md` com links `1.9.54` + procedimento F1.2 documentado |

## Não aberto

`30.19` · MITM · IPv6 · `.254` · CF/DNS · license-server · publish/deploy · ofuscação
