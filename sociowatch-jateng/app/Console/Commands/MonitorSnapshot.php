<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\AlertSetting;
use App\Models\FollowerSnapshot;
use App\Models\Keyword;
use App\Models\SocialAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorSnapshot extends Command
{
    protected $signature   = 'monitor:snapshot {--dry-run : Preview tanpa menyimpan}';
    protected $description = 'Ambil snapshot followers semua akun aktif dan cek threshold alert';

    public function handle(): int
    {
        $dryRun   = $this->option('dry-run');
        $accounts = SocialAccount::where('is_active', true)->get();
        $global   = AlertSetting::global();

        $this->info("Memproses {$accounts->count()} akun aktif…");
        $bar = $this->output->createProgressBar($accounts->count());
        $bar->start();

        $snapshotCount = 0;
        $alertCount    = 0;

        foreach ($accounts as $account) {
            // Build snapshot record
            $snapshot = [
                'social_account_id' => $account->id,
                'followers_count'   => $account->followers_count,
                'following_count'   => $account->following_count,
                'post_count'        => $account->post_count,
                'recorded_at'       => now(),
            ];

            if (!$dryRun) {
                FollowerSnapshot::create($snapshot);
            }
            $snapshotCount++;

            // Get previous snapshot for comparison
            $prev = FollowerSnapshot::where('social_account_id', $account->id)
                ->where('recorded_at', '<', now()->subMinutes(5))
                ->orderByDesc('recorded_at')
                ->first();

            if ($prev && $prev->followers_count > 0) {
                $change  = $account->followers_count - $prev->followers_count;
                $pct     = round(($change / $prev->followers_count) * 100, 2);

                // Get per-account setting, fall back to global
                $setting = $account->alertSetting ?? $global;

                if (!$setting->is_active) {
                    $bar->advance();
                    continue;
                }

                // Spike up
                if ($pct >= (float) $setting->spike_up_threshold) {
                    $alertCount += $this->createAlert($account, 'spike_up', $setting->spike_up_threshold, $pct, $change, $dryRun);
                }

                // Spike down
                if ($pct <= -(float) $setting->spike_down_threshold) {
                    $alertCount += $this->createAlert($account, 'spike_down', $setting->spike_down_threshold, abs($pct), $change, $dryRun);
                }

                // Milestone check
                $milestones = $setting->milestone_values ?? [];
                foreach ($milestones as $milestone) {
                    if ($prev->followers_count < $milestone && $account->followers_count >= $milestone) {
                        if (!$dryRun) {
                            Alert::create([
                                'social_account_id' => $account->id,
                                'alert_type'        => 'milestone',
                                'threshold_value'   => $milestone,
                                'current_value'     => $account->followers_count,
                                'change_percent'    => $pct,
                                'message'           => sprintf(
                                    '%s melewati milestone %s followers! Kini: %s',
                                    $account->username,
                                    number_format($milestone),
                                    number_format($account->followers_count)
                                ),
                                'is_read'      => false,
                                'triggered_at' => now(),
                            ]);
                        }
                        $alertCount++;
                    }
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Keyword spike detection
        $this->info('Memeriksa keyword spike…');
        $alertCount += $this->detectKeywordSpikes($global, $dryRun);

        $this->info("Snapshot: {$snapshotCount} | Alert baru: {$alertCount}" . ($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }

    private function detectKeywordSpikes(AlertSetting $global, bool $dryRun): int
    {
        $threshold = (int) ($global->keyword_spike_threshold ?? 50);
        $today     = now()->startOfDay();
        $window7   = now()->subDays(7)->startOfDay();
        $alertCount = 0;

        $keywords = Keyword::where('is_active', true)
            ->whereIn('category', ['sensitif', 'negatif'])
            ->get();

        foreach ($keywords as $keyword) {
            // Count occurrences today
            $todayCount = DB::table('post_keyword_pivot')
                ->join('posts', 'posts.id', '=', 'post_keyword_pivot.post_id')
                ->where('post_keyword_pivot.keyword_id', $keyword->id)
                ->where('posts.posted_at', '>=', $today)
                ->sum('post_keyword_pivot.occurrence_count');

            if ($todayCount === 0) {
                continue;
            }

            // Average daily count over the past 7 days (excluding today)
            $avg7 = DB::table('post_keyword_pivot')
                ->join('posts', 'posts.id', '=', 'post_keyword_pivot.post_id')
                ->where('post_keyword_pivot.keyword_id', $keyword->id)
                ->where('posts.posted_at', '>=', $window7)
                ->where('posts.posted_at', '<', $today)
                ->sum('post_keyword_pivot.occurrence_count') / 7;

            if ($avg7 <= 0) {
                continue;
            }

            $pctChange = round(($todayCount - $avg7) / $avg7 * 100, 1);

            if ($pctChange >= $threshold) {
                // Check no duplicate alert today for this keyword
                $exists = Alert::where('alert_type', 'keyword_spike')
                    ->where('message', 'LIKE', "%{$keyword->word}%")
                    ->where('triggered_at', '>=', $today)
                    ->exists();

                if (!$exists && !$dryRun) {
                    Alert::create([
                        'social_account_id' => null,
                        'alert_type'        => 'keyword_spike',
                        'threshold_value'   => $threshold,
                        'current_value'     => $todayCount,
                        'change_percent'    => $pctChange,
                        'message'           => sprintf(
                            'Keyword "%s" (%s) naik %.1f%% hari ini: %d kemunculan (rata-rata 7h: %.1f)',
                            $keyword->word, $keyword->category, $pctChange, $todayCount, $avg7
                        ),
                        'is_read'      => false,
                        'triggered_at' => now(),
                    ]);
                }
                $alertCount++;
                $this->line("  <comment>Spike:</comment> {$keyword->word} +{$pctChange}% ({$todayCount} hari ini)");
            }
        }

        return $alertCount;
    }

    private function createAlert(SocialAccount $account, string $type, float $threshold, float $pct, int $change, bool $dryRun): int
    {
        $direction = $type === 'spike_up' ? 'naik' : 'turun';
        $message   = sprintf(
            '%s %s %.1f%% → %s followers (perubahan: %+d)',
            $account->username,
            $direction,
            $pct,
            number_format($account->followers_count),
            $change
        );

        if (!$dryRun) {
            Alert::create([
                'social_account_id' => $account->id,
                'alert_type'        => $type,
                'threshold_value'   => (int) $threshold,
                'current_value'     => $account->followers_count,
                'change_percent'    => $pct,
                'message'           => $message,
                'is_read'           => false,
                'triggered_at'      => now(),
            ]);
        }

        return 1;
    }
}
