# Panduan Deploy SocioWatch Jateng di Homelab Docker (RAM 4 GB)

Konfigurasi Docker ini dirancang khusus agar **sangat hemat RAM (~180–260 MB total)** sehingga aman berjalan berdampingan dengan aplikasi lain di homelab 4 GB RAM:

| Container | Batas RAM (`mem_limit`) | Pemakaian Normal | Fungsi |
|---|---|---|---|
| `sociowatch_app` | `300m` | ~80–120 MB | PHP 8.2 Apache (tuned prefork + OPcache) + Laravel Scheduler (`schedule:work`) dalam 1 container |
| `sociowatch_db` | `256m` | ~90–140 MB | MariaDB 10.11 (`innodb_buffer_pool_size=64M`, `performance_schema=OFF`) |

> **Tip Hemat Ekstra**: Jika homelab Anda **sudah memiliki container MariaDB/MySQL**, Anda bisa menghapus/comment blok `db:` di `docker-compose.yml` dan mengarahkan `DB_HOST` di `.env` ke container database tersebut. Total RAM SocioWatch akan turun menjadi **~100 MB saja**!

---

## 1. Instalasi Pertama Kali di Homelab

```bash
# 1. Clone repo dari GitHub
git clone https://github.com/ajix21/JatengLens.git
cd JatengLens

# 2. Buat file .env production dari contoh
cp sociowatch-jateng/.env.example sociowatch-jateng/.env
nano sociowatch-jateng/.env
```

Isi variabel penting di `sociowatch-jateng/.env`:
```env
APP_NAME="SocioWatch Jateng"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sociowatch.domain-anda.com
APP_TIMEZONE=Asia/Jakarta
SESSION_SECURE_COOKIE=true

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=sociowatch_jateng
DB_USERNAME=sociowatch
DB_PASSWORD=ganti_password_kuat

# Token Meta Graph API, Gemini, Telegram, dan Browser Sync
INSTAGRAM_ACCESS_TOKEN=...
INSTAGRAM_BUSINESS_ACCOUNT_ID=...
INSTAGRAM_TOKEN_EXPIRES_AT=2027-01-20
GEMINI_API_KEY=...
TELEGRAM_BOT_TOKEN=...
TELEGRAM_CHAT_ID=7754154315
BROWSER_SYNC_TOKEN=isi_token_acak_panjang_rahasia
```

Lalu jalankan container:
```bash
# 3. Build & jalankan (port default 8085, bisa diubah via SOCIOWATCH_PORT)
docker compose up -d --build

# 4. Generate APP_KEY & jalankan migrasi
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

Aplikasi langsung bisa diakses di **`http://<IP-HOMELAB>:8085`** (atau arahkan Reverse Proxy / Nginx Proxy Manager / Cloudflare Tunnel Anda ke port `8085`).

---

## 2. Memindahkan Data 133 Akun & Postingan dari Lokal ke Homelab

Di PC lokal (Windows), export database terlebih dahulu:
```powershell
& "C:\xampp\mysql\bin\mysqldump.exe" -u root sociowatch_jateng > sociowatch_backup.sql
```

Salin `sociowatch_backup.sql` ke homelab (via `scp` / SFTP), lalu import ke container `sociowatch_db`:
```bash
docker compose exec -T db mariadb -u sociowatch -p sociowatch_jateng < sociowatch_backup.sql
```

---

## 3. Menghubungkan Ekstensi Browser ke Homelab

1. Buka popup **Ekstensi SocioWatch v2.3.1** di Chrome/Edge.
2. Klik **⚙ Pengaturan Backend**:
   - **URL Server**: `https://sociowatch.domain-anda.com` (atau `http://<IP-HOMELAB>:8085`)
   - **Sync API Token**: masukkan nilai `BROWSER_SYNC_TOKEN` yang sama dengan `.env` homelab.
3. Klik **Simpan** → **Tes Server** (status akan berubah hijau **Terhubung**).

---

## 4. Alur Update Kode Production dari Workspace Lokal

Setiap kali kita mengubah kode / menambah fitur di sini (PC lokal):
1. Uji lokal (`php artisan test`) lalu `git push` ke GitHub.
2. Di homelab, cukup jalankan **1 perintah**:
   ```bash
   chmod +x deploy.sh   # cukup sekali saja
   ./deploy.sh
   ```
   Skrip akan otomatis menjalankan `git pull`, rebuild image `app` (tanpa menyentuh data DB & thumbnail), `migrate --force`, `optimize`, dan menampilkan pemakaian RAM terkini.
