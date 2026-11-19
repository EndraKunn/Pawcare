<?php

namespace App\Modules\Endra\CatalogInventory\Livewire;

use App\Modules\Endra\CatalogInventory\Services\CatalogInventoryService;
use Illuminate\Http\Request;
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

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function updatedSpecies(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->q = '';
        $this->category = '';
        $this->species = '';
        $this->sort = 'name';
        $this->resetPage();
    }

    public function render(CatalogInventoryService $catalogService): View
    {
        $virtualRequest = Request::create('/bisnis', 'GET', [
            'q' => $this->q,
            'category' => $this->category,
            'species' => $this->species,
            'sort' => $this->sort,
        ]);

        $data = $catalogService->filterCatalog($virtualRequest);

        return view('endra-catalog::livewire.catalog-browser', $data);
    }
}
