# Rancangan: AI-Driven Narrative Intelligence & Ringkasan Eksekutif Harian

Tanggal: 2026-11-19 · Aplikasi: `sociowatch-jateng` (Laravel 11, PHP 8.2, MariaDB/MySQL)

## 1. Latar Belakang & Tujuan

SocioWatch Jateng saat ini sudah dapat menarik data akun serta postingan YouTube & Instagram secara otomatis (`social:fetch`), memantau lonjakan pengikut (`monitor:snapshot`), dan mencocokkan kata kunci (`KeywordDetectionService`). Namun bagi **Pimpinan / Pengambil Kebijakan**, sistem masih bersifat pasif dan kaku:
1. Deteksi kata kunci hanya mencocokkan potongan kata (*exact substring*) tanpa memahami negasi (misal *"tidak ada banjir"* tetap dihitung sebagai kata kunci *"banjir"*) dan tabel `posts` belum menyimpan sentimen per postingan.
2. Halaman utama **Dashboard** hanya menampilkan jumlah akun dan jumlah pengikut, belum menampilkan **situasi narasi/isu terkini** dari postingan yang masuk.
3. Halaman **Analytics** memiliki bug sintaks JavaScript (kurung siku array JSON yang hilang) sehingga grafik kata kunci dan sentimen gagal tampil di browser.

**Tujuan:**
Menghadirkan kapabilitas **Narrative Intelligence** dengan pendekatan **Hybrid (Pendekatan C)**:
1. **Level Postingan (Instan & Tanpa Kuota):** Setiap postingan memiliki klasifikasi sentimen (`sensitif`, `negatif`, `netral`, `positif`) yang dihitung otomatis secara kontekstual (bobot kata kunci + kamus leksikon Indonesia/Jawa + aturan negasi), serta dapat dikoreksi manual oleh analis.
2. **Level Pimpinan (Batch LLM + Fallback Deterministik):** Menyajikan panel **Ringkasan Eksekutif Harian & Klaster Isu Otomatis** langsung di halaman Dashboard, yang dihasilkan 2× sehari (atau lewat tombol *"Perbarui Ringkasan"*) menggunakan **Google Gemini API** (`gemini-2.5-flash`) dalam 1 panggilan batch hemat kuota, lengkap dengan **Heuristic Fallback Engine** sehingga fitur tetap berjalan 100% tanpa error meskipun `GEMINI_API_KEY` kosong atau kuota habis.

**Ukuran Keberhasilan:**
- Setiap postingan baru/update (dari auto-fetch, input manual, import CSV, maupun rescan) otomatis mendapatkan nilai `sentiment` yang akurat.
- Analis dapat memfilter postingan berdasarkan sentimen di `/posts` dan melakukan override manual jika diperlukan.
- Dashboard menampilkan panel **Ringkasan Intelijen & Klaster Isu** berisi rangkuman situasi, 3–5 klaster isu teratas (lengkap dengan urgensi, sentimen, wilayah terdampak, dan tautan postingan), serta rekomendasi perhatian pimpinan.
- Berjalan mulus baik dengan `GEMINI_API_KEY` aktif (mode AI) maupun tanpa API key (mode Analitik Otomatis / Heuristic).
- Grafik sentimen, word cloud, dan tren keyword di halaman `/analytics` tampil tanpa error JavaScript.

---

## 2. Cakupan (Goals, Non-Goals, Anti-Goals)

