# ADR-0023 — Trust chain do pacote: ativação faseada (BG-028)

**Estado:** Aceito (fase 0 activa; fase 1 pendente custódia de chaves)  
**Data:** 2026-08-05  
**Decisores:** Operador + agente (Onda I)

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

### Fase 0 (actual — pós-GO Onda F)

- Canal oficial: **comando único manual** em `MANUAL-INSTALL.md` §1/§4/§5.
- Publicação: `.pkg` + `.pkg.sha256` em `pablomichelin/Layer7`.
- `install.sh` / `uninstall.sh` automáticos **não** são oferecidos no `latest`.
- Checklist: [`docs/06-releases/RELEASE-CHECKLIST.md`](../06-releases/RELEASE-CHECKLIST.md).

### Fase 1 (critérios de activação — gate humano)

Activar quando **todos** forem verdade:

1. Par Ed25519 gerado e chave privada em custódia humana (não no repo/builder).
2. `sign-release.sh` executado com sucesso numa release de transição.
3. `verify-release.sh` PASS no stage dir.
4. `MANUAL-INSTALL.md` actualizado para reactivar `install.sh`/`uninstall.sh`.
5. Nova PORTREVISION publicada com manifesto + assinatura no GitHub Release.

---

## Consequências

- **R7** (Onda J): satisfeito com **excepção formal** até fase 1 — risco aceite documentado.
- Clientes continuam com SHA256 manual + `IGNORE_OSVERSION` conforme manual.
- Próxima release **não** deve activar fase 1 sem checklist 5.4+ e GO humano.

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
