# AI-Driven Narrative Intelligence & Ringkasan Eksekutif Harian — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menghadirkan klasifikasi sentimen kontekstual otomatis per postingan (dengan dukungan negasi dan override manual) serta panel Ringkasan Eksekutif Harian & Klaster Isu Otomatis di Dashboard pimpinan (menggunakan Google Gemini API dengan fallback deterministik tanpa API key).

**Architecture:** `SentimentAnalysisService` menentukan sentimen (`sensitif`, `negatif`, `netral`, `positif`) secara instan saat postingan dibuat/diperbarui/diimpor/di-fetch dengan menghormati bendera `sentiment_manual`. `NarrativeBriefingService` mengumpulkan postingan dalam jendela waktu adaptif dan menyusun rangkuman eksekutif beserta 3–5 klaster isu lewat satu panggilan batch ke Gemini API (`Http::` native Laravel) atau beralih otomatis ke Heuristic Clustering Engine jika `GEMINI_API_KEY` kosong/gagal. Perintah `intel:briefing` dijalankan scheduler 5 menit setelah `monitor:snapshot` (07:05 & 19:05 WIB) atau dipicu lewat tombol "Perbarui Ringkasan" di Dashboard.

**Tech Stack:** Laravel 11.51, PHP 8.2, MariaDB/MySQL, PHPUnit 11, `Http::` client + `Http::fake()`, Blade + Tailwind CDN + Font Awesome + Alpine.js + Chart.js.

**Spec:** `docs/superpowers/specs/2026-11-19-narrative-intelligence-briefing-design.md`

Semua path di bawah relatif terhadap `sociowatch-jateng/`, kecuali path `docs/…` yang relatif terhadap root repo.

## Global Constraints

- **Test TIDAK BOLEH menyentuh database `sociowatch_jateng`.** Test hanya memakai `sociowatch_jateng_test` yang sudah dijaga oleh `tests/TestCase.php`.
- Tidak ada SDK/paket Composer baru. Panggilan ke Gemini API wajib memakai `Illuminate\Support\Facades\Http` bawaan Laravel.
- Kredensial Gemini diambil dari `.env` lewat `config('services.gemini.key')`, `config('services.gemini.model')` (default `'gemini-2.5-flash'`), dan `config('services.gemini.endpoint')` (default `'https://generativelanguage.googleapis.com/v1beta'`). Nilai `GEMINI_API_KEY` tidak boleh pernah bocor ke log, kolom DB `error_message`, flash message, atau tampilan web (wajib disensor menjadi `***`).
- Jika `GEMINI_API_KEY` kosong atau panggilan API gagal/timeout/JSON rusak, `NarrativeBriefingService` **tidak boleh melempar exception tak tertangani**; wajib menghasilkan briefing menggunakan Heuristic Clustering Engine (`source = 'heuristic'`, `status = 'ok'` bila key kosong atau `'fallback'` bila API gagal).
- Jika sebuah postingan memiliki `sentiment_manual = true`, proses otomatis (`SocialFetchService`, `KeywordController::rescan`) **tidak boleh menimpa** kolom `sentiment` postingan tersebut.
- Teks UI dan ringkasan heuristik menggunakan Bahasa Indonesia formal yang selaras dengan gaya tampilan SocioWatch Jateng.
- Jadwal `intel:briefing` berjalan pukul `07:05` dan `19:05` dengan `->timezone('Asia/Jakarta')->withoutOverlapping(30)`. Riwayat `narrative_briefings` disimpan 90 hari.

## Review Focus

