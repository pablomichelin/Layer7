# Layer7 for pfSense CE — Installation Guide

**Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

> Customer-facing install guide. For licensing and activation, contact Systemup
> directly — not covered in this public document.

---

## Requirements

- pfSense CE 2.7.x or 2.8.x
- SSH access as **root**
- Network interfaces you want to monitor already configured

---

## Install (latest release)

```bash
# On pfSense — only the current public package (`latest` = 1.9.76):
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.76/install.sh \
  && sh /tmp/install.sh
```

Then open **Services → Layer 7** in the pfSense web UI.

---

## Upgrade

```bash
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.76/install.sh \
  && sh /tmp/install.sh
```

Or use **Services → Layer 7 → Settings → Check for updates** in the GUI.

---

## Uninstall

```bash
fetch -o /tmp/uninstall.sh \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.76/uninstall.sh \
  && sh /tmp/uninstall.sh
```

---

## Verify package integrity

Each release publishes:

- `pfSense-pkg-layer7-VERSION.pkg`
- `pfSense-pkg-layer7-VERSION.pkg.sha256`
- Signed release manifest (when enabled for that release)

Download assets from:  
[github.com/pablomichelin/Layer7/releases](https://github.com/pablomichelin/Layer7/releases)

---

## After installation

1. Go to **Services → Layer 7 → Settings**
2. Enable the service and select capture interfaces
3. Start with **Monitor** mode to observe traffic without blocking
4. Create policies or apply quick profiles
5. For **Enforce** (blocking), contact **Systemup** for a commercial license

---

## Support

- **Website:** [www.systemup.inf.br](https://www.systemup.inf.br)
- **Product evaluation:** [Evaluation Pack](LAYER7-EVALUATION-PACK-EN.md)

Layer7 is not affiliated with Netgate or the pfSense project.
