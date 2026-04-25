# Release Notes — 1.8.11_13

**Estado:** publicada.

**Data:** 2026-04-24
**Tag:** `v1.8.11_13`
**Repositório de distribuição:** `pablomichelin/Layer7` (público, ADR-0003)
**Artefacto (port `package/pfSense-pkg-layer7`):** `PORTVERSION=1.8.11`, `PORTREVISION=13`
**Nome do pacote FreeBSD:** `pfSense-pkg-layer7-1.8.11_13.pkg`

**Checksum:** `SHA256=041e1ace4611ebb1cebd7bfadc22e0bb2c9b2b24b99900e3034f107b534351ae`

**Trust chain F1.2/F1.4 (pacote — manifesto assinado, `install.sh` carimbado
fail-closed):** **não activada** nesta release, mantendo o padrão das
publicações anteriores (`v1.7.8` a `v1.8.11_12`). A activação formal pela
primeira vez é tratada em `docs/02-roadmap/backlog.md` **BG-028** num bloco
controlado próprio com ADR. Esta release publica apenas `.pkg` +
`.pkg.sha256` no GitHub Releases.

**Trust chain F1.3 (blacklists — manifesto Ed25519 assinado, fail-closed
F1.4):** **agora activada pela primeira vez na vida do produto.** Esta
release rotaciona a chave pública embutida no pacote (a chave anterior nunca
chegou a assinar uma snapshot pública) e publica em paralelo a primeira
snapshot UT1 oficial assinada em `pablomichelin/Layer7` na rolling tag
`blacklists-ut1-current`.

---

## Resumo

Release de habilitação operacional da **trilha F1.3 (blacklists assinadas)**
sobre a base estável `1.8.11_12`. Sem alteração funcional no daemon, na GUI
ou no enforcement PF — o único delta de código é a substituição da chave
pública Ed25519 pré-existente pela nova chave em uso. Toda a evolução
F4.1/F4.2/F4.3 anterior é preservada sem regressão.

---

## Destaques

- **Rotação da chave Ed25519 pública embutida no pacote**:
  - antes: `e501f5635bf56c6dfc6891ee969ef04ff193ed3afc879997bd4066b6ba3cb064`
  - **nova**: `6190b8d26fb9cb951ccb2c1f4e921228e4edf388c23f51afd93f1fd3ca1ba4fc`
  - chave **privada** correspondente em custódia humana, fora do builder e
    fora do repositório (alinhado com F1.3 e `AGENTS.md` "Quando parar e
    pedir validação humana / mexer em segredos, chaves").
  - rotação **gratuita**: a chave anterior nunca foi usada para assinar uma
    snapshot pública, logo nenhuma instalação em campo dependia dela.
- **Primeira publicação oficial da snapshot UT1 assinada (F1.3)**:
  - release rolling: `https://github.com/pablomichelin/Layer7/releases/tag/blacklists-ut1-current`
  - `snapshot_id`: `ut1-2026-04-25`
  - 69 categorias, 6 623 069 domínios, 31 169 229 bytes
  - `SHA256` do tar.gz: `4191e2ebdc13e3c87d777103528bab4fda6b273bc40c62a2c39cb820ad493d36`
  - upstream (autoridade de conteúdo): UT1 / Université Toulouse Capitole
- **Fail-closed F1.4 explícito**: pacotes anteriores a `1.8.11_13` recusam
  este manifesto por *fingerprint mismatch* da chave pública — comportamento
  esperado e documentado.
- **Sem regressão**: todo o trabalho F4.1/F4.2/F4.3 acumulado em
  `1.8.11_12` permanece (force_dns, anti-QUIC, DRY
  `layer7_pf_ifname_for_rules`, hooks rc.d, reload preservando estado, DNS
  forçado por NAT no anchor `natrules/layer7_nat`).

---

## Instalação (caminho oficial nesta release)

Esta release **não publica** `install.sh` assinado (gate F1.2 ainda não
activo — ver **BG-028**). O caminho oficial é o **comando único manual**
documentado em `docs/10-license-server/MANUAL-INSTALL.md` §1/§4:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_13.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_13/pfSense-pkg-layer7-1.8.11_13.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_13.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

**Após instalar/actualizar**, recompilar o ruleset PF uma vez para garantir
que as regras Layer7 entram em `/tmp/rules.debug`:

```sh
/etc/rc.filter_configure_sync && pfctl -sr | grep -i layer7
```

**Activar / actualizar blacklists UT1 (F1.3)** — só depois de instalar
`1.8.11_13`:

```sh
/usr/local/etc/layer7/update-blacklists.sh --download
```

---

## Rollback

```sh
service layer7d onestop && pkg delete -y pfSense-pkg-layer7
```

Reinstalar a versão anterior `1.8.11_12`:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_12.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_12/pfSense-pkg-layer7-1.8.11_12.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_12.pkg && sysrc layer7d_enable=YES && service layer7d onestart
```

> **Nota sobre rollback de blacklists:** ao voltar para `1.8.11_12` ou
> anterior, a chave pública embutida no pacote volta a ser a antiga
> (`e501f56…`). A snapshot publicada em `blacklists-ut1-current` é assinada
> pela chave **nova**, logo o updater do pacote anterior recusa o manifesto
> (fail-closed F1.4 — comportamento correcto). Para manter blacklists
> activas após rollback, é preciso re-instalar `1.8.11_13`.

Ver também [`docs/05-runbooks/rollback.md`](../05-runbooks/rollback.md).

---

## Compatibilidade

- **pfSense CE:** 2.7.x / 2.8.x (validado em pfSense Plus 25.11.1, mesma base
  FreeBSD/pacote)
- **FreeBSD builder:** 15.0-RELEASE-p4

---

## Itens fechados nesta publicação

- [x] Build e `.pkg` no builder (`SHA256 041e1ace…`)
- [x] `CHANGELOG.md`: secção `[1.8.11_13] - 2026-04-24` aberta
- [x] `MANUAL-INSTALL.md`: **Links da versão actual** e comandos com
      `v1.8.11_13`; nova secção **11b** (activar UT1 após instalar)
- [x] `CORTEX.md`: última release publicada actualizada para `1.8.11_13`;
      checkpoint canónico actualizado
- [x] **Trilha F1.3 de blacklists efectivamente activada** com primeira
      snapshot UT1 pública assinada (release rolling
      `blacklists-ut1-current`)
- [ ] Roteiros **10a** / **10b** / **11** do `validacao-lab` executados no
      lab — pendentes (entram quando F4.1/F4.2/F4.3 fecharem com `run_id`)