1. **Kalimat dengan kata negasi sebelum kata negatif/sensitif** (misal *"Situasi aman, tidak ada banjir maupun kericuhan"*) → tidak boleh diklasifikasikan sebagai `sensitif` atau `negatif`. Test: `test_negation_neutralizes_negative_and_sensitive_terms` (Task 2).
2. **Kalimat dengan kata negasi sebelum kata positif** (misal *"Kondisi jalan belum aman dan tidak kondusif"*) → harus diklasifikasikan sebagai `negatif`. Test: `test_negation_flips_positive_terms_to_negative` (Task 2).
3. **Sentimen yang sudah di-set manual oleh analis (`sentiment_manual = true`)** → tetap bertahan saat `SocialFetchService` memperbarui post atau saat `KeywordController::rescan` dijalankan. Test: `test_manual_sentiment_is_preserved_during_rescan_and_autofetch` (Task 2).
4. **Error Gemini API memuat API key di URL/pesan error** → disensor menjadi `***` di `narrative_briefings.error_message` dan tetap menghasilkan klaster heuristik (`status = 'fallback'`). Test: `test_gemini_failure_falls_back_to_heuristic_and_masks_api_key` (Task 3).
5. **Tidak ada postingan dalam 24 jam terakhir tetapi ada postingan beberapa hari lalu** → jendela adaptif otomatis memperluas pencarian ke 7 hari terakhir / 30 postingan terbaru sehingga briefing tetap berisi klaster isu yang bermakna. Test: `test_adaptive_window_expands_when_last_24h_is_empty` (Task 3).

---

### Task 1: Skema Database (`posts.sentiment`, `posts.sentiment_manual`, Tabel `narrative_briefings`) & Model

**Files:**
- Create: `database/migrations/2026_11_19_000001_add_sentiment_to_posts_table.php`
- Create: `database/migrations/2026_11_19_000002_create_narrative_briefings_table.php`
- Create: `app/Models/NarrativeBriefing.php`
- Modify: `app/Models/Post.php`
- Modify: `database/factories/PostFactory.php`
- Test: `tests/Feature/NarrativeIntelligence/SchemaAndModelTest.php`

**Interfaces:**
- Produces:
  - Kolom pada `posts`:
    - `sentiment` varchar(20) NOT NULL default `'netral'` (setelah `flag_reason`)
    - `sentiment_manual` boolean NOT NULL default `false` (setelah `sentiment`)
    - Index `posts_sentiment_posted_at_index` pada `['sentiment', 'posted_at']`
  - `App\Models\Post`:
    - Tambahkan `'sentiment'`, `'sentiment_manual'` ke `$fillable`
    - Tambahkan `'sentiment_manual' => 'boolean'` ke `$casts`
    - Helper statis `public static function sentimentBadge(string $sentiment): array` yang mengembalikan `['label' => ..., 'bg' => ..., 'text' => ..., 'hex' => ...]` untuk `'sensitif'`, `'negatif'`, `'netral'`, `'positif'`
  - Tabel `narrative_briefings`:
    - `id`, `period_start` timestamp, `period_end` timestamp, `window_label` varchar(100), `source` varchar(20), `status` varchar(20), `executive_summary` text, `issue_clusters` json, `recommendations` json, `stats_snapshot` json, `error_message` text nullable, `generated_by` foreignId nullable constrained `users` nullOnDelete, `timestamps`, index `created_at`
  - `App\Models\NarrativeBriefing`:
    - `$fillable`: `['period_start', 'period_end', 'window_label', 'source', 'status', 'executive_summary', 'issue_clusters', 'recommendations', 'stats_snapshot', 'error_message', 'generated_by']`
    - `$casts`: `'period_start' => 'datetime'`, `'period_end' => 'datetime'`, `'issue_clusters' => 'array'`, `'recommendations' => 'array'`, `'stats_snapshot' => 'array'`
    - Relasi `generator(): BelongsTo` ke `User::class` (`generated_by`)

- [ ] **Step 1: Write the failing test** `tests/Feature/NarrativeIntelligence/SchemaAndModelTest.php`

