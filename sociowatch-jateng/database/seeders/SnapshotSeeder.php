<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\AlertSetting;
use App\Models\FollowerSnapshot;
use App\Models\SocialAccount;
use Illuminate\Database\Seeder;

class SnapshotSeeder extends Seeder
{
    public function run(): void
    {
        // Seed default global alert setting
        AlertSetting::firstOrCreate(
            ['social_account_id' => null],
            [
                'spike_up_threshold'   => 10.00,
                'spike_down_threshold' => 10.00,
                'milestone_values'     => [1000, 5000, 10000, 50000, 100000],
                'is_active'            => true,
            ]
        );

        $accounts = SocialAccount::all();

        foreach ($accounts as $account) {
            $base       = $account->followers_count;
            $snapshots  = [];
            $prevCount  = (int) round($base * 0.85); // start ~85% of current

            for ($day = 30; $day >= 0; $day--) {
                // simulate slight daily growth with small variance
                $growthRate = (mt_rand(0, 100) / 10000); // 0–1%
                $noise      = mt_rand(-20, 30);
                $count      = max(0, (int) round($prevCount * (1 + $growthRate) + $noise));

                // cap at actual current value on day 0
                if ($day === 0) {
                    $count = $account->followers_count;
                }

                $snapshots[] = [
                    'social_account_id' => $account->id,
                    'followers_count'   => $count,
                    'following_count'   => $account->following_count,
                    'post_count'        => max(0, $account->post_count - $day),
                    'recorded_at'       => now()->subDays($day)->setTime(7, 0, 0),
                    'created_at'        => now()->subDays($day),
                    'updated_at'        => now()->subDays($day),
                ];

                $prevCount = $count;
            }

            FollowerSnapshot::insert($snapshots);

            // Seed a couple of demo alerts per account
            $latest  = $account->followers_count;
            $week    = $snapshots[count($snapshots) - 7]['followers_count'];
            $change  = $latest - $week;
            $pct     = $week > 0 ? round(($change / $week) * 100, 2) : 0;

            if (abs($pct) >= 5) {
                Alert::create([
                    'social_account_id' => $account->id,
                    'alert_type'        => $pct > 0 ? 'spike_up' : 'spike_down',
                    'threshold_value'   => 10,
                    'current_value'     => $latest,
                    'change_percent'    => abs($pct),
                    'message'           => sprintf(
                        '%s %s %.1f%% dalam 7 hari terakhir → %s followers',
                        $account->username,
                        $pct > 0 ? 'naik' : 'turun',
                        abs($pct),
                        number_format($latest)
                    ),
                    'is_read'       => false,
                    'triggered_at'  => now()->subHours(mt_rand(1, 48)),
                ]);
            }
        }
    }
}
