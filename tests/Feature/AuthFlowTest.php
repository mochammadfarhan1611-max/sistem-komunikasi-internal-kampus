<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
    }

    public function test_login_page_renders_dynamic_form(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee(route('login.submit'), false);
        $response->assertSee('name="username"', false);
        $response->assertSee('name="password"', false);
    }

    public function test_user_can_login_and_view_dynamic_dashboard(): void
    {
        $role = Role::create([
            'nama_role' => 'Mahasiswa',
            'deskripsi' => 'Mahasiswa aktif',
        ]);

        $fakultas = Fakultas::create([
            'kode_fakultas' => 'FT',
            'nama_fakultas' => 'Fakultas Teknik',
        ]);

        $prodi = ProgramStudi::create([
            'fakultas_id' => $fakultas->id,
            'kode_prodi' => 'TI',
            'nama_prodi' => 'Teknik Informatika',
        ]);

        TahunAkademik::create([
            'kode_semester' => '20261',
            'nama_semester' => 'Semester Ganjil 2026/2027',
            'is_aktif' => true,
        ]);

        $user = User::create([
            'role_id' => $role->id,
            'nama_lengkap' => 'Mochammad Farhan',
            'username' => '2421400177',
            'email_kampus' => '2421400177@mahasiswa.kampus.ac.id',
            'password' => Hash::make('password'),
            'status_akun' => 'Aktif',
            'nim' => '2421400177',
            'tahun_angkatan' => 2024,
            'prodi_id' => $prodi->id,
            'fakultas_id' => $fakultas->id,
        ]);

        $loginResponse = $this->post('/login', [
            'username' => '2421400177',
            'password' => 'password',
        ]);

        $loginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $dashboardResponse = $this->get('/dashboard');

        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Mochammad Farhan');
        $dashboardResponse->assertSee('NIM 2421400177');
        $dashboardResponse->assertSee('Fakultas Teknik');
        $dashboardResponse->assertSee('Semester Ganjil 2026/2027');
    }

    public function test_invalid_credentials_redirect_back_with_errors(): void
    {
        $response = $this->from('/login')->post('/login', [
            'username' => '2421400177',
            'password' => 'salah-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}

