# Pengambilan Data Otomatis YouTube & Instagram — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Akun YouTube & Instagram yang dipantau diperbarui otomatis (angka akun + postingan baru) dua kali sehari lewat API resmi gratis.

**Architecture:** Satu konektor per platform (`PlatformFetcher`) mengembalikan DTO seragam. `SocialFetchService` menyimpan hasilnya (dedup, deteksi kata kunci, log, isolasi error). Perintah `social:fetch` dijalankan scheduler 10 menit sebelum `monitor:snapshot` yang sudah ada. UI menambahkan tombol "Ambil sekarang", badge status, checkbox "Ambil otomatis", dan halaman status untuk superadmin.

**Tech Stack:** Laravel 11.51, PHP 8.2, MariaDB 10.4 (XAMPP), PHPUnit 11, `Http::` client + `Http::fake()`, Blade + Tailwind CDN + Font Awesome + Alpine.

**Spec:** `docs/superpowers/specs/2026-10-23-auto-fetch-youtube-instagram-design.md`

Semua path di bawah relatif terhadap `sociowatch-jateng/`, kecuali path `docs/…` yang relatif terhadap root repo.

## Global Constraints

- **Test TIDAK BOLEH menyentuh database `sociowatch_jateng`.** Test hanya memakai `sociowatch_jateng_test` (Task 1). Jangan jalankan `php artisan test` sebelum Task 1 selesai.
- Hanya API resmi gratis: YouTube Data API v3 (`https://www.googleapis.com/youtube/v3`) dan Instagram Graph API Business Discovery (`https://graph.facebook.com/{version}`). Tidak ada scraping, SDK baru, atau dependensi composer baru.
- Kredensial hanya dari `.env` lewat `config('services.youtube.key')`, `config('services.instagram.token')`, `config('services.instagram.business_id')`, `config('services.instagram.version')` (default `v23.0`), `config('services.instagram.token_expires_at')` (opsional, `Y-m-d`). Nilai kredensial tidak boleh muncul di log, flash message, kolom DB, atau output perintah.
- Platform tanpa kredensial dilewati dengan pesan `kredensial belum diatur`, tidak pernah exception.
- Hanya akun `platform ∈ {youtube, instagram}`, `is_active = true`, dan `auto_fetch = true` yang diproses. Akun platform lain tidak pernah disentuh.
- Field yang diperbarui otomatis: `followers_count`, `following_count` (IG saja), `post_count`, `external_id`, `last_fetched_at`, `last_fetch_status`, `last_fetch_error`. **Tidak ditimpa:** `display_name`, `bio`, `category_id`, `region_id`, `notes`, `profile_picture`.
- Pengambilan pertama (`last_fetched_at` null): postingan sejak 30 hari terakhir, maks 50. Berikutnya: sejak 14 hari terakhir, maks 50. Post yang sudah ada dan masih dalam jendela itu diperbarui angka engagement-nya.
- Teks UI dalam Bahasa Indonesia, mengikuti gaya view yang ada.
- Jadwal: `social:fetch` 06:50 & 18:50, `monitor:snapshot` 07:00 & 19:00, semuanya `->timezone('Asia/Jakarta')`. `social:fetch` memakai `->withoutOverlapping()`. `APP_TIMEZONE` tidak diubah.
- Log `fetch_logs` disimpan 90 hari.

## Review Focus

1. **Post manual dengan URL sama sudah ada** → harus disambungkan (isi `external_id`), bukan dibuat baris baru. Test: `test_links_existing_manual_post_by_url` (Task 5).
2. **Caption Instagram kosong / tanpa `caption` / tanpa `like_count`** → disimpan dengan content `''` dan likes 0, tidak error. Test: `test_maps_media_without_caption_or_likes` (Task 4).
3. **Token Instagram ditolak di tengah proses** → sisa akun IG dihentikan dan ditandai `auth_error`, akun YouTube tetap diproses. Test: `test_auth_error_stops_only_that_platform` (Task 5).
4. **Pesan error API mengandung API key/token** (Google menaruh key di URL error) → disensor jadi `***` sebelum disimpan atau ditampilkan. Test: `test_error_message_never_contains_credentials` (Task 5).
5. **Update engagement tidak mengubah `posted_at`**. Ini bug `ON UPDATE` yang diperbaiki di Task 2. Test: `test_updating_post_keeps_posted_at` (Task 2), ditambah asersi di `test_second_run_updates_engagement_without_duplicates` (Task 5).

