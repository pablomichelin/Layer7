#!/usr/bin/env python3
"""Lab IPC peer mock — STATUS/DECIDE. Not layer7d product. mitm_effective always false."""
import json, os, socket, struct, sys, time

SOCK = os.environ.get("L7_IPC_SOCK", "/tmp/layer7-tlsproxy-decide.sock")
MAX = 4096


def recv_frame(conn):
    hdr = conn.recv(4)
    if len(hdr) < 4:
        return None
    n = struct.unpack("!I", hdr)[0]
    if n == 0 or n > MAX:
        return None
    body = b""
    while len(body) < n:
        chunk = conn.recv(n - len(body))
        if not chunk:
            return None
        body += chunk
    return body.decode("utf-8", "replace")


def send_frame(conn, obj):
    raw = json.dumps(obj, separators=(",", ":")).encode()
    conn.sendall(struct.pack("!I", len(raw)) + raw)


def handle(msg: str):
    try:
        j = json.loads(msg) if msg.strip() else {}
    except json.JSONDecodeError:
        j = {}
    op = j.get("op", "PING")
    ts = int(time.time())
    if op == "PING":
        return {"op": "PING", "ok": True, "ts": ts, "mitm_effective": False}
    if op == "STATUS":
        return {
            "op": "STATUS",
            "ok": True,
            "ts": ts,
            "mitm_effective": False,
            "mitm_entitled": False,
            "runtime": "lab-mock",
        }
    if op == "DECIDE":
        sni = (j.get("sni") or "").lower()
        verdict = "block" if sni.endswith("blocked.test") else "allow"
        if "bank." in sni:
            verdict = "bypass"
        return {
            "op": "DECIDE",
            "ok": True,
            "ts": ts,
            "verdict": verdict,
            "mitm_effective": False,
            "sni": sni,
        }
    return {"op": "ERROR", "ok": False, "mitm_effective": False, "error": "unknown_op"}


def main():
    if os.environ.get("LAYER7_TLSPROXY_LAB") != "1":
        print("refusing: set LAYER7_TLSPROXY_LAB=1", file=sys.stderr)
        return 3
    if not SOCK.startswith("/tmp/"):
        print("refusing sock path", file=sys.stderr)
        return 3
    try:
        os.unlink(SOCK)
    except FileNotFoundError:
        pass
    srv = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    srv.bind(SOCK)
    os.chmod(SOCK, 0o600)
    srv.listen(4)
    print(f"ipc-mock listen {SOCK} mitm_effective=false", flush=True)
    oneshot = "--oneshot" in sys.argv
    while True:
        conn, _ = srv.accept()
        with conn:
            msg = recv_frame(conn)
            if msg is None:
                continue
            send_frame(conn, handle(msg))
        if oneshot:
            break
    srv.close()
    try:
        os.unlink(SOCK)
    except FileNotFoundError:
        pass
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
