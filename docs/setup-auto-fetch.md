# Panduan Setup Auto-Fetch YouTube & Instagram

Panduan ini untuk operator SocioWatch Jateng. Setelah semua langkah selesai, aplikasi mengambil data akun dan postingan YouTube/Instagram secara otomatis pukul 06:50 dan 18:50 WIB, 10 menit sebelum snapshot (07:00 dan 19:00 WIB).

> Nama menu di Google Cloud dan Meta sering berubah. Panduan ini menyebut nama halaman dan tindakan, bukan urutan klik yang pasti. Jika menu berbeda, cari nama halaman yang disebut.

> **Rahasia:** API key, access token, dan app secret adalah kunci akses. Jangan di-commit ke Git, jangan dikirim lewat chat atau email, dan jangan ditempel di tangkapan layar. Simpan hanya di file `.env`.

## Cara kerja singkat

- Hanya akun yang **aktif** dan dicentang **Ambil otomatis** (form akun) yang diproses.
- Pengambilan pertama untuk sebuah akun mengambil postingan 30 hari terakhir (maksimal 50 postingan). Pengambilan berikutnya mengambil 14 hari terakhir.
- Instagram: akun yang dipantau harus bertipe **Business** atau **Creator**. Akun pribadi tidak didukung dan ditandai "Tidak didukung - isi manual".
- Kegagalan satu akun tidak menghentikan akun lain. Kredensial ditolak atau kuota habis menghentikan platform itu untuk sisa proses.

## Bagian 1. YouTube (Google Cloud)

1. Buka Google Cloud Console (console.cloud.google.com), masuk dengan akun Google.
2. Buat project baru (misalnya `sociowatch-jateng`).
3. Buka halaman **APIs & Services > Library**, cari **YouTube Data API v3**, lalu klik **Enable**.
4. Buka halaman **APIs & Services > Credentials**, pilih **Create credentials > API key**.
5. Buka pengaturan key tersebut dan batasi: pada **API restrictions** pilih **Restrict key**, lalu centang hanya **YouTube Data API v3**. Simpan.
6. Salin API key. Nanti diisi ke `YOUTUBE_API_KEY`.

Jika kuota harian habis, status akun menjadi "Kuota habis" dan pulih di hari berikutnya.

## Bagian 2. Instagram (Meta)

Aplikasi memakai fitur Business Discovery: satu akun Instagram milik operator (Business/Creator) dipakai untuk membaca data publik akun Business/Creator lain.

### 2.1 Siapkan akun Instagram operator

1. Ubah akun Instagram operator menjadi **Business** atau **Creator** (di pengaturan akun Instagram, bagian tipe akun).
2. Hubungkan akun itu ke sebuah **Facebook Page** (Page boleh baru dan kosong; operator harus admin Page tersebut).

### 2.2 Buat app Meta

1. Buka developers.facebook.com, masuk dengan akun Facebook yang menjadi admin Page.
2. Buat **App** baru (pilih tipe/use case yang mencakup Instagram Graph API). Catat **App ID** dan **App Secret** (halaman pengaturan dasar app).
3. Selama operator adalah admin/developer app, izin di bawah umumnya dapat dipakai tanpa App Review.

### 2.3 Dapatkan user token (jangka pendek)

1. Buka **Graph API Explorer** (di menu Tools developers.facebook.com), pilih app Anda.
2. Pilih **User Token** dan tambahkan izin: `instagram_basic`, `pages_show_list`, `business_management`.
3. Klik **Generate Access Token**, setujui dialog Facebook, lalu salin token. Token ini hanya berlaku singkat (sekitar 1-2 jam), jadi lanjutkan segera ke langkah berikut.

### 2.4 Tukar ke long-lived token (sekitar 60 hari)

Buka alamat berikut di browser (atau pakai `curl`), ganti nilai dalam kurung kurawal:

```
GET https://graph.facebook.com/{version}/oauth/access_token?grant_type=fb_exchange_token&client_id={app-id}&client_secret={app-secret}&fb_exchange_token={short-token}
```

- `{version}`: cek versi terbaru di dokumentasi Meta, sesuaikan `INSTAGRAM_GRAPH_VERSION` (default `v23.0`).
- Hasilnya JSON berisi `access_token`. Itulah nilai `INSTAGRAM_ACCESS_TOKEN`.
- Token berlaku sekitar 60 hari. Hitung tanggal kedaluwarsa dan isi `INSTAGRAM_TOKEN_EXPIRES_AT` dengan format `Y-m-d` (contoh `2026-12-22`).

Peringatan: URL ini memuat app secret. Jangan menyimpannya di riwayat chat atau dokumen bersama.

### 2.5 Ambil ID akun Instagram Business

Dengan long-lived token, buka:

```
GET https://graph.facebook.com/{version}/me/accounts?fields=instagram_business_account&access_token={long-token}
```

