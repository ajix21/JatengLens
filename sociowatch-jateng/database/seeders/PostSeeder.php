<?php

namespace Database\Seeders;

use App\Models\Keyword;
use App\Models\Post;
use App\Models\PostTag;
use App\Models\SocialAccount;
use App\Services\KeywordDetectionService;
use Illuminate\Database\Seeder;

class PostSeeder extends Seeder
{
    private array $contents = [
        'Kami mendukung penuh kebijakan upah minimum yang berkeadilan untuk seluruh buruh Jawa Tengah. Solidaritas adalah kekuatan kita bersama!',
        'Rapat koordinasi bersama pengurus cabang hari ini membahas peraturan ketenagakerjaan terbaru. Akan kami sampaikan ke anggota segera.',
        'PERINGATAN: Ada indikasi manipulasi data upah di beberapa perusahaan. Kami sedang menginvestigasi dan akan melaporkan ke Disnaker.',
        'Prestasi membanggakan! 500 anggota kita berhasil mendapatkan beasiswa pendidikan lanjutan tahun ini. Terima kasih atas dukungan semua pihak.',
        'Demo besar-besaran dijadwalkan besok di depan kantor gubernur. Kami menuntut revisi kebijakan jam kerja yang tidak manusiawi.',
        'Unjuk rasa damai mahasiswa Jawa Tengah hari ini berlangsung tertib. Kami menyuarakan tolak kenaikan UKT yang memberatkan.',
        'Apresiasi setinggi-tingginya untuk Pemkot yang akhirnya memperhatikan nasib pekerja informal. Kebijakan ini sangat kami dukung!',
        'Laporan: Ditemukan kasus korupsi dana bantuan sosial di kabupaten setempat. Kami mendesak KPK untuk turun tangan.',
        'Mogok kerja massal terjadi di kawasan industri akibat PHK sepihak. Bergabunglah bersama kami dalam solidaritas pekerja!',
        'Berhasil mengadakan pelatihan keterampilan untuk 200 anggota pekerja. Terima kasih atas dukungan dan apresiasi dari berbagai pihak.',
        'Blokir akses media sosial tidak akan menghentikan perjuangan kami. Suara buruh harus tetap didengar!',
        'Rapat pleno membahas peraturan baru tentang cuti melahirkan. Kami mendukung penuh kebijakan yang pro-keluarga ini.',
        'Curang! Panitia pemilihan internal diduga melakukan kecurangan. Kami menuntut pemilihan ulang yang transparan.',
        'Keberhasilan program pemberdayaan petani di wilayah Jawa Tengah mendapat apresiasi dari Kementerian Pertanian.',
        'Bohong besar jika mereka bilang kondisi buruh di sini sudah baik. Kenyataannya banyak yang masih digaji di bawah UMR.',
        'Solidaritas mahasiswa bersama buruh dalam menolak RUU yang dianggap merugikan rakyat kecil. Bersatu kita teguh!',
        'Diskriminasi upah antara pekerja lokal dan pekerja dari luar daerah masih terjadi. Harus ada kebijakan yang tegas!',
        'Prestasi luar biasa! Media kami berhasil meraih penghargaan jurnalistik terbaik tingkat provinsi.',
        'Provokasi dari pihak tidak bertanggung jawab jangan sampai memecah belah kesatuan gerakan buruh kita.',
        'Kebijakan pemerintah terkait upah minimum regional 2026 sudah resmi ditetapkan. Simak detailnya di link berikut.',
    ];

    private array $mediaTags = [
        ['Ketenagakerjaan', '#3b82f6'],
        ['Mahasiswa',       '#8b5cf6'],
        ['Kebijakan',       '#f59e0b'],
        ['Aksi Sosial',     '#10b981'],
        ['Lingkungan',      '#06b6d4'],
    ];

    public function run(): void
    {
        $detector  = new KeywordDetectionService();
        $accounts  = SocialAccount::where('is_active', true)->get();

        // Ensure tags exist
        $tags = [];
        foreach ($this->mediaTags as [$name, $color]) {
            $tags[] = PostTag::firstOrCreate(['name' => $name], ['color' => $color]);
        }

        $mediaTypes = ['text', 'image', 'video', 'reel', 'story'];

        foreach ($accounts as $account) {
            $count = rand(5, 10);
            for ($i = 0; $i < $count; $i++) {
                $content    = $this->contents[array_rand($this->contents)];
                $postedAt   = now()->subDays(rand(0, 29))->subHours(rand(0, 23));

                $post = Post::create([
                    'social_account_id' => $account->id,
                    'platform'          => $account->platform,
                    'post_url'          => $account->profile_url ? $account->profile_url . '/post/' . rand(100000, 999999) : null,
                    'content'           => $content,
                    'media_type'        => $mediaTypes[array_rand($mediaTypes)],
                    'likes_count'       => rand(0, (int)($account->followers_count * 0.05)),
                    'comments_count'    => rand(0, (int)($account->followers_count * 0.01)),
                    'shares_count'      => rand(0, (int)($account->followers_count * 0.005)),
                    'views_count'       => rand(0, (int)($account->followers_count * 0.2)),
                    'posted_at'         => $postedAt,
                    'is_flagged'        => rand(0, 6) === 0,
                    'flag_reason'       => rand(0, 6) === 0 ? 'Konten sensitif terdeteksi' : null,
                ]);

                // Attach 1-2 tags
                $selectedTags = array_slice($tags, 0, rand(1, 2));
                $post->tags()->attach(collect($selectedTags)->pluck('id'));

                // Detect & attach keywords
                $detected = $detector->detect($content);
                if (!empty($detected)) {
                    $post->keywords()->sync($detected);
                }
            }
        }
    }
}