---

### Task 1: Isolasi database test & migration bisa dijalankan dari nol

**Files:**
- Modify: `phpunit.xml` (baris 25–26)
- Modify: `tests/TestCase.php`
- Modify: `database/migrations/2026_05_02_203809_create_account_admins_table.php`, `database/migrations/2026_05_02_203809_create_activity_logs_table.php`
- Modify: `tests/Feature/ExampleTest.php`
- Create: `database/factories/CategoryFactory.php`, `RegionFactory.php`, `SocialAccountFactory.php`, `PostFactory.php`, `KeywordFactory.php`
- Modify: `app/Models/Category.php`, `Region.php`, `SocialAccount.php`, `Post.php`, `Keyword.php` (tambah `use HasFactory;`)
- Test: `tests/Feature/TestDatabaseTest.php`

**Interfaces:**
- Produces: factory `SocialAccount::factory()` dengan state `->youtube()` (platform `youtube`, username `JawaPosTV`, profile_url `https://youtube.com/@JawaPosTV`) dan `->instagram()` (platform `instagram`, username `nu_jateng`, profile_url `https://instagram.com/nu_jateng`). Default membuat Category & Region baru. `Post::factory()` untuk akun tertentu. `Keyword::factory()` (word unik, category `netral`, is_active true).

- [ ] **Step 1:** Buat database kosong: `C:\xampp\mysql\bin\mysql.exe -uroot -e "CREATE DATABASE IF NOT EXISTS sociowatch_jateng_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"`.
- [ ] **Step 2:** Di `phpunit.xml`, ganti dua baris yang di-comment menjadi `<env name="DB_CONNECTION" value="mysql"/>` dan `<env name="DB_DATABASE" value="sociowatch_jateng_test"/>`.
- [ ] **Step 3:** Di `tests/TestCase.php`, override `setUp()`: setelah `parent::setUp()`, panggil `$this->fail(...)` kalau `config('database.connections.mysql.database')` tidak diakhiri `_test`. Ini pengaman kalau config ter-cache.
- [ ] **Step 4: Write the failing test** `tests/Feature/TestDatabaseTest.php` (pakai `RefreshDatabase`):

```php
public function test_uses_isolated_test_database(): void
{
    $this->assertSame('sociowatch_jateng_test', DB::connection()->getDatabaseName());
}

public function test_factories_build_youtube_and_instagram_accounts(): void
{
    $yt = SocialAccount::factory()->youtube()->create();
    $ig = SocialAccount::factory()->instagram()->create();
    Post::factory()->for($yt, 'socialAccount')->create();
    $this->assertSame('youtube', $yt->platform);
    $this->assertSame('instagram', $ig->platform);
    $this->assertSame(1, $yt->posts()->count());
}
```

- [ ] **Step 5:** Run `php artisan test --filter=TestDatabaseTest`. Expected: FAIL. Kemungkinan `errno: 150 "Foreign key constraint is incorrectly formed"` saat `migrate:fresh` (`account_admins` dibuat sebelum `social_accounts`), atau factory belum ada.
- [ ] **Step 6:** Di kedua migration `2026_05_02_203809_create_{account_admins,activity_logs}_table.php`, bungkus isi `up()` dengan `Schema::disableForeignKeyConstraints();` … `Schema::enableForeignKeyConstraints();`. **Jangan ganti nama file**, karena nama migration sudah tercatat di tabel `migrations` produksi.
- [ ] **Step 7:** Tambahkan `HasFactory` ke 5 model dan buat 5 factory. Kolom wajib: Region (`name`, `type` ∈ kabupaten/kota, `latitude`, `longitude`, `geojson_key`), Category (`name`, `color` `#RRGGBB`), Post (`platform` mengikuti akun, `post_url` unik, `content`, `posted_at`).
- [ ] **Step 8:** Ubah `tests/Feature/ExampleTest.php` supaya mengharapkan redirect: `$this->get('/')->assertRedirect();`.
- [ ] **Step 9:** Run `php artisan test`. Expected: semua PASS. Lalu pastikan DB produksi utuh: `mysql -uroot -N -e "SELECT COUNT(*) FROM sociowatch_jateng.posts"` → `451`.
- [ ] **Step 10: Commit** `test: isolasi database test, perbaiki urutan migration, tambah factory`.

---

### Task 2: Skema — perbaikan `posted_at`, kolom fetch, tabel log

