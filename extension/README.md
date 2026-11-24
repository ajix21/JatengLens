# SocioWatch Jateng - Instagram Companion Extension

Ekstensi browser Chrome / Edge resmi untuk sistem pemantauan **SocioWatch Jateng**. Ekstensi ini dirancang khusus untuk memfasilitasi pengambilan data profil & postingan akun Instagram publik yang bertipe *Personal Account* (seperti akun BEM, Himpunan Mahasiswa, dan Komunitas) yang secara teknis tidak didukung oleh Meta Graph API Server.

---

## 🚀 Keunggulan Modul Ini

1. **Sangat Mudah untuk Orang Awam**: Cukup 1 kali klik tombol *"Mulai Sinkronisasi Sekarang"*, semua akun langsung tersinkron.
2. **Tanpa Setup Server Rumit**: Memanfaatkan sesi browser admin yang sudah login di Instagram, tanpa perlu proxy, bot headless yang berat, atau risiko CAPTCHA server.
3. **Penyimpanan Otomatis**:
   - Deteksi keyword akurat (*whole-word boundary*).
   - Analisis sentimen otomatis.
   - Perhitungan follower, likes, komentar, dan engagement.
   - Pencegahan duplikasi postingan (*deduplication*).

---

## 📦 Cara Memasang di Browser (Chrome / Edge / Brave)

Hanya butuh 3 langkah mudah:

### Langkah 1: Buka Halaman Ekstensi
- **Google Chrome**: Buka tab baru, ketik `chrome://extensions` lalu tekan **Enter**.
- **Microsoft Edge**: Buka tab baru, ketik `edge://extensions` lalu tekan **Enter**.

### Langkah 2: Aktifkan Mode Pengembang (Developer Mode)
- Di pojok kanan atas halaman ekstensi, aktifkan toggle **"Developer mode"** (Mode Pengembang).

### Langkah 3: Muat Ekstensi (Load Unpacked)
1. Klik tombol **"Load unpacked"** (Muat yang belum dibongkar) di pojok kiri atas.
2. Pilih folder:
   ```
   D:\Project Bot - Work\JatengLens\extension
   ```
3. Ekstensi **"SocioWatch Jateng - Instagram Companion"** akan langsung muncul dan siap digunakan!
4. *(Opsional)* Klik ikon pin (📌) di toolbar browser agar ikon SocioWatch selalu terlihat di sebelah kanan address bar.

---

## 🎯 Cara Menggunakan

1. **Pastikan Anda Sudah Login ke Instagram**:
   - Buka tab baru ke [https://www.instagram.com](https://www.instagram.com) di browser ini dan pastikan akun admin sudah login.
2. **Pastikan Server SocioWatch Aktif**:
   - Jalankan backend Laravel di terminal (contoh: `php artisan serve`).
   - Default URL backend: `http://localhost:8000` (dapat diubah di menu *Pengaturan Backend* jika menggunakan domain lain).
3. **Buka Ekstensi & Klik Sinkron**:
   - Klik ikon **SocioWatch** di toolbar browser.
   - Periksa indikator status:
     - `Instagram Session`: **Login Aktif** (Hijau)
     - `Server JatengLens`: **Terhubung** (Hijau)
   - Klik tombol biru: **"Mulai Sinkronisasi Sekarang"**.
4. Selesai! Progress bar dan log akan menampilkan status pengambilan tiap akun secara real-time. Data otomatis masuk ke dashboard dan database SocioWatch Jateng.

---

## ⚙️ Konfigurasi URL Backend (Jika Server di Hosting/Domain Lain)

Jika backend SocioWatch berjalan di port lain atau domain produksi:
1. Klik teks **"⚙ Pengaturan Backend"** di bagian bawah popup ekstensi.
2. Ubah URL server (misal: `http://localhost:8080` atau `https://sociowatch.jatengprov.go.id`).
3. Klik **"Simpan"** lalu **"Tes Server"**.

---

## 🔒 Privasi & Keamanan

- Ekstensi ini **hanya** berjalan saat Anda mengklik popup dan menekan tombol sinkronisasi.
- Ekstensi **tidak** membaca password Anda dan tidak mengirim cookie Anda ke luar perangkat. Ekstensi hanya mengambil data publik postingan akun target dan menyimpannya ke server SocioWatch internal Anda.
