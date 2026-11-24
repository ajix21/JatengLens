<?php

namespace Tests\Feature;

use App\Models\AccountAdmin;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
