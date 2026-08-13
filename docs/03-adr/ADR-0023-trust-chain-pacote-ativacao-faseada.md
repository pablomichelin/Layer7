# ADR-0023 — Trust chain do pacote: ativação faseada (BG-028)

**Estado:** Aceito — **fase 1 activa** (`v1.9.58`, `2026-08-13`)  
**Data:** 2026-08-05 (fase 0); emenda fase 1 `2026-08-13`  
**Decisores:** Operador + agente (Onda I); GO humano BG-028 Fase 1

---

## Contexto

- F1.2/F1.4 define manifesto Ed25519, `install.sh` carimbado e fail-closed.
- Scripts existem: `scripts/release/deployz.sh`, `sign-release.sh`, `verify-release.sh`, `publish-release.sh`.
- Releases `v1.7.8`–`v1.8.11_69` publicam **`.pkg` + `.sha256`** sem manifesto assinado do pacote.
- Blacklists (F1.3) **já** têm trust chain activo desde `1.8.11_13`.
- BG-028 exige par Ed25519 **fora** do builder e fora do repo (custódia humana).

---

## Decisão

Adoptar **ativação faseada** do trust chain do **pacote**:

### Fase 0 (histórico — até `1.9.54`)

- Canal oficial publicado (`1.9.54`): **comando único manual** em
  `MANUAL-INSTALL.md` §1/§4/§5; assets = `.pkg` + `.pkg.sha256`.
- **Processo** (pós-`30.18` / BG-123): releases *novas* de pacote **não**
  podem omitir F1.2 no stage/sign/verify; ver addendum em
  [`RELEASE-SIGNING.md`](../06-releases/RELEASE-SIGNING.md).
- `install.sh` / `uninstall.sh` automáticos **não** eram oferecidos no `latest`
  até Fase 1.
- Checklist: [`docs/06-releases/RELEASE-CHECKLIST.md`](../06-releases/RELEASE-CHECKLIST.md).

### Fase 1 (activa — `v1.9.58` / BG-028)

Critérios cumpridos `2026-08-13`:

1. Par Ed25519 gerado e chave privada em custódia humana (não no repo/builder).
2. `sign-release.sh` executado com sucesso na release de transição `1.9.58`.
3. `verify-release.sh` PASS no stage dir.
4. `MANUAL-INSTALL.md` actualizado para reactivar `install.sh`/`uninstall.sh`.
5. PORTVERSION `1.9.58` publicada com manifesto + assinatura no GitHub Release.

Fingerprint SHA256 da chave pública:
`d26e3f007e81298bad910f99dd62a22e2109740158b3b3c7f4e79490bdc5a998`.
Evidência: [`../tests/evidence/20260813T154800Z-bg028-f12-publish/`](../tests/evidence/20260813T154800Z-bg028-f12-publish/).
Produção `.254` **não** foi promovida neste bloco (permanece `1.9.54`).

---

## Consequências

- **R7** (Onda J): satisfeito — Fase 1 activa em `v1.9.58`.
- Canal lab/`latest` usa `install.sh` fail-closed; comando manual permanece alternativa.
- Releases novas continuam a exigir F1.2 completo (política `30.18`).

---

## Alternativas rejeitadas

| Alternativa | Motivo |
|-------------|--------|
| Activar BG-028 sem chave humana | Viola F1.2 e AGENTS (segredos) |
| Adiar indefinidamente sem ADR | Deixa R7 ambíguo no fecho Onda J |
| Activar só `install.sh` sem manifesto | Fail-closed incompleto |

---

## Referências

- BG-028, F1.2, [`RELEASE-SIGNING.md`](../06-releases/RELEASE-SIGNING.md)
- [`ADR-0004`](ADR-0004-cadeia-de-confianca-dos-artefatos.md)
- Evidência Onda I: `docs/tests/evidence/20260805T012000Z-ondaI-f7-release-checklist/`