**Termasuk (Goals):**
- Migrasi penambahan kolom `sentiment` dan `sentiment_manual` pada tabel `posts`, serta pengisian nilai awal (*backfill*) untuk postingan yang sudah ada.
- `SentimentAnalysisService`: penentu sentimen postingan berbasis bobot kata kunci aktif + kamus leksikon + deteksi kata negasi (`tidak`, `bukan`, `belum`, `jangan`, `ora`, `mboten`, `tanpa`, `bebas`).
- Migrasi tabel `narrative_briefings` untuk menyimpan snapshot ringkasan eksekutif, klaster isu, dan rekomendasi.
- `NarrativeBriefingService`: pengumpul data postingan, pemanggil API Gemini (`Http::` native Laravel), penyaring kebocoran API key (`***`), dan mesin fallback heuristik deterministik.
- Perintah CLI `php artisan intel:briefing` yang dijadwalkan pukul `07:05` dan `19:05` WIB (`Asia/Jakarta`, 5 menit setelah `monitor:snapshot`), serta tombol **"Perbarui Ringkasan"** di Dashboard (khusus role `can:can-edit`).
- Pembaruan tampilan Dashboard (`dashboard/index.blade.php`), daftar/detail/form Postingan (`posts/*.blade.php`), dan perbaikan bug JavaScript serta agregasi sentimen di halaman Analytics (`analytics/index.blade.php` & `AnalyticsController`).

**Tidak Termasuk (Non-Goals):**
- Memanggil API LLM per satu postingan saat `social:fetch` atau import CSV (dilarang karena lambat dan boros kuota).
- Chatbot interaktif tanya-jawab bebas di dalam dashboard.
- Pengiriman notifikasi briefing ke WhatsApp/Telegram (akan masuk di fase pengembangan berikutnya).
- Penambahan SDK/paket Composer baru (seluruh panggilan ke Gemini API wajib menggunakan `Illuminate\Support\Facades\Http` bawaan Laravel).

**Anti-Goals (Gagal walaupun "jalan"):**
- `GEMINI_API_KEY` bocor ke database, log, flash message, atau halaman web.
- Proses `social:fetch` atau `PostController::store` menjadi lambat atau gagal hanya karena API eksternal gangguan.
- Sentimen yang sudah diubah manual oleh analis (`sentiment_manual = true`) tertimpa kembali saat auto-fetch atau rescan kata kunci.
- Dashboard error / blank ketika belum ada briefing atau ketika tabel `posts` masih kosong.

---

## 3. Pendekatan yang Dipertimbangkan & Keputusan Desain

| Pendekatan | Ringkasan | Trade-off | Keputusan |
|---|---|---|---|
| **A. Daily Digest Batch AI** | Hanya membuat ringkasan harian batch via LLM tanpa sentimen per postingan. | Hemat kuota, tetapi halaman `/posts` dan `/analytics` tidak punya data sentimen per post. | Ditolak |
| **B. Per-Post LLM Analysis** | Memanggil LLM setiap kali 1 post disimpan. | Akurat per post, tetapi lambat saat batch fetch/import dan cepat menghabiskan kuota gratis. | Ditolak |
| **C. Hybrid (Sentimen Kontekstual Instan + Batch AI Briefing & Fallback)** | Sentimen per-post dihitung instan secara lokal (keyword + leksikon + negasi); ringkasan & klaster isu pimpinan dihitung per-batch (LLM Gemini + fallback heuristik). | Cepat, tahan banting tanpa API key, hemat kuota (2 call/hari), dan menjawab kebutuhan analis maupun pimpinan. | **Dipilih** |

---

## 4. Rancangan Teknis Detail

### 4.1 Skema Database

#### A. Perubahan Tabel `posts`
Tambahkan 2 kolom melalui migration baru:
- `sentiment`: `string(20)`, default `'netral'`, nilai valid: `'sensitif'`, `'negatif'`, `'netral'`, `'positif'`. Diberi index `(sentiment, posted_at)` untuk mempercepat filter dan agregasi.
- `sentiment_manual`: `boolean`, default `false`. Jika `true`, menandakan sentimen dipilih eksplisit oleh manusia dan tidak boleh ditimpa otomatis.

