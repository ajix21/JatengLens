# SocioWatch Jateng

Sistem Pemantauan Akun Media Sosial Jawa Tengah — aplikasi web berbasis Laravel untuk memantau, memetakan, dan mengelola akun media sosial (Instagram, Twitter, Facebook, TikTok, YouTube) di 35 kabupaten/kota Jawa Tengah.

---

## Fitur Utama

| Fitur | Keterangan |
|---|---|
| **Peta Interaktif** | Marker map & choropleth berbasis Leaflet.js untuk 35 kab/kota |
| **Dashboard** | Statistik ringkasan, chart per kategori, top wilayah |
| **Manajemen Akun** | CRUD akun sosmed + data admin pemilik akun |
| **Rekapitulasi Wilayah** | Statistik per kab/kota beserta breakdown kategori |
| **Sistem Login** | Multi-user dengan 3 role: Superadmin, Admin, Viewer |
| **Export Laporan** | Export Excel & PDF untuk akun dan wilayah, ikuti filter aktif |
| **Log Aktivitas** | Audit trail semua aksi CRUD dan login/logout |

---

## Tech Stack

- **Backend**: Laravel 11.x, PHP 8.2+
- **Database**: MySQL 8.0+
- **Frontend**: Blade + Tailwind CSS (CDN) + Alpine.js
- **Peta**: Leaflet.js + MarkerCluster + GeoJSON
- **Chart**: Chart.js
- **Export PDF**: barryvdh/laravel-dompdf
- **Export Excel**: maatwebsite/excel (PhpSpreadsheet)

---

## Prasyarat

Pastikan sudah terinstall di sistem Anda:

| Kebutuhan | Versi Minimum |
|---|---|
| PHP | 8.2 |
| Composer | 2.x |
| MySQL | 8.0 |
| Node.js | 18.x (opsional, untuk build asset) |
| Git | — |

**Ekstensi PHP yang dibutuhkan:**
`pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `gd` atau `imagick`

---

## Instalasi

### Langkah 1 — Clone Repository

```bash
git clone https://github.com/ajix21/JatengLens.git
cd JatengLens/sociowatch-jateng
```

### Langkah 2 — Install Dependensi PHP

```bash
composer install
```

### Langkah 3 — Konfigurasi Environment

Salin file `.env` contoh dan sesuaikan konfigurasi:

```bash
cp .env.example .env
php artisan key:generate
```

Buka `.env` dan sesuaikan bagian berikut:

```env
APP_NAME="SocioWatch Jateng"
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sociowatch_jateng
DB_USERNAME=root
DB_PASSWORD=your_password_here
```

### Langkah 4 — Setup Database

**Opsi A — Import file SQL (lebih cepat)**

```bash
# Buat database terlebih dahulu
mysql -u root -p -e "CREATE DATABASE sociowatch_jateng CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import skema + data
mysql -u root -p sociowatch_jateng < ../database/sociowatch_jateng.sql

# Jalankan UserSeeder untuk membuat hash password yang benar
php artisan db:seed --class=UserSeeder
```

**Opsi B — Migration + Seeder (cara Laravel standar)**

```bash
# Buat database kosong terlebih dahulu
mysql -u root -p -e "CREATE DATABASE sociowatch_jateng CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Jalankan semua migration
php artisan migrate

# Jalankan semua seeder (regions, categories, accounts, users)
php artisan db:seed
```

### Langkah 5 — Storage & Cache

```bash
php artisan storage:link
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Langkah 6 — Jalankan Server

```bash
php artisan serve
```

Buka browser dan akses: **http://localhost:8000**

---

## Akun Default

Setelah seeder dijalankan, tersedia 3 akun login:

| Role | Email | Password | Hak Akses |
|---|---|---|---|
| **Superadmin** | admin@sociowatch.id | `admin123` | Akses penuh: CRUD, hapus permanen, kelola user, lihat log |
| **Admin** | operator@sociowatch.id | `operator123` | CRUD akun/kategori/admin, tidak bisa hapus permanen |
| **Viewer** | viewer@sociowatch.id | `viewer123` | Read-only, tidak ada tombol tambah/edit/hapus |

> **Penting:** Ganti password default segera setelah instalasi di production.

---

## Struktur Role & Permission

```
Superadmin
├── Semua fitur Admin
├── Manajemen User (CRUD, reset password, toggle aktif)
├── Log Aktivitas User
└── Hapus permanen (akun, kategori, admin akun)

Admin
├── CRUD akun sosmed
├── CRUD kategori
├── CRUD admin akun
└── Tidak bisa kelola user lain

Viewer
├── Akses semua halaman (read-only)
└── Tidak ada tombol create/edit/delete
```

