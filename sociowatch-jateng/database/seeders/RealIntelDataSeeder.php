<?php

namespace Database\Seeders;

use App\Models\AccountAdmin;
use App\Models\Category;
use App\Models\FollowerSnapshot;
use App\Models\Keyword;
use App\Models\Post;
use App\Models\PostTag;
use App\Models\Region;
use App\Models\SocialAccount;
use App\Services\KeywordDetectionService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RealIntelDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Pastikan Keyword penting terdaftar
            $newKeywords = [
                ['word' => 'krisis', 'category' => 'sensitif', 'color' => '#ef4444'],
                ['word' => 'karhutla', 'category' => 'sensitif', 'color' => '#ef4444'],
                ['word' => 'kamisan', 'category' => 'sensitif', 'color' => '#ef4444'],
                ['word' => 'buruh', 'category' => 'sensitif', 'color' => '#f97316'],
                ['word' => 'petani', 'category' => 'sensitif', 'color' => '#f97316'],
                ['word' => 'upah', 'category' => 'sensitif', 'color' => '#f97316'],
                ['word' => 'penindasan', 'category' => 'negatif', 'color' => '#ea580c'],
                ['word' => 'ketidakadilan', 'category' => 'negatif', 'color' => '#ea580c'],
                ['word' => 'agraria', 'category' => 'sensitif', 'color' => '#f97316'],
                ['word' => 'aksi', 'category' => 'sensitif', 'color' => '#ef4444'],
                ['word' => 'banner', 'category' => 'sensitif', 'color' => '#ef4444'],
            ];

            foreach ($newKeywords as $kw) {
                Keyword::firstOrCreate(
                    ['word' => $kw['word']],
                    ['category' => $kw['category'], 'color' => $kw['color'], 'is_active' => true]
                );
            }

            $catMahasiswa = Category::where('name', 'Mahasiswa')->firstOrFail();
            $catBuruh     = Category::where('name', 'Buruh')->firstOrFail();
            $catLsm       = Category::where('name', 'LSM')->firstOrFail();
            $catOrmas     = Category::where('name', 'Ormas')->firstOrFail();

            $regKotaSemarang = Region::where('name', 'Kota Semarang')->firstOrFail();
            $regKabSemarang  = Region::where('name', 'Kabupaten Semarang')->firstOrFail();
            $regWonosobo     = Region::where('name', 'Kabupaten Wonosobo')->firstOrFail();
            $regKotaMagelang = Region::where('name', 'Kota Magelang')->firstOrFail();
            $regBanyumas     = Region::where('name', 'Kabupaten Banyumas')->firstOrFail();
            $regPati         = Region::where('name', 'Kabupaten Pati')->firstOrFail();

            $detector = new KeywordDetectionService();

            // =========================================================================
            // AKUN 1: sospol.bemkmudinus
            // =========================================================================
            $acc1 = SocialAccount::updateOrCreate(
                ['platform' => 'instagram', 'username' => 'sospol.bemkmudinus'],
                [
                    'display_name'    => 'BEM KM UDINUS - Kementrian Sospol',
                    'profile_url'     => 'https://www.instagram.com/sospol.bemkmudinus/',
                    'followers_count' => 206,
                    'following_count' => 150,
                    'post_count'      => 25,
                    'bio'             => 'Kementerian Sosial & Politik BEM KM Universitas Dian Nuswantoro. Ruang eskalasi gerakan mahasiswa & advokasi kebijakan publik.',
                    'category_id'     => $catMahasiswa->id,
                    'region_id'       => $regKotaSemarang->id,
                    'is_active'       => true,
                    'auto_fetch'      => false,
                    'notes'           => 'Dikelola secara kolektif oleh BEM KM UDINUS (indikasi admin lebih dari satu orang), tanpa mencantumkan identitas personal pengelola pada bio profil.',
                ]
            );

            AccountAdmin::updateOrCreate(
                ['social_account_id' => $acc1->id, 'full_name' => 'Firdaus Hakiki'],
                [
                    'alias'       => 'Menteri Sospol BEM KM UDINUS',
                    'nik'         => '3324163101040003',
                    'phone'       => null,
                    'email'       => null,
                    'occupation'  => 'Pelajar/Mahasiswa (Belum Bekerja)',
                    'affiliation' => 'BEM KM UDINUS / Sospol',
                    'notes'       => 'TTL: Kendal, 31-01-2004 | Status: Belum Kawin | Alamat: Dusun Tawang Tengah, Gempolsewu, Rowosari, Kendal.',
                ]
            );

            $this->createHistoricalSnapshots($acc1, 206);

            $post1 = Post::updateOrCreate(
                ['social_account_id' => $acc1->id, 'post_url' => 'https://www.instagram.com/p/Df33UGYkntI/?img_index=1'],
                [
                    'platform'         => 'instagram',
                    'external_id'      => 'Df33UGYkntI',
                    'content'          => '[KONTEN ADVOKASI LINGKUNGAN & PANGAN] Mengunggah Konten Provokatif terkait Karhutla dan Krisis Pangan Lokal. Menyoroti dampak kebakaran hutan dan lahan terhadap kedaulatan pangan masyarakat serta mendesak evaluasi kebijakan pemerintah.',
                    'media_type'       => 'image',
                    'likes_count'      => 45,
                    'comments_count'   => 12,
                    'shares_count'     => 8,
                    'views_count'      => 320,
                    'posted_at'        => Carbon::parse('2026-11-18 10:15:00'),
                    'is_flagged'       => true,
                    'flag_reason'      => 'Mengunggah Konten Provokatif terkait Karhutla dan Krisis Pangan Lokal',
                    'sentiment'        => 'sensitif',
                    'sentiment_manual' => true,
                ]
            );
            $this->attachTags($post1, ['Karhutla', 'Krisis Pangan', 'BEM UDINUS', 'Mahasiswa']);
            $post1->keywords()->sync($detector->detect($post1->content));

            // =========================================================================
            // AKUN 2: Muslimah Pelopor Perubahan Jateng Reborn (Facebook)
            // =========================================================================
            $acc2 = SocialAccount::updateOrCreate(
                ['platform' => 'facebook', 'username' => 'Muslimah Pelopor Perubahan Jateng Reborn'],
                [
                    'external_id'     => '61594123435748',
                    'display_name'    => 'Muslimah Pelopor Perubahan Jateng Reborn',
                    'profile_url'     => 'https://www.facebook.com/profile.php?id=61594123435748',
                    'followers_count' => 1300,
                    'following_count' => 80,
                    'post_count'      => 110,
                    'bio'             => 'Media opini dan dakwah muslimah Jawa Tengah. Suara perubahan hakiki melawan hegemoni oligarki dan perusakan alam.',
                    'category_id'     => $catOrmas->id,
                    'region_id'       => $regKabSemarang->id,
                    'is_active'       => true,
                    'auto_fetch'      => false,
                    'notes'           => 'Akun ormas/kelompok perempuan di Jawa Tengah, memuat propaganda dan narasi lingkungan/SDA bernuansa ideologis.',
                ]
            );

            AccountAdmin::updateOrCreate(
                ['social_account_id' => $acc2->id, 'full_name' => 'Lely Kusumastuti'],
                [
                    'alias'       => 'kusuma_astutyy',
                    'nik'         => '3322115512980001',
                    'phone'       => null,
                    'email'       => null,
                    'occupation'  => 'Pelajar/Mahasiswa',
                    'affiliation' => 'Muslimah Pelopor Perubahan Jateng Reborn',
                    'notes'       => 'KK: 3322112908080363 | TTL: Kab. Semarang, 15-12-1998 | Status: Belum Kawin | Agama: Islam | Alamat: Ling. Berokan RT 002, Bawen, Semarang, Jawa Tengah | Akun Instagram: https://www.instagram.com/kusuma_astutyy/',
                ]
            );

            $this->createHistoricalSnapshots($acc2, 1300);

            $post2 = Post::updateOrCreate(
                ['social_account_id' => $acc2->id, 'post_url' => 'https://www.facebook.com/permalink.php?story_fbid=pfbid02sgwCMbJg2U9aDHTNtZpvAjJ9JoKpXtVaHkt98ZoqheHp4L3adi2QVjZ1XVfopj6Rl&id=61594123435748'],
                [
                    'platform'         => 'facebook',
                    'external_id'      => 'pfbid02sgwCMbJg2U9aDHTNtZpvAjJ9JoKpXtVaHkt98ZoqheHp4L3adi2QVjZ1XVfopj6Rl',
                    'content'          => 'Konten Provokatif terkait SDA Dikuasa Kepentingan, Alam dan Rakyat Menjadi Korban. Kritik keras atas privatisasi sumber daya alam dan penderitaan rakyat akibat eksploitasi lingkungan tambang dan hutan.',
                    'media_type'       => 'text',
                    'likes_count'      => 89,
                    'comments_count'   => 34,
                    'shares_count'     => 21,
                    'views_count'      => 840,
                    'posted_at'        => Carbon::parse('2026-11-18 14:20:00'),
                    'is_flagged'       => true,
                    'flag_reason'      => 'Mengunggah Konten Provokatif terkait SDA Dikuasa Kepentingan, Alam dan Rakyat Menjadi Korban',
                    'sentiment'        => 'sensitif',
                    'sentiment_manual' => true,
                ]
            );
            $this->attachTags($post2, ['SDA', 'Lingkungan', 'Ormas', 'Jateng Reborn']);
            $post2->keywords()->sync($detector->detect($post2->content));

            // =========================================================================
            // AKUN 3: kamisanwsb
            // =========================================================================
            $acc3 = SocialAccount::updateOrCreate(
                ['platform' => 'instagram', 'username' => 'kamisanwsb'],
                [
                    'display_name'    => 'Aksi Kamisan Wonosobo',
                    'profile_url'     => 'https://www.instagram.com/kamisanwsb/',
                    'followers_count' => 480,
                    'following_count' => 120,
                    'post_count'      => 65,
                    'bio'             => 'Kolektif Aksi Kamisan Wonosobo. Menolak lupa, merawat ingatan, menuntut keadilan pelanggaran HAM & ruang hidup rakyat.',
                    'category_id'     => $catLsm->id,
                    'region_id'       => $regWonosobo->id,
                    'is_active'       => true,
                    'auto_fetch'      => false,
                    'notes'           => 'Kolektif Aksi Kamisan wilayah Wonosobo, rutin menyerukan aksi kamisan dan isu pelanggaran HAM serta advokasi lingkungan.',
                ]
            );

            AccountAdmin::updateOrCreate(
                ['social_account_id' => $acc3->id, 'full_name' => 'Kolektif Kamisan Wonosobo'],
                [
                    'alias'       => 'Admin Kamisan Wsb',
                    'nik'         => null,
                    'phone'       => null,
                    'email'       => null,
                    'occupation'  => 'Aktivis Masyarakat Sipil',
                    'affiliation' => 'Jaringan Aksi Kamisan Jateng - Wonosobo',
                    'notes'       => 'Dikelola secara kolektif oleh aktivis HAM dan mahasiswa di Kabupaten Wonosobo.',
                ]
            );

            $this->createHistoricalSnapshots($acc3, 480);

            $post3 = Post::updateOrCreate(
                ['social_account_id' => $acc3->id, 'post_url' => 'https://www.instagram.com/p/Df6EsrExGD-/'],
                [
                    'platform'         => 'instagram',
                    'external_id'      => 'Df6EsrExGD-',
                    'content'          => 'Memposting Konten Provokatif terkait Seruan Aksi Kamisan ke #31 di Wonosobo. Mengajak konsolidasi massa aksi turun ke jalan menuntut penuntasan pelanggaran HAM dan solidaritas perlawanan rakyat.',
                    'media_type'       => 'image',
                    'likes_count'      => 76,
                    'comments_count'   => 15,
                    'shares_count'     => 19,
                    'views_count'      => 510,
                    'posted_at'        => Carbon::parse('2026-11-19 08:45:00'),
                    'is_flagged'       => true,
                    'flag_reason'      => 'Memposting Konten Provokatif terkait Seruan Aksi Kamisan ke #31',
                    'sentiment'        => 'sensitif',
                    'sentiment_manual' => true,
                ]
            );
            $this->attachTags($post3, ['Aksi Kamisan', 'Wonosobo', 'Aksi Kamisan #31', 'HAM', 'Unjuk Rasa']);
            $post3->keywords()->sync($detector->detect($post3->content));

            // =========================================================================
            // AKUN 4: sangkar.mgl
            // =========================================================================
            $acc4 = SocialAccount::updateOrCreate(
                ['platform' => 'instagram', 'username' => 'sangkar.mgl'],
                [
                    'display_name'    => 'Sangkar Magelang',
                    'profile_url'     => 'https://www.instagram.com/sangkar.mgl/',
                    'followers_count' => 622,
                    'following_count' => 215,
                    'post_count'      => 42,
                    'bio'             => 'Ruang alternatif pergerakan & propaganda kreatif pemuda Magelang. Bersama membentangkan narasi perlawanan rakyat.',
                    'category_id'     => $catLsm->id,
                    'region_id'       => $regKotaMagelang->id,
                    'is_active'       => true,
                    'auto_fetch'      => false,
                    'notes'           => 'Anteseden: Dikelola secara kolektif oleh sangkar.mgl (indikasi admin lebih dari satu orang, tanpa identitas personal pengelola). Terafiliasi dengan ruangjuang.mgl dan Aliansi Magelang Menggugat.',
                ]
            );

            AccountAdmin::updateOrCreate(
                ['social_account_id' => $acc4->id, 'full_name' => 'Enrille Championy Geniosa'],
                [
                    'alias'       => 'enrillegeniosa',
                    'nik'         => '3371023009020001',
                    'phone'       => null,
                    'email'       => null,
                    'occupation'  => 'Mahasiswa / Sarjana Hukum',
                    'affiliation' => 'Ketua BEM KM UNTIDAR Tahun 2023, Tergabung dalam Aliansi Magelang Menggugat, Ruang Juang Magelang',
                    'notes'       => 'TTL: Magelang, 30-09-2002 | Agama: Islam | Alamat: Jl. Serayu Raya No. 10 A, Kel. Kedungsari, Kec. Magelang Utara, Kota Magelang, Jawa Tengah | Riwayat Pendidikan: S1 Hukum Universitas Tidar | Instagram: instagram.com/enrillegeniosa | LinkedIn: id.linkedin.com/in/enrille-championy-geniosa-s-h-710977246',
                ]
            );

            $this->createHistoricalSnapshots($acc4, 622);

            $post4 = Post::updateOrCreate(
                ['social_account_id' => $acc4->id, 'post_url' => 'https://www.instagram.com/p/Df00eFRIyHn/'],
                [
                    'platform'         => 'instagram',
                    'external_id'      => 'Df00eFRIyHn',
                    'content'          => '[RENCANA AKSI BENTANG BANNER] Narasi Visual memperlihatkan Kamisan Magelang dengan kegiatan bentang banner pada 19 November 2026, jam 16.00 WIB, di Alun-alun Kota Magelang, dengan caption: "Ko biasa ae, Lehhh. ko sopo ae!!!" Rencana aksi unjuk rasa pembentangan banner di ruang publik terbuka.',
                    'media_type'       => 'image',
                    'likes_count'      => 115,
                    'comments_count'   => 28,
                    'shares_count'     => 35,
                    'views_count'      => 980,
                    'posted_at'        => Carbon::parse('2026-11-19 14:10:00'),
                    'is_flagged'       => true,
                    'flag_reason'      => 'Rencana Kegiatan Aksi Pembentangan Banner di Alun - alun Kota Magelang (Kamisan Magelang 19 Nov 2026)',
                    'sentiment'        => 'sensitif',
                    'sentiment_manual' => true,
                ]
            );
            $this->attachTags($post4, ['Bentang Banner', 'Kamisan Magelang', 'Alun-alun Magelang', 'Aksi']);
            $post4->keywords()->sync($detector->detect($post4->content));

            // =========================================================================
            // AKUN 5: bem_unsoed
            // =========================================================================
            $acc5 = SocialAccount::updateOrCreate(
                ['platform' => 'instagram', 'username' => 'bem_unsoed'],
                [
                    'display_name'    => 'BEM Universitas Jenderal Soedirman',
                    'profile_url'     => 'https://www.instagram.com/bem_unsoed/',
                    'followers_count' => 1871,
                    'following_count' => 142,
                    'post_count'      => 320,
                    'bio'             => 'Akun resmi Badan Eksekutif Mahasiswa Universitas Jenderal Soedirman (BEM Unsoed). Inisiator Gerakan Moral dan Intelektual Mahasiswa Soedirman.',
                    'category_id'     => $catMahasiswa->id,
                    'region_id'       => $regBanyumas->id,
                    'is_active'       => true,
                    'auto_fetch'      => false,
                    'notes'           => 'Anteseden / Kepemilikan Akun: Dikelola secara kolektif oleh bem_unsoed (indikasi admin lebih dari satu orang), tanpa mencantumkan identitas personal pengelola.',
                ]
            );

            AccountAdmin::updateOrCreate(
                ['social_account_id' => $acc5->id, 'full_name' => 'Azza Febra Pramudika'],
                [
                    'alias'       => 'ajjafbra (Presiden BEM Unsoed)',
                    'nik'         => '3301232702040003',
                    'phone'       => null,
                    'email'       => null,
                    'occupation'  => 'Pelajar/Mahasiswa (Presiden BEM Unsoed)',
                    'affiliation' => 'BEM KM Universitas Jenderal Soedirman (Unsoed)',
                    'notes'       => 'NKK: 3301231310080001 | TTL: Cilacap, 27-02-2004 | Gender: Laki-Laki | Status: Belum Kawin | Agama: Islam | Alamat: Jl. Lengkong RT 003/013, Mertasinga, Cilacap Utara, Cilacap, Jawa Tengah | Nama Ibu: Sri Supriyanti | Nama Ayah: Anjar Supriyatin | Akun Medsos: https://www.instagram.com/ajjafbra/',
                ]
            );

            $this->createHistoricalSnapshots($acc5, 1871);

            $post5 = Post::updateOrCreate(
                ['social_account_id' => $acc5->id, 'post_url' => 'https://www.instagram.com/p/DfyVgQkgWHO/'],
                [
                    'platform'         => 'instagram',
                    'external_id'      => 'DfyVgQkgWHO',
                    'content'          => '[SENGAJA ATAU TIDAK? TERFRAGMENTASINYA MASYARAKAT] Halo, Gensoed! Hidup Mahasiswa! Hidup Rakyat Indonesia! Hidup Perempuan yang Melawan! Mengunggah Konten Provokatif terkait Isu Ketidakadilan, Penindasan, dan Terfragmentasinya Masyarakat pada Pemerintahan Prabowo–Gibran.',
                    'media_type'       => 'image',
                    'likes_count'      => 482,
                    'comments_count'   => 87,
                    'shares_count'     => 140,
                    'views_count'      => 3420,
                    'posted_at'        => Carbon::parse('2026-11-18 17:00:00'),
                    'is_flagged'       => true,
                    'flag_reason'      => 'Mengunggah Konten Provokatif terkait Isu Ketidakadilan, Penindasan, dan Terfragmentasinya Masyarakat pada Pemerintahan Prabowo–Gibran',
                    'sentiment'        => 'sensitif',
                    'sentiment_manual' => true,
                ]
            );
            $this->attachTags($post5, ['BEM Unsoed', 'Prabowo-Gibran', 'Terfragmentasi', 'Mahasiswa', 'Ketidakadilan']);
            $post5->keywords()->sync($detector->detect($post5->content));

            // =========================================================================
            // AKUN 6: petani.pundenrejo
            // =========================================================================
            $acc6 = SocialAccount::updateOrCreate(
                ['platform' => 'instagram', 'username' => 'petani.pundenrejo'],
                [
                    'display_name'    => 'Gerakan Petani Pundenrejo Pati',
                    'profile_url'     => 'https://www.instagram.com/petani.pundenrejo/',
                    'followers_count' => 740,
                    'following_count' => 180,
                    'post_count'      => 52,
                    'bio'             => 'Perjuangan Hak Atas Tanah Petani Desa Pundenrejo, Tayu, Pati. Menuntut pemulihan hak tanah leluhur dari HGB PT. LPI.',
                    'category_id'     => $catBuruh->id,
                    'region_id'       => $regPati->id,
                    'is_active'       => true,
                    'auto_fetch'      => false,
                    'notes'           => 'Akun wadah perjuangan agraria petani Pundenrejo Pati, rutin menyuarakan konflik sengketa lahan dengan PT. LPI dan menuntut reforma agraria sejati.',
                ]
            );

            AccountAdmin::updateOrCreate(
                ['social_account_id' => $acc6->id, 'full_name' => 'Paguyuban Masyarakat Petani Pundenrejo'],
                [
                    'alias'       => 'Advokasi Pundenrejo',
                    'nik'         => null,
                    'phone'       => null,
                    'email'       => null,
                    'occupation'  => 'Petani / Aktivis Agraria',
                    'affiliation' => 'Gerakan Masyarakat Peduli Tanah Pundenrejo (Pati)',
                    'notes'       => 'Didampingi oleh koalisi LBH Semarang dan jejaring aktivis agraria Jawa Tengah dalam sengketa lahan PT. LPI.',
                ]
            );

            $this->createHistoricalSnapshots($acc6, 740);

            $post6 = Post::updateOrCreate(
                ['social_account_id' => $acc6->id, 'post_url' => 'https://www.instagram.com/reels/Df0tfknTIuu/'],
                [
                    'platform'         => 'instagram',
                    'external_id'      => 'Df0tfknTIuu',
                    'content'          => '[VIDEO REEL PERNYATAAN SIKAP] Posting konten Provokatif terkait Pernyataan Sikap Petani Pundenrejo dalam Menyikapi Izin PT.LPI. Sikap penolakan terhadap aktivitas perusahaan perkebunan dan seruan mempertahankan ruang hidup tani.',
                    'media_type'       => 'reel',
                    'likes_count'      => 164,
                    'comments_count'   => 31,
                    'shares_count'     => 42,
                    'views_count'      => 1250,
                    'posted_at'        => Carbon::parse('2026-11-19 10:45:00'),
                    'is_flagged'       => true,
                    'flag_reason'      => 'Posting konten Provokatif terkait Pernyataan Sikap Petani Pundenrejo dalam Menyikapi Izin PT.LPI',
                    'sentiment'        => 'sensitif',
                    'sentiment_manual' => true,
                ]
            );
            $this->attachTags($post6, ['Petani Pundenrejo', 'PT LPI', 'Konflik Agraria', 'Pati', 'Reforma Agraria']);
            $post6->keywords()->sync($detector->detect($post6->content));

            // =========================================================================
            // AKUN 7: lbhsemarang
            // =========================================================================
            $acc7 = SocialAccount::updateOrCreate(
                ['platform' => 'instagram', 'username' => 'lbhsemarang'],
                [
                    'display_name'    => 'LBH Semarang (Lembaga Bantuan Hukum)',
                    'profile_url'     => 'https://www.instagram.com/lbhsemarang/',
                    'followers_count' => 9850,
                    'following_count' => 420,
                    'post_count'      => 640,
                    'bio'             => 'Lembaga Bantuan Hukum Semarang (YLBHI). Bantuan hukum struktural bagi masyarakat miskin, buruh, dan tertindas di Jawa Tengah.',
                    'category_id'     => $catLsm->id,
                    'region_id'       => $regKotaSemarang->id,
                    'is_active'       => true,
                    'auto_fetch'      => false,
                    'notes'           => 'LSM bantuan hukum struktural paling aktif di Jateng, menjadi simpul advokasi berbagai isu perburuhan, lingkungan, agraria, dan advokasi buruh.',
                ]
            );

            AccountAdmin::updateOrCreate(
                ['social_account_id' => $acc7->id, 'full_name' => 'Tim Media & Advokasi LBH Semarang'],
                [
                    'alias'       => 'Divisi Advokasi Buruh & Rakyat',
                    'nik'         => null,
                    'phone'       => null,
                    'email'       => null,
                    'occupation'  => 'Pengacara Publik & Advokat HAM',
                    'affiliation' => 'LBH Semarang / YLBHI',
                    'notes'       => 'Dikelola secara institusional oleh tim pengacara publik dan advokat hak asasi manusia LBH Semarang.',
                ]
            );

            $this->createHistoricalSnapshots($acc7, 9850);

            $post7 = Post::updateOrCreate(
                ['social_account_id' => $acc7->id, 'post_url' => 'https://www.instagram.com/p/Df1sirby2ND/'],
                [
                    'platform'         => 'instagram',
                    'external_id'      => 'Df1sirby2ND',
                    'content'          => 'Memposting konten rencana diskusi terkait Hasil Riset dengan tema: "Asal ada Nasi, yang penting kenyang, berbagai siasat buruh bertahan di tengah politik upah murah". Mengkritisi kebijakan upah murah dan penindasan buruh di Jawa Tengah.',
                    'media_type'       => 'image',
                    'likes_count'      => 342,
                    'comments_count'   => 56,
                    'shares_count'     => 78,
                    'views_count'      => 2190,
                    'posted_at'        => Carbon::parse('2026-11-19 12:30:00'),
                    'is_flagged'       => true,
                    'flag_reason'      => 'Memposting konten rencana diskusi terkait Hasil Riset upah buruh murah di Jawa Tengah',
                    'sentiment'        => 'negatif',
                    'sentiment_manual' => true,
                ]
            );
            $this->attachTags($post7, ['LBH Semarang', 'Upah Murah', 'Buruh', 'Diskusi Publik', 'Advokasi']);
            $post7->keywords()->sync($detector->detect($post7->content));
        });
    }

    private function attachTags(Post $post, array $tagNames): void
    {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tag = PostTag::firstOrCreate(
                ['name' => $name],
                ['color' => '#' . substr(md5($name), 0, 6)]
            );
            $tagIds[] = $tag->id;
        }
        $post->tags()->sync($tagIds);
    }

    private function createHistoricalSnapshots(SocialAccount $account, int $currentFollowers): void
    {
        $intervals = [30, 20, 14, 7, 3, 1, 0];
        foreach ($intervals as $daysAgo) {
            $growthFactor = 1 - ($daysAgo * 0.004);
            $followers    = (int) round($currentFollowers * max(0.85, $growthFactor));
            FollowerSnapshot::updateOrCreate(
                [
                    'social_account_id' => $account->id,
                    'recorded_at'       => Carbon::now()->subDays($daysAgo)->setTime(0, 0, 0),
                ],
                [
                    'followers_count' => $followers,
                    'following_count' => $account->following_count ?? 100,
                    'post_count'      => max(1, ($account->post_count ?? 10) - (int) ($daysAgo / 3)),
                ]
            );
        }
    }
}
