<?php

namespace App\Imports;

use App\Models\Category;
use App\Models\Region;
use App\Models\SocialAccount;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class AccountsImport implements ToCollection, WithHeadingRow
{
    public array $results  = [];
    public array $errors   = [];
    public int   $imported = 0;
    public int   $skipped  = 0;

    private Collection $categories;
    private Collection $regions;

    public function __construct()
    {
        $this->categories = Category::all()->keyBy(fn($c) => strtolower($c->name));
        $this->regions    = Region::all()->keyBy(fn($r) => strtolower($r->name));
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $username = trim($row['username'] ?? '');
            $platform = strtolower(trim($row['platform'] ?? ''));

            if (!$username || !$platform) {
                $this->errors[] = "Baris {$rowNum}: username/platform kosong.";
                $this->skipped++;
                continue;
            }

            if (!in_array($platform, ['instagram', 'twitter', 'facebook', 'tiktok', 'youtube'])) {
                $this->errors[] = "Baris {$rowNum}: platform '{$platform}' tidak valid.";
                $this->skipped++;
                continue;
            }

            if (SocialAccount::where('platform', $platform)->where('username', $username)->exists()) {
                $this->errors[] = "Baris {$rowNum}: @{$username} ({$platform}) sudah ada — dilewati.";
                $this->skipped++;
                continue;
            }

            $categoryKey = strtolower(trim($row['kategori'] ?? $row['category'] ?? ''));
            $regionKey   = strtolower(trim($row['wilayah'] ?? $row['region'] ?? ''));
            $category    = $this->categories->get($categoryKey);
            $region      = $this->regions->get($regionKey);

            SocialAccount::create([
                'platform'        => $platform,
                'username'        => $username,
                'display_name'    => trim($row['display_name'] ?? $row['nama'] ?? $username),
                'profile_url'     => trim($row['profile_url'] ?? '') ?: null,
                'followers_count' => (int) ($row['followers'] ?? $row['followers_count'] ?? 0),
                'following_count' => (int) ($row['following'] ?? $row['following_count'] ?? 0),
                'post_count'      => (int) ($row['posts'] ?? $row['post_count'] ?? 0),
                'bio'             => trim($row['bio'] ?? '') ?: null,
                'category_id'     => $category?->id,
                'region_id'       => $region?->id,
                'is_active'       => true,
                'notes'           => trim($row['catatan'] ?? $row['notes'] ?? '') ?: null,
            ]);

            $this->imported++;
        }
    }
}