#### B. Tabel Baru `narrative_briefings`
Setiap baris merepresentasikan 1 edisi ringkasan situasi:
- `id`: bigIncrements
- `period_start`: timestamp
- `period_end`: timestamp
- `window_label`: string(100) — keterangan jendela data, misal `"24 Jam Terakhir"` atau `"7 Hari Terakhir"`
- `source`: string(20) — `'gemini'` atau `'heuristic'`
- `status`: string(20) — `'ok'` (berhasil sesuai sumber) atau `'fallback'` (Gemini gagal lalu otomatis dialihkan ke heuristik)
- `executive_summary`: text — 2–3 paragraf ringkasan eksekutif dalam Bahasa Indonesia
- `issue_clusters`: json — array objek klaster isu:
  ```json
  [
    {
      "title": "Keluhan Infrastruktur & Banjir Pantura",
      "sentiment": "negatif",
      "urgency": "tinggi",
      "summary": "Sorotan warga dan media lokal terkait genangan air...",
      "regions": ["Kota Semarang", "Kabupaten Demak"],
      "categories": ["Media", "Mahasiswa"],
      "post_count": 8,
      "total_engagement": 15420,
      "post_ids": [12, 15, 19]
    }
  ]
  ```
- `recommendations`: json — array string rekomendasi tindakan/pemantauan untuk pimpinan (2–4 poin)
- `stats_snapshot`: json — ringkasan angka pada saat briefing dibuat:
  ```json
  {
    "total_posts": 42,
    "total_engagement": 84500,
    "flagged_count": 3,
    "sentiment_counts": {"sensitif": 2, "negatif": 10, "netral": 22, "positif": 8},
    "top_region": "Kota Semarang"
  }
  ```
- `error_message`: text, nullable — pesan error yang sudah disanitasi (`***`) jika `status = 'fallback'`
- `generated_by`: foreignId nullable ke `users.id` (`nullOnDelete`) — terisi jika dipicu manual lewat tombol UI, `null` jika dari scheduler/CLI
- `timestamps` (`created_at` diberi index untuk pengambilan briefing terbaru `latest('id')`)

### 4.2 `SentimentAnalysisService` (Sentimen Kontekstual Per-Postingan)

Method utama:
- `analyze(string $content, ? iterable $matchedKeywords = null): string`
  Mengembalikan salah satu dari `'sensitif'`, `'negatif'`, `'netral'`, `'positif'`.

**Algoritma Penilaian:**
1. **Normalisasi Teks:** Ubah ke huruf kecil (`mb_strtolower`), pecah kalimat/token.
2. **Deteksi Negasi Kontekstual:**
   - Daftar kata negasi: `['tidak', 'tak', 'bukan', 'belum', 'jangan', 'tanpa', 'bebas', 'ora', 'mboten', 'enggak', 'gak']`.
   - Jika sebuah kata kunci atau kata leksikon didahului oleh kata negasi dalam jarak $\le 2$ kata sebelum kata tersebut (contoh: *"tidak ada kericuhan"*, *"bebas banjir"*, *"tidak terbukti korupsi"*):
     - Kata kategori `sensitif` / `negatif` tidak jadi menambah skor negatif/sensitif (dinetralkan atau menambah skor positif ringan +1).
     - Kata kategori `positif` yang dinegasi (contoh: *"tidak kondusif"*, *"belum aman"*, *"tidak transparan"*) justru menambah skor `negatif` (+2).
3. **Skor Kata Kunci Aktif (`keywords`):**
   - Setiap kemunculan kata kunci aktif (yang tidak dinegasi) memberi bobot:
     - `sensitif`: +4 per kemunculan
     - `negatif`: +2 per kemunculan
     - `positif`: +2 per kemunculan
     - `netral`: +1 per kemunculan
