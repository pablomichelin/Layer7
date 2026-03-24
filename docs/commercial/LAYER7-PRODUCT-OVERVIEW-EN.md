# Layer7 for pfSense CE — Product Overview

> **A product by [Systemup](https://www.systemup.inf.br) Solução em Tecnologia**

---

## What is Layer7?

**Layer7** is an application control solution for **pfSense CE** (Community Edition) that identifies, monitors, and blocks network traffic based on the actual application — not just by port or IP address.

While a traditional firewall only works with IP and port rules (layers 3 and 4), Layer7 operates at **Layer 7 (Application)**, identifying the actual software generating the traffic: YouTube, BitTorrent, TikTok, WhatsApp, VPN, games, streaming, and over 350 known applications.

---

## Problem it solves

| Scenario | Without Layer7 | With Layer7 |
|----------|---------------|-------------|
| Employee using BitTorrent | Impossible to detect (uses random ports) | Detects and blocks automatically |
| Students accessing TikTok/Instagram | DNS blocking is easily bypassed | Identifies by application, regardless of IP/DNS |
| Guests using VPN to bypass rules | Firewall can't distinguish | Detects WireGuard, OpenVPN, Tailscale and blocks |
| Knowing what consumes bandwidth | No visibility | Real-time dashboard with top apps and clients |

---

## Key features

### 1. Real-time application identification

Layer7 uses **Deep Packet Inspection (DPI)** technology to classify each network flow by its actual application. It recognises over **350 applications and protocols**, including:

- **Social media:** Facebook, Instagram, TikTok, Twitter/X, LinkedIn, Telegram, WhatsApp
- **Video streaming:** YouTube, Netflix, Disney+, Twitch, Amazon Prime
- **Audio streaming:** Spotify, Apple Music, Deezer
- **P2P downloads:** BitTorrent, eDonkey, Gnutella
- **Gaming:** Steam, Xbox Live, PlayStation Network
- **Video conferencing:** Zoom, Microsoft Teams, Google Meet
- **VPN/Proxy:** WireGuard, OpenVPN, Tailscale, iCloud Private Relay
- **Productivity:** Microsoft 365, Google Workspace
- **AI/Chat:** ChatGPT, AI tools
- **And hundreds more...**

### 2. Granular control policies

Create precise blocking or monitoring rules combining:

- **Specific application** — block BitTorrent but allow everything else
- **By network interface** — different rules for LAN, WiFi, Guests
- **By subnet or IP** — block social media only for the student network
- **By device group** — create named groups ("Employees", "Guests", "Directors") and apply rules per group
- **By schedule** — block YouTube only during work hours (8am-6pm, Mon-Fri)
- **By site/domain** — manually block specific domains

### 3. Four action types

| Action | Description |
|--------|-------------|
| **Monitor** | Observe and log only. No interference with traffic. |
| **Allow** | Explicitly allow traffic (useful for exceptions). |
| **Block** | Block the identified application's traffic. |
| **Tag** | Mark traffic for advanced firewall rules. |

### 4. Quick profiles (1 click)

15 pre-configured profiles for the most common scenarios:

| Profile | What it blocks |
|---------|---------------|
| YouTube | All YouTube traffic |
| Facebook | Facebook and Messenger |
| Instagram | Instagram and Stories |
| TikTok | TikTok and Musical.ly |
| WhatsApp | WhatsApp and WhatsApp Web |
| Twitter/X | Twitter and TweetDeck |
| LinkedIn | LinkedIn and LinkedIn Learning |
| Netflix | Netflix streaming |
| Spotify | Spotify streaming |
| Twitch | Twitch live streaming |
| Social Media | All social media (combo) |
| Streaming | All streaming services (combo) |
| Gaming | Steam, Xbox, PlayStation, etc. |
| VPN/Proxy | WireGuard, OpenVPN, Tailscale, etc. |
| AI Tools | ChatGPT and AI tools |

Just select the profile, choose the interface and IPs, and the policy is created automatically.

### 5. Web category blacklists (SquidGuard-style)

Category-based blacklist system with millions of classified domains:

- **70+ categories** available (pornography, gambling, malware, phishing, advertising, social media, games, etc.)
- **Rules per subnet** — block different categories for different networks
- **Exceptions per IP** — allow access for specific IPs (e.g., the director)
- **Up to 8 simultaneous rules** with different combinations
- **Automatic update** of lists on schedule
- **Global whitelist** of always-allowed domains

**Practical example:**
> Block *gambling* and *pornography* for the entire 192.168.10.0/24 network, except IP 192.168.10.1 (director).

### 6. Real-time operational dashboard

Control panel with complete visibility of what's happening on the network:

- **Real-time counters** — classified flows, blocked, allowed
- **Top 10 blocked applications** — which apps are being blocked the most
- **Top 10 clients** — which devices trigger the most blocking rules
- **Service status** — version, mode, uptime
- **Licence status** — client, validity, hardware ID

### 7. Reports module with history

Detailed reports with historical data for analysis and auditing:

- **Traffic overview** — time-series chart of classifications and blocks
- **Top blocked apps** — bar chart with the most blocked applications
- **Top blocked clients** — which devices generate the most blocks
- **Blacklists by category** — donut chart with category distribution
- **Top blocked domains** — table with the most blocked domains
- **Report by policy** — statistics for each individual rule
- **IP lookup** — complete event timeline for a specific device
- **Period filter** — 1h, 6h, 24h, 7 days, 30 days, or custom range
- **Export** — CSV, HTML (print-friendly), JSON

### 8. Flexible exceptions

Protect critical devices and networks from rules:

- Exception by **individual IP** (e.g., the administrator's computer)
- Exception by **subnet** (e.g., management network 10.0.0.0/24)
- Exception by **interface** (e.g., dedicated port for servers)
- Exceptions are **evaluated before** any policy

### 9. Time-based scheduling

- Define **days of the week** and **time range** for each policy
- Support for **overnight schedules** (e.g., 10pm to 6am)
- Policies without a schedule are **always active**

**Example:** Block social media Monday to Friday, 8am to 6pm, but allow on weekends.

### 10. Device groups

- Create **named groups** (e.g., "Sales", "HR", "IT", "Guests")
- Associate **IPs and subnets** with each group
- Use groups in policies instead of manually listing IPs
- Reusable across multiple policies

### 11. Policy testing (simulation)

- **Simulate a scenario** before enabling: "What would happen if IP 192.168.1.50 accessed YouTube?"
- Colour-coded verdict: **red** (blocked), **green** (allowed), **blue** (monitored)
- Detailed table showing each policy evaluated and why it matched or didn't

### 12. DNS anti-bypass

Multi-layer protection against attempts to circumvent blocking via alternative DNS:

- Automatic blocking of **DNS over TLS (DoT)** and **DNS over QUIC (DoQ)**
- Detection and blocking of **DNS over HTTPS (DoH)**
- Blocking of **iCloud Private Relay**
- Automatic configuration with 1 click

### 13. Selective QUIC blocking

- Toggle to block the **QUIC protocol (UDP 443)** used by Chrome/Edge
- Forces fallback to **TCP/TLS** where inspection is more effective
- Significantly improves web application identification rate

### 14. Configuration backup and restore

- **Export** entire configuration (policies, exceptions, groups, settings) to a JSON file
- **Import** configuration from another firewall or previous backup
- Ideal for replicating configurations across firewalls

### 15. Internationalisation (PT/EN)

- Interface available in **Portuguese** and **English**
- Language selector on the settings page
- All pages and messages translated

### 16. GUI-based updates

- Button to **check for new version** available
- **Automatic installation** of updates without losing configurations
- No SSH access needed

### 17. Fleet management (50+ firewalls)

For organisations with multiple branches/locations:

- **Mass update** of all firewalls via SSH
- **Custom rules synchronisation** without recompilation
- Support for **parallel updates** (e.g., 4 firewalls at once)
- **Dry-run** to preview before executing

### 18. Secure licensing

- Licence bound to the firewall's **specific hardware**
- **Ed25519** cryptographic verification offline (no "phone home")
- **14-day** grace period after expiration
- Without a valid licence: operates in **monitoring mode** (no blocking)

---

## Graphical user interface (GUI)

Layer7 integrates natively into pfSense, accessible under **Services > Layer 7** with **12 pages**:

| Page | Function |
|------|----------|
| **Status (Dashboard)** | Overview with counters, top apps, top clients, restart button |
| **Settings** | Global settings: on/off, mode, interfaces, language, QUIC, syslog |
| **Policies** | Create, edit, remove rules. Quick profiles. App/category selection |
| **Groups** | Manage named device groups |
| **nDPI Categories** | Browse all 350+ applications organised by category |
| **Test** | Simulate a scenario and see the verdict before enabling |
| **Exceptions** | Protect IPs and subnets from rules |
| **Blacklists** | Web categories (SquidGuard-style), per-subnet rules, whitelist |
| **Reports** | Historical charts, top apps/clients/domains, export |
| **Events** | Live event monitor |
| **Diagnostics** | Diagnostic tools, table status, anti-DoH |
| **Licence** | Licence status, hardware ID, activation |

---

## Typical use cases

### Business / Office
- Block social media during work hours for the employee network
- Allow full access for management
- Block BitTorrent and VPNs across the entire network
- Monitor bandwidth usage without blocking
- Monthly usage reports by department

### School / University
- Block adult content, gambling, and malware via blacklists
- Block games and streaming on the student network
- Allow YouTube for the teacher network
- Scheduling: free access outside class hours
- Block AI tools during exams

### Hotel / Public space
- Guest network: block torrents and VPNs
- Administrative network: full access
- Monitor bandwidth usage by application
- DNS anti-bypass to ensure rules cannot be circumvented

### ISP / Service provider (with pfSense)
- Offer parental controls as a premium service
- Usage reports per client
- Centralised management of 50+ firewalls

### Clinic / Hospital
- Block social media on service terminals
- Allow access to healthcare systems
- Exceptions for connected medical equipment

---

## Compatibility

| Item | Support |
|------|---------|
| **pfSense CE** | 2.7.x and 2.8.x |
| **FreeBSD** | 14 and 15 |
| **Interfaces** | Up to 8 simultaneous interfaces |
| **Policies** | Unlimited |
| **Exceptions** | Up to 16 |
| **Blacklist rules** | Up to 8 simultaneous rules |
| **Groups** | Unlimited |
| **Detectable applications** | 350+ (nDPI) |
| **Languages** | Portuguese and English |

---

## Installation

Installation is simple and quick:

1. Access pfSense via SSH
2. Run the installation command (1 line)
3. Go to **Services > Layer 7** in the pfSense web interface
4. Select the network interfaces to monitor
5. Create policies or apply quick profiles
6. Done!

Average installation time: **less than 5 minutes**.

---

## Distribution

Layer7 is distributed as a native FreeBSD `.pkg` package, installable directly on pfSense with no compilation or external dependencies required.

Updates are performed through the product's own graphical interface.

---

## About Systemup

**Systemup Solução em Tecnologia** (www.systemup.inf.br) specialises in network infrastructure and security solutions.

Layer7 for pfSense CE is developed by **Pablo Michelin**, focused on delivering enterprise-level application control capabilities to the open-source pfSense ecosystem.

---

## Contact

| | |
|--|--|
| **Website** | [www.systemup.inf.br](https://www.systemup.inf.br) |
| **Product** | Layer7 for pfSense CE |
| **Current version** | 1.4.0 |

---

*Layer7 for pfSense CE — Intelligent application control for your firewall.*

*Layer7 for pfSense CE is NOT affiliated with Netgate or the pfSense project. pfSense is a registered trademark of Electric Sheep Fencing LLC d/b/a Netgate.*