**Files:**
- Create: `database/migrations/2026_10_23_000001_fix_posted_at_and_triggered_at_on_update.php`
- Create: `database/migrations/2026_10_23_000002_add_auto_fetch_columns.php`
- Create: `database/migrations/2026_10_23_000003_create_fetch_logs_table.php`
- Create: `app/Enums/FetchStatus.php`, `app/Models/FetchLog.php`
- Modify: `app/Models/SocialAccount.php`, `app/Models/Post.php`
- Test: `tests/Feature/SocialFetch/SchemaTest.php`

**Interfaces:**
- Produces:
  - `enum App\Enums\FetchStatus: string { Ok='ok'; NotFound='not_found'; Unsupported='unsupported'; AuthError='auth_error'; Quota='quota'; Error='error'; }` dengan `label(): string` (Indonesia: Berhasil / Akun tidak ditemukan / Tidak didukung — isi manual / Kredensial ditolak / Kuota habis / Error) dan `isPlatformFatal(): bool` (true untuk `AuthError`, `Quota`).
  - `social_accounts`: `external_id` varchar(100) null, `auto_fetch` bool default true, `last_fetched_at` timestamp null, `last_fetch_status` varchar(20) null, `last_fetch_error` text null. Model: fillable dan casts (`auto_fetch` boolean, `last_fetched_at` datetime, `last_fetch_status` → `FetchStatus`). Relasi `fetchLogs(): HasMany`.
  - `posts.external_id` varchar(100) null + unique index `posts_platform_external_id_unique` (`platform`, `external_id`). Ditambahkan ke `$fillable` Post.
  - Tabel `fetch_logs`: `id`, `social_account_id` FK cascade, `status` varchar(20), `new_posts` uint default 0, `updated_posts` uint default 0, `message` text null, `fetched_at` timestamp, index (`social_account_id`,`fetched_at`). Model `FetchLog` (`$timestamps = false`, cast `status` → `FetchStatus`, `fetched_at` datetime, `socialAccount()` belongsTo).

- [ ] **Step 1: Write the failing tests**

```php
public function test_updating_post_keeps_posted_at(): void
{
    $post = Post::factory()->for(SocialAccount::factory()->youtube(), 'socialAccount')
        ->create(['posted_at' => '2026-10-01 08:00:00']);
    $this->travel(2)->days();
    $post->update(['likes_count' => 99]);
    $this->assertSame('2026-10-01 08:00:00', $post->fresh()->posted_at->format('Y-m-d H:i:s'));
}

public function test_external_id_is_unique_per_platform(): void
{
    $acc = SocialAccount::factory()->youtube()->create();
    Post::factory()->for($acc, 'socialAccount')->create(['external_id' => 'abc']);
    $this->expectException(QueryException::class);
    Post::factory()->for($acc, 'socialAccount')->create(['external_id' => 'abc']);
}

public function test_new_accounts_default_to_auto_fetch(): void
{
    $this->assertTrue(SocialAccount::factory()->instagram()->create()->fresh()->auto_fetch);
}
```

- [ ] **Step 2:** Run `php artisan test --filter=SchemaTest`. Expected: FAIL (`posted_at` berubah / kolom tidak ada).
- [ ] **Step 3:** Migration 000001: `DB::statement` `ALTER TABLE posts MODIFY posted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` dan `ALTER TABLE alerts MODIFY triggered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` (tanpa `ON UPDATE`). `down()` mengembalikan `ON UPDATE CURRENT_TIMESTAMP`.
- [ ] **Step 4:** Migration 000002 & 000003, enum, model `FetchLog`, dan perubahan model sesuai Interfaces.
- [ ] **Step 5:** Run `php artisan test`. Expected: semua PASS.
- [ ] **Step 6:** Backup lalu migrasikan produksi: `mysqldump -uroot sociowatch_jateng > "D:/Project Bot - Work/JatengLens_backup_20261022/sociowatch_before_autofetch.sql"`, lalu `php artisan migrate`. Expected: 3 migration `DONE`, dan `SELECT COUNT(*) FROM sociowatch_jateng.posts` tetap `451`.
- [ ] **Step 7: Commit** `feat(fetch): skema auto-fetch dan perbaikan posted_at ON UPDATE`.

---

### Task 3: Kontrak konektor + konektor YouTube

