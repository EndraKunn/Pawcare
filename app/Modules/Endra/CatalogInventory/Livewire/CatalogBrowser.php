<?php

namespace App\Modules\Endra\CatalogInventory\Livewire;

use App\Models\Business;
use App\Models\Pet;
use App\Models\Service;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogBrowser extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    #[Url(as: 'species', except: '')]
    public string $species = '';

    #[Url(as: 'sort', except: 'name')]
    public string $sort = 'name';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'category', 'species', 'sort']);
        $this->sort = 'name';
        $this->resetPage();
    }

    public function render(): View
    {
        $q = trim($this->q);
        $validCategory = in_array($this->category, Service::CATEGORIES, true) ? $this->category : '';
        $validSpecies = in_array($this->species, Pet::SPECIES, true) ? $this->species : '';
        $validSort = in_array($this->sort, ['name', 'price_asc', 'rating_desc'], true) ? $this->sort : 'name';

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
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q));
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

        return view('endra-catalog::livewire.catalog-browser', [
            'businesses' => $query->paginate(9),
            'activeCategory' => $validCategory,
            'activeSpecies' => $validSpecies,
            'activeSort' => $validSort,
        ]);
    }
}