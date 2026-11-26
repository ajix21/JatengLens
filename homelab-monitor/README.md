# Homelab Telegram Monitor Bot

Bot Telegram ultra-ringan (**pure Python 3 stdlib, tanpa install pip, RAM ~15 MB**) untuk memantau **CPU, RAM, Swap, Disk, Suhu, Top Proses, dan Docker Container** di Linux Homelab (ThinkPad E470 4 GB RAM).

## Fitur
- `/status` — Ringkasan lengkap CPU, RAM, Swap, Disk, Suhu, Uptime & jumlah Container Docker
- `/cpu` — Detail CPU (%), Load Average, Suhu & Top 5 proses CPU
- `/ram` — Detail RAM & Swap + Top 6 proses pemakan RAM terbanyak
- `/disk` — Penggunaan seluruh partisi disk (`df`)
- `/docker` — Status, CPU %, RAM usage/limit, dan uptime seluruh container Docker
- `/logs <nama_container>` — Ambil 20 baris log terakhir container (aman, validasi regex nama container, tanpa shell injection)
- **Auto-Alert Proaktif** setiap 60 detik:
  - RAM > 85%, Swap > 85%, CPU > 90%, Disk > 85% (cooldown 15 menit agar tidak spam)
  - Notifikasi instan saat ada **Container Docker mati (`exited`/`dead`)** atau **pulih kembali (`running`)**
- **Keamanan**:
  - Whitelist ketat `ALLOWED_CHAT_IDS` (user/grup lain otomatis diabaikan)
  - `systemd` limit `MemoryMax=64M`, `CPUQuota=15%`, `NoNewPrivileges=true`

## Cara Deploy ke Homelab Tailscale (`100.79.110.65`)

### 1. Copy folder ke homelab (dari PowerShell Windows)
```powershell
scp -r "d:\Project Bot - Work\JatengLens\homelab-monitor" androlab@100.79.110.65:~/homelab-monitor
```

### 2. SSH ke homelab & jalankan installer
```bash
ssh androlab@100.79.110.65
cd ~/homelab-monitor
sed -i 's/\r$//' install.sh bot.py .env.example
cp .env.example .env
nano .env   # Isi TELEGRAM_BOT_TOKEN (dari @BotFather) & pastikan ALLOWED_CHAT_IDS=7754154315
chmod +x install.sh
./install.sh
```

### 3. Cek log service
```bash
sudo journalctl -u homelab-monitor -f
```
