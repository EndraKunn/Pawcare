<?php

namespace App\Modules\Elga\Admin\Services;

use App\Models\Business;
use App\Models\User;
use App\Modules\Elga\Shared\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminManagementService
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function toggleUser(User $actor, User $target): User
    {
        abort_unless($actor->isAdmin(), 403);
        if ($target->is($actor)) {
            throw ValidationException::withMessages(['user' => 'Admin tidak boleh menonaktifkan akun sendiri.']);
        }
        return DB::transaction(function () use ($actor, $target) {
            $target = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($target->isAdmin() && $target->status && User::where('role', User::ROLE_ADMIN)->where('status', true)->count() <= 1) {
                throw ValidationException::withMessages(['user' => 'Admin aktif terakhir tidak boleh dinonaktifkan.']);
            }
            $oldStatus = $target->status;
            $target->update(['status' => ! $oldStatus]);
            $this->audit->record('admin.user.status', $target, ['status' => $oldStatus], ['status' => $target->status], $actor->id);
            if ($target->isOwner() && ! $target->status) {
                foreach ($target->businesses()->where('status', true)->get() as $business) {
                    $business->update(['status' => false]);
                    $this->audit->record('admin.business.suspend-owner', $business, ['status' => true], ['status' => false], $actor->id);
                }
            }
            return $target->refresh();
        });
    }

    public function updateBusiness(User $actor, Business $business, array $data): Business
    {
        abort_unless($actor->isAdmin(), 403);
        return DB::transaction(function () use ($actor, $business, $data) {
            $allowed = array_intersect_key($data, array_flip(['business_name', 'address', 'description']));
            $before = $business->only(array_keys($allowed));
            $business->update($allowed);
            $this->audit->record('admin.business.update', $business, $before, $business->only(array_keys($allowed)), $actor->id);
            return $business->refresh();
        });
    }

    public function toggleBusiness(User $actor, Business $business): Business
    {
        abort_unless($actor->isAdmin(), 403);
        return DB::transaction(function () use ($actor, $business) {
            $business = Business::whereKey($business->id)->lockForUpdate()->firstOrFail();
            if (! $business->status && ! $business->owner->status) {
                throw ValidationException::withMessages(['business' => 'Aktifkan akun pemilik dahulu sebelum mengaktifkan bisnis.']);
            }
            $old = $business->status;
            $business->update(['status' => ! $old]);
            $this->audit->record('admin.business.status', $business, ['status' => $old], ['status' => $business->status], $actor->id);
            return $business->refresh();
        });
    }
}