4. **Leksikon Kontekstual Bawaan (Fallback untuk post tanpa keyword terdaftar):**
   - Leksikon `sensitif`: `['ricuh', 'bentrok', 'demo', 'unjuk rasa', 'mogok', 'boikot', 'darurat', 'korupsi', 'suap', 'pungli', 'intoleransi', 'radikal', 'teror', 'hoax', 'fitnah', 'krisis']` (bobot +3).
   - Leksikon `negatif`: `['keluhan', 'protes', 'macet', 'rusak', 'banjir', 'longsor', 'mahal', 'langka', 'PHK', 'sampah', 'polusi', 'lambat', 'kecewa', 'meresahkan', 'ancaman', 'gagal']` (bobot +1.5).
   - Leksikon `positif`: `['apresiasi', 'kondusif', 'aman', 'prestasi', 'penghargaan', 'bantuan', 'solusi', 'sukses', 'lancar', 'inovasi', 'sinergi', 'sejahtera', 'tuntas', 'terkendali']` (bobot +1.5).
5. **Keputusan Akhir:**
   - Jika skor `sensitif` $\ge 3$ → `'sensitif'`
   - Jika skor `negatif` $>$ skor `positif` dan skor `negatif` $\ge 1.5$ → `'negatif'`
   - Jika skor `positif` $>$ skor `negatif` dan skor `positif` $\ge 1.5$ → `'positif'`
   - Selain itu → `'netral'`.

**Titik Integrasi:**
- `PostController::store`: Jika admin memilih sentimen spesifik di form (`sentiment_mode` bukan `'auto'`), simpan pilihan tersebut dan set `sentiment_manual = true`. Jika `'auto'` (default), hitung lewat `SentimentAnalysisService` dan set `sentiment_manual = false`.
- `PostController::update`: Jika `sentiment_mode` diubah ke nilai manual, set `sentiment_manual = true`. Jika `'auto'`, set `sentiment_manual = false` dan hitung ulang.
- `SocialFetchService`: Saat post baru dibuat, hitung `sentiment`. Saat post lama diperbarui kontennya, hitung ulang **hanya jika** `sentiment_manual === false`.
- `PostsImport`: Hitung `sentiment` otomatis saat import CSV.
- `KeywordController::rescan`: Hitung ulang keyword dan perbarui `sentiment` untuk semua post yang `sentiment_manual === false`.

### 4.3 `NarrativeBriefingService` (Klasterisasi Isu & Ringkasan Eksekutif)

Method utama:
- `generate(?int $hours = 24, bool $forceHeuristic = false, ?int $userId = null): NarrativeBriefing`

**Langkah 1: Pengumpulan Data Jendela Waktu**
1. Cari postingan dengan `posted_at >= now()->subHours($hours)` (`window_label = "{$hours} Jam Terakhir"`).
2. **Adaptive Window:** Jika jumlah postingan pada jendela 24 jam adalah 0, perluas pencarian ke 7 hari terakhir (`window_label = "7 Hari Terakhir"`). Jika masih 0, ambil hingga 30 postingan terbaru di database (`window_label = "30 Postingan Terakhir"`).
3. Muat relasi `socialAccount.region`, `socialAccount.category`, `keywords`, dan `tags`, diurutkan berdasarkan `total_engagement` tertinggi (maksimal 60 postingan teratas untuk dianalisis agar payload ringkas dan cepat).

**Langkah 2: Jalur Utama — Google Gemini API (Jika Kredensial Tersedia & Tidak `--force-heuristic`)**
- Konfigurasi di `config/services.php`:
  ```php
  'gemini' => [
      'key' => env('GEMINI_API_KEY'),
      'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
      'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta'),
  ],
  ```
- Panggil `POST {endpoint}/models/{model}:generateContent?key={key}` dengan header `Content-Type: application/json`, timeout 25 detik:
  - `generationConfig.responseMimeType = "application/json"`
  - Prompt sistem menginstruksikan Gemini bertindak sebagai Analis Intelijen Media Sosial Provinsi Jawa Tengah dan wajib mengembalikan skema JSON:
    - `executive_summary` (string, 2 paragraf bahasa Indonesia formal & lugas)
    - `issue_clusters` (array 3–5 objek: `title`, `sentiment` (`sensitif`|`negatif`|`netral`|`positif`), `urgency` (`tinggi`|`sedang`|`rendah`), `summary`, `post_ids` (array integer dari daftar post yang diberikan))
    - `recommendations` (array 2–4 string rekomendasi konkret untuk pimpinan)