---

## Struktur Direktori Penting

```
sociowatch-jateng/
├── app/
│   ├── Exports/              # Export class (Excel)
│   ├── Http/Controllers/     # Semua controller
│   ├── Models/               # Eloquent models
│   └── Providers/            # AppServiceProvider (Gate definitions)
├── database/
│   ├── migrations/           # Skema tabel
│   └── seeders/              # Data awal (regions, categories, accounts, users)
├── public/
│   └── geojson/
│       └── jawa-tengah.geojson  # GeoJSON 35 kab/kota untuk choropleth
├── resources/views/
│   ├── auth/                 # Halaman login
│   ├── accounts/             # CRUD akun sosmed
│   ├── categories/           # CRUD kategori
│   ├── admins/               # CRUD admin akun
│   ├── regions/              # Rekapitulasi wilayah
│   ├── map/                  # Peta interaktif
│   ├── users/                # Manajemen user (superadmin)
│   ├── exports/              # Template PDF
│   ├── dashboard/            # Dashboard utama
│   └── layouts/app.blade.php # Layout utama
└── routes/web.php            # Definisi semua route
```

---

## Route Utama

| Method | URL | Keterangan |
|---|---|---|
| GET | `/login` | Halaman login |
| POST | `/logout` | Logout |
| GET | `/` | Dashboard |
| GET | `/map` | Peta interaktif |
| GET | `/map/markers` | API: marker data (JSON) |
| GET | `/map/choropleth` | API: choropleth data (JSON) |
| GET | `/map/region/{id}` | API: akun per wilayah (JSON) |
| GET/POST | `/accounts` | Manajemen akun sosmed |
| GET/POST | `/categories` | Manajemen kategori |
| GET/POST | `/admins` | Manajemen admin akun |
| GET | `/regions` | Rekapitulasi wilayah |
| GET | `/users` | Manajemen user (superadmin) |
| GET | `/users-activity-log` | Log aktivitas (superadmin) |
| GET | `/export/accounts` | Export Excel akun |
| GET | `/export/accounts/pdf` | Export PDF akun |
| GET | `/export/regions` | Export Excel wilayah |
| GET | `/export/regions/pdf` | Export PDF wilayah |
| GET | `/export/map` | Export Excel data peta (filter aktif) |

---

## Export Laporan

### Export Akun Sosmed (`/accounts`)
- Tombol **[Excel]** dan **[PDF]** mengikuti filter aktif (platform, kategori, wilayah, status)
- Excel: 2 sheet — *Data Akun* + *Ringkasan Statistik*
- PDF: landscape A4 dengan header logo + tanggal cetak + filter yang digunakan

### Export Rekapitulasi Wilayah (`/regions`)
- Tombol **[Export Excel]** dan **[Export PDF]**
- Konten: 35 kab/kota, jumlah akun, total followers, kategori dominan, breakdown per kategori

### Export Data Peta (`/map`)
- Tombol **[Export Data]** muncul di mode Marker Map
- Export Excel mengikuti semua filter aktif di peta (kategori, platform, wilayah, rentang followers)

---

## Troubleshooting

**Error: `SQLSTATE[HY000] [2002] Connection refused`**
Pastikan MySQL sudah berjalan dan konfigurasi `DB_*` di `.env` sudah benar.

**Error: `php_network_getaddresses: getaddrinfo for ...`**
Periksa `DB_HOST` — gunakan `127.0.0.1` bukan `localhost` jika menggunakan socket TCP.

**Halaman blank / tampilan rusak**
```bash
php artisan config:clear
php artisan view:clear
php artisan cache:clear
```

**GeoJSON choropleth tidak muncul**
Pastikan file `public/geojson/jawa-tengah.geojson` ada. File ini di-commit di repository dan tidak perlu didownload ulang.

**Export gagal / class not found**
```bash
composer dump-autoload
```

---

## Catatan Production

1. Set `APP_ENV=production` dan `APP_DEBUG=false` di `.env`
2. Jalankan `php artisan config:cache` dan `php artisan route:cache`
3. Ganti semua password default akun seeder
4. Pastikan direktori `storage/` dan `bootstrap/cache/` writable:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```
5. Setup cron untuk Laravel Scheduler jika diperlukan:
   ```
   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
   ```

---
