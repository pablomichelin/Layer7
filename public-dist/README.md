# Layer7 for pfSense CE — Public Distribution

> **Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

Public repository for **signed releases and product documentation only**.
Source code and internal engineering docs are not published here.

---

## Documentation

| Document | Description |
|----------|-------------|
| [Evaluation Pack (EN)](docs/commercial/LAYER7-EVALUATION-PACK-EN.md) | Start here for product evaluation |
| [Evaluation Pack (PT)](docs/commercial/LAYER7-EVALUATION-PACK-PT.md) | Avaliação do produto |
| [Product Overview (EN)](docs/commercial/LAYER7-PRODUCT-OVERVIEW-EN.md) | Features and use cases |
| [Product Overview (PT)](docs/commercial/LAYER7-PRODUCT-OVERVIEW-PT.md) | Funcionalidades |
| [Installation Guide (EN)](docs/commercial/LAYER7-INSTALL-GUIDE-EN.md) | Install, upgrade, uninstall |
| [Installation Guide (PT)](docs/commercial/LAYER7-INSTALL-GUIDE-PT.md) | Instalação |
| [GitHub Releases](https://github.com/pablomichelin/Layer7/releases) | `.pkg`, installers, checksums, release notes |

**Licensing and activation** are not documented publicly. Contact Systemup for
commercial licenses, trials, and enforce mode.

---

## Quick install

```bash
# On pfSense CE (SSH as root). Use the latest tag from Releases:
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_65/install.sh \
  && sh /tmp/install.sh
```

Then open **Services → Layer 7** in the pfSense web UI.

---

## Modes

| Mode | Description |
|------|-------------|
| **Monitor** | Free — observe traffic, no blocking |
| **Enforce** | Commercial license from Systemup (contact sales) |

---

## Contact

- **Website:** [www.systemup.inf.br](https://www.systemup.inf.br)

For evaluation, pricing, licensing, and MSP partnerships — contact Systemup.

---

Layer7 is not affiliated with Netgate or the pfSense project.  
pfSense® is a registered trademark of Electric Sheep Fencing LLC d/b/a Netgate.
