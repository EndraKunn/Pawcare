<?php

namespace App\Modules\Endra\PetHealth\Services;

use App\Events\CareNotePublished;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Pet;
use App\Models\PetCareNote;
use App\Models\PetHealthRecord;
use App\Models\PetHealthSharingPermission;
use App\Models\User;
use App\Modules\Elga\Shared\AuditTrail;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PetHealthService
{
    public function __construct(private AuditTrail $audit)
    {
    }

    public function storeRecord(User $customer, Pet $pet, Request $request): PetHealthRecord
    {
        $data = $this->validateRecord($request);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store(PetHealthRecord::ATTACHMENT_DIRECTORY, PetHealthRecord::ATTACHMENT_DISK);
            $attachmentName = $file->getClientOriginalName();
        }

        $record = PetHealthRecord::create([
            'pet_id' => $pet->id,
            'customer_id' => $customer->id,
            'record_type' => $data['record_type'],
            'title' => trim($data['title']),
            'recorded_date' => $data['recorded_date'] ?? null,
            'next_due_date' => $data['next_due_date'] ?? null,
            'notes' => isset($data['notes']) ? trim($data['notes']) : null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        if (Schema::hasTable('audit_logs')) {
            $this->audit->record('pet.health_record.created', $record, [], [
                'pet_id' => $pet->id,
                'record_type' => $record->record_type,
                'title' => $record->title,
            ], $customer->id);
        }

        return $record;
    }

    public function updateRecord(User $customer, Pet $pet, PetHealthRecord $record, Request $request): PetHealthRecord
    {
        $data = $this->validateRecord($request);

        if ($request->hasFile('attachment')) {
            if ($record->attachment_path) {
                Storage::disk(PetHealthRecord::ATTACHMENT_DISK)->delete($record->attachment_path);
            }
            $file = $request->file('attachment');
            $record->attachment_path = $file->store(PetHealthRecord::ATTACHMENT_DIRECTORY, PetHealthRecord::ATTACHMENT_DISK);
            $record->attachment_name = $file->getClientOriginalName();
        }

        $record->update([
            'record_type' => $data['record_type'],
            'title' => trim($data['title']),
            'recorded_date' => $data['recorded_date'] ?? null,
            'next_due_date' => $data['next_due_date'] ?? null,
            'notes' => isset($data['notes']) ? trim($data['notes']) : null,
            'attachment_path' => $record->attachment_path,
            'attachment_name' => $record->attachment_name,
        ]);

        return $record;
    }

    public function deleteRecord(PetHealthRecord $record): void
    {
        if ($record->attachment_path) {
            Storage::disk(PetHealthRecord::ATTACHMENT_DISK)->delete($record->attachment_path);
        }

        $record->delete();
    }

    public function toggleGlobalShare(User $customer, Pet $pet): bool
    {
        $before = (bool) $pet->share_health_with_business;
        $newState = ! $before;

        $pet->update([
            'share_health_with_business' => $newState,
        ]);

        if (Schema::hasTable('pet_health_sharing_permissions')) {
            $businessIds = Booking::where('pet_id', $pet->id)
                ->pluck('business_id')
                ->unique()
                ->values();

            $records = $pet->healthRecords()->get(['id']);

            foreach ($records as $record) {
                foreach ($businessIds as $bizId) {
                    PetHealthSharingPermission::updateOrCreate(
                        [
                            'pet_health_record_id' => $record->id,
                            'business_id' => $bizId,
                        ],
                        [
                            'granted_by' => $customer->id,
                            'granted_at' => now(),
                            'revoked_at' => $newState ? null : now(),
                        ]
                    );
                }
            }
        }

        if (Schema::hasTable('audit_logs')) {
            $this->audit->record('pet.health.share_toggled', $pet, [
                'share_health_with_business' => $before,
            ], [
                'share_health_with_business' => $newState,
            ], $customer->id);
        }

        return $newState;
    }

    public function setRecordPermission(
        User $customer,
        PetHealthRecord $record,
        Business $business,
        bool $granted,
        ?Booking $booking = null
    ): PetHealthSharingPermission {
        $permission = PetHealthSharingPermission::updateOrCreate(
            [
                'pet_health_record_id' => $record->id,
                'business_id' => $business->id,
            ],
            [
                'booking_id' => $booking?->id,
                'granted_by' => $customer->id,
                'granted_at' => now(),
                'revoked_at' => $granted ? null : now(),
            ]
        );

        if (Schema::hasTable('audit_logs')) {
            $this->audit->record('pet.health.permission_updated', $permission, [], [
                'pet_health_record_id' => $record->id,
                'business_id' => $business->id,
                'granted' => $granted,
            ], $customer->id);
        }

        return $permission;
    }

    public function storeCareNote(User $owner, Booking $booking, Request $request): PetCareNote
    {
        $data = $request->validate([
            'care_summary' => ['required', 'string', 'max:2000'],
            'recommendations' => ['nullable', 'string', 'max:2000'],
            'visible_to_customer' => ['nullable', 'boolean'],
        ], [
            'care_summary.required' => 'Catatan hasil perawatan wajib diisi.',
        ]);

        $note = PetCareNote::create([
            'booking_id' => $booking->id,
            'pet_id' => $booking->pet_id,
            'business_id' => $booking->business_id,
            'created_by' => $owner->id,
            'care_summary' => trim($data['care_summary']),
            'recommendations' => isset($data['recommendations']) ? trim($data['recommendations']) : null,
            'visible_to_customer' => $request->boolean('visible_to_customer', true),
        ]);

        CareNotePublished::dispatch($note);

        if (Schema::hasTable('audit_logs')) {
            $this->audit->record('pet.care_note.created', $note, [], [
                'booking_id' => $booking->id,
                'pet_id' => $booking->pet_id,
                'visible_to_customer' => $note->visible_to_customer,
            ], $owner->id);
        }

        return $note;
    }

    public function validateRecord(Request $request): array
    {
        return $request->validate([
            'record_type' => ['required', 'string', Rule::in(array_keys(PetHealthRecord::TYPES))],
            'title' => ['required', 'string', 'max:150'],
            'recorded_date' => ['nullable', 'date'],
            'next_due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => [
                'nullable',
                'file',
                'mimes:'.implode(',', PetHealthRecord::ATTACHMENT_EXTENSIONS),
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }
                    $realPath = $value->getRealPath();
                    if ($realPath && is_file($realPath) && filesize($realPath) > 0) {
                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                        $actualMime = $finfo->file($realPath) ?: '';
                        if (! in_array($actualMime, PetHealthRecord::ATTACHMENT_MIMES, true)) {
                            $fail('Lampiran kesehatan harus berupa file JPG, PNG, WebP, atau PDF yang valid.');
                        }
                    }
                },
            ],
        ], [
            'record_type.required' => 'Pilih jenis catatan kesehatan.',
            'title.required' => 'Judul / nama vaksin / alergi wajib diisi.',
        ]);
    }
}
