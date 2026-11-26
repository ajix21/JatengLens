#!/bin/bash
set -e

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
CURRENT_USER="$(whoami)"

echo "=== Installer Homelab Telegram Monitor Bot ==="
echo "Direktori : $APP_DIR"
echo "User      : $CURRENT_USER"

if ! command -v python3 >/dev/null 2>&1; then
    echo "[ERROR] python3 tidak ditemukan. Install dulu: sudo apt install -y python3"
    exit 1
fi

if [ ! -f "$APP_DIR/.env" ]; then
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    chmod 600 "$APP_DIR/.env"
    echo ""
    echo "[INFO] File .env dibuat dari .env.example."
    echo "Silakan isi TELEGRAM_BOT_TOKEN dan ALLOWED_CHAT_IDS terlebih dahulu:"
    echo "    nano $APP_DIR/.env"
    echo "Lalu jalankan ulang: ./install.sh"
    exit 0
fi

# Pastikan .env punya token
if ! grep -qE '^TELEGRAM_BOT_TOKEN=.+' "$APP_DIR/.env"; then
    echo "[ERROR] TELEGRAM_BOT_TOKEN di $APP_DIR/.env masih kosong!"
    echo "Edit dulu dengan: nano $APP_DIR/.env"
    exit 1
fi

chmod +x "$APP_DIR/bot.py"
chmod 600 "$APP_DIR/.env"

SERVICE_FILE="/etc/systemd/system/homelab-monitor.service"
echo "[INFO] Membuat systemd service di $SERVICE_FILE (batas RAM 64M)..."

sudo tee "$SERVICE_FILE" > /dev/null <<EOF
[Unit]
Description=Homelab Telegram Monitor Bot (CPU, RAM, Disk, Docker)
After=network-online.target docker.service
Wants=network-online.target

[Service]
Type=simple
User=$CURRENT_USER
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/python3 -u $APP_DIR/bot.py
Restart=always
RestartSec=10
MemoryMax=64M
CPUQuota=15%
NoNewPrivileges=true

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable homelab-monitor.service
sudo systemctl restart homelab-monitor.service

echo ""
echo "=== SELESAI! Bot Homelab Monitor sudah berjalan ==="
sudo systemctl status homelab-monitor.service --no-pager -l
