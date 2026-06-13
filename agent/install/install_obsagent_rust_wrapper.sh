#!/usr/bin/env bash
set -euo pipefail

# Wrapper to download, verify (GPG) and install obsagent binary
# Usage: sudo ./install_obsagent_rust_wrapper.sh --artifact-url <url> --sig-url <url> --pubkey-url <url>

ARTIFACT_URL=""
SIG_URL=""
PUBKEY_URL=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --artifact-url) ARTIFACT_URL="$2"; shift 2;;
    --sig-url) SIG_URL="$2"; shift 2;;
    --pubkey-url) PUBKEY_URL="$2"; shift 2;;
    --help) echo "Usage: $0 --artifact-url <url> --sig-url <url> --pubkey-url <url>"; exit 0;;
    *) echo "Unknown arg: $1"; exit 1;;
  esac
done

if [ -z "$ARTIFACT_URL" ] || [ -z "$SIG_URL" ] || [ -z "$PUBKEY_URL" ]; then
  echo "--artifact-url --sig-url --pubkey-url are required" >&2
  exit 1
fi

TMPDIR=$(mktemp -d)
cleanup(){ rm -rf "$TMPDIR"; }
trap cleanup EXIT

echo "Downloading artifact..."
curl -fsSL "$ARTIFACT_URL" -o "$TMPDIR/obsagent.tar.gz"
echo "Downloading signature..."
curl -fsSL "$SIG_URL" -o "$TMPDIR/obsagent.tar.gz.asc"

echo "Importing public key..."
gpg --batch --import <(curl -fsSL "$PUBKEY_URL")

echo "Verifying signature..."
gpg --batch --verify "$TMPDIR/obsagent.tar.gz.asc" "$TMPDIR/obsagent.tar.gz"

echo "Extracting and installing..."
tar -xzf "$TMPDIR/obsagent.tar.gz" -C "$TMPDIR"
if [ -f "$TMPDIR/obsagent" ]; then
  install -m 0755 "$TMPDIR/obsagent" /usr/local/bin/obsagent
  echo "obsagent installed to /usr/local/bin/obsagent"
else
  echo "obsagent binary not found in archive" >&2
  exit 1
fi

echo "Installation complete"
