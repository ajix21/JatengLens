<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name'        => 'Mahasiswa',
                'color'       => '#3B82F6',
                'description' => 'Akun media sosial milik organisasi atau individu mahasiswa',
            ],
            [
                'name'        => 'Buruh',
                'color'       => '#EF4444',
                'description' => 'Akun media sosial terkait serikat buruh dan tenaga kerja',
            ],
            [
                'name'        => 'LSM',
                'color'       => '#10B981',
                'description' => 'Lembaga Swadaya Masyarakat dan organisasi non-profit',
            ],
            [
                'name'        => 'Media',
                'color'       => '#F59E0B',
                'description' => 'Akun media online, jurnalisme warga, dan pers lokal',
            ],
            [
                'name'        => 'Ormas',
                'color'       => '#8B5CF6',
                'description' => 'Organisasi kemasyarakatan dan komunitas daerah',
            ],
        ];

        DB::table('categories')->insert(array_map(function ($cat) {
            return array_merge($cat, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $categories));
    }
}
