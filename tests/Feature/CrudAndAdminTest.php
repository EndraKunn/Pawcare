<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Payment;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class CrudAndAdminTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_customer_crud_hewan_sendiri(): void
    {
        $cust = $this->makeUser();
        $this->actingAs($cust);

        $this->post(route('customer.pets.store'), ['name' => 'Milo', 'species' => 'Anjing', 'breed' => 'Pug', 'age_years' => 2, 'gender' => 'Jantan'])
            ->assertRedirect(route('customer.pets.index'));
        $pet = Pet::firstOrFail();
        $this->assertSame($cust->id, $pet->customer_id);
        $this->assertSame(24, $pet->age);

        $this->put(route('customer.pets.update', $pet->id), ['name' => 'Milo2', 'species' => 'Kucing', 'gender' => 'Betina'])->assertSessionHasNoErrors();
        $this->assertSame('Milo2', $pet->fresh()->name);

        $this->post(route('customer.pets.store'), ['name' => 'X', 'species' => 'Naga', 'gender' => 'Jantan'])->assertSessionHasErrors('species');

        $this->delete(route('customer.pets.destroy', $pet->id));
        $this->assertSame(0, Pet::count());
    }

    public function test_hewan_dengan_riwayat_booking_tidak_dapat_dihapus(): void
    {
        [, $biz] = $this->makeOwnerWithBusiness();
        $cust = $this->makeUser();
        $pet = $this->makePet($cust);
        $svc = $this->makeService($biz);
        $this->makeBooking($cust, $biz, $pet, [$svc->id => 1]);

        $this->actingAs($cust)->delete(route('customer.pets.destroy', $pet->id))->assertSessionHas('error');
        $this->assertSame(1, Pet::count());
    }

    public function test_owner_crud_layanan_dan_business_id_ditentukan_server(): void
    {
        [$owner, $biz] = $this->makeOwnerWithBusiness();
        [, $other] = $this->makeOwnerWithBusiness();
        $this->actingAs($owner);

        $this->post(route('owner.services.store'), ['name' => 'Grooming', 'category' => 'Grooming', 'unit' => 'sesi', 'price' => 90000, 'business_id' => $other->id])
            ->assertRedirect(route('owner.services.index'));
        $svc = Service::firstOrFail();
        $this->assertSame($biz->id, $svc->business_id);

        $this->put(route('owner.services.update', $svc->id), ['name' => 'Grooming Pro', 'category' => 'Grooming', 'unit' => 'sesi', 'price' => 95000, 'status' => 0])
            ->assertSessionHasNoErrors();
        $this->assertSame(95000.0, $svc->fresh()->price);
        $this->assertFalse($svc->fresh()->status);

        $this->delete(route('owner.services.destroy', $svc->id));
        $this->assertSame(0, Service::count());
    }

    public function test_layanan_yang_pernah_dipesan_dinonaktifkan_bukan_dihapus(): void
    {
        [$owner, $biz] = $this->makeOwnerWithBusiness();
        $cust = $this->makeUser();
        $svc = $this->makeService($biz);
        $this->makeBooking($cust, $biz, $this->makePet($cust), [$svc->id => 1]);

        $this->actingAs($owner)->delete(route('owner.services.destroy', $svc->id));
        $this->assertSame(1, Service::count());
        $this->assertFalse($svc->fresh()->status);
    }

    public function test_admin_melihat_rekap_seluruh_platform(): void
    {
        [, $a] = $this->makeOwnerWithBusiness('Alpha');
        [, $b] = $this->makeOwnerWithBusiness('Beta');
        $cust = $this->makeUser();
        $pet = $this->makePet($cust);
        foreach ([[$a, 100000], [$b, 200000]] as [$biz, $price]) {
            $svc = $this->makeService($biz, $price);
            $bk = $this->makeBooking($cust, $biz, $pet, [$svc->id => 1]);
            $bk->payment->update(['status' => Payment::PAID, 'paid_at' => now(), 'proof_path' => 'x.png']);
        }

        $this->actingAs($this->makeUser('admin'));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Rp 300.000');
        $this->get(route('admin.reports'))->assertOk()->assertSee($a->business_name)->assertSee($b->business_name)->assertSee('Rp 300.000');
        $this->get(route('admin.clients.index'))->assertOk()->assertSee($a->business_name)->assertSee($b->business_name);
        $this->get(route('admin.bookings'))->assertOk();
        $this->get(route('admin.payments'))->assertOk();
        $this->get(route('admin.services'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_admin_suspend_bisnis_menyembunyikan_dari_katalog_dan_menolak_booking_baru(): void
    {
        [, $biz] = $this->makeOwnerWithBusiness('Zeta');
        $svc = $this->makeService($biz);
        $cust = $this->makeUser();
        $pet = $this->makePet($cust);
        $admin = $this->makeUser('admin');

        $this->get(route('catalog'))->assertSee($biz->business_name);
        $this->actingAs($admin)->patch(route('admin.clients.toggle', $biz->id));
        $this->assertFalse($biz->fresh()->status);

        auth()->logout();
        $this->get(route('catalog'))->assertDontSee($biz->business_name);
        $this->actingAs($cust)->post(route('customer.bookings.store'), $this->bookingPayload($biz, $pet, [$svc->id]))->assertSessionHasErrors('business_id');
    }

    public function test_admin_tidak_bisa_menonaktifkan_diri_sendiri_dan_bisa_menonaktifkan_user_lain(): void
    {
        $admin = $this->makeUser('admin');
        $cust = $this->makeUser();
        $this->actingAs($admin);

        $this->patch(route('admin.users.toggle', $admin->id))->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->status);
        $this->patch(route('admin.users.toggle', $cust->id));
        $this->assertFalse($cust->fresh()->status);
    }

    public function test_profil_dan_ganti_password(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->put(route('profile.update'), ['name' => 'Nama Baru', 'email' => $user->email, 'phone' => '0899', 'role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame('Nama Baru', $user->fresh()->name);
        $this->assertSame('customer', $user->fresh()->role);

        $this->put(route('profile.password'), ['current_password' => 'salah', 'password' => 'barubaru1', 'password_confirmation' => 'barubaru1'])->assertSessionHasErrors('current_password');
        $this->put(route('profile.password'), ['current_password' => 'password', 'password' => 'barubaru1', 'password_confirmation' => 'barubaru1'])->assertSessionHasNoErrors();
        $this->assertTrue(\Hash::check('barubaru1', $user->fresh()->password));
    }
}