```php
<?php

namespace Tests\Feature\NarrativeIntelligence;

use App\Models\NarrativeBriefing;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaAndModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_table_has_sentiment_columns_with_defaults(): void
    {
        $account = SocialAccount::factory()->youtube()->create();
        $post = Post::factory()->for($account, 'socialAccount')->create();

        $fresh = $post->fresh();
        $this->assertSame('netral', $fresh->sentiment);
        $this->assertFalse($fresh->sentiment_manual);
        $this->assertSame('Sensitif', Post::sentimentBadge('sensitif')['label']);
    }

    public function test_narrative_briefing_casts_json_attributes_and_links_generator(): void
    {
        $user = User::factory()->create();
        $briefing = NarrativeBriefing::create([
            'period_start'      => now()->subDay(),
            'period_end'        => now(),
            'window_label'      => '24 Jam Terakhir',
            'source'            => 'heuristic',
            'status'            => 'ok',
            'executive_summary' => 'Situasi Jawa Tengah kondusif.',
            'issue_clusters'    => [['title' => 'Isu A', 'sentiment' => 'negatif', 'urgency' => 'sedang', 'post_count' => 2]],
            'recommendations'   => ['Pantau wilayah Pantura.'],
            'stats_snapshot'    => ['total_posts' => 10],
            'generated_by'      => $user->id,
        ]);

        $fresh = $briefing->fresh();
        $this->assertIsArray($fresh->issue_clusters);
        $this->assertSame('Isu A', $fresh->issue_clusters[0]['title']);
        $this->assertSame(['Pantau wilayah Pantura.'], $fresh->recommendations);
        $this->assertSame(10, $fresh->stats_snapshot['total_posts']);
        $this->assertTrue($fresh->generator->is($user));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter=SchemaAndModelTest`
  Expected: FAIL (`NarrativeBriefing` not found / columns missing).

- [ ] **Step 3: Implement migrations, `NarrativeBriefing` model, and `Post` updates**
  Buat kedua migration, model `NarrativeBriefing`, perbarui `Post` (`$fillable`, `$casts`, `sentimentBadge`), dan pastikan `PostFactory` menyertakan default `'sentiment' => 'netral'`, `'sentiment_manual' => false`.

- [ ] **Step 4: Run test to verify it passes**
  Run: `php artisan test --filter=SchemaAndModelTest`
  Expected: PASS.

- [ ] **Step 5: Commit**
  `git add sociowatch-jateng/database/migrations/2026_11_19_* sociowatch-jateng/app/Models/NarrativeBriefing.php sociowatch-jateng/app/Models/Post.php sociowatch-jateng/database/factories/PostFactory.php sociowatch-jateng/tests/Feature/NarrativeIntelligence/SchemaAndModelTest.php`
  `git commit -m "feat(intel): skema sentimen postingan dan tabel narrative_briefings"`

---

### Task 2: `SentimentAnalysisService` & Integrasi Siklus Hidup Postingan

**Files:**
- Create: `app/Services/SentimentAnalysisService.php`
- Modify: `app/Http/Controllers/PostController.php`
- Modify: `app/Imports/PostsImport.php`
- Modify: `app/Services/SocialFetch/SocialFetchService.php`
- Modify: `app/Http/Controllers/KeywordController.php`
- Test: `tests/Feature/NarrativeIntelligence/SentimentAnalysisTest.php`

**Interfaces:**
- Consumes: `Post`, `Keyword`, `KeywordDetectionService`
- Produces:
  - `App\Services\SentimentAnalysisService`:
    - `public function analyze(string $content, ?iterable $matchedKeywords = null): string` → mengembalikan `'sensitif' | 'negatif' | 'netral' | 'positif'`.
    - `public function assignToPost(Post $post, bool $respectManual = true): string` → jika `$respectManual && $post->sentiment_manual`, kembalikan `$post->sentiment` tanpa mengubahnya; jika tidak, hitung sentimen dari `$post->content`, simpan ke `$post->sentiment`, dan kembalikan nilainya.

- [ ] **Step 1: Write the failing test** `tests/Feature/NarrativeIntelligence/SentimentAnalysisTest.php`

