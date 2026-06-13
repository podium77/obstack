#!/usr/bin/env bash
set -euo pipefail

# Script CI local: build and package a Rust obsagent binary.
# Usage: TARGET=x86_64-unknown-linux-gnu ./build_obsagent_rust.sh

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
RUST_PROJECT_DIR="$REPO_ROOT/agent/rust"
TARGET="${TARGET:-x86_64-unknown-linux-gnu}"
OUTDIR="$REPO_ROOT/agent/release"

mkdir -p "$OUTDIR"

if [ ! -d "$RUST_PROJECT_DIR" ]; then
  echo "No Rust project found at $RUST_PROJECT_DIR. Create one or adjust path." >&2
  exit 1
fi

pushd "$RUST_PROJECT_DIR" >/dev/null

echo "Building obsagent for target=$TARGET"
cargo build --release --target "$TARGET"

BIN_PATH="target/$TARGET/release/obsagent"
if [ ! -f "$BIN_PATH" ]; then
  # fallback to native release
  BIN_PATH="target/release/obsagent"
fi

if [ ! -f "$BIN_PATH" ]; then
  echo "Build failed: binary not found." >&2
  exit 1
fi

rm -rf "$OUTDIR/tmp"
mkdir -p "$OUTDIR/tmp/bin"
cp "$BIN_PATH" "$OUTDIR/tmp/bin/obsagent"
strip "$OUTDIR/tmp/bin/obsagent" 2>/dev/null || true

ARCHIVE_NAME="obsagent-$(date +%Y%m%d)-${TARGET}.tar.gz"
tar -C "$OUTDIR/tmp" -czf "$OUTDIR/$ARCHIVE_NAME" .
rm -rf "$OUTDIR/tmp"

echo "Packaged: $OUTDIR/$ARCHIVE_NAME"
popd >/dev/null

exit 0
