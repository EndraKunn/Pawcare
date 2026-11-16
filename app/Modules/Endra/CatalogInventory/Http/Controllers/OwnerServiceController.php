<?php

namespace App\Modules\Endra\CatalogInventory\Http\Controllers;

use App\Http\Controllers\Owner\OwnerController;
use App\Modules\Endra\CatalogInventory\Services\CatalogInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerServiceController extends OwnerController
{
    public function __construct(private CatalogInventoryService $catalogService)
    {
    }

    public function index(Request $request): View
    {
        return view('endra-catalog::owner.services.index', [
            'business' => $this->business($request),
            'services' => $this->services($request)->orderBy('category')->orderBy('name')->paginate(15),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $this->business($request);
        $this->catalogService->storeService($request->user(), $business, $request);

        return redirect()->route('owner.services.index')->with('success', 'Layanan ditambahkan.');
    }

    public function edit(Request $request, int $service): View
    {
        return view('endra-catalog::owner.services.edit', [
            'business' => $this->business($request),
            'service' => $this->services($request)->findOrFail($service),
        ]);
    }

    public function update(Request $request, int $service): RedirectResponse
    {
        $model = $this->services($request)->findOrFail($service);
        $this->catalogService->updateService($request->user(), $model, $request);

        return redirect()->route('owner.services.index')->with('success', 'Layanan diperbarui.');
    }

    public function toggle(Request $request, int $service): RedirectResponse
    {
        $model = $this->services($request)->findOrFail($service);
        $updated = $this->catalogService->toggleService($request->user(), $model);

        return back()->with('success', 'Status layanan '.($updated->status ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(Request $request, int $service): RedirectResponse
    {
        $model = $this->services($request)->findOrFail($service);
        $result = $this->catalogService->deleteOrDeactivateService($request->user(), $model);

        return back()->with('success', $result['message']);
    }
}
