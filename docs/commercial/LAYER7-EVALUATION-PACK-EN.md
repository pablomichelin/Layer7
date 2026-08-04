# Layer7 for pfSense CE — Evaluation Pack

**Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)

> Public evaluation document. Describes **what the product does**, not internal
> implementation. For architecture or security deep-dives, contact Systemup
> (NDA may apply).

---

## 1. Executive summary

**Layer7** is a commercial application-control add-on for **pfSense CE**. It
identifies network traffic by real application (YouTube, BitTorrent, TikTok,
VPN, etc.) using Deep Packet Inspection (nDPI), and lets administrators
**monitor**, **allow**, or **block** traffic with policies by interface,
subnet, device group, and schedule — integrated into the pfSense GUI, **without
MITM**.

| | |
|--|--|
| **Vendor** | Systemup Solução em Tecnologia |
| **Product** | Layer7 for pfSense CE |
| **License server** | [license.systemup.inf.br](https://license.systemup.inf.br) |
| **Public downloads** | [GitHub Releases](https://github.com/pablomichelin/Layer7/releases) |
| **Monitor mode** | Free (observation only, no blocking) |
| **Enforce mode** | Valid annual license required |

Layer7 is **not** affiliated with Netgate or the pfSense project. pfSense® is a
registered trademark of Electric Sheep Fencing LLC d/b/a Netgate.

---

## 2. Problem solved

| Scenario | Without Layer7 | With Layer7 |
|----------|----------------|-------------|
| Employee using BitTorrent | Hard to detect (random ports) | Detected and blocked by application |
| Students on TikTok/Instagram | DNS blocks are easy to bypass | Identified by app, not just IP/DNS |
| VPN bypass | Firewall cannot distinguish | Detects WireGuard, OpenVPN, Tailscale, etc. |
| Bandwidth visibility | Limited | Dashboard with top apps and clients |

---

## 3. Key capabilities

- **350+ applications** classified in real time (social, streaming, P2P, games, VPN, AI tools, etc.)
- **Policy actions:** Monitor, Allow, Block, Tag
- **Quick profiles (1-click):** YouTube, Facebook, Instagram, TikTok, WhatsApp, Social combo, Streaming, Games, VPN/Proxy, AI Tools, and more
- **Web category blacklists** (70+ categories, SquidGuard-style)
- **Scheduling** by day and time range (including overnight)
- **Device groups** for reusable policy targets
- **Policy simulation** before enabling rules
- **Reports** with history and export (CSV, HTML, JSON)
- **DNS anti-bypass** (DoT/DoQ/DoH mitigation options)
- **Selective QUIC blocking** for better web app identification
- **Backup/restore** of configuration (JSON)
- **GUI update check** from the pfSense interface
- **Fleet tools** for bulk updates across multiple firewalls (MSP use case)
- **Bilingual UI:** Portuguese and English

---

## 4. Compatibility

| Item | Support |
|------|---------|
| **pfSense CE** | 2.7.x and 2.8.x |
| **FreeBSD** | 14 and 15 |
| **Interfaces** | Up to 8 simultaneous |
| **Policies** | Unlimited |
| **Blacklist rules** | Up to 8 simultaneous |
| **Languages** | Portuguese, English |

Contact Systemup for pfSense Plus compatibility in your environment.

---

## 5. Licensing model

| Mode | Blocking | License |
|------|----------|---------|
| **Monitor** | No | Not required (free) |
| **Enforce** | Yes | Annual license per firewall (hardware-bound) |

- Activation via `license.systemup.inf.br`
- Offline validation with Ed25519-signed `.lic` file
- **14-day grace** after expiration for an already-issued license
- Without a valid license: system stays in **monitor-only** mode

---

## 6. Pricing (USD)

Annual license per firewall. Initial installation is a one-time fee (year 1).

| Plan | Best for | License / year | Initial install | **Year 1 total** |
|------|----------|----------------|-----------------|------------------|
| **Starter** | Small office (~30 users) | $349 | $550 | **$899** |
| **Professional** | SMB (30–150 users) | $649 | $750 | **$1,399** |
| **Business** | Larger sites (150–500 users) | $999 | $950 | **$1,949** |
| **Education** | Schools (verified) | $499 | $650 | **$1,149** |
| **Enterprise** | Critical / ISP / hospital | $1,499 | $1,450 | **$2,949** |

**Renewal (year 2+):** license fee only (no re-installation unless new appliance).

| Add-on | Price |
|--------|-------|
| 30-day enforce trial | On request |
| On-site installation | +$800 (travel excluded) |
| Managed service (Premium) | from $349/month |
| 3-year prepay | 15% discount |

Prices are indicative. Contact Systemup for MSP volume quotes and formal proposals.

---

## 7. Installation (overview)

Installation uses a signed `.pkg` from GitHub Releases and a one-line installer script.
Full operational steps: [MANUAL-INSTALL.md](../10-license-server/MANUAL-INSTALL.md).

```bash
# On pfSense (SSH as root) — replace VERSION with the latest release tag:
fetch -o /tmp/install.sh \
  https://github.com/pablomichelin/Layer7/releases/download/vVERSION/install.sh \
  && sh /tmp/install.sh
```

Then open **Services → Layer 7** in the pfSense web UI.

Average setup time: **under 5 minutes** (excluding policy design).

---

## 8. Comparison with common alternatives

| | **ntopng** | **pfBlockerNG** | **Layer7** |
|--|------------|-----------------|------------|
| **Primary focus** | Traffic analytics | DNS/IP lists | Application policy enforcement |
| **Block by app** | Not core | Limited | Yes |
| **pfSense native GUI** | Separate tool | Partial | Yes (integrated) |
| **Quick profiles** | No | No | Yes |
| **Scheduling** | No | Limited | Yes |

Layer7 complements visibility tools; it is built for **enforcing policies** on
pfSense, not replacing full network forensics platforms.

---

## 9. Evaluation process

| Step | Action |
|------|--------|
| 1 | Review this pack + [Product Overview](LAYER7-PRODUCT-OVERVIEW-EN.md) |
| 2 | Install on lab pfSense (monitor mode is free) |
| 3 | Request **30-day enforce trial** if blocking tests are needed |
| 4 | Optional: security/architecture review under NDA |

**What we share without NDA:** product capabilities, compatibility, installation,
licensing model, pricing.

**What requires NDA or scoped engagement:** internal architecture, enforcement
internals, license-server implementation details.

---

## 10. Contact

| | |
|--|--|
| **Website** | [www.systemup.inf.br](https://www.systemup.inf.br) |
| **Licensing** | [license.systemup.inf.br](https://license.systemup.inf.br) |
| **Downloads** | [github.com/pablomichelin/Layer7/releases](https://github.com/pablomichelin/Layer7/releases) |

For evaluation licenses, proposals, or MSP partnerships, contact Systemup through
the website or your Systemup representative.

---

*Document version: 2026-08-04 · Systemup Solução em Tecnologia*
