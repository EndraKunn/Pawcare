<?php

namespace App\Modules\Elga\Business\Services;

use App\Models\Business;
use App\Models\Pet;
use App\Models\User;
use App\Modules\Elga\Shared\AuditTrail;
use App\Support\PhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessManagementService
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function owned(User $owner): Business
    {
        return Business::query()->where('owner_id', $owner->id)->orderBy('id')->firstOrFail();
    }

    public function updateProfile(User $owner, array $input, ?UploadedFile $cover = null, bool $removeCover = false): Business
    {
        $business = $this->owned($owner);
        $allowed = ['business_name','address','description','bank_name','bank_account_number','bank_account_name'];
        $data = array_intersect_key($input, array_flip($allowed));
        if (array_key_exists('accepted_species', $input)) {
            $data['accepted_species'] = array_values(array_intersect(Pet::SPECIES, $input['accepted_species']));
        }
        $previousCover = $business->cover_photo;
        if ($cover) {
            $data['cover_photo'] = PhotoStorage::store($cover, 'businesses');
        } elseif ($removeCover) {
            $data['cover_photo'] = null;
        }
        $updated = DB::transaction(function () use ($owner, $business, $data) {
            $before = $business->only(['business_name','address','description','accepted_species']);
            $business->update($data);
            $this->audit->record('business.profile.update', $business, $before, $business->only(array_keys($before)), $owner->id);
            return $business->refresh();
        });
        // Replaced/removed cover is no longer linked from the business and can be deleted.
        if (($cover || $removeCover) && $previousCover && $previousCover !== $updated->cover_photo) {
            PhotoStorage::delete($previousCover);
        }
        return $updated;
    }

    public function updateSchedule(User $owner, array $hours): void
    {
        $business = $this->owned($owner);
        $normalized = [];
        foreach (range(1, 7) as $day) {
            $row = $hours[$day] ?? [];
            $closed = (bool) ($row['is_closed'] ?? false);
            $open = $row['opens_at'] ?? null;
            $close = $row['closes_at'] ?? null;
            $breakStart = $row['break_starts_at'] ?? null;
            $breakEnd = $row['break_ends_at'] ?? null;
            if (! $closed) {
                if (! $open || ! $close || $close <= $open) {
                    throw ValidationException::withMessages(["hours.$day.opens_at" => 'Jam operasional wajib lengkap dan jam tutup setelah jam buka.']);
                }
                if (($breakStart && ! $breakEnd) || (! $breakStart && $breakEnd)
                    || ($breakStart && ($breakStart < $open || $breakEnd > $close || $breakEnd <= $breakStart))) {
                    throw ValidationException::withMessages(["hours.$day.break_starts_at" => 'Jam istirahat harus lengkap dan berada dalam jam operasional.']);
                }
            }
            $normalized[$day] = [
                'is_closed' => $closed,
                'opens_at' => $closed ? null : $open,
                'closes_at' => $closed ? null : $close,
                'slot_duration_minutes' => (int) ($row['slot_duration_minutes'] ?? 60),
                'slot_capacity' => (int) ($row['slot_capacity'] ?? 5),
                'break_starts_at' => $closed ? null : $breakStart,
                'break_ends_at' => $closed ? null : $breakEnd,
            ];
        }
        DB::transaction(function () use ($business, $owner, $normalized) {
            foreach ($normalized as $day => $row) {
                $business->operatingHours()->updateOrCreate(['day_of_week' => $day], $row);
            }
            $this->audit->record('business.schedule.update', $business, [], ['days_updated' => count($normalized)], $owner->id);
        });
    }

    public function storeClosure(User $owner, array $data): void
    {
        $business = $this->owned($owner);
        DB::transaction(function () use ($owner, $business, $data) {
            $closure = $business->closures()->updateOrCreate(
                ['closed_date' => $data['closed_date']],
                ['reason' => $data['reason'] ?? null, 'is_active' => true]
            );
            $this->audit->record('business.closure.activate', $closure, [], ['date' => $closure->closed_date], $owner->id);
        });
    }

    public function deactivateClosure(User $owner, int $closureId): void
    {
        $business = $this->owned($owner);
        DB::transaction(function () use ($owner, $business, $closureId) {
            $closure = $business->closures()->whereKey($closureId)->firstOrFail();
            $closure->update(['is_active' => false]);
            $this->audit->record('business.closure.deactivate', $closure, ['is_active' => true], ['is_active' => false], $owner->id);
        });
    }
}
