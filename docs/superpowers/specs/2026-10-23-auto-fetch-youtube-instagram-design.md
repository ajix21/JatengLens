# Rancangan: Pengambilan Data Otomatis YouTube & Instagram

Tanggal: 2026-10-23 · Aplikasi: `sociowatch-jateng` (Laravel 11, PHP 8.2, MariaDB)

## 1. Latar belakang & tujuan

Semua angka follower dan postingan di SocioWatch Jateng saat ini diisi manual.
Update terakhir tercatat 5 Mei 2026. Akibatnya grafik pertumbuhan, alert lonjakan,
deteksi kata kunci, dan halaman analitik praktis berhenti, karena semuanya
bergantung pada data yang tidak lagi diperbarui.

**Tujuan:** untuk akun **YouTube** dan **Instagram** yang dipantau (target hingga
sekitar 100 akun total), angka akun dan postingan baru masuk otomatis setiap hari,
**tanpa biaya** (hanya API resmi gratis). Platform lain (Facebook, X/Twitter,
TikTok) tetap diisi manual seperti sekarang.

**Ukuran keberhasilan:**
- Akun YT/IG yang memenuhi syarat diperbarui otomatis minimal 1× sehari tanpa campur tangan.
- Postingan baru muncul di halaman Postingan, lengkap dengan deteksi kata kunci, dan tidak pernah dobel.
- Snapshot follower dan alert (spike, milestone, keyword spike) kembali berjalan dari data segar.
- Admin bisa melihat akun mana yang gagal diambil dan alasannya.

## 2. Cakupan

**Termasuk:**
- Konektor YouTube (YouTube Data API v3).
- Konektor Instagram (Instagram Graph API, *Business Discovery*).
- Perintah terjadwal untuk mengambil data, tombol "Ambil sekarang" per akun, dan halaman/indikator status pengambilan.
- Perbaikan bug kolom `posts.posted_at` (lihat §6.1).
- Panduan setup kredensial API dalam Bahasa Indonesia.

**Tidak termasuk (non-goals):**
- Facebook, X/Twitter, TikTok. Strukturnya disiapkan agar konektor baru mudah ditambahkan, tapi tidak dibuat sekarang.
- Mengambil isi komentar, story, atau daftar follower.
- Scraping, login memakai akun, atau layanan berbayar.
- Antrean/worker latar belakang (lihat §7).

**Anti-goals (gagal walaupun "jalan"):**
- API key/token bocor ke repo, database, log, atau halaman.
- Postingan dobel di database.
- Data akun platform lain (yang manual) ikut berubah atau tertimpa.
- Input manual untuk YT/IG jadi tidak bisa dipakai. Input manual harus tetap berfungsi sebagai cadangan.
- Satu akun yang error menghentikan pengambilan akun lain.

## 3. Batasan platform yang harus dihormati

**YouTube Data API v3**
- Butuh 1 API key dari Google Cloud (gratis, kuota default 10.000 unit/hari).
- Per channel per pengambilan kira-kira: `channels.list` (1 unit) + `playlistItems.list` pada playlist *uploads* (1 unit) + `videos.list` untuk statistik video (1 unit). Untuk 100 channel 2×/hari kebutuhannya jauh di bawah kuota.
- Akun di aplikasi disimpan sebagai handle (contoh `profile_url` = `https://youtube.com/@JawaPosTV`, `username` = `JawaPosTV`). Handle diubah ke channel ID lewat `channels.list?forHandle=` sekali saja, lalu channel ID disimpan.
- "Following" tidak ada di YouTube, jadi tetap 0. `followers_count` = subscriberCount (bisa dibulatkan oleh YouTube, terima apa adanya). `post_count` = videoCount.

