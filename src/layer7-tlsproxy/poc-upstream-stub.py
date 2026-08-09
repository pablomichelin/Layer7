#!/usr/bin/env python3
"""Loopback HTTP stub for PoC-4 upstream tests. Bind 127.0.0.1 only."""
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import sys

PORT = int(sys.argv[1]) if len(sys.argv) > 1 else 19080


class H(BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200)
        self.send_header("Content-Type", "text/plain")
        self.end_headers()
        self.wfile.write(b"UPSTREAM_OK\n")

    def log_message(self, *_args):
        return


if __name__ == "__main__":
    ThreadingHTTPServer(("127.0.0.1", PORT), H).serve_forever()
