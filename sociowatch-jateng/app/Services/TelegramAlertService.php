<?php

namespace App\Services;

use App\Models\NarrativeBriefing;
use App\Models\Post;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramAlertService
{
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function isConfigured(): bool
    {
        $token = trim((string) config('services.telegram.bot_token', ''));
        $chatId = trim((string) config('services.telegram.chat_id', ''));

        return $token !== '' && $chatId !== '';
    }

    public function sendMessage(string $htmlMessage): bool
    {
        $this->lastError = null;

        if (! $this->isConfigured()) {
            $this->lastError = 'TELEGRAM_BOT_TOKEN atau TELEGRAM_CHAT_ID kosong.';
            return false;
        }

        $token = trim((string) config('services.telegram.bot_token'));
        $chatId = trim((string) config('services.telegram.chat_id'));

        try {
            $response = Http::timeout(15)
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id'                  => $chatId,
                'text'                     => $htmlMessage,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($response->successful()) {
                return true;
            }

            $desc = $response->json('description') ?? $response->body();
            $this->lastError = "HTTP {$response->status()}: " . mb_substr((string) $desc, 0, 200);
            return false;
        } catch (Throwable $e) {
            $this->lastError = str_replace($token, '***', $e->getMessage());
            return false;
        }
    }

    public function notifySensitivePost(Post $post): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $post->loadMissing(['socialAccount.region', 'socialAccount.category', 'keywords']);

        $account = $post->socialAccount;
        $handle = $account ? '@' . $account->username : '-';
        $displayName = $account?->display_name ?? '-';
        $region = $account?->region?->name ?? '-';
        $category = $account?->category?->name ?? '-';

        $keywords = $post->keywords->pluck('word')->all();
        $kwText = ! empty($keywords) ? implode(', ', $keywords) : '-';

        $snippet = mb_substr(trim((string) preg_replace('/\s+/', ' ', (string) $post->content)), 0, 350);
        if (mb_strlen((string) $post->content) > 350) {
            $snippet .= '...';
        }

        $lines = [
            "🚨 <b>POSTINGAN SENSITIF TERDETEKSI</b>",
            "",
            "<b>Akun:</b> " . $this->esc($displayName) . " (" . $this->esc($handle) . ")",
            "<b>Wilayah:</b> " . $this->esc($region) . " | <b>Kategori:</b> " . $this->esc($category),
            "<b>Keyword:</b> " . $this->esc($kwText),
            "",
            "<i>\"" . $this->esc($snippet) . "\"</i>",
        ];

        if (! empty($post->post_url)) {
            $lines[] = "";
            $lines[] = "🔗 <a href=\"" . $this->esc($post->post_url) . "\">Buka Postingan Asli</a>";
        }

        return $this->sendMessage(implode("\n", $lines));
    }

    public function notifyBriefing(NarrativeBriefing $briefing): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $stats = is_array($briefing->stats_snapshot) ? $briefing->stats_snapshot : [];
        $sent = $stats['sentiment_counts'] ?? [];
        $totalPosts = $stats['total_posts'] ?? 0;

        $summary = mb_substr(trim((string) $briefing->executive_summary), 0, 700);
        if (mb_strlen((string) $briefing->executive_summary) > 700) {
            $summary .= '...';
        }

        $lines = [
            "📊 <b>RINGKASAN EKSEKUTIF SOCIOWATCH JATENG</b>",
            "<i>Periode: " . $this->esc((string) ($briefing->window_label ?: '24 Jam Terakhir')) . "</i>",
            "",
            $this->esc($summary),
            "",
            "<b>Statistik ({$totalPosts} Postingan):</b>",
            "• Sensitif: " . (int) ($sent['sensitif'] ?? 0) . " | Negatif: " . (int) ($sent['negatif'] ?? 0)
                . " | Netral: " . (int) ($sent['netral'] ?? 0) . " | Positif: " . (int) ($sent['positif'] ?? 0),
        ];

        $clusters = is_array($briefing->issue_clusters) ? array_slice($briefing->issue_clusters, 0, 3) : [];
        if (! empty($clusters)) {
            $lines[] = "";
            $lines[] = "<b>Klaster Isu Utama:</b>";
            foreach ($clusters as $idx => $c) {
                $num = $idx + 1;
                $title = (string) ($c['title'] ?? 'Isu');
                $urgency = strtoupper((string) ($c['urgency'] ?? 'sedang'));
                $lines[] = "{$num}. " . $this->esc($title) . " <code>[{$urgency}]</code>";
            }
        }

        return $this->sendMessage(implode("\n", $lines));
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
