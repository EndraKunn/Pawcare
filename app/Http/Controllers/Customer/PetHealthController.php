<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Pet;
use App\Models\PetHealthRecord;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PetHealthController extends Controller
{
    public function show(Request $request, int $pet): View
    {
        $user = $request->user();
        $model = Pet::ownedBy($user)
            ->with([
                'healthRecords',
                'bookings' => fn ($q) => $q
                    ->with([
                        'business:id,business_name',
                        'items.service:id,name,category',
                        'careNotes' => fn ($cn) => $cn->where('visible_to_customer', true)->with('author:id,name'),
                    ])
                    ->orderByDesc('booking_date')
                    ->orderByDesc('id'),
            ])
            ->findOrFail($pet);

        $vaccinations = $model->healthRecords->where('record_type', PetHealthRecord::TYPE_VACCINATION)->values();
        $allergies = $model->healthRecords->where('record_type', PetHealthRecord::TYPE_ALLERGY)->values();
        $conditions = $model->healthRecords->where('record_type', PetHealthRecord::TYPE_CONDITION)->values();
        $medications = $model->healthRecords->where('record_type', PetHealthRecord::TYPE_MEDICATION)->values();
        $notes = $model->healthRecords->where('record_type', PetHealthRecord::TYPE_NOTE)->values();

        $upcomingVaccinations = $vaccinations->filter(
            fn (PetHealthRecord $r) => $r->next_due_date !== null && $r->next_due_date->gte(now()->startOfDay())
        )->sortBy('next_due_date')->values();

        return view('customer.pets.show', [
            'pet' => $model,
            'vaccinations' => $vaccinations,
            'allergies' => $allergies,
            'conditions' => $conditions,
            'medications' => $medications,
            'notes' => $notes,
            'upcomingVaccinations' => $upcomingVaccinations,
        ]);
    }

    public function store(Request $request, int $pet): RedirectResponse
    {
        $user = $request->user();
        $petModel = Pet::ownedBy($user)->findOrFail($pet);

        $data = $this->validateRecord($request);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store(PetHealthRecord::ATTACHMENT_DIRECTORY, PetHealthRecord::ATTACHMENT_DISK);
            $attachmentName = $file->getClientOriginalName();
        }

        PetHealthRecord::create([
            'pet_id' => $petModel->id,
            'customer_id' => $user->id,
            'record_type' => $data['record_type'],
            'title' => trim($data['title']),
            'recorded_date' => $data['recorded_date'] ?? null,
            'next_due_date' => $data['next_due_date'] ?? null,
            'notes' => isset($data['notes']) ? trim($data['notes']) : null,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        return back()->with('success', 'Catatan kesehatan hewan berhasil ditambahkan.');
    }

    public function update(Request $request, int $pet, int $record): RedirectResponse
    {
        $user = $request->user();
        $petModel = Pet::ownedBy($user)->findOrFail($pet);
        $model = PetHealthRecord::where('pet_id', $petModel->id)
            ->where('customer_id', $user->id)
            ->findOrFail($record);

        $data = $this->validateRecord($request);

        if ($request->hasFile('attachment')) {
            if ($model->attachment_path) {
                Storage::disk(PetHealthRecord::ATTACHMENT_DISK)->delete($model->attachment_path);
            }
            $file = $request->file('attachment');
            $model->attachment_path = $file->store(PetHealthRecord::ATTACHMENT_DIRECTORY, PetHealthRecord::ATTACHMENT_DISK);
            $model->attachment_name = $file->getClientOriginalName();
        }

        $model->update([
            'record_type' => $data['record_type'],
            'title' => trim($data['title']),
            'recorded_date' => $data['recorded_date'] ?? null,
            'next_due_date' => $data['next_due_date'] ?? null,
            'notes' => isset($data['notes']) ? trim($data['notes']) : null,
            'attachment_path' => $model->attachment_path,
            'attachment_name' => $model->attachment_name,
        ]);

        return back()->with('success', 'Catatan kesehatan hewan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $pet, int $record): RedirectResponse
    {
        $user = $request->user();
        $petModel = Pet::ownedBy($user)->findOrFail($pet);
        $model = PetHealthRecord::where('pet_id', $petModel->id)
            ->where('customer_id', $user->id)
            ->findOrFail($record);

        if ($model->attachment_path) {
            Storage::disk(PetHealthRecord::ATTACHMENT_DISK)->delete($model->attachment_path);
        }

        $model->delete();

        return back()->with('success', 'Catatan kesehatan hewan dihapus.');
    }

    public function toggleShare(Request $request, int $pet): RedirectResponse
    {
        $petModel = Pet::ownedBy($request->user())->findOrFail($pet);
        $petModel->update([
            'share_health_with_business' => ! $petModel->share_health_with_business,
        ]);

        return back()->with(
            'success',
            $petModel->share_health_with_business
                ? 'Izin berbagi ringkasan kesehatan hewan untuk bisnis yang menangani booking diaktifkan.'
                : 'Izin berbagi ringkasan kesehatan hewan dengan bisnis dinonaktifkan.'
        );
    }

    private function validateRecord(Request $request): array
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