```php
<?php

namespace Tests\Feature\NarrativeIntelligence;

use App\Models\Keyword;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SentimentAnalysisService;
use App\Services\SocialFetch\FetchedAccount;
use App\Services\SocialFetch\FetchedPost;
use App\Services\SocialFetch\SocialFetchService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\SocialFetch\FakeFetcher;
use Tests\TestCase;

class SentimentAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_classifies_sensitive_negative_positive_and_neutral_content(): void
    {
        Keyword::factory()->create(['word' => 'unjuk rasa', 'category' => 'sensitif', 'is_active' => true]);
        Keyword::factory()->create(['word' => 'jalan rusak', 'category' => 'negatif', 'is_active' => true]);
        Keyword::factory()->create(['word' => 'penghargaan', 'category' => 'positif', 'is_active' => true]);

        $svc = app(SentimentAnalysisService::class);

        $this->assertSame('sensitif', $svc->analyze('Terjadi aksi unjuk rasa besar di depan gedung DPRD'));
        $this->assertSame('negatif', $svc->analyze('Warga mengeluhkan jalan rusak parah dan macet'));
        $this->assertSame('positif', $svc->analyze('Pemprov meraih penghargaan inovasi pelayanan publik yang sukses'));
        $this->assertSame('netral', $svc->analyze('Jadwal rapat koordinasi rutin hari Senin pukul 09.00 WIB'));
    }

    public function test_negation_neutralizes_negative_and_sensitive_terms(): void
    {
        Keyword::factory()->create(['word' => 'banjir', 'category' => 'negatif', 'is_active' => true]);
        Keyword::factory()->create(['word' => 'ricuh', 'category' => 'sensitif', 'is_active' => true]);

        $svc = app(SentimentAnalysisService::class);

        $this->assertNotSame('sensitif', $svc->analyze('Situasi pemilu berjalan tertib, tidak ada ricuh sama sekali'));
        $this->assertNotSame('negatif', $svc->analyze('Wilayah ini bebas banjir dan tidak banjir saat hujan kemarin'));
    }

    public function test_negation_flips_positive_terms_to_negative(): void
    {
        Keyword::factory()->create(['word' => 'kondusif', 'category' => 'positif', 'is_active' => true]);
        $svc = app(SentimentAnalysisService::class);

        $this->assertSame('negatif', $svc->analyze('Suasana rapat menjadi tidak kondusif dan belum aman'));
    }

    public function test_manual_sentiment_is_preserved_during_rescan_and_autofetch(): void
    {
        Keyword::factory()->create(['word' => 'ricuh', 'category' => 'sensitif', 'is_active' => true]);
        $account = SocialAccount::factory()->youtube()->create(['external_id' => 'UC1']);

        $post = Post::factory()->for($account, 'socialAccount')->create([
            'external_id'      => 'vid1',
            'content'          => 'Berita ricuh kemarin',
            'sentiment'        => 'netral',
            'sentiment_manual' => true,
            'posted_at'        => now()->subDay(),
        ]);

        // 1. Rescan via KeywordController
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->post(route('keywords.rescan'));
        $this->assertSame('netral', $post->fresh()->sentiment);
        $this->assertTrue($post->fresh()->sentiment_manual);

        // 2. Auto-fetch update via SocialFetchService
        $fake = new FakeFetcher('youtube', true, fn() => new FetchedAccount(
            externalId: 'UC1',
            followers: 100,
            following: null,
            postCount: 1,
            posts: [
                new FetchedPost('vid1', 'https://www.youtube.com/watch?v=vid1', 'Berita ricuh kemarin update', 'video', 10, 2, 100, CarbonImmutable::now()->subDay()),
            ]
        ));
        $fetchService = new SocialFetchService([$fake], app(\App\Services\KeywordDetectionService::class), app(SentimentAnalysisService::class));
        $fetchService->fetchAccount($account);

        $this->assertSame('netral', $post->fresh()->sentiment);
        $this->assertTrue($post->fresh()->sentiment_manual);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter=SentimentAnalysisTest`
  Expected: FAIL (`SentimentAnalysisService` not found).

- [ ] **Step 3: Implement `SentimentAnalysisService` and wire into `PostController`, `PostsImport`, `SocialFetchService`, `KeywordController`**
  - Implementasikan algoritma di spec §4.2 (pencocokan keyword aktif + leksikon bawaan + pemeriksaan kata negasi `['tidak', 'tak', 'bukan', 'belum', 'jangan', 'tanpa', 'bebas', 'ora', 'mboten', 'enggak', 'gak']` dalam jarak 1–2 kata sebelum term).
  - Di `SocialFetchService`: tambahkan parameter opsional `?SentimentAnalysisService $sentiment = null` di constructor (default `new SentimentAnalysisService()`), set `sentiment` saat membuat post baru atau mengupdate post yang `sentiment_manual === false`.
  - Di `PostController::store` dan `update`: dukung input opsional `sentiment_mode` (`'auto'`, `'sensitif'`, `'negatif'`, `'netral'`, `'positif'`). Jika dipilih selain `'auto'`, simpan `sentiment = $mode` dan `sentiment_manual = true`; jika `'auto'` atau tidak diisi, hitung lewat `SentimentAnalysisService` dan set `sentiment_manual = false`.
  - Di `PostsImport` dan `KeywordController::rescan`: hitung dan simpan `sentiment` untuk setiap post yang `sentiment_manual === false`.

