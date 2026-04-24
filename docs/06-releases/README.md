# Releases

Esta pasta concentra a governanca documental de release.

## Estado actual conhecido

- a V1 Comercial ja foi publicada;
- o artefacto publico actual conhecido e o **`.pkg`**;
- o conjunto operacional vigente da F1.1 e `.pkg` + `.pkg.sha256` +
  `install.sh`/`uninstall.sh` versionados por tag;
- a F1.2 acrescenta `release-manifest.v1.txt`,
  `release-manifest.v1.txt.sig` e `release-signing-public-key.pem`;
- a F1.4 faz o `install.sh` versionado validar esse conjunto antes do
  `pkg add`, falhando em `fail-closed` se a trust chain da release divergir;
- `docs/changelog/CHANGELOG.md` e a linha temporal oficial;
- `docs/10-license-server/MANUAL-INSTALL.md` e o manual operacional canónico
  de instalacao, upgrade e uninstall.

## O que fica aqui

- notas de release por tag;
- template de release notes;
- documentacao operacional da assinatura de release;
- referencias cruzadas para rollback e distribuicao.

## Regras

1. Nao publicar release sem changelog sincronizado.
2. Nao publicar release sem `MANUAL-INSTALL.md` sincronizado com a versao real.
3. Nao assumir `.txz` como artefacto actual; esse tema fica preservado apenas
   em material historico e no ADR-0002.
4. A partir da F1.2, release oficial exige manifesto versionado, assinatura
   destacada e public key de verificacao.
5. Quando a F7 abrir, esta pasta passa a concentrar tambem o checklist
   operacional de publicacao.

## Arquivos desta area

- [`release-notes-template.md`](release-notes-template.md)
- [`release-notes-1.8.11_10-DRAFT.md`](release-notes-1.8.11_10-DRAFT.md) — rascunho
  pre-publicacao (conteudo alinhado ao port `1.8.11_12` no branch; nome do ficheiro
  legado ate F6/F7; nao confundir com a release
  publica listada no `MANUAL-INSTALL` ate que a tag e o `.pkg` existam)
- [`RELEASE-SIGNING.md`](RELEASE-SIGNING.md)
- [`release-notes-v0.1.0.md`](release-notes-v0.1.0.md) — historico
- [`../04-package/deploy-github-lab.md`](../04-package/deploy-github-lab.md) — cadeia builder → stage → GitHub (suplementar; ver tambem [`../scripts/release/README.md`](../../scripts/release/README.md) na raiz do repo)
- [`../05-runbooks/rollback.md`](../05-runbooks/rollback.md)
- [`../10-license-server/MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md)
