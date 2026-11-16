<?php

namespace App\Modules\Endra\CatalogInventory\Services;

use App\Events\CatalogChanged;
use App\Models\Business;
use App\Models\Pet;
use App\Models\Service;
use App\Models\User;
use App\Modules\Elga\Shared\AuditTrail;
use App\Services\ReportService;
use App\Services\SlotAvailabilityService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CatalogInventoryService
{
    public function __construct(
        private SlotAvailabilityService $slotService,
        private ReportService $reportService,
        private AuditTrail $audit
    ) {
    }

    public function getFeaturedBusinesses(int $limit = 3): Collection
    {
        $query = Business::active()
            ->with('owner:id,name')
            ->withMin(['services as min_price' => fn ($q) => $q->active()], 'price');

        if (Schema::hasTable('reviews')) {
            $query->withAvg(['reviews as rating_avg' => fn ($r) => $r->validCompleted()], 'rating')
                ->withCount(['reviews as reviews_count' => fn ($r) => $r->validCompleted()]);
        }

        return $query->latest()->take($limit)->get();
    }

    /**
     * @return array{businesses:LengthAwarePaginator,q:string,category:string,species:string,sort:string}
     */
    public function filterCatalog(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $species = trim((string) $request->query('species', ''));
        $sort = trim((string) $request->query('sort', 'name'));

        $validCategory = in_array($category, Service::CATEGORIES, true) ? $category : '';
        $validSpecies = in_array($species, Pet::SPECIES, true) ? $species : '';
        $validSort = in_array($sort, ['name', 'price_asc', 'rating_desc'], true) ? $sort : 'name';

        $query = Business::active()
            ->with(['owner:id,name', 'operatingHours'])
            ->withCount([
                'services as services_count' => fn ($s) => $s->active(),
            ])
            ->withMin([
                'services as min_price' => fn ($s) => $s->active(),
            ], 'price');

        if (Schema::hasTable('reviews')) {
            $query->withAvg(['reviews as rating_avg' => fn ($r) => $r->validCompleted()], 'rating')
                ->withCount(['reviews as reviews_count' => fn ($r) => $r->validCompleted()]);
        }

        $query->when($q !== '', function ($b) use ($q) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($q));
            $keyword = "%{$escaped}%";

            $b->where(function ($w) use ($keyword) {
                $w->whereRaw("LOWER(COALESCE(business_name, '')) LIKE ? ESCAPE '\\'", [$keyword])
                    ->orWhereRaw("LOWER(COALESCE(address, '')) LIKE ? ESCAPE '\\'", [$keyword])
                    ->orWhereHas('services', function ($s) use ($keyword) {
                        $s->active()->whereRaw("LOWER(COALESCE(name, '')) LIKE ? ESCAPE '\\'", [$keyword]);
                    });
            });
        })
        ->when(
            $validCategory !== '',
            fn ($b) => $b->whereHas('services', fn ($s) => $s->active()->where('category', $validCategory))
        )
        ->when(
            $validSpecies !== '',
            fn ($b) => $b->acceptsSpecies($validSpecies)
        );

        if ($validSort === 'price_asc') {
            $query->orderByRaw('COALESCE(min_price, 999999999) ASC')->orderBy('business_name');
        } elseif ($validSort === 'rating_desc' && Schema::hasTable('reviews')) {
            $query->orderByRaw('COALESCE(rating_avg, 0) DESC')->orderBy('business_name');
        } else {
            $query->orderBy('business_name');
        }

        $businesses = $query->paginate(9)->withQueryString();

        return [
            'businesses' => $businesses,
            'q' => $q,
            'category' => $validCategory,
            'species' => $validSpecies,
            'sort' => $validSort,
        ];
    }

    /**
     * @return array{business:Business,services:Collection,ratingSummary:array,reviews:mixed,slotPreview:array,previewDate:string}
     */
    public function getBusinessDetail(Business $business, ?string $date = null, ?int $serviceId = null): array
    {
        $business->load([
            'owner:id,name',
            'operatingHours',
            'closures',
        ]);

        $services = $business->services()
            ->active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $previewDate = $date ? Carbon::parse($date)->toDateString() : now()->addDay()->toDateString();
        $selectedService = $serviceId ? $services->firstWhere('id', $serviceId) : $services->first();
        $slotPreview = $this->checkAvailability($business, $previewDate, $selectedService);

        $ratingSummary = $this->reportService->ratingSummary([$business->id]);
        $reviews = Schema::hasTable('reviews')
            ? $business->reviews()
                ->validCompleted()
                ->with(['customer:id,name', 'reply.owner:id,name', 'booking.items.service:id,name'])
                ->latest('id')
                ->paginate(5)
                ->withQueryString()
            : collect();

        return [
            'business' => $business,
            'services' => $services,
            'ratingSummary' => $ratingSummary,
            'reviews' => $reviews,
            'slotPreview' => $slotPreview,
            'previewDate' => $previewDate,
            'selectedServiceId' => $selectedService?->id,
        ];
    }

    public function checkAvailability(Business $business, string $dateString, ?Service $service = null): array
    {
        $duration = $service ? max(15, (int) ($service->duration_minutes ?: 60)) : 60;
        $daily = $this->slotService->getDailySlots($business, $dateString, $duration);

        if (! $daily['is_open']) {
            return $daily;
        }

        $serviceCap = $service && $service->capacity ? max(1, (int) $service->capacity) : null;
        if ($serviceCap !== null) {
            $effectiveCap = min((int) $daily['slot_capacity'], $serviceCap);
            $daily['slot_capacity'] = $effectiveCap;
            $daily['service_capacity'] = $serviceCap;

            $daily['slots'] = array_map(function (array $slot) use ($effectiveCap): array {
                $remaining = $slot['is_break'] ? 0 : max(0, $effectiveCap - (int) $slot['used']);
                $slot['capacity'] = $effectiveCap;
                $slot['remaining'] = $remaining;
                $slot['available'] = ! $slot['is_break'] && $remaining > 0;

                return $slot;
            }, $daily['slots']);
        }

        return $daily;
    }

    public function storeService(User $owner, Business $business, Request $request): Service
    {
        $data = $this->validatedServiceData($request);
        $service = $business->services()->create($data);

        CatalogChanged::dispatch(
            $business->id,
            $service->id,
            $service->name,
            $service->category,
            (int) round((float) $service->price),
            (int) $service->duration_minutes,
            (int) ($service->capacity ?? 5),
            (bool) $service->status,
            'created'
        );

        $this->recordAudit('service.created', $service, [], [
            'name' => $service->name,
            'category' => $service->category,
            'price' => (int) round((float) $service->price),
            'capacity' => (int) ($service->capacity ?? 5),
            'status' => (bool) $service->status,
        ], $owner->id);

        return $service;
    }

    public function updateService(User $owner, Service $service, Request $request): Service
    {
        $before = [
            'name' => $service->name,
            'price' => (int) round((float) $service->price),
            'capacity' => (int) ($service->capacity ?? 5),
            'status' => (bool) $service->status,
        ];

        $data = $this->validatedServiceData($request);
        $service->update($data);

        CatalogChanged::dispatch(
            (int) $service->business_id,
            (int) $service->id,
            (string) $service->name,
            (string) $service->category,
            (int) round((float) $service->price),
            (int) ($service->duration_minutes ?: 60),
            (int) ($service->capacity ?: 5),
            (bool) $service->status,
            'updated'
        );

        $this->recordAudit('service.updated', $service, $before, [
            'name' => $service->name,
            'price' => (int) round((float) $service->price),
            'capacity' => (int) ($service->capacity ?? 5),
            'status' => (bool) $service->status,
        ], $owner->id);

        return $service;
    }

    public function toggleService(User $owner, Service $service): Service
    {
        $before = (bool) $service->status;
        $service->update(['status' => ! $before]);

        CatalogChanged::dispatch(
            (int) $service->business_id,
            (int) $service->id,
            (string) $service->name,
            (string) $service->category,
            (int) round((float) $service->price),
            (int) ($service->duration_minutes ?: 60),
            (int) ($service->capacity ?: 5),
            (bool) $service->status,
            'toggled'
        );

        $this->recordAudit('service.toggled', $service, ['status' => $before], [
            'status' => (bool) $service->status,
        ], $owner->id);

        return $service;
    }

    /**
     * @return array{deleted:bool,message:string}
     */
    public function deleteOrDeactivateService(User $owner, Service $service): array
    {
        if ($service->bookingItems()->exists()) {
            $before = (bool) $service->status;
            $service->update(['status' => false]);

            CatalogChanged::dispatch(
                (int) $service->business_id,
                (int) $service->id,
                (string) $service->name,
                (string) $service->category,
                (int) round((float) $service->price),
                (int) ($service->duration_minutes ?: 60),
                (int) ($service->capacity ?: 5),
                false,
                'deactivated'
            );

            $this->recordAudit('service.deactivated', $service, ['status' => $before], [
                'status' => false,
            ], $owner->id);

            return [
                'deleted' => false,
                'message' => 'Layanan sudah pernah dipesan, sehingga dinonaktifkan (tidak dihapus) demi menjaga histori transaksi.',
            ];
        }

        $businessId = (int) $service->business_id;
        $serviceId = (int) $service->id;
        $serviceName = (string) $service->name;
        $category = (string) $service->category;
        $priceRupiah = (int) round((float) $service->price);
        $duration = (int) ($service->duration_minutes ?: 60);
        $capacity = (int) ($service->capacity ?: 5);

        $this->recordAudit('service.deleted', $service, [
            'name' => $serviceName,
            'price' => $priceRupiah,
        ], [], $owner->id);

        $service->delete();

        CatalogChanged::dispatch(
            $businessId,
            $serviceId,
            $serviceName,
            $category,
            $priceRupiah,
            $duration,
            $capacity,
            false,
            'deleted'
        );

        return [
            'deleted' => true,
            'message' => 'Layanan dihapus.',
        ];
    }

    public function validatedServiceData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(Service::CATEGORIES)],
            'unit' => ['required', Rule::in(Service::UNITS)],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], [], [
            'name' => 'nama layanan',
            'category' => 'jenis layanan',
            'unit' => 'satuan',
            'duration_minutes' => 'durasi layanan',
            'capacity' => 'kapasitas kuota layanan',
            'description' => 'deskripsi',
            'price' => 'harga',
        ]);

        $payload = [
            'name' => trim($data['name']),
            'category' => $data['category'],
            'unit' => $data['unit'],
            'duration_minutes' => (int) ($data['duration_minutes'] ?? 60),
            'description' => $data['description'] ?? null,
            'price' => (float) round((float) $data['price']),
            'status' => $request->boolean('status', true),
        ];

        if (Schema::hasColumn('services', 'capacity')) {
            $payload['capacity'] = (int) ($data['capacity'] ?? 5);
        }

        return $payload;
    }

    private function recordAudit(string $action, Service $service, array $before, array $after, int $actorId): void
    {
        if (Schema::hasTable('audit_logs')) {
            $this->audit->record($action, $service, $before, $after, $actorId);
        }
    }
}
