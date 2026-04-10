#!/usr/bin/env bash
set -euo pipefail

# Usage:
#   sudo bash workers/systemd/install_kantar_poller_service.sh /var/www/html/ERP_test /usr/bin/php www-data 20
#
# Args:
#   1) APP_DIR      : ERP root directory (default: /var/www/html/ERP_test)
#   2) PHP_BIN      : php binary path   (default: /usr/bin/php)
#   3) SERVICE_USER : service user      (default: www-data)
#   4) INTERVAL_SEC : polling interval  (default: 20)

APP_DIR="${1:-/var/www/html/ERP_test}"
PHP_BIN="${2:-/usr/bin/php}"
SERVICE_USER="${3:-www-data}"
INTERVAL_SEC="${4:-20}"

SERVICE_NAME="kantar-poller.service"
SERVICE_PATH="/etc/systemd/system/${SERVICE_NAME}"
WORKER_PATH="${APP_DIR}/workers/kantar_poller.php"

if [[ ! -f "${WORKER_PATH}" ]]; then
  echo "[ERROR] Worker file not found: ${WORKER_PATH}"
  exit 1
fi

if [[ ! -x "${PHP_BIN}" ]]; then
  echo "[ERROR] PHP binary not executable: ${PHP_BIN}"
  exit 1
fi

if ! id -u "${SERVICE_USER}" >/dev/null 2>&1; then
  echo "[ERROR] Service user not found: ${SERVICE_USER}"
  exit 1
fi

cat > "${SERVICE_PATH}" <<EOF
[Unit]
Description=ERP Kantar Poller Worker
After=network-online.target mariadb.service mysql.service
Wants=network-online.target

[Service]
Type=simple
User=${SERVICE_USER}
Group=${SERVICE_USER}
WorkingDirectory=${APP_DIR}
ExecStart=${PHP_BIN} ${WORKER_PATH} --interval=${INTERVAL_SEC}
Restart=always
RestartSec=3
KillSignal=SIGINT
TimeoutStopSec=20
Environment=TZ=Europe/Istanbul

[Install]
WantedBy=multi-user.target
EOF

echo "[INFO] Service file written: ${SERVICE_PATH}"

systemctl daemon-reload
systemctl enable "${SERVICE_NAME}"
systemctl restart "${SERVICE_NAME}"

echo "[OK] Service installed and started."
echo "[INFO] Check status: sudo systemctl status ${SERVICE_NAME} --no-pager"
echo "[INFO] Follow logs : sudo journalctl -u ${SERVICE_NAME} -f"