**Instagram Graph API — Business Discovery**
- Butuh: (1) akun Instagram **Business/Creator milik operator**, terhubung ke (2) sebuah Facebook Page, (3) aplikasi di Meta for Developers, (4) **long-lived access token** (berlaku sekitar 60 hari) dan ID akun IG Business operator.
- Endpoint: `GET /{ig-business-id}?fields=business_discovery.username({username}){followers_count,follows_count,media_count,media.limit(N){id,caption,like_count,comments_count,media_type,media_product_type,permalink,timestamp}}`.
- **Hanya bisa membaca akun target bertipe Business/Creator.** Akun pribadi menghasilkan error. Akun seperti itu ditandai "tidak didukung" dan tetap diisi manual.
- View/reach postingan akun lain tidak tersedia, jadi `views_count` tetap 0. Like count bisa disembunyikan pemilik akun; kalau tidak ada, simpan 0.
- Batas panggilan sekitar 200/jam per token. Pengambilan harus berhenti dengan rapi kalau kena limit.
- Token kedaluwarsa sekitar 60 hari. Aplikasi wajib memberi peringatan sebelum dan saat kedaluwarsa (lihat §6.5).

## 4. Keputusan yang sudah disepakati dengan pemilik

- Gratis dulu: hanya API resmi YouTube & Instagram.
- Mulai dari YouTube dan Instagram; platform lain manual.
- Yang diambil: angka akun **dan** postingan baru (isi, engagement, tanggal), dicek per hari.

## 5. Alur kerja

1. **Terjadwal**: perintah artisan baru (nama usulan `social:fetch`) dijalankan oleh scheduler **10 menit sebelum** `monitor:snapshot` yang sudah ada, yaitu pukul 06:50 dan 18:50. Perintah ini memperbarui angka akun dan menyimpan postingan. `monitor:snapshot` pukul 07:00/19:00 tetap seperti sekarang, mencatat snapshot dan memicu alert untuk **semua** akun. Dengan begitu logika snapshot dan alert tidak diduplikasi.
2. **Manual per akun**: tombol "Ambil sekarang" di halaman detail akun YT/IG (hanya untuk role dengan gate `can-edit`). Tombol ini menjalankan pengambilan untuk akun itu saja, lalu menampilkan hasilnya (berhasil, jumlah postingan baru, atau pesan error). Pengambilan manual tidak membuat snapshot; snapshot tetap dibuat oleh jadwal.
3. Perintah juga bisa dijalankan dari terminal: `php artisan social:fetch` (semua), `--platform=youtube`, `--account=ID`, `--dry-run` (tampilkan saja, tidak menyimpan).

## 6. Perilaku detail

### 6.1 Perbaikan bug `posted_at`
Kolom `posts.posted_at` saat ini `ON UPDATE current_timestamp()`. Setiap update baris post (misalnya memperbarui like) akan **menimpa tanggal posting** dengan waktu sekarang. Buat migration yang mengubahnya menjadi `timestamp NOT NULL` tanpa `ON UPDATE`. `alerts.triggered_at` punya masalah serupa dan ikut diperbaiki. Migration ini harus aman dijalankan di database yang sudah berisi data.

### 6.2 Data akun
Kolom baru di `social_accounts` (nama dan tipe diserahkan ke builder):
- `external_id`: channel ID YouTube (hasil resolusi handle). Untuk IG boleh menyimpan ID dari Business Discovery.
- `auto_fetch`: boolean, default true untuk akun YT/IG. Bisa dimatikan per akun dari form edit akun.
- `last_fetched_at`, `last_fetch_status`, `last_fetch_error`.

Status (`last_fetch_status`) minimal membedakan: berhasil, akun tidak ditemukan, akun IG bukan Business/Creator (tidak didukung), token/API key tidak valid atau kedaluwarsa, kuota/limit habis, dan error lain.

Yang **diperbarui** otomatis: `followers_count`, `following_count` (IG), `post_count`.
Yang **tidak** ditimpa: `display_name`, `bio`, `category_id`, `region_id`, `notes`, `profile_picture` (data kurasi admin). *Keputusan saya, bisa diubah.*