**Files:**
- Create: `app/Services/SocialFetch/PlatformFetcher.php`, `FetchedAccount.php`, `FetchedPost.php`, `FetchException.php`, `YouTubeFetcher.php`
- Modify: `config/services.php`, `.env.example`
- Test: `tests/Feature/SocialFetch/YouTubeFetcherTest.php`

**Interfaces:**
- Produces:
  - `interface PlatformFetcher { public function platform(): string; public function isConfigured(): bool; /** @throws FetchException */ public function fetch(SocialAccount $account, CarbonImmutable $since, int $limit): FetchedAccount; }`
  - `final readonly class FetchedPost { __construct(string $externalId, string $url, string $content, string $mediaType, int $likes, int $comments, int $views, CarbonImmutable $postedAt) }`. `$mediaType` ∈ `text,image,video,reel,story`.
  - `final readonly class FetchedAccount { __construct(string $externalId, int $followers, ?int $following, int $postCount, array $posts /* list<FetchedPost> */) }`.
  - `class FetchException extends RuntimeException { __construct(public readonly FetchStatus $status, string $message) }`.
  - `YouTubeFetcher implements PlatformFetcher` (`platform()` = `'youtube'`).
  - `config/services.php`: kunci `youtube.key` (`YOUTUBE_API_KEY`) dan `instagram.{token,business_id,version,token_expires_at}` (`INSTAGRAM_ACCESS_TOKEN`, `INSTAGRAM_BUSINESS_ACCOUNT_ID`, `INSTAGRAM_GRAPH_VERSION` default `v23.0`, `INSTAGRAM_TOKEN_EXPIRES_AT`). Tambahkan baris kosong di `.env.example`.

- [ ] **Step 1: Write the failing tests** (semua pakai `Http::fake([...])` dan `Http::preventStrayRequests()`, dengan `config(['services.youtube.key' => 'TESTKEY'])`):
  - `test_resolves_handle_and_maps_channel_and_videos`: akun `->youtube()` tanpa `external_id`. Fake `channels*` → `{"items":[{"id":"UC123","statistics":{"subscriberCount":"1500","videoCount":"42"},"contentDetails":{"relatedPlaylists":{"uploads":"UU123"}}}]}`, `playlistItems*` → 2 item (`videoId` `v1` terbit 2 hari lalu, `v2` terbit 40 hari lalu), `videos*` → statistik `v1` (title `Judul`, description `Isi`, viewCount `100`, likeCount `7`, commentCount `3`). Assert: `externalId === 'UC123'`, `followers === 1500`, `following === null`, `postCount === 42`, hanya 1 post (`v2` di luar `$since` = 30 hari), dengan `url === 'https://www.youtube.com/watch?v=v1'`, `content === "Judul\n\nIsi"`, `mediaType === 'video'`, `views === 100`, `likes === 7`, `comments === 3`. Assert request pertama memakai `forHandle=@JawaPosTV`.
  - `test_uses_stored_channel_id_or_channel_url`: dengan `external_id = 'UCabc'` request memakai `id=UCabc`. Dengan `external_id` null dan `profile_url = 'https://youtube.com/channel/UCxyz'` request memakai `id=UCxyz`.
  - `test_missing_channel_throws_not_found`: `{"items":[]}` → `FetchException` dengan status `NotFound`.
  - `test_quota_and_bad_key_map_to_platform_fatal_statuses`: 403 dengan `error.errors[0].reason = "quotaExceeded"` → `Quota`. 400 dengan reason `keyInvalid` → `AuthError`.
  - `test_not_configured_without_key`: `config(['services.youtube.key' => null])` → `isConfigured() === false`.