- Validasi & Pengayaan (*Enrichment*):
  - Untuk setiap klaster dari Gemini, sistem memvalidasi `post_ids` terhadap koleksi post asli, lalu menghitung secara akurat `regions`, `categories`, `post_count`, dan `total_engagement` dari database (sehingga angka statistik tidak pernah halusinasi AI).
  - Simpan dengan `source = 'gemini'`, `status = 'ok'`.

**Langkah 3: Jalur Cadangan Otomatis — Heuristic Clustering Engine**
Dijalankan jika:
- `GEMINI_API_KEY` belum diatur (`source = 'heuristic'`, `status = 'ok'`, `error_message = null`), ATAU
- Panggilan Gemini gagal / timeout / kuota habis / JSON tidak valid (`source = 'heuristic'`, `status = 'fallback'`, `error_message = $this->sanitize($e->getMessage())`).

**Cara Kerja Heuristic Clustering Engine:**
1. Mengelompokkan postingan berdasarkan:
   - Kata kunci aktif yang paling banyak muncul / memiliki engagement tertinggi.
   - Postingan berstatus `is_flagged = true` atau bersentimen `sensitif`/`negatif` yang belum masuk klaster keyword, dikelompokkan berdasarkan Kategori Akun atau Wilayah (`Region`).
   - Jika tidak ada keyword sama sekali, kelompokkan berdasarkan Kategori Akun (`Mahasiswa`, `Buruh`, `LSM`, `Media`, `Ormas`).
2. Ambil maksimal 4 klaster teratas berdasarkan skor urgensi (kombinasi bobot sentimen + jumlah engagement + jumlah post).
3. Tentukan `urgency`:
   - `'tinggi'` jika sentimen dominan `'sensitif'` atau terdapat post `is_flagged = true`.
   - `'sedang'` jika sentimen dominan `'negatif'` atau `post_count >= 3`.
   - `'rendah'` selain itu.
4. Susun `executive_summary` (2 paragraf Bahasa Indonesia otomatis yang menyebutkan total postingan, total interaksi, wilayah paling aktif, distribusi sentimen, dan topik utama yang menonjol) serta `recommendations` (2–3 poin berdasarkan klaster berurgensi tinggi/sedang).

**Sanitasi Keamanan Kredensial:**
- Method `sanitize(string $message): string` mengganti nilai `config('services.gemini.key')`, `config('services.youtube.key')`, dan `config('services.instagram.token')` serta pola query `?key=[^&\s]+` menjadi `***`, dipotong maksimal 500 karakter.

### 4.4 CLI, Penjadwalan & Rute Web

- **Artisan Command `intel:briefing`** (`app/Console/Commands/GenerateNarrativeBriefing.php`):
  - Signature: `intel:briefing {--hours=24} {--heuristic : Paksa gunakan mesin heuristik tanpa memanggil API Gemini}`
  - Menghapus data `narrative_briefings` yang lebih tua dari 90 hari.
  - Mencetak ringkasan hasil (`source`, `status`, jumlah klaster, dan ringkasan singkat) ke konsol dan mengembalikan `self::SUCCESS`.
- **Scheduler (`routes/console.php`):**
  - `Schedule::command('intel:briefing')->dailyAt('07:05')->timezone('Asia/Jakarta')->withoutOverlapping(30);`
  - `Schedule::command('intel:briefing')->dailyAt('19:05')->timezone('Asia/Jakarta')->withoutOverlapping(30);`
