#!/usr/bin/env bash
set -euo pipefail

# Secure installer for obstack Python agent (collector)
# Usage: sudo ./install_obsagent_python.sh --token <TOKEN> [--api-url <URL>] [--install-dir /opt/obstack-agent]

TOKEN=""
API_URL=""
INSTALL_DIR="/opt/obstack-agent"
CREATE_USER="obsagent"
LOG_DIR="/var/log/obstack-agent"
SPOOL_DIR="/var/spool/obstack-agent"


ARTIFACT_URL=""
SIG_URL=""
PUBKEY_URL=""
PUBKEY_FILE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --token) TOKEN="$2"; shift 2;;
    --api-url) API_URL="$2"; shift 2;;
    --install-dir) INSTALL_DIR="$2"; shift 2;;
    --artifact-url) ARTIFACT_URL="$2"; shift 2;;
    --sig-url) SIG_URL="$2"; shift 2;;
    --pubkey-url) PUBKEY_URL="$2"; shift 2;;
    --pubkey-file) PUBKEY_FILE="$2"; shift 2;;
    --help) echo "Usage: $0 --token <TOKEN> [--api-url <URL>] [--artifact-url <URL> --sig-url <URL> --pubkey-url <URL>]|--pubkey-file <file>"; exit 0;;
    *) echo "Unknown arg: $1"; exit 1;;
  esac
done

if [ -z "$TOKEN" ]; then
  echo "--token is required" >&2
  exit 1
fi

echo "Installing obsagent to $INSTALL_DIR"

mkdir -p "$INSTALL_DIR/bin" "$INSTALL_DIR/config" "$SPOOL_DIR" "$LOG_DIR"
if ! id -u "$CREATE_USER" >/dev/null 2>&1; then
  useradd --system --no-create-home --shell /usr/sbin/nologin "$CREATE_USER" || true
fi
chown -R "$CREATE_USER":"$CREATE_USER" "$INSTALL_DIR" "$SPOOL_DIR" "$LOG_DIR"
chmod 750 "$INSTALL_DIR" "$SPOOL_DIR" "$LOG_DIR"

# Install python and venv if needed (Debian/Ubuntu)
if command -v apt-get >/dev/null 2>&1; then
  apt-get update
  apt-get install -y --no-install-recommends python3 python3-venv python3-pip curl gnupg ca-certificates
fi

PYTHON_BIN="/usr/bin/python3"
"$PYTHON_BIN" -m venv "$INSTALL_DIR/venv"
source "$INSTALL_DIR/venv/bin/activate"
pip install --upgrade pip --no-cache-dir
pip install --no-cache-dir psutil requests || true

# Copy collector script if present next to this installer, otherwise use bundled script
SCRIPT_SRC_DIR="$(cd "$(dirname "$0")/.." && pwd)/scripts"

if [ -n "$ARTIFACT_URL" ]; then
  # Download artifact and optional signature, verify with provided pubkey
  TMPDIR=$(mktemp -d)
  ARTFILE="$TMPDIR/obsagent.tar.gz"
  SIGFILE="$TMPDIR/obsagent.tar.gz.asc"
  echo "Downloading artifact: $ARTIFACT_URL"
  curl -fsSL "$ARTIFACT_URL" -o "$ARTFILE"
  if [ -n "$SIG_URL" ]; then
    curl -fsSL "$SIG_URL" -o "$SIGFILE"
  else
    curl -fsSL "${ARTIFACT_URL}.asc" -o "$SIGFILE" || true
  fi

  # Import public key
  GNUPGHOME=$(mktemp -d)
  export GNUPGHOME
  chmod 700 "$GNUPGHOME"
  if [ -n "$PUBKEY_URL" ]; then
    curl -fsSL "$PUBKEY_URL" -o "$TMPDIR/pubkey.asc"
    gpg --batch --import "$TMPDIR/pubkey.asc"
  elif [ -n "$PUBKEY_FILE" ]; then
    gpg --batch --import "$PUBKEY_FILE"
  else
    echo "No pubkey provided; cannot verify signature." >&2
    rm -rf "$TMPDIR" "$GNUPGHOME"
    exit 1
  fi

  if [ -f "$SIGFILE" ]; then
    echo "Verifying signature..."
    if ! gpg --batch --verify "$SIGFILE" "$ARTFILE" >/dev/null 2>&1; then
      echo "GPG verification failed" >&2
      rm -rf "$TMPDIR" "$GNUPGHOME"
      exit 1
    fi
    echo "Signature OK"
  else
    echo "No signature file found to verify." >&2
    rm -rf "$TMPDIR" "$GNUPGHOME"
    exit 1
  fi

  # Extract artifact into install dir
  tar -xzf "$ARTFILE" -C "$TMPDIR"
  if [ -f "$TMPDIR/obsagent" ]; then
    cp "$TMPDIR/obsagent" "$INSTALL_DIR/bin/obsagent"
    chmod 750 "$INSTALL_DIR/bin/obsagent"
    chown "$CREATE_USER":"$CREATE_USER" "$INSTALL_DIR/bin/obsagent"
  else
    echo "Artifact did not contain obsagent binary" >&2
    rm -rf "$TMPDIR" "$GNUPGHOME"
    exit 1
  fi

  rm -rf "$TMPDIR" "$GNUPGHOME"
else
  if [ -f "$SCRIPT_SRC_DIR/collect_metrics.py" ]; then
    cp "$SCRIPT_SRC_DIR/collect_metrics.py" "$INSTALL_DIR/bin/collect_metrics.py"
  else
    echo "Warning: local collect_metrics.py not found; generating minimal placeholder." >&2
    cat > "$INSTALL_DIR/bin/collect_metrics.py" <<'PY'
#!/usr/bin/env python3
print('Minimal placeholder collector. Replace with real script.')
PY
  fi

  chmod 750 "$INSTALL_DIR/bin/collect_metrics.py"
  chown -R "$CREATE_USER":"$CREATE_USER" "$INSTALL_DIR"
fi

# Write config (restrict permissions)
cat > "$INSTALL_DIR/config/agent.json" <<JSON
{
  "api_url": "${API_URL}",
  "token": "${TOKEN}",
  "spool_dir": "${SPOOL_DIR}"
}
JSON
chown "$CREATE_USER":"$CREATE_USER" "$INSTALL_DIR/config/agent.json"
chmod 640 "$INSTALL_DIR/config/agent.json"

# systemd service to run collector (timer triggers a oneshot)
cat > /etc/systemd/system/obsagent-collect.service <<'UNIT'
[Unit]
Description=ObsAgent collect metrics
After=network-online.target

[Service]
Type=oneshot
User=obsagent
Group=obsagent
WorkingDirectory=/opt/obstack-agent
ExecStart=/opt/obstack-agent/venv/bin/python /opt/obstack-agent/bin/collect_metrics.py --config /opt/obstack-agent/config/agent.json
StandardOutput=journal
StandardError=journal
Restart=on-failure
RestartSec=5
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
UNIT

# systemd timer to execute every minute
cat > /etc/systemd/system/obsagent-collect.timer <<'TIMER'
[Unit]
Description=Run obsagent collect every minute

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min

[Install]
WantedBy=timers.target
TIMER

systemctl daemon-reload
systemctl enable --now obsagent-collect.timer

# Logrotate configuration
cat > /etc/logrotate.d/obsagent <<'LR'
/var/log/obstack-agent/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 obsagent adm
    sharedscripts
}
LR

echo "obsagent python installer complete. Service timer enabled. Logs: $LOG_DIR"
