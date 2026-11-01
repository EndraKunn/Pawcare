<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_guest_ditolak_di_semua_dashboard(): void
    {
        foreach (['/customer', '/owner', '/admin', '/profil', '/dashboard'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_customer_tidak_bisa_membuka_owner_dan_admin(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_CUSTOMER));
        $this->get('/owner')->assertForbidden();
        $this->get('/owner/layanan')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get('/admin/pengguna')->assertForbidden();
        $this->get('/customer')->assertOk();
    }

    public function test_owner_tidak_bisa_membuka_customer_dan_admin(): void
    {
        [$owner] = $this->makeOwnerWithBusiness();
        $this->actingAs($owner);
        $this->get('/customer')->assertForbidden();
        $this->get('/admin')->assertForbidden();
        $this->get('/owner')->assertOk();
    }

    public function test_admin_tidak_bisa_membuka_area_owner_dan_customer(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_ADMIN));
        $this->get('/owner')->assertForbidden();
        $this->get('/customer')->assertForbidden();
        $this->get('/admin')->assertOk();
    }

    public function test_user_nonaktif_yang_masih_punya_sesi_dikeluarkan(): void
    {
        $user = $this->makeUser(User::ROLE_CUSTOMER, ['status' => false]);
        $this->actingAs($user)->get('/customer')->assertRedirect(route('login'));
    }

    public function test_dashboard_redirect_sesuai_role(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_ADMIN))->get('/dashboard')->assertRedirect(route('admin.dashboard'));
    }
}
