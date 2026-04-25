# Release Notes — 1.8.11_12

> **Naming:** o ficheiro mantém o nome `release-notes-1.8.11_10-DRAFT.md` até
> reorganização F6/F7 (proibido renomear/mover ficheiros antes da F6, ver
> `AGENTS.md`). O **port**, os artefactos e os comandos abaixo reflectem
> **`1.8.11_12`** (`PORTREVISION=12`).

**Estado:** publicada.

**Data:** 2026-04-24
**Tag:** `v1.8.11_12`
**Repositório de distribuição:** `pablomichelin/Layer7` (público, ADR-0003)
**Artefacto (port `package/pfSense-pkg-layer7`):** `PORTVERSION=1.8.11`, `PORTREVISION=12`
**Nome do pacote FreeBSD:** `pfSense-pkg-layer7-1.8.11_12.pkg`

**Checksum:** `SHA256=902736db23fc94ae5f52d9aeaf71fcf5e75c723799209b55e5e51dcb00138dc7`

**Trust chain F1.2/F1.4 (manifesto assinado, `install.sh` carimbado
fail-closed):** **não activada** nesta release, mantendo o padrão das
publicações anteriores (`v1.7.8` a `v1.8.3`). A activação formal pela
primeira vez é tratada em `docs/02-roadmap/backlog.md` **BG-028** num bloco
controlado próprio com ADR. Esta release publica apenas `.pkg` +
`.pkg.sha256` no GitHub Releases.

---

## Resumo

Entrega acumulada de estabilidade **F4.1–F4.3** no branch de trabalho: daemon/rc.d,
reloader de blacklists, e geração **NAT** do trilho **DNS forçado** (`force_dns`)
com deduplicação, ordem estável de interfaces e CIDRs por regra.

---

## Destaques (F4.3 / BG-011 — `force_dns`)

- Deduplicação de pares **(interface, CIDR)** entre regras de blacklist com
  `force_dns` (a partir de `1.8.11_8`).
- Ordem **alfabética** das interfaces na emissão de `rdr` (a partir de
  `1.8.11_9`).
- Por regra, CIDRs IPv4 **válidos**, **únicos** e **ordenados**, validados de
  forma coerente antes do cruzamento com interfaces (a partir de `1.8.11_10`).
- Manutenção **`1.8.11_11`:** `layer7.inc` — fallback de nome de interface em
  `force_dns` reutiliza `layer7_pf_ifname_for_rules()` (DRY; sem mudança de
  comportamento).
- Manutenção **`1.8.11_12`:** `layer7.inc` — anti-QUIC por interface em
  `layer7_generate_rules()` reutiliza a mesma função (DRY; sem mudança de
  comportamento).

Ver detalhe operacional e comandos de verificação: `docs/10-license-server/MANUAL-INSTALL.md`
(addenda F4.3) e `docs/04-package/validacao-lab.md` (secção **11** e notas
`1.8.11_8`–`10`; anti-QUIC opcional no mesmo roteiro). A mesma secção **11** inclui um **cenário de lab sugerido**
multi-interface / VLAN para aproximar a evidência **BG-011** / matriz **6.7**
a combinações com vários segmentos.

---

## Instalação (caminho oficial nesta release)

Esta release **não publica** `install.sh` assinado (gate F1.2 ainda não
activo — ver **BG-028**). O caminho oficial é o **comando único manual**
documentado em `docs/10-license-server/MANUAL-INSTALL.md` §1/§4:

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.11_12.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_12/pfSense-pkg-layer7-1.8.11_12.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.11_12.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

**Após instalar/actualizar**, recompilar o ruleset PF uma vez para garantir
que as regras Layer7 entram em `/tmp/rules.debug`:

```sh
/etc/rc.filter_configure_sync && pfctl -sr | grep -i layer7
```

---

## Rollback

```sh
service layer7d onestop && pkg delete -y pfSense-pkg-layer7
```

Reinstalar a versão anterior `1.8.3` (canal antigo público, último `.pkg`
estável no padrão `.pkg + .sha256`):

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.8.3.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.8.3/pfSense-pkg-layer7-1.8.3.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.8.3.pkg && sysrc layer7d_enable=YES && service layer7d onestart
```

Ver também [`docs/05-runbooks/rollback.md`](../05-runbooks/rollback.md).

---

## Compatibilidade

- **pfSense CE:** 2.7.x / 2.8.x (validado em pfSense Plus 25.11.1, mesma base
  FreeBSD/pacote)
- **FreeBSD builder:** 15.0-RELEASE-p4

---

## Itens fechados nesta publicação

- [x] Build e `.pkg` no builder (`SHA256 902736db…`)
- [x] `CHANGELOG.md`: secção `[1.8.11_12] - 2026-04-24` aberta
- [x] `MANUAL-INSTALL.md`: **Links da versão actual** e comandos com `v1.8.11_12`
- [x] `CORTEX.md`: última release publicada actualizada para `1.8.11_12`
- [x] `BG-028` aberto no backlog para activar F1.2 num bloco futuro
- [ ] Roteiros **10a** / **10b** / **11** do `validacao-lab` executados no lab — pendentes (entram quando F4.1/F4.2/F4.3 fecharem com run_id)
