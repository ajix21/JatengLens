<?php

namespace App\Services;

use App\Models\NarrativeBriefing;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class NarrativeBriefingService
{
    private const VALID_SENTIMENTS = ['sensitif', 'negatif', 'netral', 'positif'];
    private const VALID_URGENCIES = ['tinggi', 'sedang', 'rendah'];

    private const SENTIMENT_PRIORITY = [
        'sensitif' => 4,
        'negatif'  => 3,
        'positif'  => 2,
        'netral'   => 1,
    ];

    private const URGENCY_WEIGHT = [
        'tinggi' => 3,
        'sedang' => 2,
        'rendah' => 1,
    ];

    public function isGeminiConfigured(): bool
    {
        $key = config('services.gemini.key');

        return is_string($key) && trim($key) !== '';
    }

    public function generate(int $hours = 24, bool $forceHeuristic = false, ?int $userId = null): NarrativeBriefing
    {
        [$posts, $periodStart, $periodEnd, $windowLabel] = $this->collectPosts($hours);
        $statsSnapshot = $this->computeStatsSnapshot($posts);

        $source = 'heuristic';
        $status = 'ok';
        $errorMessage = null;
        $payload = null;

        if (!$forceHeuristic && $this->isGeminiConfigured() && $posts->isNotEmpty()) {
            try {
                $payload = $this->requestGeminiBriefing($posts, $statsSnapshot, $windowLabel);
                $source = 'gemini';
                $status = 'ok';
            } catch (Throwable $e) {
                $status = 'fallback';
                $errorMessage = $this->sanitize($e->getMessage());
            }
        }

        if ($payload === null) {
            $payload = $this->buildHeuristicBriefing($posts, $statsSnapshot, $windowLabel);
        }

        return NarrativeBriefing::create([
            'period_start'      => $periodStart,
            'period_end'        => $periodEnd,
            'window_label'      => $windowLabel,
            'source'            => $source,
            'status'            => $status,
            'executive_summary' => $payload['executive_summary'],
            'issue_clusters'    => $payload['issue_clusters'],
            'recommendations'   => $payload['recommendations'],
            'stats_snapshot'    => $statsSnapshot,
            'error_message'     => $errorMessage,
            'generated_by'      => $userId,
        ]);
    }

    /**
     * Collect posts with adaptive time window: requested hours -> 7 days -> latest 30 posts.
     *
     * @return array{0: Collection<int, Post>, 1: Carbon, 2: Carbon, 3: string}
     */
    private function collectPosts(int $hours): array
    {
        $now = now();
        $hours = max(1, $hours);
        $periodStart = $now->copy()->subHours($hours);
        $windowLabel = "{$hours} Jam Terakhir";

        $baseQuery = fn () => Post::with(['socialAccount.region', 'socialAccount.category', 'keywords', 'tags']);

        $posts = $baseQuery()
            ->where('posted_at', '>=', $periodStart)
            ->orderByRaw('(likes_count + comments_count + shares_count + views_count) DESC')
            ->take(60)
            ->get();

        if ($posts->isEmpty()) {
            $periodStart = $now->copy()->subDays(7);
            $windowLabel = '7 Hari Terakhir';
            $posts = $baseQuery()
                ->where('posted_at', '>=', $periodStart)
                ->orderByRaw('(likes_count + comments_count + shares_count + views_count) DESC')
                ->take(60)
                ->get();
        }

        if ($posts->isEmpty()) {
            $windowLabel = '30 Postingan Terakhir';
            $posts = $baseQuery()
                ->orderByDesc('posted_at')
                ->take(30)
                ->get()
                ->sortByDesc(fn (Post $post) => $post->total_engagement)
                ->values();

            $minPostedAt = $posts->min('posted_at');
            $periodStart = $minPostedAt ? Carbon::parse($minPostedAt) : $now->copy()->subDay();
        }

        return [$posts, $periodStart, $now, $windowLabel];
    }

    private function computeStatsSnapshot(Collection $posts): array
    {
        $topRegion = $posts
            ->map(fn (Post $post) => $post->socialAccount?->region?->name)
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first() ?? '-';

        return [
            'total_posts'      => $posts->count(),
            'total_engagement' => (int) $posts->sum(fn (Post $post) => $post->total_engagement),
            'flagged_count'    => $posts->where('is_flagged', true)->count(),
            'sentiment_counts' => [
                'sensitif' => $posts->where('sentiment', 'sensitif')->count(),
                'negatif'  => $posts->where('sentiment', 'negatif')->count(),
                'netral'   => $posts->where('sentiment', 'netral')->count(),
                'positif'  => $posts->where('sentiment', 'positif')->count(),
            ],
            'top_region' => $topRegion,
        ];
    }

    private function requestGeminiBriefing(Collection $posts, array $statsSnapshot, string $windowLabel): array
    {
        $apiKey = trim((string) config('services.gemini.key'));
        $model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $endpoint = rtrim((string) config('services.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta'), '/');

        $compactPosts = $posts->map(fn (Post $post) => [
            'id'         => $post->id,
            'region'     => $post->socialAccount?->region?->name ?? 'Tidak diketahui',
            'category'   => $post->socialAccount?->category?->name ?? 'Umum',
            'account'    => $post->socialAccount?->username ? '@' . $post->socialAccount->username : ($post->socialAccount?->display_name ?? '-'),
            'sentiment'  => $post->sentiment ?? 'netral',
            'engagement' => $post->total_engagement,
            'flagged'    => (bool) $post->is_flagged,
            'content'    => mb_substr(trim((string) preg_replace('/\s+/', ' ', (string) $post->content)), 0, 250),
        ])->values()->all();

        $prompt = $this->buildGeminiPrompt($compactPosts, $statsSnapshot, $windowLabel);
        $url = "{$endpoint}/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(25)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature'      => 0.2,
            ],
        ]);

        if ($response->failed()) {
            throw new \RuntimeException("Gemini API error ({$response->status()}): " . $response->body());
        }

        $rawText = $response->json('candidates.0.content.parts.0.text');
        if (!is_string($rawText) || trim($rawText) === '') {
            throw new \RuntimeException('Respons Gemini kosong atau tidak memiliki kandidat teks.');
        }

        $cleanedJson = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($rawText)));
        $decoded = json_decode($cleanedJson, true);

        if (
            !is_array($decoded)
            || empty($decoded['executive_summary'])
            || !is_string($decoded['executive_summary'])
            || !isset($decoded['issue_clusters'])
            || !is_array($decoded['issue_clusters'])
            || !isset($decoded['recommendations'])
            || !is_array($decoded['recommendations'])
        ) {
            throw new \RuntimeException('Struktur JSON respons Gemini tidak sesuai skema briefing.');
        }

        $postsById = $posts->keyBy('id');
        $enrichedClusters = [];

        foreach (array_slice($decoded['issue_clusters'], 0, 5) as $cluster) {
            if (!is_array($cluster)) {
                continue;
            }

            $title = trim((string) ($cluster['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $matchedPosts = $this->matchClusterPosts($cluster['post_ids'] ?? [], $title, $posts, $postsById);
            $enrichedClusters[] = $this->buildClusterFromPosts(
                $title,
                $matchedPosts,
                trim((string) ($cluster['summary'] ?? '')),
                is_string($cluster['sentiment'] ?? null) ? $cluster['sentiment'] : null,
                is_string($cluster['urgency'] ?? null) ? $cluster['urgency'] : null
            );
        }

        $recommendations = collect($decoded['recommendations'])
            ->filter(fn ($rec) => is_string($rec) && trim($rec) !== '')
            ->map(fn (string $rec) => trim($rec))
            ->take(5)
            ->values()
            ->all();

        return [
            'executive_summary' => trim($decoded['executive_summary']),
            'issue_clusters'    => $enrichedClusters,
            'recommendations'   => $recommendations,
        ];
    }

    private function buildGeminiPrompt(array $compactPosts, array $statsSnapshot, string $windowLabel): string
    {
        $postsJson = json_encode($compactPosts, JSON_UNESCAPED_UNICODE);
        $statsJson = json_encode($statsSnapshot, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Anda adalah analis pemantauan media dan komunikasi publik strategis untuk Pemerintah Provinsi Jawa Tengah (SocioWatch Jateng).
Analisis daftar postingan pada jendela waktu "{$windowLabel}" dengan ringkasan metrik {$statsJson}.

Data postingan:
{$postsJson}

Kembalikan HANYA JSON valid dengan struktur persis berikut (dalam Bahasa Indonesia formal):
{
  "executive_summary": "Ringkasan eksekutif 2 paragraf yang merangkum dinamika isu Jawa Tengah, sentimen dominan, dan wilayah paling aktif.",
  "issue_clusters": [
    {
      "title": "Judul klaster isu yang ringkas",
      "sentiment": "sensitif|negatif|netral|positif",
      "urgency": "tinggi|sedang|rendah",
      "summary": "Ringkasan 1-2 kalimat mengenai topik dan pola interaksi.",
      "post_ids": [1, 2]
    }
  ],
  "recommendations": [
    "Rekomendasi tindak lanjut strategis yang spesifik dan operasional."
  ]
}

Aturan:
- Buat 1 sampai 5 klaster isu sesuai variasi topik yang tersedia.
- Isi post_ids hanya dengan ID integer dari daftar postingan di atas.
- Gunakan nilai sentiment hanya dari: sensitif, negatif, netral, positif.
- Gunakan nilai urgency hanya dari: tinggi, sedang, rendah.
PROMPT;
    }

    private function matchClusterPosts(mixed $rawPostIds, string $title, Collection $posts, Collection $postsById): Collection
    {
        $matched = collect();

        if (is_array($rawPostIds)) {
            $matched = collect($rawPostIds)
                ->map(fn ($id) => $postsById->get((int) $id))
                ->filter()
                ->unique('id')
                ->values();
        }

        if ($matched->isNotEmpty()) {
            return $matched;
        }

        $titleWords = collect(preg_split('/[\s,.:;\-\/]+/u', mb_strtolower($title), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $word) => mb_strlen($word) >= 4)
            ->values();

        if ($titleWords->isNotEmpty()) {
            $matched = $posts->filter(function (Post $post) use ($titleWords) {
                $haystack = mb_strtolower((string) $post->content . ' ' . $post->keywords->pluck('word')->implode(' '));
                foreach ($titleWords as $word) {
                    if (str_contains($haystack, $word)) {
                        return true;
                    }
                }

                return false;
            })->take(10)->values();
        }

        return $matched->isNotEmpty() ? $matched : $posts->take(3)->values();
    }

    private function buildHeuristicBriefing(Collection $posts, array $statsSnapshot, string $windowLabel): array
    {
        if ($posts->isEmpty()) {
            return [
                'executive_summary' => 'Belum ada data postingan yang tercatat pada periode pemantauan ini. Silakan jalankan pengambilan data otomatis (auto-fetch) atau tambahkan postingan untuk mulai menghasilkan analisis narasi.',
                'issue_clusters'    => [],
                'recommendations'   => ['Pastikan jadwal auto-fetch berjalan atau tambahkan data postingan terbaru.'],
            ];
        }

        $keywordGroups = [];
        $assignedPostIds = [];

        foreach ($posts as $post) {
            $activeKeywords = $post->keywords->where('is_active', true);
            foreach ($activeKeywords as $keyword) {
                $groupKey = 'kw:' . $keyword->id;
                if (!isset($keywordGroups[$groupKey])) {
                    $keywordGroups[$groupKey] = [
                        'title' => 'Sorotan Isu: ' . Str::ucfirst((string) $keyword->word),
                        'posts' => collect(),
                    ];
                }
                $keywordGroups[$groupKey]['posts']->push($post);
                $assignedPostIds[$post->id] = true;
            }
        }

        $fallbackGroups = [];
        $remainingPosts = $posts->filter(fn (Post $post) => !isset($assignedPostIds[$post->id]));

        foreach ($remainingPosts as $post) {
            $categoryName = $post->socialAccount?->category?->name;
            if (is_string($categoryName) && trim($categoryName) !== '') {
                $groupKey = 'cat:' . $categoryName;
                $title = 'Aktivitas Sektor ' . $categoryName;
            } else {
                $sentiment = in_array($post->sentiment, self::VALID_SENTIMENTS, true) ? $post->sentiment : 'netral';
                $groupKey = 'sent:' . $sentiment;
                $title = 'Pantauan Narasi ' . Str::ucfirst($sentiment);
            }

            if (!isset($fallbackGroups[$groupKey])) {
                $fallbackGroups[$groupKey] = [
                    'title' => $title,
                    'posts' => collect(),
                ];
            }
            $fallbackGroups[$groupKey]['posts']->push($post);
        }

        $candidateGroups = array_merge(array_values($keywordGroups), array_values($fallbackGroups));
        $clusters = [];

        foreach ($candidateGroups as $group) {
            $uniquePosts = $group['posts']->unique('id')->values();
            if ($uniquePosts->isEmpty()) {
                continue;
            }
            $clusters[] = $this->buildClusterFromPosts($group['title'], $uniquePosts);
        }

        usort($clusters, function (array $a, array $b) {
            $weightDiff = (self::URGENCY_WEIGHT[$b['urgency']] ?? 1) <=> (self::URGENCY_WEIGHT[$a['urgency']] ?? 1);
            if ($weightDiff !== 0) {
                return $weightDiff;
            }

            return $b['total_engagement'] <=> $a['total_engagement'];
        });

        $topClusters = array_slice($clusters, 0, 4);

        return [
            'executive_summary' => $this->composeHeuristicSummary($statsSnapshot, $windowLabel, $topClusters),
            'issue_clusters'    => $topClusters,
            'recommendations'   => $this->composeHeuristicRecommendations($statsSnapshot, $topClusters),
        ];
    }

    private function buildClusterFromPosts(
        string $title,
        Collection $matchedPosts,
        string $summary = '',
        ?string $preferredSentiment = null,
        ?string $preferredUrgency = null
    ): array {
        $regions = $matchedPosts
            ->map(fn (Post $post) => $post->socialAccount?->region?->name)
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();

        $categories = $matchedPosts
            ->map(fn (Post $post) => $post->socialAccount?->category?->name)
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();

        $postCount = $matchedPosts->count();
        $totalEngagement = (int) $matchedPosts->sum(fn (Post $post) => $post->total_engagement);
        $postIds = $matchedPosts
            ->sortByDesc(fn (Post $post) => $post->total_engagement)
            ->take(10)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $sentiment = in_array($preferredSentiment, self::VALID_SENTIMENTS, true)
            ? $preferredSentiment
            : $this->dominantSentiment($matchedPosts);

        $urgency = in_array($preferredUrgency, self::VALID_URGENCIES, true)
            ? $preferredUrgency
            : $this->inferUrgency($matchedPosts, $sentiment);

        if ($summary === '') {
            $topPost = $matchedPosts->sortByDesc(fn (Post $post) => $post->total_engagement)->first();
            $regionText = !empty($regions) ? implode(', ', array_slice($regions, 0, 3)) : 'lintas wilayah Jawa Tengah';
            $accountLabel = $topPost?->socialAccount?->username ? '@' . $topPost->socialAccount->username : 'akun terpantau';
            $excerpt = Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) ($topPost?->content ?? ''))), 110);
            $engagementFormatted = number_format($totalEngagement, 0, ',', '.');
            $summary = "Tercatat {$postCount} postingan di {$regionText} dengan total interaksi {$engagementFormatted}. Sorotan utama dari {$accountLabel}: \"{$excerpt}\".";
        }

        return [
            'title'            => $title,
            'sentiment'        => $sentiment,
            'urgency'          => $urgency,
            'regions'          => $regions,
            'categories'       => $categories,
            'post_count'       => $postCount,
            'total_engagement' => $totalEngagement,
            'post_ids'         => $postIds,
            'summary'          => $summary,
        ];
    }

    private function dominantSentiment(Collection $posts): string
    {
        $counts = [];
        foreach (self::VALID_SENTIMENTS as $sentiment) {
            $counts[$sentiment] = 0;
        }

        foreach ($posts as $post) {
            $sentiment = in_array($post->sentiment, self::VALID_SENTIMENTS, true) ? $post->sentiment : 'netral';
            $counts[$sentiment]++;
        }

        uksort($counts, function (string $a, string $b) use ($counts) {
            $countDiff = $counts[$b] <=> $counts[$a];
            if ($countDiff !== 0) {
                return $countDiff;
            }

            return (self::SENTIMENT_PRIORITY[$b] ?? 0) <=> (self::SENTIMENT_PRIORITY[$a] ?? 0);
        });

        return array_key_first($counts) ?? 'netral';
    }

    private function inferUrgency(Collection $posts, string $dominantSentiment): string
    {
        $hasSensitiveOrFlagged = $posts->contains(
            fn (Post $post) => $post->sentiment === 'sensitif' || (bool) $post->is_flagged
        );
        if ($hasSensitiveOrFlagged) {
            return 'tinggi';
        }

        if ($dominantSentiment === 'negatif' || $posts->contains('sentiment', 'negatif') || $posts->count() >= 3) {
            return 'sedang';
        }

        return 'rendah';
    }

    private function composeHeuristicSummary(array $stats, string $windowLabel, array $clusters): string
    {
        $totalPosts = number_format((int) ($stats['total_posts'] ?? 0), 0, ',', '.');
        $totalEngagement = number_format((int) ($stats['total_engagement'] ?? 0), 0, ',', '.');
        $topRegion = $stats['top_region'] ?? '-';
        $sc = $stats['sentiment_counts'] ?? [];

        $paragraph1 = "Pemantauan narasi publik Jawa Tengah pada jendela {$windowLabel} mencatat {$totalPosts} postingan dengan akumulasi {$totalEngagement} interaksi, dengan konsentrasi tertinggi di wilayah {$topRegion}. "
            . 'Distribusi sentimen meliputi '
            . ($sc['sensitif'] ?? 0) . ' konten sensitif, '
            . ($sc['negatif'] ?? 0) . ' negatif, '
            . ($sc['netral'] ?? 0) . ' netral, dan '
            . ($sc['positif'] ?? 0) . ' positif.';

        $clusterTitles = collect($clusters)->pluck('title')->take(3)->implode('; ');
        $flaggedCount = (int) ($stats['flagged_count'] ?? 0);

        $paragraph2 = $clusterTitles !== ''
            ? "Klaster isu utama yang teridentifikasi meliputi: {$clusterTitles}."
            : 'Belum terbentuk klaster isu dominan pada periode ini.';

        if ($flaggedCount > 0) {
            $paragraph2 .= " Terdapat {$flaggedCount} postingan bertanda perhatian khusus (flagged) yang memerlukan verifikasi lebih lanjut.";
        } else {
            $paragraph2 .= ' Pemantauan lanjutan tetap disarankan untuk mengantisipasi eskalasi percakapan di tingkat daerah.';
        }

        return $paragraph1 . "\n\n" . $paragraph2;
    }

    private function composeHeuristicRecommendations(array $stats, array $clusters): array
    {
        $recommendations = [];
        $topCluster = $clusters[0] ?? null;

        if ($topCluster !== null) {
            $regions = !empty($topCluster['regions']) ? implode(', ', array_slice($topCluster['regions'], 0, 2)) : ($stats['top_region'] ?? 'wilayah terdampak');
            if ($topCluster['urgency'] === 'tinggi') {
                $recommendations[] = "Segera lakukan koordinasi lintas OPD dan pemantauan lapangan di {$regions} terkait klaster \"{$topCluster['title']}\".";
            } elseif ($topCluster['urgency'] === 'sedang') {
                $recommendations[] = "Siapkan klarifikasi dan respons komunikasi publik terarah untuk klaster \"{$topCluster['title']}\" di {$regions}.";
            } else {
                $recommendations[] = "Pertahankan pemantauan berkala pada klaster \"{$topCluster['title']}\" di {$regions} serta amplifikasi narasi positif pemerintah daerah.";
            }
        }

        if (($stats['flagged_count'] ?? 0) > 0 || ($stats['sentiment_counts']['sensitif'] ?? 0) > 0) {
            $recommendations[] = 'Verifikasi konten bersentimen sensitif atau berstatus flagged bersama tim humas daerah untuk mencegah eskalasi disinformasi.';
        }

        $topRegion = $stats['top_region'] ?? '-';
        if ($topRegion !== '-' && $topRegion !== '') {
            $recommendations[] = "Prioritaskan pemantauan kanal media sosial lokal di {$topRegion} sebagai pusat aktivitas percakapan tertinggi pada periode ini.";
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Lanjutkan pemantauan rutin dan pastikan jadwal sinkronisasi data media sosial berjalan normal.';
        }

        return array_slice($recommendations, 0, 3);
    }

    private function sanitize(string $message): string
    {
        $secrets = [
            config('services.gemini.key'),
            config('services.youtube.key'),
            config('services.instagram.token'),
        ];

        foreach ($secrets as $secret) {
            if (is_string($secret) && trim($secret) !== '') {
                $message = str_replace(trim($secret), '***', $message);
            }
        }

        $message = (string) preg_replace('/(key|token|access_token)=([^&\s"\']+)/i', '$1=***', $message);

        return mb_substr($message, 0, 500);
    }
}
