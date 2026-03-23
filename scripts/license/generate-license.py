#!/usr/bin/env python3
"""
Layer7 License Tool — generate Ed25519 key pairs and signed license files.

Prerequisites:
    pip install PyNaCl   (or: pip install cryptography)

Usage:
    # 1. Generate a new key pair (only once, keep private key SECRET):
    python3 generate-license.py keygen

    # 2. Generate a license for a specific hardware ID:
    python3 generate-license.py sign \
        --private-key layer7-private.key \
        --hardware-id <64-char-hex> \
        --customer "Company Name" \
        --expiry 2027-01-01 \
        --features full \
        --output layer7.lic

    # 3. Show the C array for the public key (embed in license.c):
    python3 generate-license.py c-pubkey --public-key layer7-public.key
"""

import argparse
import json
import os
import sys
from datetime import datetime


def _load_nacl():
    try:
        from nacl.signing import SigningKey, VerifyKey
        from nacl.encoding import HexEncoder
        return SigningKey, VerifyKey, HexEncoder, "pynacl"
    except ImportError:
        pass
    try:
        from cryptography.hazmat.primitives.asymmetric.ed25519 import (
            Ed25519PrivateKey,
        )
        from cryptography.hazmat.primitives import serialization
        return Ed25519PrivateKey, None, serialization, "cryptography"
    except ImportError:
        pass
    print("ERROR: install PyNaCl (pip install PyNaCl) or "
          "cryptography (pip install cryptography)", file=sys.stderr)
    sys.exit(1)


def cmd_keygen(args):
    SK, VK, Enc, lib = _load_nacl()
    if lib == "pynacl":
        sk = SK.generate()
        priv_hex = sk.encode(Enc).decode()
        pub_hex = sk.verify_key.encode(Enc).decode()
    else:
        from cryptography.hazmat.primitives.asymmetric.ed25519 import (
            Ed25519PrivateKey,
        )
        from cryptography.hazmat.primitives import serialization
        sk = Ed25519PrivateKey.generate()
        priv_bytes = sk.private_bytes(
            serialization.Encoding.Raw,
            serialization.PrivateFormat.Raw,
            serialization.NoEncryption(),
        )
        pub_bytes = sk.public_key().public_bytes(
            serialization.Encoding.Raw,
            serialization.PublicFormat.Raw,
        )
        priv_hex = priv_bytes.hex()
        pub_hex = pub_bytes.hex()

    priv_path = args.private_key or "layer7-private.key"
    pub_path = args.public_key or "layer7-public.key"

    with open(priv_path, "w") as f:
        f.write(priv_hex + "\n")
    os.chmod(priv_path, 0o600)

    with open(pub_path, "w") as f:
        f.write(pub_hex + "\n")

    print(f"Private key: {priv_path}")
    print(f"Public key:  {pub_path}")
    print(f"\nPublic key hex: {pub_hex}")
    print(f"\nKEEP {priv_path} SECRET. Never commit it to git.")
    print(f"Embed the public key in license.c (use 'c-pubkey' command).")


def cmd_sign(args):
    SK, VK, Enc, lib = _load_nacl()

    priv_hex = open(args.private_key).read().strip()
    if len(priv_hex) != 64:
        print("ERROR: private key must be 64 hex chars (32 bytes)",
              file=sys.stderr)
        sys.exit(1)

    data = json.dumps({
        "hardware_id": args.hardware_id,
        "expiry": args.expiry,
        "customer": args.customer,
        "features": args.features,
        "issued": datetime.now().strftime("%Y-%m-%d"),
    }, separators=(",", ":"))

    data_bytes = data.encode("utf-8")

    if lib == "pynacl":
        from nacl.signing import SigningKey
        from nacl.encoding import HexEncoder
        sk = SigningKey(bytes.fromhex(priv_hex))
        signed = sk.sign(data_bytes)
        sig_hex = signed.signature.hex()
    else:
        from cryptography.hazmat.primitives.asymmetric.ed25519 import (
            Ed25519PrivateKey,
        )
        sk = Ed25519PrivateKey.from_private_bytes(bytes.fromhex(priv_hex))
        sig = sk.sign(data_bytes)
        sig_hex = sig.hex()

    lic = json.dumps({"data": data, "sig": sig_hex}, indent=2)

    output = args.output or "layer7.lic"
    with open(output, "w") as f:
        f.write(lic + "\n")

    print(f"License written to: {output}")
    print(f"  Hardware ID: {args.hardware_id[:16]}...")
    print(f"  Customer:    {args.customer}")
    print(f"  Expiry:      {args.expiry}")
    print(f"  Features:    {args.features}")


def cmd_c_pubkey(args):
    pub_hex = open(args.public_key).read().strip()
    if len(pub_hex) != 64:
        print("ERROR: public key must be 64 hex chars (32 bytes)",
              file=sys.stderr)
        sys.exit(1)

    pub_bytes = bytes.fromhex(pub_hex)

    print("/*")
    print(" * Ed25519 public key for license verification.")
    print(f" * Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print(" * Replace the placeholder in license.c with this block.")
    print(" */")
    print("static const unsigned char l7_ed25519_pubkey[32] = {")
    for i in range(0, 32, 8):
        line = "\t"
        line += ", ".join(f"0x{pub_bytes[j]:02x}" for j in range(i, min(i + 8, 32)))
        if i + 8 < 32:
            line += ","
        print(line)
    print("};")


def main():
    parser = argparse.ArgumentParser(
        description="Layer7 License Tool — key generation and license signing")
    sub = parser.add_subparsers(dest="command")

    kg = sub.add_parser("keygen", help="Generate Ed25519 key pair")
    kg.add_argument("--private-key", default="layer7-private.key")
    kg.add_argument("--public-key", default="layer7-public.key")

    sg = sub.add_parser("sign", help="Sign a license file")
    sg.add_argument("--private-key", required=True)
    sg.add_argument("--hardware-id", required=True,
                    help="64-char hex hardware fingerprint")
    sg.add_argument("--customer", required=True)
    sg.add_argument("--expiry", required=True, help="YYYY-MM-DD")
    sg.add_argument("--features", default="full")
    sg.add_argument("--output", default="layer7.lic")

    cp = sub.add_parser("c-pubkey",
                        help="Print public key as C array for license.c")
    cp.add_argument("--public-key", default="layer7-public.key")

    args = parser.parse_args()
    if args.command == "keygen":
        cmd_keygen(args)
    elif args.command == "sign":
        cmd_sign(args)
    elif args.command == "c-pubkey":
        cmd_c_pubkey(args)
    else:
        parser.print_help()


if __name__ == "__main__":
    main()