- [ ] **Step 2:** Run `php artisan test --filter=YouTubeFetcherTest`. Expected: FAIL (class tidak ada).
- [ ] **Step 3:** Implementasikan DTO, exception, interface, config, dan `YouTubeFetcher`. Urutan resolusi channel (spec §6.9): `external_id` → `/channel/(UC[\w-]+)` di `profile_url` → `/@([\w.-]+)` di `profile_url` → `username` sebagai handle. Panggilan: `channels` (`part=statistics,contentDetails`), `playlistItems` (`part=contentDetails`, `maxResults=min($limit,50)`), `videos` (`part=snippet,statistics`, id dipisah koma). Saring `videoPublishedAt >= $since`. Statistik yang tidak ada → 0. Timeout 20 detik, `retry(2, 500)` hanya untuk `ConnectionException`. Error lain (non-2xx) → `FetchException(Error, ...)`.
- [ ] **Step 4:** Run `php artisan test --filter=YouTubeFetcherTest`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(fetch): kontrak konektor dan konektor YouTube Data API`.

---

### Task 4: Konektor Instagram (Business Discovery)

**Files:**
- Create: `app/Services/SocialFetch/InstagramFetcher.php`
- Test: `tests/Feature/SocialFetch/InstagramFetcherTest.php`

**Interfaces:**
- Consumes: `PlatformFetcher`, `FetchedAccount`, `FetchedPost`, `FetchException`, `FetchStatus` (Task 2–3).
- Produces: `InstagramFetcher implements PlatformFetcher` (`platform()` = `'instagram'`; `isConfigured()` = token dan business_id terisi).

- [ ] **Step 1: Write the failing tests** (config token `TESTTOKEN`, business_id `1789`, version `v23.0`):
  - `test_maps_business_discovery_response`: fake `graph.facebook.com/v23.0/1789*` → `{"business_discovery":{"id":"555","followers_count":2000,"follows_count":10,"media_count":300,"media":{"data":[…]}}}` dengan 3 media terbit kemarin: IMAGE (caption `Halo`, like 5, comment 1, permalink `https://www.instagram.com/p/A/`), VIDEO dengan `media_product_type: "REELS"`, dan CAROUSEL_ALBUM. Assert: `externalId === '555'`, `followers === 2000`, `following === 10`, `postCount === 300`, `mediaType` berturut-turut `image`, `reel`, `image`, `url` = permalink, `views === 0`. Assert query `fields` memuat `business_discovery.username(nu_jateng)` dan `media.limit(50)`.
  - `test_maps_media_without_caption_or_likes`: media tanpa `caption` dan tanpa `like_count` → `content === ''`, `likes === 0`.
  - `test_filters_media_older_than_since`.
  - `test_error_codes_map_to_statuses`: `error.code` 190 → `AuthError`, 4/17/32/613 → `Quota`, 100 atau 110 → `Unsupported` dengan pesan `Akun tidak ditemukan atau bukan akun Business/Creator`.