- **Web Route (`routes/web.php`):**
  - `POST /briefings/generate` → `[DashboardController::class, 'generateBriefing']`, dilindungi `middleware('can:can-edit')`, nama rute `briefings.generate`. Redirect kembali ke Dashboard dengan flash message `success` (atau `warning` jika fallback).

### 4.5 Antarmuka Pengguna (UI/UX)

1. **Dashboard (`resources/views/dashboard/index.blade.php`):**
   - Tambahkan seksi **"Ringkasan Intelijen & Klaster Isu (Executive Briefing)"** tepat di bawah 4 Stat Cards utama.
   - Header panel menampilkan:
     - Judul & badge sumber: `AI Gemini` (ungu/indigo) atau `Analitik Otomatis` (biru/slate), ditambah badge peringatan kuning jika `status === 'fallback'`.
     - Waktu pembaruan terakhir (`created_at->diffForHumans()`) & label jendela data (`window_label`).
     - Tombol **"Perbarui Ringkasan"** (`@can('can-edit')`, form POST ke `route('briefings.generate')`).
   - Isi panel (2 kolom pada layar besar):
     - **Kolom Kiri (Ringkasan & Rekomendasi):** Teks `executive_summary`, bar komposisi sentimen (`Sensitif`, `Negatif`, `Netral`, `Positif`), dan kotak **Rekomendasi Perhatian Pimpinan**.
     - **Kolom Kanan (Klaster Isu Dominan):** Kartu untuk setiap klaster isu (`title`, badge `urgency`, badge `sentiment`, ringkasan singkat, tag wilayah terdampak, angka `post_count` & `total_engagement`, serta tautan ke postingan terkait).
   - Jika tabel `narrative_briefings` masih kosong saat Dashboard dibuka dan terdapat data di `posts`, `DashboardController::index` secara otomatis membangkitkan 1 briefing awal lewat mode heuristik cepat agar pimpinan langsung melihat ringkasan tanpa halaman kosong.

2. **Halaman Postingan (`resources/views/posts/index.blade.php`, `show.blade.php`, `_form.blade.php`):**
   - Tambahkan dropdown filter **Sentimen** (`Semua Sentimen`, `Sensitif`, `Negatif`, `Netral`, `Positif`) di `posts/index.blade.php` dan dukung filter `$request->filled('sentiment')` di `PostController::index`.
   - Tampilkan badge sentimen berwarna di tabel daftar postingan dan halaman detail postingan (dengan indikator kecil jika *Manual*).
   - Tambahkan pilihan **Sentimen** di `posts/_form.blade.php` (`Otomatis (Deteksi Sistem)` sebagai default, atau pilihan manual `Sensitif`, `Negatif`, `Netral`, `Positif`).

3. **Halaman Analitik (`AnalyticsController` & `resources/views/analytics/index.blade.php`):**
   - Di `AnalyticsController::contentAnalytics`, gunakan agregasi langsung dari kolom `posts.sentiment` (`GROUP BY sentiment`) untuk grafik distribusi sentimen 30 hari terakhir (lebih cepat dan mencakup seluruh postingan).
   - Perbaiki bug sintaks JavaScript di `resources/views/analytics/index.blade.php` dengan membungkus ekspresi JSON array pada `#trendAggChart` (baris 380), `#wordCloudCanvas` (baris 453), `#kwTrendChart` (baris 480), dan `#sentimentChart` (baris 516 & 519) menggunakan kurung siku `[ ... ]` atau `@json(...)`.

---

## 5. Yang Diserahkan kepada Builder

- Detail penataan kelas Tailwind CSS pada panel Executive Briefing selama konsisten dengan kartu Dashboard yang sudah ada (warna indigo/slate/emerald/red).
- Struktur internal helper di dalam `SentimentAnalysisService` dan `NarrativeBriefingService` selama kontrak method publik dan struktur JSON sesuai spesifikasi di atas.
