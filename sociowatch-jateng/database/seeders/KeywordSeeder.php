<?php

namespace Database\Seeders;

use App\Models\Keyword;
use Illuminate\Database\Seeder;

class KeywordSeeder extends Seeder
{
    public function run(): void
    {
        $keywords = [
            // Sensitif
            ['word' => 'demo',          'category' => 'sensitif', 'color' => '#ef4444'],
            ['word' => 'mogok',         'category' => 'sensitif', 'color' => '#ef4444'],
            ['word' => 'blokir',        'category' => 'sensitif', 'color' => '#ef4444'],
            ['word' => 'tolak',         'category' => 'sensitif', 'color' => '#ef4444'],
            ['word' => 'provokasi',     'category' => 'sensitif', 'color' => '#ef4444'],
            ['word' => 'unjuk rasa',    'category' => 'sensitif', 'color' => '#ef4444'],
            // Negatif
            ['word' => 'korupsi',       'category' => 'negatif',  'color' => '#f97316'],
            ['word' => 'curang',        'category' => 'negatif',  'color' => '#f97316'],
            ['word' => 'bohong',        'category' => 'negatif',  'color' => '#f97316'],
            ['word' => 'diskriminasi',  'category' => 'negatif',  'color' => '#f97316'],
            ['word' => 'manipulasi',    'category' => 'negatif',  'color' => '#f97316'],
            ['word' => 'penipuan',      'category' => 'negatif',  'color' => '#f97316'],
            // Netral
            ['word' => 'kebijakan',     'category' => 'netral',   'color' => '#6b7280'],
            ['word' => 'peraturan',     'category' => 'netral',   'color' => '#6b7280'],
            ['word' => 'rapat',         'category' => 'netral',   'color' => '#6b7280'],
            // Positif
            ['word' => 'apresiasi',     'category' => 'positif',  'color' => '#22c55e'],
            ['word' => 'mendukung',     'category' => 'positif',  'color' => '#22c55e'],
            ['word' => 'berhasil',      'category' => 'positif',  'color' => '#22c55e'],
            ['word' => 'prestasi',      'category' => 'positif',  'color' => '#22c55e'],
            ['word' => 'solidaritas',   'category' => 'positif',  'color' => '#22c55e'],
        ];

        foreach ($keywords as $kw) {
            Keyword::firstOrCreate(['word' => $kw['word']], $kw);
        }
    }
}
