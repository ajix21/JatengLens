<?php

namespace App\Imports;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\KeywordDetectionService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PostsImport implements ToCollection, WithHeadingRow
{
    public int   $imported = 0;
    public int   $skipped  = 0;
    public array $errors   = [];

    private Collection $accounts;
    private KeywordDetectionService $detector;

    public function __construct()
    {
        $this->accounts = SocialAccount::all()->mapWithKeys(
            fn($a) => [$a->platform . '|' . $a->username => $a]
        );
        $this->detector = new KeywordDetectionService();
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum   = $i + 2;
            $platform = strtolower(trim($row['platform'] ?? ''));
            $username = trim($row['username'] ?? '');

            if (!$platform || !$username) {
                $this->errors[] = "Baris {$rowNum}: platform/username kosong.";
                $this->skipped++;
                continue;
            }

            $account = $this->accounts->get($platform . '|' . $username);
            if (!$account) {
                $this->errors[] = "Baris {$rowNum}: Akun @{$username} ({$platform}) tidak ditemukan.";
                $this->skipped++;
                continue;
            }

            $content   = trim($row['content'] ?? '');
            $postedAt  = $row['posted_at'] ?? now();

            $post = Post::create([
                'social_account_id' => $account->id,
                'platform'          => $platform,
                'post_url'          => trim($row['post_url'] ?? '') ?: null,
                'content'           => $content ?: '(tidak ada konten)',
                'media_type'        => in_array($row['media_type'] ?? '', ['text','image','video','reel','story'])
                    ? $row['media_type'] : null,
                'likes_count'       => (int) ($row['likes_count'] ?? 0),
                'comments_count'    => (int) ($row['comments_count'] ?? 0),
                'shares_count'      => (int) ($row['shares_count'] ?? 0),
                'views_count'       => (int) ($row['views_count'] ?? 0),
                'posted_at'         => $postedAt,
                'is_flagged'        => false,
            ]);

            if ($content) {
                $detected = $this->detector->detect($content);
                if (!empty($detected)) {
                    $post->keywords()->sync($detected);
                }
            }

            $this->imported++;
        }
    }
}
