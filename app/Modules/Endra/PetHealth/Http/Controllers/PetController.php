<?php

namespace App\Modules\Endra\PetHealth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Modules\Endra\PetHealth\Services\PetManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PetController extends Controller
{
    public function __construct(private PetManagementService $service)
    {
    }

    public function index(Request $request): View
    {
        $pets = Pet::ownedBy($request->user())
            ->withCount(['bookings', 'healthRecords'])
            ->orderBy('name')
            ->paginate(12);

        return view('endra-pet-health::pets.index', compact('pets'));
    }

    public function create(): View
    {
        return view('endra-pet-health::pets.form', ['pet' => new Pet]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->service->store($request->user(), $request);

        return redirect()->route('customer.pets.index')->with('success', 'Data hewan berhasil ditambahkan.');
    }

    public function edit(Request $request, int $pet): View
    {
        return view('endra-pet-health::pets.form', ['pet' => $this->findOwned($request, $pet)]);
    }

    public function update(Request $request, int $pet): RedirectResponse
    {
        $model = $this->findOwned($request, $pet);
        $this->service->update($request->user(), $model, $request);

        return redirect()->route('customer.pets.index')->with('success', 'Data hewan berhasil diperbarui.');
    }

    public function destroy(Request $request, int $pet): RedirectResponse
    {
        $model = $this->findOwned($request, $pet);
        $result = $this->service->destroy($request->user(), $model);

        if (! $result['deleted']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('customer.pets.index')->with('success', $result['message']);
    }

    public function archive(Request $request, int $pet): RedirectResponse
    {
        $model = $this->findOwned($request, $pet);
        $result = $this->service->archive($request->user(), $model);

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('customer.pets.index')->with('success', $result['message']);
    }

    public function restore(Request $request, int $pet): RedirectResponse
    {
        $model = $this->findOwned($request, $pet);
        $result = $this->service->restore($request->user(), $model);

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        return redirect()->route('customer.pets.index')->with('success', $result['message']);
    }

    private function findOwned(Request $request, int $id): Pet
    {
        return Pet::ownedBy($request->user())->findOrFail($id);
    }
}
