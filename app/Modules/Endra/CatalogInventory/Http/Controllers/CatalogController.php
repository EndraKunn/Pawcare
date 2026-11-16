<?php

namespace App\Modules\Endra\CatalogInventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Modules\Endra\CatalogInventory\Services\CatalogInventoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(private CatalogInventoryService $catalogService)
    {
    }

    public function index(): View
    {
        $businesses = $this->catalogService->getFeaturedBusinesses(3);

        return view('public.home', compact('businesses'));
    }

    public function catalog(Request $request): View
    {
        $data = $this->catalogService->filterCatalog($request);

        return view('endra-catalog::public.catalog', $data);
    }

    public function showBusiness(Request $request, Business $business): View
    {
        abort_unless($business->status, 404);

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'service' => ['nullable', 'integer', 'min:1'],
        ]);

        $detail = $this->catalogService->getBusinessDetail(
            $business,
            $validated['date'] ?? null,
            isset($validated['service']) ? (int) $validated['service'] : null
        );

        return view('endra-catalog::public.business', $detail);
    }

    public function availability(Request $request, Business $business): JsonResponse
    {
        abort_unless($business->status, 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'service_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();
        $service = null;
        if (! empty($validated['service_id'])) {
            $service = $business->services()->active()->findOrFail((int) $validated['service_id']);
        }

        $availability = $this->catalogService->checkAvailability($business, $date, $service);

        return response()->json([
            'business_id' => $business->id,
            'date' => $date,
            'service_id' => $service?->id,
            'service_name' => $service?->name,
            'availability' => $availability,
        ]);
    }
}
