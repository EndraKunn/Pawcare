<?php

namespace App\Http\Controllers\Owner;

use App\Models\PetCareNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PetCareNoteController extends OwnerController
{
    public function store(Request $request, int $booking): RedirectResponse
    {
        $bk = $this->bookings($request)->findOrFail($booking);

        $data = $request->validate([
            'care_summary' => ['required', 'string', 'max:2000'],
            'recommendations' => ['nullable', 'string', 'max:2000'],
            'visible_to_customer' => ['nullable', 'boolean'],
        ], [
            'care_summary.required' => 'Catatan hasil perawatan wajib diisi.',
        ]);

        PetCareNote::create([
            'booking_id' => $bk->id,
            'pet_id' => $bk->pet_id,
            'business_id' => $bk->business_id,
            'created_by' => $request->user()->id,
            'care_summary' => trim($data['care_summary']),
            'recommendations' => isset($data['recommendations']) ? trim($data['recommendations']) : null,
            'visible_to_customer' => $request->boolean('visible_to_customer', true),
        ]);

        return back()->with('success', 'Catatan hasil perawatan hewan berhasil disimpan.');
    }

    public function destroy(Request $request, int $note): RedirectResponse
    {
        $businessIds = $this->businessIds($request);
        $model = PetCareNote::whereIn('business_id', $businessIds)->findOrFail($note);
        $model->delete();

        return back()->with('success', 'Catatan perawatan dihapus.');
    }
}