Cari `instagram_business_account.id` pada Page yang terhubung. Angka itu adalah nilai `INSTAGRAM_BUSINESS_ACCOUNT_ID`.

### 2.6 Memperbarui token sebelum 60 hari

Ulangi langkah 2.3 sampai 2.4 (ambil token pendek baru, tukar ke long-lived), ganti `INSTAGRAM_ACCESS_TOKEN` dan `INSTAGRAM_TOKEN_EXPIRES_AT` di `.env`, lalu jalankan `php artisan config:clear`. Lakukan sekitar 1 minggu sebelum tanggal kedaluwarsa.

## Bagian 3. Isi `.env` dan uji

1. Buka file `.env` di `D:\Project Bot - Work\JatengLens\sociowatch-jateng` dan isi (contoh nilai fiktif):

```
YOUTUBE_API_KEY=isi-api-key-youtube
INSTAGRAM_ACCESS_TOKEN=isi-long-lived-token
INSTAGRAM_BUSINESS_ACCOUNT_ID=17841400000000000
INSTAGRAM_GRAPH_VERSION=v23.0
INSTAGRAM_TOKEN_EXPIRES_AT=2026-12-22
```

2. Buka terminal, lalu jalankan:

```
cd "D:\Project Bot - Work\JatengLens\sociowatch-jateng"
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan social:fetch --dry-run
C:\xampp\php\php.exe artisan social:fetch
```

- `--dry-run` menghitung saja tanpa menyimpan data. Pakai dulu untuk memastikan kredensial benar.
- Opsi lain: `--platform=youtube` atau `--platform=instagram` untuk satu platform, dan `--account=ID` untuk satu akun.
- Platform tanpa kredensial dilewati dengan pesan "Lewati ...: kredensial belum diatur".
- Pastikan akun yang ingin diambil berstatus aktif dan "Ambil otomatis" dicentang di form akun.

Anda juga dapat menekan tombol **Ambil sekarang** di halaman detail akun untuk satu akun.

## Bagian 4. Jadwal otomatis (Windows Task Scheduler)

Laravel menjalankan jadwal lewat `schedule:run` yang harus dipanggil setiap menit. Buat tugas di Command Prompt (jalankan sebagai administrator jika diperlukan):

```
schtasks /create /sc minute /mo 1 /tn "SocioWatch Scheduler" /tr "\"C:\xampp\php\php.exe\" \"D:\Project Bot - Work\JatengLens\sociowatch-jateng\artisan\" schedule:run"
```

Tanda `\"` membungkus path yang mengandung spasi di dalam nilai `/tr`.

Verifikasi:

```
cd "D:\Project Bot - Work\JatengLens\sociowatch-jateng"
C:\xampp\php\php.exe artisan schedule:list
```

Daftar harus memuat dua kali `social:fetch` (06:50 dan 18:50) dan dua kali `monitor:snapshot` (07:00 dan 19:00), zona waktu Asia/Jakarta. Catatan: kolom cron pada `schedule:list` bisa tampil dalam UTC (misalnya `50 23` = 06:50 WIB, `0 0` = 07:00 WIB, `50 11` = 18:50 WIB, `0 12` = 19:00 WIB). Komputer harus menyala pada jam tersebut.

## Bagian 5. Memantau hasil

Superadmin dapat membuka halaman `/settings/auto-fetch` untuk melihat status pengambilan setiap akun.

## Troubleshooting

| Status | Arti | Tindakan |
|---|---|---|
| `not_found` (Akun tidak ditemukan) | Username/channel salah atau akun sudah tidak ada. | Periksa username/URL akun di form akun lalu perbaiki. |
| `unsupported` (Tidak didukung - isi manual) | Akun Instagram bukan Business/Creator, atau tidak dapat dibaca lewat Business Discovery. | Isi data akun itu secara manual, atau matikan "Ambil otomatis". |
| `auth_error` (Kredensial ditolak) | API key salah/dibatasi keliru, atau token Instagram kedaluwarsa/dicabut. | Periksa `.env`; untuk Instagram perbarui token (bagian 2.6); jalankan `config:clear`. |
| `quota` (Kuota habis) | Kuota harian YouTube atau batas permintaan Instagram terlampaui. | Tunggu sampai kuota pulih (biasanya hari berikutnya), kurangi jumlah akun otomatis, atau naikkan kuota di Google Cloud. |
| `error` (Error) | Kesalahan lain, misalnya jaringan. | Lihat pesan di halaman status, coba lagi dengan `social:fetch --account=ID`. |

## Catatan keamanan

Kerentanan PHP2Shell (CVE-2026-109618 dan CVE-2026-109619) mewajibkan PHP diperbarui ke versi **8.2.35 atau lebih baru** sebelum aplikasi dibuka ke publik. PHP di mesin ini saat ini masih 8.2.30, jadi perbarui dulu sebelum diekspos. Jangan pernah membagikan atau meng-commit file `.env`.
