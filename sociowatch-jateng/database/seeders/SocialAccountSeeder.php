<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialAccountSeeder extends Seeder
{
    public function run(): void
    {
        // region IDs: 1–29 kabupaten, 30–35 kota (sesuai urutan RegionSeeder)
        // category IDs: 1=Mahasiswa, 2=Buruh, 3=LSM, 4=Media, 5=Ormas

        $accounts = [
            [
                'platform'        => 'instagram',
                'username'        => 'bem_undip_official',
                'display_name'    => 'BEM Universitas Diponegoro',
                'profile_url'     => 'https://instagram.com/bem_undip_official',
                'followers_count' => 45200,
                'following_count' => 312,
                'post_count'      => 874,
                'bio'             => 'Badan Eksekutif Mahasiswa UNDIP Semarang',
                'category_id'     => 1,
                'region_id'       => 34, // Kota Semarang
                'is_active'       => true,
            ],
            [
                'platform'        => 'twitter',
                'username'        => 'spsi_jateng',
                'display_name'    => 'SPSI Jawa Tengah',
                'profile_url'     => 'https://twitter.com/spsi_jateng',
                'followers_count' => 12800,
                'following_count' => 540,
                'post_count'      => 2310,
                'bio'             => 'Serikat Pekerja Seluruh Indonesia - Jawa Tengah',
                'category_id'     => 2,
                'region_id'       => 34, // Kota Semarang
                'is_active'       => true,
            ],
            [
                'platform'        => 'facebook',
                'username'        => 'walhi.jateng',
                'display_name'    => 'WALHI Jawa Tengah',
                'profile_url'     => 'https://facebook.com/walhi.jateng',
                'followers_count' => 28500,
                'following_count' => 180,
                'post_count'      => 3450,
                'bio'             => 'Wahana Lingkungan Hidup Indonesia - Jawa Tengah',
                'category_id'     => 3,
                'region_id'       => 34, // Kota Semarang
                'is_active'       => true,
            ],
            [
                'platform'        => 'youtube',
                'username'        => 'JawaPosTV',
                'display_name'    => 'Jawa Pos TV Semarang',
                'profile_url'     => 'https://youtube.com/@JawaPosTV',
                'followers_count' => 98700,
                'following_count' => 0,
                'post_count'      => 1250,
                'bio'             => 'Berita terkini Jawa Tengah dari Jawa Pos',
                'category_id'     => 4,
                'region_id'       => 34, // Kota Semarang
                'is_active'       => true,
            ],
            [
                'platform'        => 'instagram',
                'username'        => 'nu_jateng',
                'display_name'    => 'Nahdlatul Ulama Jawa Tengah',
                'profile_url'     => 'https://instagram.com/nu_jateng',
                'followers_count' => 67300,
                'following_count' => 420,
                'post_count'      => 5600,
                'bio'             => 'Akun resmi PWNU Jawa Tengah',
                'category_id'     => 5,
                'region_id'       => 34, // Kota Semarang
                'is_active'       => true,
            ],
            [
                'platform'        => 'instagram',
                'username'        => 'bem_ums_solo',
                'display_name'    => 'BEM UMS Surakarta',
                'profile_url'     => 'https://instagram.com/bem_ums_solo',
                'followers_count' => 23100,
                'following_count' => 278,
                'post_count'      => 621,
                'bio'             => 'BEM Universitas Muhammadiyah Surakarta',
                'category_id'     => 1,
                'region_id'       => 35, // Kota Surakarta
                'is_active'       => true,
            ],
            [
                'platform'        => 'tiktok',
                'username'        => 'solopos_update',
                'display_name'    => 'Solopos Update',
                'profile_url'     => 'https://tiktok.com/@solopos_update',
                'followers_count' => 142000,
                'following_count' => 85,
                'post_count'      => 980,
                'bio'             => 'Berita Solo dan Jawa Tengah',
                'category_id'     => 4,
                'region_id'       => 35, // Kota Surakarta
                'is_active'       => true,
            ],
            [
                'platform'        => 'facebook',
                'username'        => 'lksbhi.semarang',
                'display_name'    => 'LKS BHI Semarang',
                'profile_url'     => 'https://facebook.com/lksbhi.semarang',
                'followers_count' => 8900,
                'following_count' => 320,
                'post_count'      => 1120,
                'bio'             => 'Lembaga Kajian dan Studi Buruh & HAM Indonesia',
                'category_id'     => 2,
                'region_id'       => 7,  // Kabupaten Cilacap
                'is_active'       => true,
            ],
            [
                'platform'        => 'instagram',
                'username'        => 'yayasan_samin_blora',
                'display_name'    => 'Yayasan Samin Blora',
                'profile_url'     => 'https://instagram.com/yayasan_samin_blora',
                'followers_count' => 5400,
                'following_count' => 190,
                'post_count'      => 430,
                'bio'             => 'Pelestarian budaya dan kearifan lokal Blora',
                'category_id'     => 3,
                'region_id'       => 4,  // Kabupaten Blora
                'is_active'       => true,
            ],
            [
                'platform'        => 'twitter',
                'username'        => 'pbnu_kudus',
                'display_name'    => 'PCNU Kudus',
                'profile_url'     => 'https://twitter.com/pbnu_kudus',
                'followers_count' => 9800,
                'following_count' => 450,
                'post_count'      => 1870,
                'bio'             => 'Pengurus Cabang Nahdlatul Ulama Kudus',
                'category_id'     => 5,
                'region_id'       => 15, // Kabupaten Kudus
                'is_active'       => true,
            ],
            [
                'platform'        => 'instagram',
                'username'        => 'bem_unsoed_purwokerto',
                'display_name'    => 'BEM UNSOED Purwokerto',
                'profile_url'     => 'https://instagram.com/bem_unsoed_purwokerto',
                'followers_count' => 31500,
                'following_count' => 195,
                'post_count'      => 712,
                'bio'             => 'BEM Universitas Jenderal Soedirman Purwokerto',
                'category_id'     => 1,
                'region_id'       => 2,  // Kabupaten Banyumas
                'is_active'       => true,
            ],
            [
                'platform'        => 'youtube',
                'username'        => 'cilacap_news',
                'display_name'    => 'Cilacap News TV',
                'profile_url'     => 'https://youtube.com/@cilacap_news',
                'followers_count' => 34500,
                'following_count' => 0,
                'post_count'      => 560,
                'bio'             => 'Media berita online Cilacap dan sekitarnya',
                'category_id'     => 4,
                'region_id'       => 7,  // Kabupaten Cilacap
                'is_active'       => true,
            ],
            [
                'platform'        => 'facebook',
                'username'        => 'komunitas.petani.kebumen',
                'display_name'    => 'Komunitas Petani Kebumen',
                'profile_url'     => 'https://facebook.com/komunitas.petani.kebumen',
                'followers_count' => 7200,
                'following_count' => 510,
                'post_count'      => 980,
                'bio'             => 'Wadah aspirasi dan informasi petani Kebumen',
                'category_id'     => 2,
                'region_id'       => 12, // Kabupaten Kebumen
                'is_active'       => true,
            ],
            [
                'platform'        => 'tiktok',
                'username'        => 'pati_raya_updates',
                'display_name'    => 'Pati Raya Updates',
                'profile_url'     => 'https://tiktok.com/@pati_raya_updates',
                'followers_count' => 58000,
                'following_count' => 120,
                'post_count'      => 345,
                'bio'             => 'Informasi viral Pati dan sekitarnya',
                'category_id'     => 4,
                'region_id'       => 17, // Kabupaten Pati
                'is_active'       => true,
            ],
            [
                'platform'        => 'instagram',
                'username'        => 'muhammadiyah_temanggung',
                'display_name'    => 'PDM Temanggung',
                'profile_url'     => 'https://instagram.com/muhammadiyah_temanggung',
                'followers_count' => 6700,
                'following_count' => 290,
                'post_count'      => 520,
                'bio'             => 'Pimpinan Daerah Muhammadiyah Temanggung',
                'category_id'     => 5,
                'region_id'       => 27, // Kabupaten Temanggung
                'is_active'       => true,
            ],
            [
                'platform'        => 'twitter',
                'username'        => 'lpbi_jepara',
                'display_name'    => 'LPBI Jepara',
                'profile_url'     => 'https://twitter.com/lpbi_jepara',
                'followers_count' => 4300,
                'following_count' => 380,
                'post_count'      => 760,
                'bio'             => 'Lembaga Pemberdayaan Buruh dan Industri Jepara',
                'category_id'     => 2,
                'region_id'       => 10, // Kabupaten Jepara
                'is_active'       => true,
            ],
            [
                'platform'        => 'instagram',
                'username'        => 'bem_untidar_magelang',
                'display_name'    => 'BEM UNTIDAR Magelang',
                'profile_url'     => 'https://instagram.com/bem_untidar_magelang',
                'followers_count' => 18900,
                'following_count' => 210,
                'post_count'      => 490,
                'bio'             => 'BEM Universitas Tidar Magelang',
                'category_id'     => 1,
                'region_id'       => 30, // Kota Magelang
                'is_active'       => true,
            ],
            [
                'platform'        => 'facebook',
                'username'        => 'lkm.wonogiri',
                'display_name'    => 'LKM Wonogiri Berdaya',
                'profile_url'     => 'https://facebook.com/lkm.wonogiri',
                'followers_count' => 3800,
                'following_count' => 260,
                'post_count'      => 320,
                'bio'             => 'Lembaga Kemandirian Masyarakat Wonogiri',
                'category_id'     => 3,
                'region_id'       => 28, // Kabupaten Wonogiri
                'is_active'       => true,
            ],
            [
                'platform'        => 'tiktok',
                'username'        => 'semarang_viral',
                'display_name'    => 'Semarang Viral',
                'profile_url'     => 'https://tiktok.com/@semarang_viral',
                'followers_count' => 215000,
                'following_count' => 98,
                'post_count'      => 1230,
                'bio'             => 'Konten viral dan berita Semarang',
                'category_id'     => 4,
                'region_id'       => 23, // Kabupaten Semarang
                'is_active'       => true,
            ],
            [
                'platform'        => 'instagram',
                'username'        => 'fspmi_tegal',
                'display_name'    => 'FSPMI Tegal Raya',
                'profile_url'     => 'https://instagram.com/fspmi_tegal',
                'followers_count' => 11200,
                'following_count' => 430,
                'post_count'      => 870,
                'bio'             => 'Federasi Serikat Pekerja Metal Indonesia - Tegal',
                'category_id'     => 2,
                'region_id'       => 36, // Kota Tegal
                'is_active'       => true,
            ],
        ];

        DB::table('social_accounts')->insert(array_map(function ($acc) {
            return array_merge([
                'profile_picture' => null,
                'notes'           => null,
            ], $acc, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, $accounts));
    }
}
