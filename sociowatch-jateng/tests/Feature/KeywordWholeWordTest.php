<?php

namespace Tests\Feature;

use App\Models\Keyword;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\KeywordDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordWholeWordTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_detection_matches_whole_words_only(): void
    {
        $kwAksi = Keyword::factory()->create(['word' => 'aksi', 'color' => '#ef4444', 'is_active' => true]);
        $kwDemo = Keyword::factory()->create(['word' => 'demo', 'color' => '#3b82f6', 'is_active' => true]);

        $detector = new KeywordDetectionService();

        // Substring inside other words must NOT match
        $contentSubstring = 'Yogyakarta kembali menjadi saksi kelam ketika ruang demokrasi dicederai oleh tindakan aparat.';
        $detectedSub = $detector->detect($contentSubstring);
        $this->assertArrayNotHasKey($kwAksi->id, $detectedSub, 'Keyword aksi should NOT match inside saksi');
        $this->assertArrayNotHasKey($kwDemo->id, $detectedSub, 'Keyword demo should NOT match inside demokrasi');

        // Standalone words MUST match
        $contentStandalone = 'Massa aksi menggelar demo damai di depan gedung dewan.';
        $detectedStand = $detector->detect($contentStandalone);
        $this->assertArrayHasKey($kwAksi->id, $detectedStand);
        $this->assertSame(1, $detectedStand[$kwAksi->id]['occurrence_count']);
        $this->assertArrayHasKey($kwDemo->id, $detectedStand);
        $this->assertSame(1, $detectedStand[$kwDemo->id]['occurrence_count']);

        // Mixed: saksi + peserta aksi
        $contentMixed = 'Menjadi saksi perjuangan ratusan peserta aksi bersama masyarakat.';
        $detectedMixed = $detector->detect($contentMixed);
        $this->assertArrayHasKey($kwAksi->id, $detectedMixed);
        $this->assertSame(1, $detectedMixed[$kwAksi->id]['occurrence_count'], 'Should only count standalone aksi, not saksi');
    }

    public function test_post_show_highlights_whole_words_only(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $acc  = SocialAccount::factory()->instagram()->create();
        $kwAksi = Keyword::factory()->create(['word' => 'aksi', 'color' => '#ef4444', 'is_active' => true]);

        $post = Post::factory()->for($acc, 'socialAccount')->create([
            'content' => 'Menjadi saksi perjuangan peserta aksi hari ini.',
        ]);
        $post->keywords()->attach([$kwAksi->id => ['occurrence_count' => 1]]);

        $response = $this->actingAs($user)->get(route('posts.show', $post));
        $response->assertOk();

        // Must highlight 'aksi' in 'peserta aksi', but must NOT corrupt 'saksi' into 's<mark>aksi</mark>'
        $response->assertDontSee('s<mark', false);
        $response->assertSee('<mark', false);
    }
}
