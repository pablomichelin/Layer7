# P5 — GA4.8 (token offline)

**Run ID:** `20260814T224213Z-bg127` · **Host:** `192.168.100.254`  
**Veredicto:** **PASS**

`/etc/hosts` → 127.0.0.2 **não** isolou o `curl` (Unbound). Isolamento
efectivo: `HTTPS_PROXY=http://127.0.0.2:1` só no `update-blacklists.sh`.