- [ ] **Step 2:** Run `php artisan test --filter=InstagramFetcherTest`. Expected: FAIL.
- [ ] **Step 3:** Implementasikan satu panggilan `GET https://graph.facebook.com/{version}/{business_id}` dengan `fields=business_discovery.username({username}){id,followers_count,follows_count,media_count,media.limit({limit}){id,caption,like_count,comments_count,media_type,media_product_type,permalink,timestamp}}` dan `access_token`. Username diambil dari `username` tanpa `@`. Pemetaan media: REELS → `reel`, VIDEO → `video`, selain itu → `image`. Timeout/retry sama seperti Task 3.
- [ ] **Step 4:** Run `php artisan test --filter=InstagramFetcherTest`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(fetch): konektor Instagram Business Discovery`.

---

### Task 5: `SocialFetchService` — simpan hasil, dedup, kata kunci, isolasi error

**Files:**
- Create: `app/Services/SocialFetch/SocialFetchService.php`, `app/Services/SocialFetch/FetchResult.php`
- Modify: `app/Providers/AppServiceProvider.php` (bind daftar fetcher)
- Test: `tests/Feature/SocialFetch/SocialFetchServiceTest.php`

**Interfaces:**
- Consumes: Task 2–4, plus `App\Services\KeywordDetectionService::detect(string): array` yang sudah ada.
- Produces:
  - `final readonly class FetchResult { __construct(SocialAccount $account, FetchStatus $status, int $newPosts, int $updatedPosts, ?string $message) }`.
  - `SocialFetchService::__construct(iterable $fetchers /* PlatformFetcher[] */, KeywordDetectionService $detector)`. Container mendaftarkan `YouTubeFetcher` dan `InstagramFetcher`.
  - `fetchAccount(SocialAccount $account, bool $dryRun = false): FetchResult`
  - `fetchAll(?string $platform = null, ?int $accountId = null, bool $dryRun = false): Collection` (berisi `FetchResult`). Untuk platform yang tidak terkonfigurasi, akunnya tidak diproses; method `skippedPlatforms(): array` mengembalikan nama platform yang dilewati pada pemanggilan terakhir.
  - `eligibleAccounts(?string $platform, ?int $accountId): Builder`.

Aturan (dari Global Constraints dan spec §6.2–6.4):
- `$since` = now − 30 hari kalau `last_fetched_at` null, kalau tidak now − 14 hari. `$limit` = 50.
- Pencocokan post: (`platform`, `external_id`), lalu `post_url` dengan `external_id` null pada akun yang sama. Kalau cocok: update `likes/comments/views`, isi `external_id`, dan kalau `content` berubah, update dan re-sync keyword. Kalau tidak cocok: create (`is_flagged` false) dan sync `keywords()` dengan hasil `detect()`. Tag dan flag tidak disentuh.
- Sukses: update field akun yang diizinkan, `last_fetched_at = now()`, status `Ok`, error null.
- `FetchException`: simpan status dan pesan (sudah disensor) ke akun. `last_fetched_at` tidak diubah. Kalau `isPlatformFatal()`, sisa akun di platform itu ditandai status yang sama tanpa memanggil API.
- Exception lain (`Throwable`): status `Error`, lanjut ke akun berikutnya.
- Sensor: ganti setiap nilai kredensial dari config (`services.youtube.key`, `services.instagram.token`) di pesan dengan `***`, potong maks 500 karakter.
- Setiap akun yang diproses (kecuali `$dryRun`) membuat satu baris `FetchLog`. `$dryRun` tidak menulis apa pun ke DB.
- Simpan per akun dalam `DB::transaction`.

- [ ] **Step 1: Write the failing tests** (fake fetcher in-memory yang mengimplementasikan `PlatformFetcher`, di-inject lewat konstruktor, tanpa HTTP):
  - `test_creates_posts_updates_account_and_logs`: 2 post baru → akun `followers_count`/`post_count`/`external_id` terupdate, `last_fetch_status === FetchStatus::Ok`, 2 post dengan `external_id`, 1 `FetchLog` dengan `new_posts === 2`.
  - `test_second_run_updates_engagement_without_duplicates`: jalankan dua kali, run kedua likes 7 → 20. Assert jumlah post tetap 2, likes 20, `posted_at` sama persis dengan run pertama, `updated_posts === 2` pada log kedua.
  - `test_links_existing_manual_post_by_url`: post manual dengan `post_url` sama dan `external_id` null → setelah fetch jumlah post tetap 1 dan `external_id` terisi.
  - `test_detects_keywords_on_new_posts`: `Keyword::factory(['word' => 'banjir'])`, content `Banjir di Demak` → pivot terisi dengan `occurrence_count` 1.
  - `test_does_not_overwrite_curated_fields`: `display_name`, `bio`, `notes` tidak berubah.
  - `test_first_run_uses_30_day_window_then_14`: fake fetcher merekam `$since`. Run pertama ≈ now − 30 hari, run kedua ≈ now − 14 hari.
  - `test_auth_error_stops_only_that_platform`: 2 akun IG (yang pertama melempar `AuthError`) + 1 akun YT. Assert fetcher IG hanya dipanggil 1×, kedua akun IG berstatus `auth_error`, dan akun YT `ok`.
  - `test_unexpected_exception_is_isolated`: akun pertama melempar `RuntimeException`, akun kedua tetap `ok`.
  - `test_error_message_never_contains_credentials`: `config(['services.youtube.key' => 'SECRETKEY123'])`, fetcher melempar pesan `bad key=SECRETKEY123` → `last_fetch_error` dan `FetchLog.message` tidak memuat `SECRETKEY123` tetapi memuat `***`.
  - `test_skips_unconfigured_platform_and_ineligible_accounts`: fetcher IG `isConfigured() === false`, akun twitter, akun YT dengan `auto_fetch` false, dan akun YT `is_active` false → tidak ada yang diproses. `skippedPlatforms() === ['instagram']`.
  - `test_dry_run_writes_nothing`.
- [ ] **Step 2:** Run `php artisan test --filter=SocialFetchServiceTest`. Expected: FAIL.
- [ ] **Step 3:** Implementasikan `FetchResult`, `SocialFetchService`, dan binding di `AppServiceProvider::register()`.
- [ ] **Step 4:** Run `php artisan test`. Expected: semua PASS.
- [ ] **Step 5: Commit** `feat(fetch): service penyimpanan hasil fetch dengan dedup dan isolasi error`.

---

### Task 6: Perintah `social:fetch` + jadwal

**Files:**
- Create: `app/Console/Commands/SocialFetch.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/SocialFetch/SocialFetchCommandTest.php`

**Interfaces:**
- Consumes: `SocialFetchService` (Task 5).
- Produces: signature `social:fetch {--platform=} {--account=} {--dry-run}`. Output per akun: `[status] platform @username — N baru, M diperbarui` (+ pesan bila gagal). Untuk platform yang dilewati: `Lewati {platform}: kredensial belum diatur`. Ringkasan akhir: `Selesai: X berhasil, Y gagal`. Exit code `SUCCESS` walaupun ada akun gagal.

- [ ] **Step 1: Write the failing tests** (bind `SocialFetchService` dengan fake fetcher seperti Task 5):
  - `test_command_runs_and_prints_summary`: `$this->artisan('social:fetch')->expectsOutputToContain('Selesai: 1 berhasil, 0 gagal')->assertSuccessful()`.
  - `test_platform_and_account_options_filter`.
  - `test_reports_skipped_platform`: output memuat `Lewati instagram: kredensial belum diatur`.
  - `test_prunes_logs_older_than_90_days`: `FetchLog` 91 hari lalu terhapus, yang 89 hari tetap ada. Tidak dipangkas saat `--dry-run`.
  - `test_schedule_registers_fetch_before_snapshot_in_jakarta_time`: dari `app(Schedule::class)->events()`, ada event `social:fetch` dengan expression `50 6 * * *` dan `50 18 * * *`, timezone `Asia/Jakarta`, `withoutOverlapping` aktif. Event `monitor:snapshot` `0 7 * * *` dan `0 19 * * *` dengan timezone `Asia/Jakarta`.
- [ ] **Step 2:** Run `php artisan test --filter=SocialFetchCommandTest`. Expected: FAIL.
- [ ] **Step 3:** Implementasikan command dan ubah `routes/console.php` sesuai Global Constraints.
- [ ] **Step 4:** Run `php artisan test`. Expected: semua PASS. Lalu `php artisan social:fetch --dry-run` di mesin ini (tanpa kredensial). Expected: dua baris `Lewati … kredensial belum diatur`, tanpa error.
- [ ] **Step 5: Commit** `feat(fetch): perintah social:fetch dan jadwal WIB`.

---

### Task 7: UI — tombol, badge, checkbox, halaman status

**Files:**
- Modify: `routes/web.php`, `app/Http/Controllers/AccountController.php` (`$rules` + `store/update` + method baru `fetchNow`)
- Create: `app/Http/Controllers/FetchStatusController.php`, `resources/views/settings/auto-fetch.blade.php`, `resources/views/accounts/_fetch-status.blade.php`
- Modify: `resources/views/accounts/show.blade.php`, `index.blade.php`, `create.blade.php`, `edit.blade.php`, `resources/views/layouts/app.blade.php` (setelah link API Tokens, baris ~274)
- Test: `tests/Feature/SocialFetch/FetchUiTest.php`

**Interfaces:**
- Consumes: `SocialFetchService::fetchAccount`, `FetchStatus::label()`, `FetchLog`, `PlatformFetcher::isConfigured()`.
- Produces: route `POST /accounts/{account}/fetch` → `accounts.fetch` (middleware `can:can-edit`) dan `GET /settings/auto-fetch` → `auto-fetch.index` (middleware `can:manage-users`).

Perilaku:
- `fetchNow`: kalau platform bukan youtube/instagram → `back()` dengan error `Platform ini tidak mendukung pengambilan otomatis.` Kalau tidak → panggil `fetchAccount` dan kembalikan `back()` dengan `success` `Data diperbarui: N postingan baru, M diperbarui.` atau `error` berisi `status->label()` + pesan.
- Partial `_fetch-status` (dipakai di show dan index): badge warna (hijau Ok, abu belum pernah, kuning Unsupported, merah lainnya) + `last_fetched_at->diffForHumans()`. Tampil hanya untuk akun youtube/instagram. Untuk akun dengan `auto_fetch` false tampilkan "Otomatis mati".
- Show: tombol "Ambil sekarang" (`@can('can-edit')`, form POST + `@csrf`). Kalau status Unsupported: teks `Tidak bisa diambil otomatis — isi manual.`
- Create/edit: checkbox `auto_fetch` (label "Ambil otomatis (YouTube/Instagram)"), default tercentang. Rules `'auto_fetch' => 'boolean'`, simpan dengan `$request->boolean('auto_fetch', true)` pada store dan `$request->boolean('auto_fetch')` pada update.
- Halaman status: per platform (YouTube, Instagram) tampilkan kredensial terpasang/tidak, waktu jalan terakhir (max `fetched_at`), jumlah akun Ok/gagal dari `last_fetch_status`. Tabel akun gagal (username, platform, label status, pesan, waktu). Peringatan Instagram: kalau `services.instagram.token_expires_at` diisi, tampilkan sisa hari (kuning ≤ 7 hari, merah kalau lewat). Kalau ada akun IG berstatus `auth_error`, tampilkan banner merah `Token Instagram ditolak — perbarui token (lihat docs/setup-auto-fetch.md).` Tidak pernah menampilkan nilai kredensial.
- Link sidebar "Auto-Fetch" (ikon `fa-rotate`) di dalam blok `@can('manage-users')`.

- [ ] **Step 1: Write the failing tests** (pakai `User::factory()` dengan `role` superadmin/admin/viewer, dan bind service dengan fake fetcher):
  - `test_editor_can_fetch_now_and_sees_result`: admin POST `accounts.fetch` → redirect dengan session `success` memuat `postingan baru`.
  - `test_viewer_cannot_fetch_now`: viewer → 403.
  - `test_fetch_now_rejects_other_platforms`: akun twitter → session `error`.
  - `test_show_page_displays_status_and_button`: halaman `accounts.show` akun YT memuat `Ambil sekarang` dan label status. Halaman akun twitter tidak memuat `Ambil sekarang`.
  - `test_auto_fetch_checkbox_persists_on_update`: update tanpa `auto_fetch` → false.
  - `test_status_page_for_superadmin_only_and_hides_secrets`: superadmin 200 dan respons tidak memuat nilai `services.youtube.key`/`services.instagram.token` yang di-set di test. Admin → 403.
  - `test_status_page_warns_on_instagram_auth_error`.
- [ ] **Step 2:** Run `php artisan test --filter=FetchUiTest`. Expected: FAIL.
- [ ] **Step 3:** Implementasikan route, controller, view, dan link sidebar.
- [ ] **Step 4:** Run `php artisan test`. Expected: semua PASS. Lalu `php artisan view:clear`, login sebagai `andro@sociowatch.id` di `http://localhost:8000`, dan buka detail akun YT, daftar akun, form edit, dan `/settings/auto-fetch`. Expected: tidak ada error dan tidak ada `{{` mentah.
- [ ] **Step 5: Commit** `feat(fetch): tombol ambil sekarang, status akun, halaman status auto-fetch`.

