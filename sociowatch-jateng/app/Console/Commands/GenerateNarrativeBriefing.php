<?php

namespace App\Console\Commands;

use App\Models\NarrativeBriefing;
use App\Services\NarrativeBriefingService;
use App\Services\TelegramAlertService;
use Illuminate\Console\Command;

class GenerateNarrativeBriefing extends Command
{
    protected $signature = 'intel:briefing {--hours=24 : Jendela waktu jam ke belakang} {--heuristic : Paksa gunakan mesin heuristik lokal}';

    protected $description = 'Buat ringkasan eksekutif harian dan klaster isu otomatis';

    public function handle(NarrativeBriefingService $service, TelegramAlertService $telegram): int
    {
        NarrativeBriefing::where('created_at', '<', now()->subDays(90))->delete();

        $hours = max(1, (int) $this->option('hours'));
        $forceHeuristic = (bool) $this->option('heuristic');

        $briefing = $service->generate($hours, $forceHeuristic);

        $totalPosts = $briefing->stats_snapshot['total_posts'] ?? 0;
        $clusterCount = count($briefing->issue_clusters ?? []);

        $this->info(
            "Briefing #{$briefing->id} selesai [{$briefing->source}/{$briefing->status}] — jendela: {$briefing->window_label}, total post: {$totalPosts}, klaster: {$clusterCount}"
        );

        if ($briefing->status === 'fallback' && $briefing->error_message) {
            $this->warn('Fallback heuristik aktif: ' . $briefing->error_message);
        }

        if ($telegram->isConfigured()) {
            $sent = $telegram->notifyBriefing($briefing);
            $this->info($sent ? 'Ringkasan briefing terkirim ke Telegram.' : 'Gagal mengirim ringkasan briefing ke Telegram.');
        }

        return self::SUCCESS;
    }
}
