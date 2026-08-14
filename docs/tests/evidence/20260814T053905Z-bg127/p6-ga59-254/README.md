# P6 — GA5.9 (revogação) no `.254`

**Run ID:** `20260814T053905Z-bg127` · **Host:** `192.168.100.254` · **Veredicto:** **FAIL** (campo)

Revogação no painel do id **14** (`BG-127-TEST`) succedeu. O cliente oficial `1.9.63` envia `nonce` (30.13); o API **live** rejeita o campo (`400`). N3 manteve `valid=1`. Probe legado sem nonce: `409 revoked` (servidor correcto; resposta **não** assinada). Produção restaurada. Id **13** intacto.
