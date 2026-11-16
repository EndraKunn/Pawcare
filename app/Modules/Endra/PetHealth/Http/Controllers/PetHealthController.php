<?php

namespace App\Modules\Endra\PetHealth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Pet;
use App\Models\PetHealthRecord;
use App\Modules\Endra\PetHealth\Services\PetHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PetHealthController extends Controller
{
    public function __construct(private PetHealthService $service)
    {
    }

    public function show(Request $request, int $pet): View
    {
        $user = $request->user();
        $model = Pet::ownedBy($user)
            ->with([
                'healthRecords.sharingPermissions.business:id,business_name',
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

        $relatedBusinesses = $model->bookings
            ->pluck('business')
            ->filter()
            ->unique('id')
            ->values();

        return view('endra-pet-health::pets.show', [
            'pet' => $model,
            'vaccinations' => $vaccinations,
            'allergies' => $allergies,
            'conditions' => $conditions,
            'medications' => $medications,
            'notes' => $notes,
            'upcomingVaccinations' => $upcomingVaccinations,
            'relatedBusinesses' => $relatedBusinesses,
        ]);
    }

    public function store(Request $request, int $pet): RedirectResponse
    {
        $user = $request->user();
        $petModel = Pet::ownedBy($user)->findOrFail($pet);

        $this->service->storeRecord($user, $petModel, $request);

        return back()->with('success', 'Catatan kesehatan hewan berhasil ditambahkan.');
    }

    public function update(Request $request, int $pet, int $record): RedirectResponse
    {
        $user = $request->user();
        $petModel = Pet::ownedBy($user)->findOrFail($pet);
        $model = PetHealthRecord::where('pet_id', $petModel->id)
            ->where('customer_id', $user->id)
            ->findOrFail($record);

        $this->service->updateRecord($user, $petModel, $model, $request);

        return back()->with('success', 'Catatan kesehatan hewan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $pet, int $record): RedirectResponse
    {
        $user = $request->user();
        $petModel = Pet::ownedBy($user)->findOrFail($pet);
        $model = PetHealthRecord::where('pet_id', $petModel->id)
            ->where('customer_id', $user->id)
            ->findOrFail($record);

        $this->service->deleteRecord($model);

        return back()->with('success', 'Catatan kesehatan hewan dihapus.');
    }

    public function toggleShare(Request $request, int $pet): RedirectResponse
    {
        $user = $request->user();
        $petModel = Pet::ownedBy($user)->findOrFail($pet);
        $enabled = $this->service->toggleGlobalShare($user, $petModel);

        return back()->with(
            'success',
            $enabled
                ? 'Izin berbagi ringkasan kesehatan hewan untuk bisnis yang menangani booking diaktifkan.'
                : 'Izin berbagi ringkasan kesehatan hewan dengan bisnis dinonaktifkan.'
        );
    }

    public function updatePermission(Request $request, int $pet, int $record): RedirectResponse
    {
        $user = $request->user();
        $petModel = Pet::ownedBy($user)->findOrFail($pet);
        $recordModel = PetHealthRecord::where('pet_id', $petModel->id)
            ->where('customer_id', $user->id)
            ->findOrFail($record);

        $data = $request->validate([
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'granted' => ['required', 'boolean'],
        ]);

        $business = Business::findOrFail((int) $data['business_id']);
        $latestBooking = $petModel->bookings()
            ->where('business_id', $business->id)
            ->latest('id')
            ->first();

        abort_if(! $latestBooking, 403, 'Izin kesehatan hanya dapat diatur untuk bisnis yang memiliki hubungan layanan.');

        $granted = $request->boolean('granted');
        $this->service->setRecordPermission($user, $recordModel, $business, $granted, $latestBooking);

        return back()->with(
            'success',
            $granted
                ? "Izin akses catatan {$recordModel->title} untuk {$business->business_name} diaktifkan."
                : "Izin akses catatan {$recordModel->title} untuk {$business->business_name} dicabut."
        );
    }
}
