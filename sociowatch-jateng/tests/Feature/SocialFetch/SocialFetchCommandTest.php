<?php

namespace Tests\Feature\SocialFetch;

use App\Models\FetchLog;
use App\Models\SocialAccount;
use App\Services\KeywordDetectionService;
use App\Services\SocialFetch\FetchedAccount;
use App\Services\SocialFetch\SocialFetchService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialFetchCommandTest extends TestCase
{
    use RefreshDatabase;

    private function bindFakes(FakeFetcher ...$fakes): void
    {
        $this->app->instance(
            SocialFetchService::class,
            new SocialFetchService($fakes, app(KeywordDetectionService::class))
        );
    }

    private function fake(string $platform, bool $configured = true): FakeFetcher
    {
        $f = new FakeFetcher($platform, $configured);
        $f->default = new FetchedAccount('EXT', 100, null, 5, []);

        return $f;
    }

    public function test_command_runs_and_prints_summary(): void
    {
        SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $this->bindFakes($this->fake('youtube'));

        $this->artisan('social:fetch')
            ->expectsOutputToContain('[ok] youtube @JawaPosTV — 0 baru, 0 diperbarui')
            ->expectsOutputToContain('Selesai: 1 berhasil, 0 gagal')
            ->assertSuccessful();
    }

    public function test_platform_and_account_options_filter(): void
    {
        $yt = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $ig = SocialAccount::factory()->instagram()->create(['auto_fetch' => true]);
        $this->bindFakes($this->fake('youtube'), $this->fake('instagram'));

        $this->artisan('social:fetch', ['--platform' => 'instagram'])
            ->expectsOutputToContain('Selesai: 1 berhasil, 0 gagal')
            ->assertSuccessful();
        $this->assertSame($ig->id, FetchLog::first()->social_account_id);
        $this->assertSame(1, FetchLog::count());

        FetchLog::query()->delete();
        $this->artisan('social:fetch', ['--account' => (string) $yt->id])
            ->expectsOutputToContain('Selesai: 1 berhasil, 0 gagal')
            ->assertSuccessful();
        $this->assertSame($yt->id, FetchLog::first()->social_account_id);
        $this->assertSame(1, FetchLog::count());
    }

    public function test_reports_skipped_platform(): void
    {
        SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        SocialAccount::factory()->instagram()->create(['auto_fetch' => true]);
        $this->bindFakes($this->fake('youtube'), $this->fake('instagram', false));

        $this->artisan('social:fetch')
            ->expectsOutputToContain('Lewati instagram: kredensial belum diatur')
            ->expectsOutputToContain('Selesai: 1 berhasil, 0 gagal')
            ->assertSuccessful();
    }

    public function test_prunes_logs_older_than_90_days(): void
    {
        $acc = SocialAccount::factory()->youtube()->create(['auto_fetch' => true]);
        $mk = fn (int $days) => FetchLog::create([
            'social_account_id' => $acc->id, 'status' => 'ok', 'new_posts' => 0,
            'updated_posts' => 0, 'message' => null, 'fetched_at' => now()->subDays($days),
        ]);
        $old = $mk(91);
        $recent = $mk(89);
        $this->bindFakes($this->fake('youtube'));

        $this->artisan('social:fetch', ['--dry-run' => true])->assertSuccessful();
        $this->assertNotNull(FetchLog::find($old->id));

        $this->artisan('social:fetch')->assertSuccessful();
        $this->assertNull(FetchLog::find($old->id));
        $this->assertNotNull(FetchLog::find($recent->id));
    }

    public function test_schedule_registers_fetch_before_snapshot_in_jakarta_time(): void
    {
        $events = collect(app(Schedule::class)->events());
        $find = fn (string $cmd, string $expr) => $events->first(
            fn ($e) => str_contains($e->command, $cmd) && $e->expression === $expr
        );

        foreach (['50 6 * * *', '50 18 * * *'] as $expr) {
            $e = $find('social:fetch', $expr);
            $this->assertNotNull($e, "social:fetch $expr");
            $this->assertSame('Asia/Jakarta', (string) $e->timezone);
            $this->assertTrue($e->withoutOverlapping);
            $this->assertSame(60, $e->expiresAt);
        }
        foreach (['0 7 * * *', '0 19 * * *'] as $expr) {
            $e = $find('monitor:snapshot', $expr);
            $this->assertNotNull($e, "monitor:snapshot $expr");
            $this->assertSame('Asia/Jakarta', (string) $e->timezone);
        }
    }
}
