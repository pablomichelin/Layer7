# Layer7 for pfSense CE — Public Distribution

> **Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

Public repository for **signed releases and customer-facing documentation only**.
Source code and internal engineering docs live in the **private** development
repository (`pfsense-layer7`).

---

## What this repository contains

| Content | Purpose |
|---------|---------|
| [Evaluation Pack (EN)](docs/commercial/LAYER7-EVALUATION-PACK-EN.md) | Product evaluation for prospects |
| [Evaluation Pack (PT)](docs/commercial/LAYER7-EVALUATION-PACK-PT.md) | Avaliação do produto |
| [Product Overview (EN)](docs/commercial/LAYER7-PRODUCT-OVERVIEW-EN.md) | Feature list and use cases |
| [Product Overview (PT)](docs/commercial/LAYER7-PRODUCT-OVERVIEW-PT.md) | Funcionalidades e casos de uso |
| [Installation manual](docs/10-license-server/MANUAL-INSTALL.md) | Install, upgrade, rollback |
| [Changelog](docs/changelog/CHANGELOG.md) | Version history |
| [GitHub Releases](https://github.com/pablomichelin/Layer7/releases) | `.pkg`, `install.sh`, checksums, signatures |

**This repository does not contain** source code, ADRs, internal roadmaps, license
server implementation, or enforcement internals.

---

## Quick install

```bash
# On pfSense CE (SSH as root). Use the latest tag from Releases:
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/v1.8.11_65/install.sh \
  && sh /tmp/install.sh
```

Then open **Services → Layer 7** in the pfSense web UI.

See [MANUAL-INSTALL.md](docs/10-license-server/MANUAL-INSTALL.md) for full procedures.

---

## Licensing

| Mode | Description |
|------|-------------|
| **Monitor** | Free — observe traffic, no blocking |
| **Enforce** | Requires annual license per firewall |

Activate at **[license.systemup.inf.br](https://license.systemup.inf.br)**.

For evaluation: start with monitor mode, or request a **30-day enforce trial**
from Systemup.

---

## Documentation for evaluators

1. [Evaluation Pack (EN)](docs/commercial/LAYER7-EVALUATION-PACK-EN.md) — start here
2. [Product Overview](docs/commercial/LAYER7-PRODUCT-OVERVIEW-EN.md)
3. [Installation manual](docs/10-license-server/MANUAL-INSTALL.md)

Architecture and security deep-dives are available under NDA on request.

---

## Legal

Layer7 for pfSense CE is **not** affiliated with Netgate or the pfSense project.
pfSense® is a registered trademark of Electric Sheep Fencing LLC d/b/a Netgate.

License: see [LICENSE](LICENSE).

---

## Contact

- **Website:** [www.systemup.inf.br](https://www.systemup.inf.br)
- **Licensing:** [license.systemup.inf.br](https://license.systemup.inf.br)
