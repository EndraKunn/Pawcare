<?php

namespace Tests\Feature;

use App\Mail\PawCareWelcomeMail;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class ElgaBusinessAdminFeatureTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_owner_cannot_deactivate_closure_of_other_business(): void
    {
        [$ownerA,$businessA]=$this->makeOwnerWithBusiness('Alpha');
        [$ownerB,$businessB]=$this->makeOwnerWithBusiness('Beta');
        $closure=$businessB->closures()->create(['closed_date'=>now()->addDays(8)->toDateString(),'reason'=>'Libur']);
        $this->actingAs($ownerA)->patch(route('owner.business.closures.deactivate',$closure->id))->assertNotFound();
        $this->assertTrue((bool)$closure->fresh()->is_active);
    }

    public function test_closure_is_deactivated_without_deleting_the_record(): void
    {
        [$owner,$business]=$this->makeOwnerWithBusiness();
        $closure=$business->closures()->create(['closed_date'=>now()->addDays(8)->toDateString(),'reason'=>'Libur']);
        $this->actingAs($owner)->patch(route('owner.business.closures.deactivate',$closure->id))->assertRedirect();
        $this->assertDatabaseHas('business_closures',['id'=>$closure->id,'is_active'=>false]);
    }

    public function test_admin_deactivation_suspends_business_and_records_audit(): void
    {
        $admin=$this->makeUser(User::ROLE_ADMIN);
        [$owner,$business]=$this->makeOwnerWithBusiness();
        $this->actingAs($admin)->patch(route('admin.users.toggle',$owner->id))->assertRedirect();
        $this->assertFalse($owner->fresh()->status);
        $this->assertFalse($business->fresh()->status);
        $this->assertDatabaseHas('audit_logs',['action'=>'admin.user.status','subject_id'=>$owner->id]);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin=$this->makeUser(User::ROLE_ADMIN);
        $this->actingAs($admin)->patch(route('admin.users.toggle',$admin->id))->assertSessionHasErrors('user');
        $this->assertTrue($admin->fresh()->status);
    }

    public function test_owner_business_schedule_validation_rejects_reversed_hours(): void
    {
        [$owner]=$this->makeOwnerWithBusiness();
        $hours=[];
        foreach(range(1,7) as $day) $hours[$day]=['opens_at'=>'17:00','closes_at'=>'08:00','slot_capacity'=>5,'slot_duration_minutes'=>60];
        $this->actingAs($owner)->put(route('owner.business.schedule.update'),['hours'=>$hours])->assertSessionHasErrors('hours.1.opens_at');
    }

    public function test_owner_can_replace_and_remove_business_cover_without_orphaning_old_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake(\App\Support\PhotoStorage::DISK);
        [$owner, $business] = $this->makeOwnerWithBusiness();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $first = \Illuminate\Http\UploadedFile::fake()->createWithContent('cover.png', $png);
        $this->actingAs($owner)->put(route('owner.business.update'), [
            'business_name' => 'Contoh bisnis', 'address' => 'Alamat contoh', 'cover' => $first,
        ])->assertSessionHasNoErrors();
        $path = $business->fresh()->cover_photo;
        $this->assertNotNull($path);
        $this->put(route('owner.business.update'), [
            'business_name' => 'Contoh bisnis', 'address' => 'Alamat contoh', 'remove_cover' => 1,
        ])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Storage::disk(\App\Support\PhotoStorage::DISK)->assertMissing($path);
    }
}