- [ ] **Step 4: Run tests to verify they pass**
  Run: `php artisan test`
  Expected: Semua test (termasuk `SocialFetchServiceTest` yang sudah ada) PASS.

- [ ] **Step 5: Commit**
  `git add sociowatch-jateng/app/Services/SentimentAnalysisService.php sociowatch-jateng/app/Services/SocialFetch/SocialFetchService.php sociowatch-jateng/app/Http/Controllers/PostController.php sociowatch-jateng/app/Http/Controllers/KeywordController.php sociowatch-jateng/app/Imports/PostsImport.php sociowatch-jateng/tests/Feature/NarrativeIntelligence/SentimentAnalysisTest.php`
  `git commit -m "feat(intel): layanan analisis sentimen kontekstual dengan deteksi negasi"`

---

### Task 3: `NarrativeBriefingService`, Integrasi Gemini API + Fallback Heuristik, & Perintah `intel:briefing`

**Files:**
- Create: `app/Services/NarrativeBriefingService.php`
- Create: `app/Console/Commands/GenerateNarrativeBriefing.php`
- Modify: `config/services.php`, `.env.example`, `routes/console.php`
- Test: `tests/Feature/NarrativeIntelligence/NarrativeBriefingServiceTest.php`

**Interfaces:**
- Consumes: `Post`, `NarrativeBriefing`, `SentimentAnalysisService`
- Produces:
  - `App\Services\NarrativeBriefingService`:
    - `public function generate(int $hours = 24, bool $forceHeuristic = false, ?int $userId = null): NarrativeBriefing`
    - `public function isGeminiConfigured(): bool`
  - Artisan command `intel:briefing {--hours=24} {--heuristic}`
  - Jadwal di `routes/console.php`: `intel:briefing` pukul `07:05` & `19:05` WIB (`Asia/Jakarta`, `withoutOverlapping(30)`).
  - Konfigurasi `services.gemini.{key,model,endpoint}` di `config/services.php` dan variabel di `.env.example`.

- [ ] **Step 1: Write the failing test** `tests/Feature/NarrativeIntelligence/NarrativeBriefingServiceTest.php`