---

### Task 8: Panduan setup

**Files:**
- Create: `docs/setup-auto-fetch.md` (root repo)
- Modify: `sociowatch-jateng/README.md` (tambah satu bagian singkat yang menautkan panduan)

- [ ] **Step 1:** Tulis panduan berbahasa Indonesia, mencakup spec §9 poin 1–4:
  - Google Cloud: buat project, aktifkan YouTube Data API v3, buat API key, batasi ke YouTube Data API.
  - Meta: ubah IG operator ke Business/Creator, hubungkan ke Facebook Page, buat app di developers.facebook.com, dapatkan user token dengan izin `instagram_basic`, `pages_show_list`, `business_management`, tukar ke long-lived token, ambil `INSTAGRAM_BUSINESS_ACCOUNT_ID` lewat `/me/accounts?fields=instagram_business_account`, isi `INSTAGRAM_TOKEN_EXPIRES_AT`, dan cara memperbarui token sebelum 60 hari. Sertakan catatan: cek versi Graph API terbaru dan sesuaikan `INSTAGRAM_GRAPH_VERSION`.
  - Isi `.env`, lalu `php artisan config:clear`, `php artisan social:fetch --dry-run`, dan `php artisan social:fetch`.
  - Windows Task Scheduler: tugas tiap 1 menit menjalankan `C:\xampp\php\php.exe "D:\Project Bot - Work\JatengLens\sociowatch-jateng\artisan" schedule:run`, contoh perintah `schtasks /create /sc minute /mo 1 /tn "SocioWatch Scheduler" /tr "..."`. Cara cek dengan `php artisan schedule:list`.
  - Troubleshooting per status (`not_found`, `unsupported`, `auth_error`, `quota`).
- [ ] **Step 2:** Verifikasi: `php artisan schedule:list` menampilkan 4 event dengan jam WIB yang benar, dan semua perintah di panduan cocok dengan nama option/env yang diimplementasikan (`grep` nama env di `config/services.php`).
- [ ] **Step 3: Commit** `docs: panduan setup auto-fetch YouTube & Instagram`.
