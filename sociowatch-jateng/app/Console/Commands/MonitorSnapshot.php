<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\AlertSetting;
use App\Models\FollowerSnapshot;
use App\Models\SocialAccount;
use Illuminate\Console\Command;

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
        $this->info("Snapshot: {$snapshotCount} | Alert baru: {$alertCount}" . ($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
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
