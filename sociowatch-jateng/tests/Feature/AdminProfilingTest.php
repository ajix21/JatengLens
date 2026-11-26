<?php

namespace Tests\Feature;

use App\Models\AccountAdmin;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProfilingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_has_new_profiling_columns_and_wa_format(): void
    {
        $account = SocialAccount::factory()->create();

        $admin = AccountAdmin::create([
            'social_account_id' => $account->id,
            'full_name'         => 'Ahmad Fauzi',
            'alias'             => 'Fauzi',
            'nik'               => '3374011234567890',
            'no_kk'             => '3374019876543210',
            'status'            => 'Lajang',
            'agama'             => 'Islam',
            'ttl'               => 'Semarang, 15 Juli 1999',
            'occupation'        => 'Aktivis / Mahasiswa',
            'alamat'            => 'Jl. Tembalang Baru No. 10, Kota Semarang',
            'phone'             => '081234567890',
            'email'             => 'fauzi@example.com',
            'affiliation'       => 'BEM KM Jateng',
            'notes'             => 'Koordinator Aksi Advokasi Kebijakan Publik',
        ]);

        $this->assertDatabaseHas('account_admins', [
            'id'     => $admin->id,
            'no_kk'  => '3374019876543210',
            'status' => 'Lajang',
            'agama'  => 'Islam',
            'ttl'    => 'Semarang, 15 Juli 1999',
            'alamat' => 'Jl. Tembalang Baru No. 10, Kota Semarang',
        ]);

        $waText = $admin->toWhatsAppText();

        $this->assertStringContainsString('* Nama: Ahmad Fauzi (Alias: Fauzi)', $waText);
        $this->assertStringContainsString('* NIK: 3374011234567890', $waText);
        $this->assertStringContainsString('* No KK: 3374019876543210', $waText);
        $this->assertStringContainsString('* Status: Lajang', $waText);
        $this->assertStringContainsString('* Agama: Islam', $waText);
        $this->assertStringContainsString('* TTL: Semarang, 15 Juli 1999', $waText);
        $this->assertStringContainsString('* Pekerjaan: Aktivis / Mahasiswa', $waText);
        $this->assertStringContainsString('* Alamat: Jl. Tembalang Baru No. 10, Kota Semarang', $waText);
        $this->assertStringContainsString('* No HP/WA: 081234567890', $waText);
        $this->assertStringContainsString('* Catatan: Koordinator Aksi Advokasi Kebijakan Publik', $waText);
    }

    public function test_account_show_renders_admin_profiling_and_copy_button(): void
    {
        $user = User::factory()->create(['role' => 'superadmin']);
        $account = SocialAccount::factory()->create();

        AccountAdmin::create([
            'social_account_id' => $account->id,
            'full_name'         => 'Siti Rahma',
            'nik'               => '3374098765432100',
            'no_kk'             => '3374091122334455',
            'status'            => 'Menikah',
            'agama'             => 'Islam',
            'ttl'               => 'Kudus, 20 Agustus 1995',
            'occupation'        => 'Staf Hukum',
            'alamat'            => 'Jl. Pemuda No. 45, Semarang',
            'notes'             => 'Admin teknis publikasi konten media',
        ]);

        $response = $this->actingAs($user)->get(route('accounts.show', $account));

        $response->assertOk();
        $response->assertSee('Siti Rahma');
        $response->assertSee('Salin Format WA');
        $response->assertSee('No KK:');
        $response->assertSee('3374091122334455');
        $response->assertSee('Status:');
        $response->assertSee('Menikah');
        $response->assertSee('Agama:');
        $response->assertSee('TTL:');
        $response->assertSee('Kudus, 20 Agustus 1995');
        $response->assertSee('Alamat:');
        $response->assertSee('Jl. Pemuda No. 45, Semarang');
        $response->assertSee('Catatan:');
        $response->assertDontSee('Catatan Intelijen Profiling:');
    }

    public function test_admin_photo_upload_display_update_and_delete(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $account = SocialAccount::factory()->create();

        // 1. Store admin with photo
        $file = UploadedFile::fake()->image('admin-avatar.jpg', 200, 200);
        $this->actingAs($user)->post(route('admins.store'), [
            'social_account_id' => $account->id,
            'full_name'         => 'Budi Santoso',
            'photo'             => $file,
        ])->assertRedirect(route('admins.index'));

        $admin = AccountAdmin::where('full_name', 'Budi Santoso')->firstOrFail();
        $this->assertNotNull($admin->photo);
        $this->assertStringStartsWith('admin-photos/', $admin->photo);
        Storage::disk('public')->assertExists($admin->photo);

        // 2. Photo appears in admins.index and accounts.show
        $this->actingAs($user)->get(route('admins.index'))
            ->assertOk()
            ->assertSee('Foto')
            ->assertSee($admin->photo_url);

        $this->actingAs($user)->get(route('accounts.show', $account))
            ->assertOk()
            ->assertSee($admin->photo_url);

        // 3. Remove photo via update
        $oldPath = $admin->photo;
        $this->actingAs($user)->put(route('admins.update', $admin), [
            'social_account_id' => $account->id,
            'full_name'         => 'Budi Santoso',
            'remove_photo'      => '1',
        ])->assertRedirect(route('admins.index'));

        $admin->refresh();
        $this->assertNull($admin->photo);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_photo_rejects_disguised_script_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $account = SocialAccount::factory()->create();

        $probePayload = '<' . '?php echo "security-probe"; ?' . '>';
        $fakeFile = UploadedFile::fake()->createWithContent('avatar.php.jpg', $probePayload);

        $this->actingAs($user)->post(route('admins.store'), [
            'social_account_id' => $account->id,
            'full_name'         => 'Attacker Disguised',
            'photo'             => $fakeFile,
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('account_admins', ['full_name' => 'Attacker Disguised']);
        $this->assertEmpty(Storage::disk('public')->files('admin-photos'));
    }

    public function test_admin_photo_rejects_polyglot_jpeg_with_embedded_php_tag(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $account = SocialAccount::factory()->create();

        $cleanJpg = UploadedFile::fake()->image('clean.jpg', 120, 120);
        $probeTag = "\n" . '<' . '?php echo "polyglot-probe"; ?' . '>';
        $polyglotFile = UploadedFile::fake()->createWithContent(
            'polyglot.jpg',
            file_get_contents($cleanJpg->getRealPath()) . $probeTag
        );

        $this->actingAs($user)->post(route('admins.store'), [
            'social_account_id' => $account->id,
            'full_name'         => 'Attacker Polyglot',
            'photo'             => $polyglotFile,
        ])->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('account_admins', ['full_name' => 'Attacker Polyglot']);
        $this->assertEmpty(Storage::disk('public')->files('admin-photos'));
    }

    public function test_admin_photo_rejects_svg_and_oversized_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $account = SocialAccount::factory()->create();

        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><' . 'script>void(0)</' . 'script></svg>';
        $svg = UploadedFile::fake()->createWithContent('avatar.svg', $svgContent);

        $this->actingAs($user)->post(route('admins.store'), [
            'social_account_id' => $account->id,
            'full_name'         => 'Attacker SVG',
            'photo'             => $svg,
        ])->assertSessionHasErrors('photo');

        $oversized = UploadedFile::fake()->image('huge.jpg', 200, 200)->size(2500);

        $this->actingAs($user)->post(route('admins.store'), [
            'social_account_id' => $account->id,
            'full_name'         => 'Oversized Admin',
            'photo'             => $oversized,
        ])->assertSessionHasErrors('photo');

        $this->assertEmpty(Storage::disk('public')->files('admin-photos'));
    }

    public function test_admin_photo_reencodes_and_strips_trailing_bytes(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $account = SocialAccount::factory()->create();

        $cleanJpg = UploadedFile::fake()->image('my-secret-name.jpg', 150, 150);
        $marker = 'TRAILING_METADATA_MARKER_SHOULD_BE_STRIPPED';
        $jpgWithTrailing = UploadedFile::fake()->createWithContent(
            'my-secret-name.jpg',
            file_get_contents($cleanJpg->getRealPath()) . $marker
        );

        $this->actingAs($user)->post(route('admins.store'), [
            'social_account_id' => $account->id,
            'full_name'         => 'Admin Bersih',
            'photo'             => $jpgWithTrailing,
        ])->assertRedirect(route('admins.index'));

        $admin = AccountAdmin::where('full_name', 'Admin Bersih')->firstOrFail();
        $this->assertStringNotContainsString('my-secret-name', $admin->photo);
        $this->assertStringEndsWith('.jpg', $admin->photo);

        $storedContent = Storage::disk('public')->get($admin->photo);
        $this->assertStringNotContainsString($marker, $storedContent);
    }

    public function test_account_edit_form_supports_secure_admin_photo_upload_and_preserves_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'superadmin', 'is_active' => true]);
        $account = SocialAccount::factory()->create();

        // 1. Halaman edit akun menampilkan area drag & drop foto admin
        $this->actingAs($user)->get(route('accounts.edit', $account))
            ->assertOk()
            ->assertSee('Foto Admin')
            ->assertSee('drag &amp; drop', false);

        // 2. Upload foto admin lewat form Edit Akun
        $photo1 = UploadedFile::fake()->image('pengelola1.jpg', 180, 180);
        $this->actingAs($user)->put(route('accounts.update', $account), [
            'platform'     => $account->platform,
            'username'     => $account->username,
            'display_name' => $account->display_name,
            'category_id'  => $account->category_id,
            'region_id'    => $account->region_id,
            'admins'       => [
                [
                    'full_name' => 'Pengelola Pertama',
                    'photo'     => $photo1,
                ],
            ],
        ])->assertRedirect(route('accounts.show', $account));

        $admin = $account->admins()->firstOrFail();
        $this->assertNotNull($admin->photo);
        Storage::disk('public')->assertExists($admin->photo);
        $savedPhoto = $admin->photo;

        // 3. Edit akun lagi tanpa upload foto baru -> foto lama tetap tersimpan
        $this->actingAs($user)->put(route('accounts.update', $account), [
            'platform'     => $account->platform,
            'username'     => $account->username,
            'display_name' => $account->display_name,
            'category_id'  => $account->category_id,
            'region_id'    => $account->region_id,
            'admins'       => [
                [
                    'id'        => $admin->id,
                    'full_name' => 'Pengelola Pertama Diedit',
                ],
            ],
        ])->assertRedirect(route('accounts.show', $account));

        $admin->refresh();
        $this->assertSame('Pengelola Pertama Diedit', $admin->full_name);
        $this->assertSame($savedPhoto, $admin->photo);
        Storage::disk('public')->assertExists($savedPhoto);

        // 4. Hapus foto via checkbox remove_photo di Edit Akun
        $this->actingAs($user)->put(route('accounts.update', $account), [
            'platform'     => $account->platform,
            'username'     => $account->username,
            'display_name' => $account->display_name,
            'category_id'  => $account->category_id,
            'region_id'    => $account->region_id,
            'admins'       => [
                [
                    'id'           => $admin->id,
                    'full_name'    => 'Pengelola Pertama Diedit',
                    'remove_photo' => '1',
                ],
            ],
        ])->assertRedirect(route('accounts.show', $account));

        $admin->refresh();
        $this->assertNull($admin->photo);
        Storage::disk('public')->assertMissing($savedPhoto);
    }
}
