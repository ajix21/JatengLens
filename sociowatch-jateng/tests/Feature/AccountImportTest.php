<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Region;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AccountImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_import_page_renders_successfully(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Category::factory()->create(['name' => 'Pemerintahan']);
        Region::factory()->create(['name' => 'Kota Semarang']);

        $res = $this->actingAs($user)->get(route('accounts.import'));

        $res->assertOk();
        $res->assertSee('Import Akun');
        $res->assertSee('Petunjuk Import');
        $res->assertSee('Download Template CSV');
    }

    public function test_accounts_import_template_downloads_successfully(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $res = $this->actingAs($user)->get(route('accounts.import.template'));

        $res->assertOk();
        $res->assertHeader('Content-Disposition', 'attachment; filename="template_import_akun.csv"');
    }

    public function test_accounts_import_processes_valid_csv(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $cat  = Category::factory()->create(['name' => 'Pemerintahan']);
        $reg  = Region::factory()->create(['name' => 'Kota Semarang']);

        $csvContent = implode("\n", [
            'platform,username,display_name,profile_url,followers_count,following_count,post_count,bio,kategori,wilayah,catatan',
            'instagram,diskominfo_jateng,Diskominfo Jateng,https://instagram.com/diskominfo_jateng,50000,100,500,Akun resmi,Pemerintahan,Kota Semarang,Akun Pemprov',
        ]);

        $file = UploadedFile::fake()->createWithContent('accounts.csv', $csvContent);

        $res = $this->actingAs($user)->post(route('accounts.import.process'), [
            'file' => $file,
        ]);

        $res->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('social_accounts', [
            'platform'     => 'instagram',
            'username'     => 'diskominfo_jateng',
            'display_name' => 'Diskominfo Jateng',
            'category_id'  => $cat->id,
            'region_id'    => $reg->id,
        ]);
    }

    public function test_accounts_import_skips_invalid_category_or_region(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $csvContent = implode("\n", [
            'platform,username,display_name,profile_url,followers_count,following_count,post_count,bio,kategori,wilayah,catatan',
            'instagram,unknown_cat_acc,Akun Unknown,https://instagram.com/unknown_cat_acc,100,10,5,Bio,KategoriPalsu,WilayahPalsu,Catatan',
        ]);

        $file = UploadedFile::fake()->createWithContent('accounts.csv', $csvContent);

        $res = $this->actingAs($user)->post(route('accounts.import.process'), [
            'file' => $file,
        ]);

        $res->assertRedirect(route('accounts.index'));
        $this->assertDatabaseMissing('social_accounts', [
            'username' => 'unknown_cat_acc',
        ]);
    }
}
