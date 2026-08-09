# Layer7 for pfSense CE — Installation Guide

**Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

> Customer installation guide.  
> **Reference version:** **`1.9.47`**  
> **SHA256:** `2155daca7f80eb0c90af4f736d71131d01d22b63942831aa1c0191240f9df833`  
> Full operator manual (PT): [LAYER7-MANUAL-PRODUTO-PT.md](LAYER7-MANUAL-PRODUTO-PT.md)  
> Licensing/activation: contact Systemup — not detailed in this public repository.

---

## Requirements

- pfSense CE 2.7.x or 2.8.x (validate in your environment)
- SSH as **root** (or Diagnostics → Command Prompt)
- Capture interfaces already configured

---

## Install — `1.9.47`

> Official path for this release: **fetch + `pkg add`**.  
> `install.sh` is **not** attached to `v1.9.47`.

**One-liner:**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.47.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.47/pfSense-pkg-layer7-1.9.47.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.47.pkg && sysrc layer7d_enable=YES && service layer7d onestart && layer7d -V
```

**Integrity check:**

```sh
fetch -o /tmp/pfSense-pkg-layer7-1.9.47.pkg.sha256 \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.47/pfSense-pkg-layer7-1.9.47.pkg.sha256 \
  && sha256 -q /tmp/pfSense-pkg-layer7-1.9.47.pkg | tee /tmp/l7-actual.sha256 \
  && cat /tmp/pfSense-pkg-layer7-1.9.47.pkg.sha256
```

Expected: `2155daca7f80eb0c90af4f736d71131d01d22b63942831aa1c0191240f9df833`.

Then open **Services → Layer 7** in the pfSense GUI.

---

## Upgrade to `1.9.47`

```sh
service layer7d onestop && fetch -o /tmp/pfSense-pkg-layer7-1.9.47.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.47/pfSense-pkg-layer7-1.9.47.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.47.pkg && service layer7d onestart && layer7d -V
```

```sh
/etc/rc.filter_configure_sync
pfctl -sr | grep -i layer7
```

Or use **Services → Layer 7 → Settings → Check for updates** in the GUI
(`releases/latest`).

---

## Uninstall

```sh
service layer7d onestop
sysrc -x layer7d_enable
pkg delete -y pfSense-pkg-layer7
```

---

## Lab rollback (`1.9.46`)

```sh
service layer7d onestop && fetch -o /tmp/pfSense-pkg-layer7-1.9.46.pkg https://github.com/pablomichelin/Layer7/releases/download/v1.9.46/pfSense-pkg-layer7-1.9.46.pkg && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.46.pkg && service layer7d onestart && layer7d -V
```

---

## After install

1. **Services → Layer 7 → Settings**
2. Enable the service and select capture interfaces
3. Start in **Monitor** mode
4. Create policies or apply quick profiles
5. For **Enforce**, contact **Systemup** for a commercial license
6. Keep **MITM OFF** unless you have explicit commercial guidance

---

## Support

- **Full manual (PT):** [LAYER7-MANUAL-PRODUTO-PT.md](LAYER7-MANUAL-PRODUTO-PT.md)
- **Website:** [www.systemup.inf.br](https://www.systemup.inf.br)
- **Evaluation:** [Evaluation Pack](LAYER7-EVALUATION-PACK-EN.md)
- **Releases:** [github.com/pablomichelin/Layer7/releases](https://github.com/pablomichelin/Layer7/releases)

Layer7 is not affiliated with Netgate or the pfSense project.