Riwayat tiap pengambilan per akun disimpan ke tabel log baru (waktu, akun, status, jumlah post baru/diperbarui, pesan error) untuk halaman status. Simpan sekitar 90 hari terakhir.

### 6.3 Postingan
- Kolom baru `posts.external_id` (ID video YT / ID media IG), **unik per platform**. Postingan lama hasil input manual boleh `NULL`.
- **Dedup**: cocokkan berdasarkan `(platform, external_id)`, lalu sebagai cadangan berdasarkan `post_url` (permalink IG / `https://www.youtube.com/watch?v=ID`). Kalau postingan manual dengan URL yang sama sudah ada, **isi `external_id`-nya, jangan buat baris baru**.
- Pemetaan isi:
  - YouTube: `content` = judul + baris kosong + deskripsi. `media_type` = `video`. `views_count`, `likes_count`, `comments_count` dari statistik. `shares_count` = 0.
  - Instagram: `content` = caption (kosong → string kosong, karena kolomnya NOT NULL). `media_type`: IMAGE/CAROUSEL_ALBUM → `image`, VIDEO → `video`, `media_product_type` REELS → `reel`. `views_count` = 0.
  - `posted_at` = waktu terbit dari API, disimpan sesuai timezone aplikasi.
- **Pengambilan pertama** (akun belum pernah diambil): ambil postingan dari **30 hari terakhir**, maksimal 50 per akun. *Keputusan saya.*
- **Pengambilan berikutnya**: ambil halaman terbaru (sekitar 25–50 item). Post yang belum ada disimpan. Post yang sudah ada dan terbit dalam **14 hari terakhir** diperbarui angka engagement-nya. Post yang lebih tua tidak disentuh. *Keputusan saya.*
- Setiap post baru, atau post yang isinya berubah, dijalankan melalui `KeywordDetectionService` yang sudah ada dan pivot `post_keyword_pivot` di-sync, sama seperti saat input manual (`PostController::store`).
- Tag dan flag tidak diubah oleh pengambilan otomatis.

### 6.4 Ketahanan
- Error satu akun dicatat lalu lanjut ke akun berikutnya.
- Error token/API key tidak valid atau kuota habis: hentikan sisa akun **di platform itu saja** dan catat statusnya. Platform lain tetap jalan.
- Timeout HTTP wajar (sekitar 15–20 detik) dan retry ringan untuk error jaringan sementara.
- Perintah tidak boleh berjalan dobel bersamaan (`withoutOverlapping` di scheduler).
- API key/token **tidak pernah** dicetak ke log, pesan error, atau halaman. Pesan error dari API disaring dulu.

### 6.5 Tampilan
- **Detail akun (YT/IG)**: badge status pengambilan terakhir (waktu + status), tombol "Ambil sekarang", dan pesan jelas kalau akun IG bukan Business ("Tidak bisa diambil otomatis — isi manual").
- **Daftar akun**: ikon kecil status otomatis per akun YT/IG (berhasil / gagal / tidak didukung / mati).
- **Halaman status pengambilan** (menu Pengaturan, superadmin): ringkasan per platform (kredensial terpasang/tidak, terakhir jalan, jumlah berhasil/gagal), tabel akun yang gagal beserta alasannya, dan **peringatan token Instagram** (sisa hari sebelum kedaluwarsa kalau bisa diketahui, atau peringatan saat token sudah ditolak).
- Form tambah/edit akun: checkbox "Ambil otomatis" untuk platform YT/IG.
- Gaya mengikuti tampilan yang ada (Blade + Tailwind CDN + Font Awesome, bahasa Indonesia).

### 6.6 Konfigurasi
- `.env`: `YOUTUBE_API_KEY`, `INSTAGRAM_ACCESS_TOKEN`, `INSTAGRAM_BUSINESS_ACCOUNT_ID`, dan versi Graph API (`INSTAGRAM_GRAPH_VERSION`). Dibaca lewat `config/services.php`. Tambahkan placeholder kosong di `.env.example`.
- Kalau kredensial suatu platform kosong, konektornya **non-aktif dengan rapi**: perintah melewati platform itu dengan pesan "kredensial belum diatur", dan halaman status menunjukkannya. Tidak boleh error.

