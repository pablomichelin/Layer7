# Layer7 for pfSense CE — Public Distribution

> **Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

Public repository for **the current signed package and product documentation**.
Source code and internal engineering docs are not published here.

**Current public package (`latest`):** **`1.9.77`**  
SHA256: `1b595f5014316f0fa25e52a974b1e7137a13ec443af80f5e000849c103445f57`  
Release: [v1.9.77](https://github.com/pablomichelin/Layer7/releases/tag/v1.9.77)

This repository publishes **only the latest package**. Older package releases
are not available for download.

---

## Documentation

| Document | Description |
|----------|-------------|
| **[Manual do Produto (PT)](docs/commercial/LAYER7-MANUAL-PRODUTO-PT.md)** | **Start here** — hub + guia do operador |
| [Evaluation Pack (EN)](docs/commercial/LAYER7-EVALUATION-PACK-EN.md) | Product evaluation |
| [Evaluation Pack (PT)](docs/commercial/LAYER7-EVALUATION-PACK-PT.md) | Avaliação do produto |
| [Product Overview (EN)](docs/commercial/LAYER7-PRODUCT-OVERVIEW-EN.md) | Features and use cases |
| [Product Overview (PT)](docs/commercial/LAYER7-PRODUCT-OVERVIEW-PT.md) | Funcionalidades |
| [Installation Guide (EN)](docs/commercial/LAYER7-INSTALL-GUIDE-EN.md) | Install, upgrade, uninstall |
| [Installation Guide (PT)](docs/commercial/LAYER7-INSTALL-GUIDE-PT.md) | Instalação |
| [GitHub Releases](https://github.com/pablomichelin/Layer7/releases/latest) | Current `.pkg`, installer, checksums |

**Licensing and activation** are not documented in depth publicly. Contact
Systemup for commercial licenses, trials, and enforce mode.

---

## Quick install (`1.9.77`)

On pfSense CE (SSH as **root**):

```bash
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.77/install.sh \
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
