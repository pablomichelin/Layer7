# Sync fail ~25s — root cause + fix 1.9.44

## Root cause (E)

`layer7_exec_timeout` wraps `service … onestart` with FreeBSD `/usr/bin/timeout` **without** `--foreground`.
Default mode tracks the process group; the daemonized `layer7-tlsproxy` stays in that group, so:

1. `timeout` does not return when `daemon`/`service` returns;
2. after ~20s (+5s `-k`) it kills the group, including the helper that already printed mint-mode ON / listen;
3. `layer7_mitm_helper_listening()` is false → `sync_helper` cleanup → `sync=fail` / `sync_sec≈25.5`.

This also explains the older 141s hang with peer leaf UP (no `-k`: waiter blocked, child survived).

## Fix

- `layer7.inc`: `timeout --foreground -k …`
- `rc.d/layer7-tlsproxy`: `daemon -f -p` (stdio detached)

## Proof on .254 after install 1.9.44

- `SYNC=ok sync_sec=0.332 listen=yes`
- leaf `CN=mitm-lab.test` / issuer lab CA / EKU serverAuth
- left MITM OFF
