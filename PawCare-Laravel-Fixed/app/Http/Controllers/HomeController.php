<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Pet;
use App\Models\Service;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $query = Business::active()
            ->with('owner:id,name')
            ->withMin(['services as min_price' => fn ($q) => $q->active()], 'price');

        if (Schema::hasTable('reviews')) {
            $query->withAvg(['reviews as rating_avg' => fn ($r) => $r->validCompleted()], 'rating')
                ->withCount(['reviews as reviews_count' => fn ($r) => $r->validCompleted()]);
        }

        $businesses = $query->latest()->take(3)->get();

        return view('public.home', compact('businesses'));
    }

    public function catalog(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('category', ''));
        $species = trim((string) $request->query('species', ''));

        $validCategory = in_array($category, Service::CATEGORIES, true) ? $category : '';
        $validSpecies = in_array($species, Pet::SPECIES, true) ? $species : '';

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

        $businesses = $query
            ->when($q !== '', function ($b) use ($q) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q));
                $keyword = "%{$escaped}%";

                $b->where(function ($w) use ($keyword) {
                    $w->whereRaw("LOWER(COALESCE(business_name, '')) LIKE ? ESCAPE '\\'", [$keyword])
                        ->orWhereRaw("LOWER(COALESCE(address, '')) LIKE ? ESCAPE '\\'", [$keyword]);
                });
            })
            ->when(
                $validCategory !== '',
                fn ($b) => $b->whereHas('services', fn ($s) => $s->active()->where('category', $validCategory))
            )
            ->when(
                $validSpecies !== '',
                fn ($b) => $b->acceptsSpecies($validSpecies)
            )
            ->orderBy('business_name')
            ->paginate(9)
            ->withQueryString();

        return view('public.catalog', [
            'businesses' => $businesses,
            'q' => $q,
            'category' => $validCategory,
            'species' => $validSpecies,
        ]);
    }

    public function showBusiness(Business $business, ReportService $reports): View
    {
        abort_unless($business->status, 404);

        $business->load([
            'owner:id,name',
            'operatingHours',
            'closures',
        ]);
        $services = $business->services()->active()->orderBy('category')->orderBy('name')->get();

        $ratingSummary = $reports->ratingSummary([$business->id]);
        $reviews = Schema::hasTable('reviews')
            ? $business->reviews()
                ->validCompleted()
                ->with(['customer:id,name', 'reply.owner:id,name', 'booking.items.service:id,name'])
                ->latest('id')
                ->paginate(5)
                ->withQueryString()
            : collect();

        return view('public.business', compact('business', 'services', 'ratingSummary', 'reviews'));
    }
}
