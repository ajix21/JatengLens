<?php

namespace Tests\Feature\SocialFetch;

use App\Models\Post;
use App\Models\SocialAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_post_keeps_posted_at(): void
    {
        $post = Post::factory()->for(SocialAccount::factory()->youtube(), 'socialAccount')
            ->create(['posted_at' => '2026-10-01 08:00:00']);
        $this->travel(2)->days();
        $post->update(['likes_count' => 99]);
        $this->assertSame('2026-10-01 08:00:00', $post->fresh()->posted_at->format('Y-m-d H:i:s'));
    }

    public function test_external_id_is_unique_per_platform(): void
    {
        $acc = SocialAccount::factory()->youtube()->create();
        Post::factory()->for($acc, 'socialAccount')->create(['external_id' => 'abc']);
        $this->expectException(QueryException::class);
        Post::factory()->for($acc, 'socialAccount')->create(['external_id' => 'abc']);
    }

    public function test_new_accounts_default_to_auto_fetch(): void
    {
        $this->assertTrue(SocialAccount::factory()->instagram()->create()->fresh()->auto_fetch);
    }
}