### 6.7 Scheduler di Windows
Scheduler Laravel hanya berjalan kalau `php artisan schedule:run` dipanggil tiap menit. Di mesin ini (Windows/XAMPP) belum ada. Panduan setup harus menyertakan cara membuat **Windows Task Scheduler** untuk itu.

### 6.8 Zona waktu jadwal
`APP_TIMEZONE=UTC`, jadi jadwal `monitor:snapshot` "07:00/19:00 WIB" yang ada sekarang sebenarnya berjalan pukul 14:00/02:00 WIB. Jadwal `social:fetch` **dan** `monitor:snapshot` diberi `->timezone('Asia/Jakarta')` supaya benar-benar pukul 06:50/07:00 dan 18:50/19:00 WIB. `APP_TIMEZONE` tidak diubah, supaya data waktu yang sudah tersimpan tidak bergeser. *Keputusan saya.*

### 6.9 Mengenali channel YouTube dari data akun
Urutan resolusi: `external_id` yang sudah tersimpan → channel ID dari `profile_url` bentuk `/channel/UC…` → handle dari `profile_url` bentuk `/@handle` → `username` diperlakukan sebagai handle. Kalau semua gagal, status "akun tidak ditemukan". *Keputusan saya.*

## 7. Keputusan teknis (dari saya, bukan dari pemilik)
- Pakai HTTP client bawaan Laravel (`Http::`). Tidak perlu paket SDK baru.
- Pengambilan berjalan sinkron di dalam perintah artisan, tanpa queue worker. Untuk sekitar 100 akun waktunya beberapa menit, dan di mesin ini tidak ada worker yang berjalan.
- Struktur kode: satu antarmuka konektor per platform (mis. `YouTubeFetcher`, `InstagramFetcher`) yang mengembalikan data akun + daftar postingan dalam format seragam, ditambah satu service yang menyimpan ke database (dedup, keyword detection, log). Konektor platform lain nanti cukup mengimplementasikan antarmuka yang sama. Detail nama kelas diserahkan ke builder.
- Tombol "Ambil sekarang" memanggil service yang sama secara langsung (sinkron) untuk satu akun.

## 8. Pengujian
- Feature test dengan `Http::fake()` untuk kedua konektor, mencakup: sukses, akun tidak ditemukan, IG bukan Business, token tidak valid, kuota habis, dedup (dijalankan dua kali tidak menambah post), cocok dengan post manual berdasarkan URL, update engagement tanpa mengubah `posted_at`, keyword detection terpasang, platform tanpa kredensial dilewati, dan akun platform lain tidak tersentuh.
- Test tidak boleh memanggil API sungguhan.
- Uji coba nyata dilakukan setelah pemilik memasang kredensial, dengan `--dry-run` dulu.

## 9. Panduan setup (deliverable)
Dokumen Bahasa Indonesia (mis. `docs/setup-auto-fetch.md`) berisi langkah demi langkah:
1. Membuat project Google Cloud, mengaktifkan YouTube Data API v3, membuat dan membatasi API key.
2. Mengubah akun IG operator ke Business/Creator, menghubungkannya ke Facebook Page, membuat aplikasi Meta, mendapatkan long-lived token dan IG Business ID, serta cara memperbarui token sebelum 60 hari.
3. Mengisi `.env`, menjalankan `php artisan social:fetch --dry-run`, lalu menjalankan sungguhan.
4. Membuat Windows Task Scheduler untuk `schedule:run`.

## 10. Diserahkan ke builder
Nama kolom/tabel/kelas, desain detail halaman status, cara menyimpan perkiraan kedaluwarsa token, besaran retry/timeout, dan pembagian test.
