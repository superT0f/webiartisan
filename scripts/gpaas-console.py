#!/usr/bin/env python3
"""
WebiArtisan — Gandi gPaas emergency console helper.

Opens the emergency SSH console without typing the password each time.

Usage:
    python3 scripts/gpaas-console.py                 # interactive shell on the server
    python3 scripts/gpaas-console.py exec "<cmd>"    # run one command, print output, exit

Password resolution order:
    1. $GPAAS_CONSOLE_PASSWORD environment variable
    2. .gpaas-console-password file at the repo root (gitignored, chmod 600)

Web admin console (Gandi Simple Hosting):
    https://admin.gandi.net/simplehosting/94bfdc39-ba8a-46ad-aff8-633112a25dfc/instances/c82ad26c-00cd-11ea-81f0-00163e108e85/administration

Requires: python3 + pexpect (pip install pexpect)
"""
import os
import sys
import time
from pathlib import Path

HOST = "4144916@console.dc2.gpaas.net"
SENTINEL = "ENDOFCMD42"

try:
    import pexpect
except ImportError:
    pexpect = None

REPO_ROOT = Path(__file__).resolve().parent.parent
PASSWORD_FILE = REPO_ROOT / ".gpaas-console-password"


def get_password() -> str:
    pw = os.environ.get("GPAAS_CONSOLE_PASSWORD", "").strip()
    if pw:
        return pw
    if PASSWORD_FILE.exists():
        return PASSWORD_FILE.read_text(encoding="utf-8").strip()
    sys.exit(
        "Password not found. Set GPAAS_CONSOLE_PASSWORD or create "
        f"{PASSWORD_FILE} (chmod 600)."
    )


def connect(password: str):
    if pexpect is None:
        sys.exit("pexpect is required: pip install pexpect")

    child = pexpect.spawn(
        "ssh",
        [
            "-o", "StrictHostKeyChecking=no",
            "-o", "UserKnownHostsFile=/dev/null",
            "-tt",
            HOST,
        ],
        timeout=120,
        encoding="utf-8",
    )
    i = child.expect([r"[Pp]assword:", pexpect.EOF, pexpect.TIMEOUT])
    if i != 0:
        sys.exit("No password prompt — connection failed:\n" + (child.before or ""))
    child.sendline(password)
    idx = child.expect(["Ok", r"[Pp]assword:", pexpect.EOF, pexpect.TIMEOUT])
    if idx != 0:
        sys.exit("Authentication failed (check the password).")
    time.sleep(1)
    child.sendline("")
    child.expect([r"[$#] ", pexpect.EOF, pexpect.TIMEOUT])
    return child


def interactive() -> None:
    child = connect(get_password())
    print(f"Connected to {HOST} — emergency console. Ctrl-D or 'exit' to leave.")
    child.interact()
    child.close()


def run_once(command: str) -> int:
    child = connect(get_password())
    child.sendline(command + f" ; echo {SENTINEL}_$?")
    idx = child.expect([SENTINEL + r"_(\d+)", pexpect.EOF, pexpect.TIMEOUT])
    rc = 0
    if idx == 0:
        try:
            rc = int(child.match.group(1))
        except Exception:
            rc = 1

    out = (child.before or "").replace("\r", "")
    lines = out.split("\n")
    # Drop the echoed command line
    if lines and command[:20] in lines[0]:
        lines = lines[1:]
    print("\n".join(lines).strip())

    child.sendline("exit")
    child.close()
    return rc


if __name__ == "__main__":
    if len(sys.argv) >= 3 and sys.argv[1] == "exec":
        sys.exit(run_once(sys.argv[2]))
    if len(sys.argv) >= 2 and sys.argv[1] in ("-h", "--help"):
        print(__doc__)
        sys.exit(0)
    interactive()
