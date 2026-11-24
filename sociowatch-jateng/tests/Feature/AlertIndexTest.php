<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerts_page_renders_successfully_with_alerts(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $acc  = SocialAccount::factory()->youtube()->create();

        Alert::create([
            'social_account_id' => $acc->id,
            'alert_type'        => 'spike_up',
            'threshold_value'   => 10,
            'current_value'     => 50,
            'change_percent'    => 40.0,
            'message'           => 'Lonjakan engagement terdeteksi',
            'is_read'           => false,
            'triggered_at'      => now(),
        ]);

        Alert::create([
            'social_account_id' => $acc->id,
            'alert_type'        => 'spike_down',
            'threshold_value'   => 10,
            'current_value'     => 5,
            'change_percent'    => -50.0,
            'message'           => 'Penurunan engagement terdeteksi',
            'is_read'           => false,
            'triggered_at'      => now(),
        ]);

        Alert::create([
            'social_account_id' => $acc->id,
            'alert_type'        => 'milestone',
            'threshold_value'   => 1000,
            'current_value'     => 1000,
            'change_percent'    => 0,
            'message'           => 'Milestone 1000 followers tercapai',
            'is_read'           => true,
            'triggered_at'      => now(),
        ]);

        $res = $this->actingAs($user)->get(route('alerts.index'));

        $res->assertOk();
        $res->assertSee('Spike Naik');
        $res->assertSee('Spike Turun');
        $res->assertSee('Milestone');
    }
}
