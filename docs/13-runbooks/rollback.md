# Rollback — pfSense-pkg-layer7

## Objetivo

Reverter a instalação do pacote Layer7 no pfSense, restaurando o estado
anterior do appliance **sem** deixar enforcement ou artefactos inconsistentes.

---

## Preferido: `pkg add -f` sem `pkg delete`

Upgrade/rollback in-place. O hook vê `PKG_UPGRADE` e **preserva**
`layer7.json`, `layer7.lic`, CA MITM, secrets Identity, `profiles-custom.json`
e o estado de check-in em `/var/db` (BG-128 P1-7/P1-8). O staging de
CA/secrets é `/var/db/layer7/deinstall-preserve` (0700, 0600 nos
segredos); se o backup obrigatório falhar, o hook **não** faz
`rm -rf /usr/local/etc/layer7` (A1/A2).

```sh
fetch -o /tmp/pfSense-pkg-layer7-VERSAO.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/vVERSAO/pfSense-pkg-layer7-VERSAO.pkg
IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-VERSAO.pkg
sysrc layer7d_enable=YES
service layer7d onestart
layer7d -V
```

Versões canónicas: lab `1.9.62`; soak `1.9.59`; enforce produção `1.9.0`
(pin histórico; produção enforce permanece `1.9.8`).

---

## `pkg delete` (desinstalação real)

```sh
pkg delete -y pfSense-pkg-layer7
```

**Sem** `/var/run/layer7-uninstall-keep-config` (nem `--keep-config`), o
POST-DEINSTALL **apaga**:

- `/usr/local/etc/layer7.json` e `/usr/local/etc/layer7.lic`
- `/usr/local/etc/layer7/` (cache; CA/secrets só se não houver keep-config)
- `/var/db/layer7-checkin.json`
- `/var/db/layer7/clock-mark.json`
- `/var/db/layer7/content-subscription.json`
- overrides anti-DoH do Unbound (P2-12)

Para apagar o pacote e **manter** licença/config/CA/secrets/check-in:

```sh
touch /var/run/layer7-uninstall-keep-config
pkg delete -y pfSense-pkg-layer7
```

Ou: `sh uninstall.sh --keep-config --yes`.

---

## O que o `pkg delete` remove (ficheiros do port)

- `/usr/local/sbin/layer7d`
- `/usr/local/pkg/layer7.xml` / `layer7.inc`
- `/usr/local/www/packages/layer7/*.php`
- `/usr/local/etc/rc.d/layer7d`
- `/usr/local/etc/layer7.json.sample`
- `/etc/inc/priv/layer7.priv.inc`
- `/usr/local/share/pfSense-pkg-layer7/info.xml`

O hook também pára o daemon, faz flush PF `layer7_*` e `sysrc layer7d_enable=NO`.

---

## Reinstalar versão anterior

Preferir o `pkg add -f` da tag desejada (secção *Preferido*). O `install.sh`
versionado da release também serve.

Se o fluxo oficial de reinstall do `MANUAL-INSTALL` usar `pkg delete` +
`pkg add -f`, **sem** keep-config a licença e as políticas **não** sobrevivem
— é preciso reactivar.

---

## Rollback completo (snapshot)

1. Parar o pacote: `service layer7d onestop`
2. Remover com keep-config se quiser repor o `.pkg` depois
3. Ou restaurar snapshot da VM

---

## Verificação pós-rollback

```sh
pkg info | grep layer7
ps auxww | grep layer7d | grep -v grep
ls -la /usr/local/sbin/layer7d 2>&1
ls -la /usr/local/etc/layer7.json /usr/local/etc/layer7.lic 2>&1
ls -la /var/db/layer7-checkin.json 2>&1
```

Após desinstalação **completa**, json/`.lic`/check-in devem estar ausentes.
Após `pkg add -f` de rollback, json/`.lic`/check-in devem ter sobrevivido.