```php
<?php

namespace Tests\Feature\NarrativeIntelligence;

use App\Models\Category;
use App\Models\Keyword;
use App\Models\NarrativeBriefing;
use App\Models\Post;
use App\Models\Region;
use App\Models\SocialAccount;
use App\Services\NarrativeBriefingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NarrativeBriefingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_heuristic_briefing_when_gemini_key_is_empty(): void
    {
        config(['services.gemini.key' => null]);
        Http::preventStrayRequests();

        $region = Region::factory()->create(['name' => 'Kota Semarang']);
        $cat    = Category::factory()->create(['name' => 'Mahasiswa']);
        $acc    = SocialAccount::factory()->youtube()->create(['region_id' => $region->id, 'category_id' => $cat->id]);
        $kw     = Keyword::factory()->create(['word' => 'banjir', 'category' => 'negatif', 'is_active' => true]);

        $post = Post::factory()->for($acc, 'socialAccount')->create([
            'content'     => 'Keluhan banjir di jalan protokol Kota Semarang',
            'sentiment'   => 'negatif',
            'likes_count' => 150,
            'posted_at'   => now()->subHours(3),
        ]);
        $post->keywords()->sync([$kw->id => ['occurrence_count' => 2]]);

        $briefing = app(NarrativeBriefingService::class)->generate(24);

        $this->assertSame('heuristic', $briefing->source);
        $this->assertSame('ok', $briefing->status);
        $this->assertNotEmpty($briefing->executive_summary);
        $this->assertNotEmpty($briefing->issue_clusters);
        $this->assertContains('Kota Semarang', $briefing->issue_clusters[0]['regions']);
        $this->assertSame(1, $briefing->stats_snapshot['total_posts']);
    }

    public function test_generates_gemini_briefing_and_enriches_cluster_metrics_from_db(): void
    {
        config([
            'services.gemini.key'      => 'SECRET_GEMINI_KEY_123',
            'services.gemini.model'    => 'gemini-2.5-flash',
            'services.gemini.endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
        ]);

        $region = Region::factory()->create(['name' => 'Kabupaten Banyumas']);
        $acc    = SocialAccount::factory()->instagram()->create(['region_id' => $region->id]);
        $post   = Post::factory()->for($acc, 'socialAccount')->create([
            'content'        => 'Aksi tuntutan kenaikan UMK Banyumas',
            'sentiment'      => 'sensitif',
            'likes_count'    => 200,
            'comments_count' => 50,
            'posted_at'      => now()->subHours(2),
        ]);

        $aiPayload = [
            'executive_summary' => 'Sorotan utama hari ini adalah aksi UMK di Kabupaten Banyumas.',
            'issue_clusters'    => [
                [
                    'title'     => 'Tuntutan UMK Banyumas',
                    'sentiment' => 'sensitif',
                    'urgency'   => 'tinggi',
                    'summary'   => 'Serikat pekerja menyuarakan aspirasi UMK.',
                    'post_ids'  => [$post->id],
                ],
            ],
            'recommendations'   => ['Koordinasi dengan Disnaker Kabupaten Banyumas.'],
        ];

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => json_encode($aiPayload)]]]],
                ],
            ], 200),
        ]);

        $briefing = app(NarrativeBriefingService::class)->generate(24);

        $this->assertSame('gemini', $briefing->source);
        $this->assertSame('ok', $briefing->status);
        $this->assertSame('Tuntutan UMK Banyumas', $briefing->issue_clusters[0]['title']);
        $this->assertSame(250, $briefing->issue_clusters[0]['total_engagement']);
        $this->assertContains('Kabupaten Banyumas', $briefing->issue_clusters[0]['regions']);
    }

    public function test_gemini_failure_falls_back_to_heuristic_and_masks_api_key(): void
    {
        config(['services.gemini.key' => 'MY_SUPER_SECRET_KEY']);

        $acc = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create(['posted_at' => now()->subHours(1)]);

        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'Invalid key MY_SUPER_SECRET_KEY in request https://generativelanguage.googleapis.com?key=MY_SUPER_SECRET_KEY'],
            ], 400),
        ]);

        $briefing = app(NarrativeBriefingService::class)->generate(24);

        $this->assertSame('heuristic', $briefing->source);
        $this->assertSame('fallback', $briefing->status);
        $this->assertNotNull($briefing->error_message);
        $this->assertStringNotContainsString('MY_SUPER_SECRET_KEY', $briefing->error_message);
        $this->assertStringContainsString('***', $briefing->error_message);
    }

    public function test_adaptive_window_expands_when_last_24h_is_empty(): void
    {
        config(['services.gemini.key' => null]);
        $acc = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create([
            'content'   => 'Postingan tiga hari lalu',
            'posted_at' => now()->subDays(3),
        ]);

        $briefing = app(NarrativeBriefingService::class)->generate(24);

        $this->assertSame('7 Hari Terakhir', $briefing->window_label);
        $this->assertSame(1, $briefing->stats_snapshot['total_posts']);
    }

    public function test_artisan_command_generates_briefing_and_prunes_old_records(): void
    {
        config(['services.gemini.key' => null]);
        $old = NarrativeBriefing::create([
            'period_start'      => now()->subDays(100),
            'period_end'        => now()->subDays(99),
            'window_label'      => '24 Jam Terakhir',
            'source'            => 'heuristic',
            'status'            => 'ok',
            'executive_summary' => 'Lama',
            'issue_clusters'    => [],
            'recommendations'   => [],
            'stats_snapshot'    => ['total_posts' => 0],
        ]);
        NarrativeBriefing::whereKey($old->id)->update(['created_at' => now()->subDays(95)]);

        $this->artisan('intel:briefing --heuristic')->assertExitCode(0);

        $this->assertDatabaseMissing('narrative_briefings', ['id' => $old->id]);
        $this->assertSame(1, NarrativeBriefing::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter=NarrativeBriefingServiceTest`
  Expected: FAIL (`NarrativeBriefingService` not found).

