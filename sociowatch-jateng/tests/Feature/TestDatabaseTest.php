<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseTest extends TestCase
{
    use RefreshDatabase;

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
}
