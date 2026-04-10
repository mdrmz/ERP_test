#!/usr/bin/env bash
set -euo pipefail

SERVICE_NAME="plc-hub.service"

sudo systemctl disable --now "$SERVICE_NAME" || true
sudo rm -f "/etc/systemd/system/${SERVICE_NAME}"
sudo systemctl daemon-reload

echo "Servis kaldirildi: $SERVICE_NAME"