- [ ] **Step 3: Implement `NarrativeBriefingService`, `GenerateNarrativeBriefing` command, config, and schedule**
  - Tambahkan konfigurasi `gemini` di `config/services.php` dan `.env.example`.
  - Implementasikan `NarrativeBriefingService` sesuai spec §4.3 (jendela adaptif 24 jam → 7 hari → 30 post terakhir, panggilan Gemini REST `generateContent`, pengayaan statistik klaster dari DB, fallback heuristik deterministik, dan sanitasi secret `***`).
  - Buat command `intel:briefing` dan daftarkan jadwal `07:05` & `19:05` WIB di `routes/console.php`.

- [ ] **Step 4: Run test to verify it passes**
  Run: `php artisan test --filter=NarrativeBriefingServiceTest`
  Expected: PASS.

- [ ] **Step 5: Commit**
  `git add sociowatch-jateng/app/Services/NarrativeBriefingService.php sociowatch-jateng/app/Console/Commands/GenerateNarrativeBriefing.php sociowatch-jateng/config/services.php sociowatch-jateng/.env.example sociowatch-jateng/routes/console.php sociowatch-jateng/tests/Feature/NarrativeIntelligence/NarrativeBriefingServiceTest.php`
  `git commit -m "feat(intel): layanan ringkasan eksekutif Gemini dengan fallback heuristik dan jadwal WIB"`

---

### Task 4: Panel Executive Briefing di Dashboard, Filter/Badge Sentimen di Postingan, & Perbaikan Grafik Analytics

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `app/Http/Controllers/PostController.php`
- Modify: `app/Http/Controllers/AnalyticsController.php`
- Modify: `resources/views/dashboard/index.blade.php`
- Modify: `resources/views/posts/index.blade.php`
- Modify: `resources/views/posts/show.blade.php`
- Modify: `resources/views/posts/_form.blade.php`
- Modify: `resources/views/analytics/index.blade.php`
- Test: `tests/Feature/NarrativeIntelligence/BriefingAndSentimentUiTest.php`

**Interfaces:**
- Consumes: `NarrativeBriefing`, `NarrativeBriefingService`, `SentimentAnalysisService`, `Post::sentimentBadge()`
- Produces:
  - Rute `POST /briefings/generate` bernama `briefings.generate` dengan middleware `can:can-edit` → `DashboardController@generateBriefing`.
  - Panel **Ringkasan Intelijen & Klaster Isu** di `dashboard/index.blade.php`.
  - Filter `sentiment`, badge sentimen, dan dropdown `sentiment_mode` di views `posts/`.
  - Agregasi sentimen dari `posts.sentiment` di `AnalyticsController` dan perbaikan kurung siku array JS di `analytics/index.blade.php`.

- [ ] **Step 1: Write the failing test** `tests/Feature/NarrativeIntelligence/BriefingAndSentimentUiTest.php`

