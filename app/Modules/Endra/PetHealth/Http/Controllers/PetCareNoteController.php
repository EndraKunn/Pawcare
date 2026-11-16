<?php

namespace App\Modules\Endra\PetHealth\Http\Controllers;

use App\Http\Controllers\Owner\OwnerController;
use App\Models\PetCareNote;
use App\Modules\Endra\PetHealth\Services\PetHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PetCareNoteController extends OwnerController
{
    public function __construct(private PetHealthService $service)
    {
    }

    public function store(Request $request, int $booking): RedirectResponse
    {
        $bk = $this->bookings($request)->findOrFail($booking);
        $this->service->storeCareNote($request->user(), $bk, $request);

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
