# Releases

Esta pasta concentra a governanca documental de release.

## Estado actual conhecido

- a V1 Comercial ja foi publicada;
- o artefacto publico actual conhecido e o **`.pkg`**;
- o conjunto operacional vigente da F1.1 e `.pkg` + `.pkg.sha256` +
  `install.sh`/`uninstall.sh` versionados por tag;
- `docs/changelog/CHANGELOG.md` e a linha temporal oficial;
- `docs/10-license-server/MANUAL-INSTALL.md` e o manual operacional canónico
  de instalacao, upgrade e uninstall.

## O que fica aqui

- notas de release por tag;
- template de release notes;
- referencias cruzadas para rollback e distribuicao.

## Regras

1. Nao publicar release sem changelog sincronizado.
2. Nao publicar release sem `MANUAL-INSTALL.md` sincronizado com a versao real.
3. Nao assumir `.txz` como artefacto actual; esse tema fica preservado apenas
   em material historico e no ADR-0002.
4. Manifesto e assinatura continuam contratados pelos ADRs, mas so entram
   como gate operacional a partir da F1.2.
5. Quando a F7 abrir, esta pasta passa a concentrar tambem o checklist
   operacional de publicacao.

## Arquivos desta area

- [`release-notes-template.md`](release-notes-template.md)
- [`release-notes-v0.1.0.md`](release-notes-v0.1.0.md) — historico
- [`../05-runbooks/rollback.md`](../05-runbooks/rollback.md)
- [`../10-license-server/MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md)