```php
<?php

namespace Tests\Feature\NarrativeIntelligence;

use App\Models\NarrativeBriefing;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BriefingAndSentimentUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_executive_briefing_and_auto_generates_when_posts_exist(): void
    {
        config(['services.gemini.key' => null]);
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $acc  = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create([
            'content'   => 'Aspirasi perbaikan jalan provinsi Jawa Tengah',
            'sentiment' => 'negatif',
            'posted_at' => now()->subHours(2),
        ]);

        $res = $this->actingAs($user)->get(route('dashboard'));

        $res->assertOk();
        $res->assertSee('Ringkasan Intelijen');
        $res->assertSee('Perbarui Ringkasan');
        $this->assertSame(1, NarrativeBriefing::count());
    }

    public function test_generate_briefing_endpoint_requires_can_edit_gate(): void
    {
        config(['services.gemini.key' => null]);
        $viewer = User::factory()->create(['role' => 'viewer', 'is_active' => true]);
        $admin  = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($viewer)->post(route('briefings.generate'))->assertForbidden();

        $this->actingAs($admin)->post(route('briefings.generate'))
            ->assertRedirect(route('dashboard'));
        $this->assertSame(1, NarrativeBriefing::count());
    }

    public function test_posts_index_filters_by_sentiment_and_displays_badge(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $acc  = SocialAccount::factory()->instagram()->create();

        Post::factory()->for($acc, 'socialAccount')->create([
            'content'   => 'Konten apresiasi layanan publik',
            'sentiment' => 'positif',
        ]);
        Post::factory()->for($acc, 'socialAccount')->create([
            'content'   => 'Konten kericuhan demo',
            'sentiment' => 'sensitif',
        ]);

        $res = $this->actingAs($user)->get(route('posts.index', ['sentiment' => 'sensitif']));
        $res->assertOk();
        $res->assertSee('Konten kericuhan demo');
        $res->assertDontSee('Konten apresiasi layanan publik');
    }

    public function test_analytics_page_renders_valid_js_arrays_and_sentiment_counts(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $acc  = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create([
            'sentiment' => 'negatif',
            'posted_at' => now()->subDay(),
        ]);

        $res = $this->actingAs($user)->get(route('analytics.index', ['tab' => 'content']));
        $res->assertOk();
        $res->assertSee('sentimentChart');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**
  Run: `php artisan test --filter=BriefingAndSentimentUiTest`
  Expected: FAIL (route `briefings.generate` not defined).

- [ ] **Step 3: Implement routes, controller updates, and Blade views**
  - `routes/web.php`: Tambahkan `Route::post('/briefings/generate', [DashboardController::class, 'generateBriefing'])->middleware('can:can-edit')->name('briefings.generate');`.
  - `DashboardController`:
    - Di `index()`: Ambil `$latestBriefing = NarrativeBriefing::latest('id')->first()`. Jika `null` dan `Post::exists()`, panggil `$latestBriefing = app(NarrativeBriefingService::class)->generate(24, true)` (mode heuristik cepat agar halaman pertama tidak lambat). Kirim `$latestBriefing` ke view.
    - Tambahkan `generateBriefing(Request $request, NarrativeBriefingService $service)` yang memanggil `$service->generate(24, false, auth()->id())` dan redirect ke `route('dashboard')` dengan flash message.
  - `dashboard/index.blade.php`: Tambahkan panel **Ringkasan Intelijen & Klaster Isu** di bawah Stat Cards sesuai spec §4.5.
  - `PostController` & `resources/views/posts/{index,show,_form}.blade.php`: Tambahkan filter `sentiment`, badge sentimen dengan `Post::sentimentBadge($post->sentiment)`, serta pilihan `sentiment_mode` di form post.
  - `AnalyticsController::contentAnalytics` & `resources/views/analytics/index.blade.php`: Hitung `$sentiment` langsung dari `Post::where('posted_at', '>=', $day30)->selectRaw('sentiment, COUNT(*) as total')->groupBy('sentiment')->pluck('total', 'sentiment')->toArray()` dan perbaiki kurung siku array JS `[ ... ]` pada baris 380, 453, 480, 516, dan 519.

- [ ] **Step 4: Run entire test suite**
  Run: `php artisan test`
  Expected: Seluruh test suite PASS.

- [ ] **Step 5: Commit**
  `git add sociowatch-jateng/routes/web.php sociowatch-jateng/app/Http/Controllers/DashboardController.php sociowatch-jateng/app/Http/Controllers/PostController.php sociowatch-jateng/app/Http/Controllers/AnalyticsController.php sociowatch-jateng/resources/views/dashboard/index.blade.php sociowatch-jateng/resources/views/posts/ sociowatch-jateng/resources/views/analytics/index.blade.php sociowatch-jateng/tests/Feature/NarrativeIntelligence/BriefingAndSentimentUiTest.php`
  `git commit -m "feat(intel): panel executive briefing di dashboard, filter sentimen post, dan perbaikan chart analytics"`
