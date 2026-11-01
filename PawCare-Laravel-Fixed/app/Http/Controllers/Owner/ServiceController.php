<?php

namespace App\Http\Controllers\Owner;

use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends OwnerController
{
    public function index(Request $request): View
    {
        return view('owner.services.index', [
            'business' => $this->business($request),
            'services' => $this->services($request)->orderBy('category')->orderBy('name')->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->business($request);

        // business_id ditentukan server, tidak pernah dari input browser.
        $business->services()->create($this->validated($request));

        return redirect()->route('owner.services.index')->with('success', 'Layanan ditambahkan.');
    }

    public function edit(Request $request, int $service): View
    {
        return view('owner.services.edit', [
            'business' => $this->business($request),
            'service' => $this->services($request)->findOrFail($service),
        ]);
    }

    public function update(Request $request, int $service): RedirectResponse
    {
        $this->services($request)->findOrFail($service)->update($this->validated($request));

        return redirect()->route('owner.services.index')->with('success', 'Layanan diperbarui.');
    }

    public function toggle(Request $request, int $service): RedirectResponse
    {
        $model = $this->services($request)->findOrFail($service);
        $model->update(['status' => ! $model->status]);

        return back()->with('success', 'Status layanan '.($model->status ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(Request $request, int $service): RedirectResponse
    {
        $model = $this->services($request)->findOrFail($service);

        // Layanan yang sudah pernah dipesan tidak dihapus agar histori transaksi aman.
        if ($model->bookingItems()->exists()) {
            $model->update(['status' => false]);

            return back()->with('success', 'Layanan sudah pernah dipesan, sehingga dinonaktifkan (tidak dihapus) demi menjaga histori transaksi.');
        }

        $model->delete();

        return back()->with('success', 'Layanan dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(Service::CATEGORIES)],
            'unit' => ['required', Rule::in(Service::UNITS)],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], [], [
            'name' => 'nama layanan',
            'category' => 'jenis layanan',
            'unit' => 'satuan',
            'duration_minutes' => 'durasi layanan',
            'description' => 'deskripsi',
            'price' => 'harga',
        ]);
        $data['duration_minutes'] = (int) ($data['duration_minutes'] ?? 60);
        $data['status'] = $request->boolean('status', true);

        return $data;
    }
}
