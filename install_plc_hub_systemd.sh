#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SERVICE_NAME="plc-hub.service"
SERVICE_PATH="/etc/systemd/system/${SERVICE_NAME}"
PYTHON_BIN="${PYTHON_BIN:-$(command -v python3 || true)}"
RUN_USER="${RUN_USER:-$USER}"

if [[ -z "$PYTHON_BIN" ]]; then
  echo "python3 bulunamadi. Once python3 kurun." >&2
  exit 1
fi

if [[ ! -f "$APP_DIR/plc_hub.py" ]]; then
  echo "plc_hub.py bulunamadi: $APP_DIR/plc_hub.py" >&2
  exit 1
fi

sudo tee "$SERVICE_PATH" >/dev/null <<EOF
[Unit]
Description=PLC Hub (UN1/UN2/KEPEK)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=${RUN_USER}
WorkingDirectory=${APP_DIR}
ExecStart=${PYTHON_BIN} ${APP_DIR}/plc_hub.py --interval 1 --kg-max 5000 --no-clear
Restart=always
RestartSec=2
StandardOutput=append:${APP_DIR}/plc_hub_service.log
StandardError=append:${APP_DIR}/plc_hub_service.log

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now "$SERVICE_NAME"
sudo systemctl status "$SERVICE_NAME" --no-pager

echo "Kurulum tamamlandi."
echo "Log: $APP_DIR/plc_hub_service.log"
