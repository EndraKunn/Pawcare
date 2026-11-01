<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_login_berhasil_dan_redirect_sesuai_role(): void
    {
        $map = [
            User::ROLE_CUSTOMER => 'customer.dashboard',
            User::ROLE_OWNER => 'owner.dashboard',
            User::ROLE_ADMIN => 'admin.dashboard',
        ];

        foreach ($map as $role => $route) {
            $user = $this->makeUser($role);
            $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect(route($route));
            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_login_gagal_dengan_password_salah(): void
    {
        $user = $this->makeUser();
        $this->from(route('login'))->post(route('login.attempt'), ['email' => $user->email, 'password' => 'salah'])
            ->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_login(): void
    {
        $user = $this->makeUser(User::ROLE_CUSTOMER, ['status' => false]);
        $this->post(route('login.attempt'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registrasi_customer_menyimpan_password_ter_hash_dan_role_tidak_bisa_dipalsukan(): void
    {
        $this->post(route('register.customer.store'), [
            'name' => 'Pelanggan Baru', 'email' => 'baru@example.test', 'phone' => '0812',
            'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            'role' => 'admin', // harus diabaikan
        ])->assertRedirect(route('customer.dashboard'));

        $user = User::where('email', 'baru@example.test')->firstOrFail();
        $this->assertSame('customer', $user->role);
        $this->assertNotSame('rahasia123', $user->password);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_registrasi_owner_membuat_bisnis(): void
    {
        $this->post(route('register.owner.store'), [
            'name' => 'Owner Baru', 'business_name' => 'Pet Baru', 'email' => 'owner.baru@example.test',
            'phone' => '0812', 'address' => 'Jl. A', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
        ])->assertRedirect(route('owner.dashboard'));

        $owner = User::where('email', 'owner.baru@example.test')->firstOrFail();
        $this->assertSame('business_owner', $owner->role);
        $this->assertSame(1, Business::where('owner_id', $owner->id)->count());
    }

    public function test_logout(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
