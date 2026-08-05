# Checklist interno de release (BG-017 / F7)

**Versão:** `1.0` (`2026-08-05`)  
**SSOT operacional:** este ficheiro + [`RELEASE-SIGNING.md`](RELEASE-SIGNING.md) + [`../../scripts/release/README.md`](../../scripts/release/README.md)  
**Produção enforce actual:** `1.9.8`

> Usar **antes** de cada GitHub Release em `pablomichelin/Layer7`. Sem checklist
> completo, a release **não existe** para clientes (updater GUI).

---

## 1. Pré-build (builder `192.168.100.12`)

| # | Item | Comando / prova | ☐ |
|---|------|-----------------|---|
| 1.1 | Working tree limpo (ou stash consciente) | `git status` | |
| 1.2 | `PORTREVISION` bump se código mudou | `package/pfSense-pkg-layer7/Makefile` | |
| 1.3 | Testes locais | `tests/run-local.sh` (macOS) | |
| 1.4 | Lint shell release | `sh -n scripts/release/*.sh` | |
| 1.5 | Pull + stash license local (builder) | fluxo `AGENTS.md` | |

---

## 2. Build (FreeBSD builder)

| # | Item | Comando / prova | ☐ |
|---|------|-----------------|---|
| 2.1 | Build pacote | `cd package/pfSense-pkg-layer7 && make clean && DISABLE_LICENSES=yes make package DISABLE_VULNERABILITIES=yes` | |
| 2.2 | Smoke daemon | `layer7d -t` no artefacto | |
| 2.3 | SHA256 registado | `sha256 -q work/pkg/*.pkg` | |
| 2.4 | Copiar `.pkg` local se necessário | `tmp-release/` ou stage dir | |

---

## 3. Documentação (mesmo bloco)

| # | Item | Ficheiro | ☐ |
|---|------|----------|---|
| 3.1 | Links + comandos operacionais | `docs/10-license-server/MANUAL-INSTALL.md` (gate `grep releases/download/v`) | |
| 3.2 | Changelog | `docs/changelog/CHANGELOG.md` | |
| 3.3 | CORTEX checkpoint | `CORTEX.md` | |
| 3.4 | Release notes (se aplicável) | `docs/06-releases/release-notes-*.md` | |

---

## 4. Publicação GitHub (`pablomichelin/Layer7`)

| # | Item | Prova | ☐ |
|---|------|-------|---|
| 4.1 | Tag `v<PORTVERSION>` criada | `gh release view` | |
| 4.2 | Asset `.pkg` anexado | release page | |
| 4.3 | Asset `.sha256` anexado | release page | |
| 4.4 | `releases/latest` aponta para tag correcta | `gh api .../releases/latest` | |
| 4.5 | Rolling tags (`blacklists-ut1-current`) como **prerelease** | `MANUAL-INSTALL.md` §11b | |

---

## 5. Trust chain pacote (BG-028 — fase actual)

| # | Item | Estado `2026-08-05` | ☐ |
|---|------|----------------------|---|
| 5.1 | Manifesto + assinatura Ed25519 | **Não activo** — ver [ADR-0023](../03-adr/ADR-0023-trust-chain-pacote-ativacao-faseada.md) | N/A |
| 5.2 | `install.sh` carimbado fail-closed | **Não activo** — caminho manual `MANUAL-INSTALL` §1/§4 | N/A |
| 5.3 | Scripts prontos (`deployz`, `sign`, `verify`, `publish`) | Verificados no repo | ✓ |

**Quando ADR-0023 fase 1 activar:** acrescentar passos 5.4–5.8 de `RELEASE-SIGNING.md` a esta checklist.

---

## 6. Pós-publicação

| # | Item | ☐ |
|---|------|---|
| 6.1 | Commit + push `pfsense-layer7` | |
| 6.2 | Smoke mínimo lab (se mudança funcional) | `tests/lab/run-f5-smoke-checklist.sh` | |
| 6.3 | Evidência `run_id` em `docs/tests/evidence/` | |
| 6.4 | Rollback documentado (`_68` imediato pós-GO) | |

---

## Rollback release

1. `gh release delete vX.Y.Z` (humano) ou marcar superseded nas notas.
2. Reinstalar versão anterior no appliance (`MANUAL-INSTALL` §12).
3. Actualizar CORTEX se `latest` foi afectado.

---

## Referências

- [`RELEASE-SIGNING.md`](RELEASE-SIGNING.md)
- [`MANUAL-INSTALL.md`](../10-license-server/MANUAL-INSTALL.md)
- [`ADR-0003`](../03-adr/ADR-0003-hierarquia-oficial-de-distribuicao.md)
- [`ADR-0023`](../03-adr/ADR-0023-trust-chain-pacote-ativacao-faseada.md)
