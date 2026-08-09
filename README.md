# Layer7 for pfSense CE — Public Distribution

> **Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

Public repository for **signed releases and product documentation only**.
Source code and internal engineering docs are not published here.

**Current public package (`latest`):** **`1.9.47`**  
SHA256: `2155daca7f80eb0c90af4f736d71131d01d22b63942831aa1c0191240f9df833`  
Release: [v1.9.47](https://github.com/pablomichelin/Layer7/releases/tag/v1.9.47)

---

## Documentation

| Document | Description |
|----------|-------------|
| **[Manual do Produto (PT)](docs/commercial/LAYER7-MANUAL-PRODUTO-PT.md)** | **Start here** — hub + guia completo do operador |
| [Evaluation Pack (EN)](docs/commercial/LAYER7-EVALUATION-PACK-EN.md) | Product evaluation |
| [Evaluation Pack (PT)](docs/commercial/LAYER7-EVALUATION-PACK-PT.md) | Avaliação do produto |
| [Product Overview (EN)](docs/commercial/LAYER7-PRODUCT-OVERVIEW-EN.md) | Features and use cases |
| [Product Overview (PT)](docs/commercial/LAYER7-PRODUCT-OVERVIEW-PT.md) | Funcionalidades |
| [Installation Guide (EN)](docs/commercial/LAYER7-INSTALL-GUIDE-EN.md) | Install, upgrade, uninstall |
| [Installation Guide (PT)](docs/commercial/LAYER7-INSTALL-GUIDE-PT.md) | Instalação |
| [GitHub Releases](https://github.com/pablomichelin/Layer7/releases) | `.pkg`, checksums, release notes |

**Licensing and activation** are not documented in depth publicly. Contact
Systemup for commercial licenses, trials, and enforce mode.

---

## Quick install (`1.9.47`)

On pfSense (SSH as **root**). Official path for this release is **fetch + `pkg add`**
(`install.sh` is not attached to `v1.9.47`):

```bash
fetch -o /tmp/pfSense-pkg-layer7-1.9.47.pkg \
  https://github.com/pablomichelin/Layer7/releases/download/v1.9.47/pfSense-pkg-layer7-1.9.47.pkg \
  && IGNORE_OSVERSION=yes pkg add -f /tmp/pfSense-pkg-layer7-1.9.47.pkg \
  && sysrc layer7d_enable=YES \
  && service layer7d onestart \
  && layer7d -V
```

Verify SHA256 against
`2155daca7f80eb0c90af4f736d71131d01d22b63942831aa1c0191240f9df833`
(see the [product manual](docs/commercial/LAYER7-MANUAL-PRODUTO-PT.md)).

Then open **Services → Layer 7** in the pfSense web UI.

---

## Modes

| Mode | Description |
|------|-------------|
| **Monitor** | Observe traffic, no Layer7 blocking |
| **Enforce** | Commercial license from Systemup (contact sales) |

**MITM** (TLS inspection) ships **OFF** by default and is not released for
external/permanent pilot without an explicit commercial GO and site scope.

---

## Contact

- **Website:** [www.systemup.inf.br](https://www.systemup.inf.br)

For evaluation, pricing, licensing, and MSP partnerships — contact Systemup.

---

Layer7 is not affiliated with Netgate or the pfSense project.  
pfSense® is a registered trademark of Electric Sheep Fencing LLC d/b/a Netgate.
