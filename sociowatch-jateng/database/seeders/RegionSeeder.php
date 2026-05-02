<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            // Kabupaten
            ['name' => 'Kabupaten Banjarnegara', 'type' => 'kabupaten', 'latitude' => -7.3907, 'longitude' => 109.6858, 'geojson_key' => 'BANJARNEGARA'],
            ['name' => 'Kabupaten Banyumas',     'type' => 'kabupaten', 'latitude' => -7.5151, 'longitude' => 109.2947, 'geojson_key' => 'BANYUMAS'],
            ['name' => 'Kabupaten Batang',        'type' => 'kabupaten', 'latitude' => -6.9143, 'longitude' => 109.7293, 'geojson_key' => 'BATANG'],
            ['name' => 'Kabupaten Blora',         'type' => 'kabupaten', 'latitude' => -6.9627, 'longitude' => 111.4140, 'geojson_key' => 'BLORA'],
            ['name' => 'Kabupaten Boyolali',      'type' => 'kabupaten', 'latitude' => -7.5325, 'longitude' => 110.5993, 'geojson_key' => 'BOYOLALI'],
            ['name' => 'Kabupaten Brebes',        'type' => 'kabupaten', 'latitude' => -6.8798, 'longitude' => 108.9047, 'geojson_key' => 'BREBES'],
            ['name' => 'Kabupaten Cilacap',       'type' => 'kabupaten', 'latitude' => -7.7232, 'longitude' => 109.0147, 'geojson_key' => 'CILACAP'],
            ['name' => 'Kabupaten Demak',         'type' => 'kabupaten', 'latitude' => -6.8944, 'longitude' => 110.6381, 'geojson_key' => 'DEMAK'],
            ['name' => 'Kabupaten Grobogan',      'type' => 'kabupaten', 'latitude' => -7.0126, 'longitude' => 110.9189, 'geojson_key' => 'GROBOGAN'],
            ['name' => 'Kabupaten Jepara',        'type' => 'kabupaten', 'latitude' => -6.5892, 'longitude' => 110.6718, 'geojson_key' => 'JEPARA'],
            ['name' => 'Kabupaten Karanganyar',   'type' => 'kabupaten', 'latitude' => -7.5958, 'longitude' => 111.0300, 'geojson_key' => 'KARANGANYAR'],
            ['name' => 'Kabupaten Kebumen',       'type' => 'kabupaten', 'latitude' => -7.6820, 'longitude' => 109.6527, 'geojson_key' => 'KEBUMEN'],
            ['name' => 'Kabupaten Kendal',        'type' => 'kabupaten', 'latitude' => -7.0232, 'longitude' => 110.2017, 'geojson_key' => 'KENDAL'],
            ['name' => 'Kabupaten Klaten',        'type' => 'kabupaten', 'latitude' => -7.7070, 'longitude' => 110.6017, 'geojson_key' => 'KLATEN'],
            ['name' => 'Kabupaten Kudus',         'type' => 'kabupaten', 'latitude' => -6.8048, 'longitude' => 110.8369, 'geojson_key' => 'KUDUS'],
            ['name' => 'Kabupaten Magelang',      'type' => 'kabupaten', 'latitude' => -7.5479, 'longitude' => 110.2179, 'geojson_key' => 'MAGELANG'],
            ['name' => 'Kabupaten Pati',          'type' => 'kabupaten', 'latitude' => -6.7478, 'longitude' => 111.0384, 'geojson_key' => 'PATI'],
            ['name' => 'Kabupaten Pekalongan',    'type' => 'kabupaten', 'latitude' => -7.0883, 'longitude' => 109.6640, 'geojson_key' => 'PEKALONGAN'],
            ['name' => 'Kabupaten Pemalang',      'type' => 'kabupaten', 'latitude' => -6.9078, 'longitude' => 109.3793, 'geojson_key' => 'PEMALANG'],
            ['name' => 'Kabupaten Purbalingga',   'type' => 'kabupaten', 'latitude' => -7.3903, 'longitude' => 109.3647, 'geojson_key' => 'PURBALINGGA'],
            ['name' => 'Kabupaten Purworejo',     'type' => 'kabupaten', 'latitude' => -7.7132, 'longitude' => 110.0178, 'geojson_key' => 'PURWOREJO'],
            ['name' => 'Kabupaten Rembang',       'type' => 'kabupaten', 'latitude' => -6.7059, 'longitude' => 111.3412, 'geojson_key' => 'REMBANG'],
            ['name' => 'Kabupaten Semarang',      'type' => 'kabupaten', 'latitude' => -7.2271, 'longitude' => 110.4280, 'geojson_key' => 'SEMARANG'],
            ['name' => 'Kabupaten Sragen',        'type' => 'kabupaten', 'latitude' => -7.4254, 'longitude' => 111.0186, 'geojson_key' => 'SRAGEN'],
            ['name' => 'Kabupaten Sukoharjo',     'type' => 'kabupaten', 'latitude' => -7.6868, 'longitude' => 110.8316, 'geojson_key' => 'SUKOHARJO'],
            ['name' => 'Kabupaten Tegal',         'type' => 'kabupaten', 'latitude' => -7.0000, 'longitude' => 109.1400, 'geojson_key' => 'TEGAL'],
            ['name' => 'Kabupaten Temanggung',    'type' => 'kabupaten', 'latitude' => -7.3167, 'longitude' => 110.1667, 'geojson_key' => 'TEMANGGUNG'],
            ['name' => 'Kabupaten Wonogiri',      'type' => 'kabupaten', 'latitude' => -7.8167, 'longitude' => 110.9167, 'geojson_key' => 'WONOGIRI'],
            ['name' => 'Kabupaten Wonosobo',      'type' => 'kabupaten', 'latitude' => -7.3613, 'longitude' => 109.9027, 'geojson_key' => 'WONOSOBO'],

            // Kota
            ['name' => 'Kota Magelang',    'type' => 'kota', 'latitude' => -7.4797, 'longitude' => 110.2177, 'geojson_key' => 'KOTA MAGELANG'],
            ['name' => 'Kota Pekalongan', 'type' => 'kota', 'latitude' => -6.8886, 'longitude' => 109.6753, 'geojson_key' => 'KOTA PEKALONGAN'],
            ['name' => 'Kota Salatiga',    'type' => 'kota', 'latitude' => -7.3306, 'longitude' => 110.5084, 'geojson_key' => 'KOTA SALATIGA'],
            ['name' => 'Kota Semarang',    'type' => 'kota', 'latitude' => -6.9932, 'longitude' => 110.4203, 'geojson_key' => 'KOTA SEMARANG'],
            ['name' => 'Kota Surakarta',   'type' => 'kota', 'latitude' => -7.5755, 'longitude' => 110.8243, 'geojson_key' => 'KOTA SURAKARTA'],
            ['name' => 'Kota Tegal',       'type' => 'kota', 'latitude' => -6.8694, 'longitude' => 109.1402, 'geojson_key' => 'KOTA TEGAL'],
        ];

        DB::table('regions')->insert(array_map(function ($region) {
            return array_merge($region, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $regions));
    }
}
