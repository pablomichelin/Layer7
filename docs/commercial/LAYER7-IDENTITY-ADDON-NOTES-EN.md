# Layer7 — Commercial notes: Identity add-on (SKU Y)

**Systemup Solução em Tecnologia** · [www.systemup.inf.br](https://www.systemup.inf.br)  
**Status:** `2026-08-08` — step **20.32** (IM9) · lab/`latest` package **`1.9.29`**  
**Production enforce pin:** **`1.9.8`**

Sales / MSP / onboarding brief. Does not replace ADRs or install manuals.

---

## Offer

| SKU | `features` | Pitch |
|-----|------------|--------|
| **Standard (X)** | `base` | Application control (base product) |
| **Identity (Y)** | `base,identity` | **SME anchor** — AD user/group policies via **network** User-ID |
| **Y+ MITM** | `base,identity,mitm` | **Future** — token may exist; TLS inspection **not shipped** (DEFER) |

Legacy `full` = **base only** (T1). Identity requires an explicit re-issue.

---

## Tell the SME customer

1. Layer7 policies by **AD user/group**, not only static IP.
2. Sources: **RADIUS accounting** and/or **light DC agent** + LDAP for groups.
3. Same pfSense package; add-on is **opt-in** (package upgrade **and**
   license upgrade do not enable it). The operator turns the GUI toggle on.
4. Accuracy = **network User-ID** (IP seen at the firewall), not a per-PC agent.

## Do **not** promise

- Per-PC endpoint agent (deferred — ADR-0029).
- Terminal Server / VDI multi-user on one IP for `ad_*` policies.
- MITM / TLS decryption as available today.
- Layer7 captive portal.
- NGFW TLS parity with major vendors.

---

## References

Portuguese SSOT: [`LAYER7-IDENTITY-ADDON-NOTES-PT.md`](LAYER7-IDENTITY-ADDON-NOTES-PT.md).  
Licensing: [`../10-license-server/MANUAL-USO-LICENCAS.md`](../10-license-server/MANUAL-USO-LICENCAS.md) §14.
