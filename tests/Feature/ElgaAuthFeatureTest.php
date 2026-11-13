<?php

namespace Tests\Feature;

use App\Mail\PawCareWelcomeMail;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class ElgaAuthFeatureTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_registration_customer_sends_welcome_mail_and_fixes_role_server_side(): void
    {
        Mail::fake();
        $this->post(route('register.customer.store'), [
            'name'=>'Pelanggan','email'=>'pelanggan.baru@example.test','phone'=>'08123456789',
            'password'=>'secret1234','password_confirmation'=>'secret1234','role'=>'admin',
        ])->assertRedirect(route('customer.dashboard'));
        $this->assertDatabaseHas('users', ['email'=>'pelanggan.baru@example.test','role'=>User::ROLE_CUSTOMER]);
        Mail::assertSent(PawCareWelcomeMail::class,1);
        $this->assertDatabaseHas('audit_logs',['action'=>'user.register.customer']);
    }

    public function test_registration_owner_creates_business_atomically(): void
    {
        Mail::fake();
        $this->post(route('register.owner.store'), [
            'name'=>'Owner','business_name'=>'Klinik Hewan','email'=>'owner.baru@example.test',
            'phone'=>'08123456789','address'=>'Jl Mawar 1','password'=>'secret1234','password_confirmation'=>'secret1234',
        ])->assertRedirect(route('owner.dashboard'));
        $owner = User::where('email','owner.baru@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_OWNER,$owner->role);
        $this->assertSame(1,Business::where('owner_id',$owner->id)->count());
        Mail::assertSent(PawCareWelcomeMail::class,1);
    }

    public function test_inactive_session_cannot_access_profile(): void
    {
        $disabled = $this->makeUser(User::ROLE_CUSTOMER, ['status' => false]);
        $this->actingAs($disabled)->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
