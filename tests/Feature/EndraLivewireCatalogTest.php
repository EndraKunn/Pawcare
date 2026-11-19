<?php

namespace Tests\Feature;

use App\Modules\Endra\CatalogInventory\Livewire\CatalogBrowser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesPawCareData;
use Tests\TestCase;

class EndraLivewireCatalogTest extends TestCase
{
    use RefreshDatabase, CreatesPawCareData;

    public function test_catalog_page_renders_livewire_catalog_browser_component(): void
    {
        [, $business] = $this->makeOwnerWithBusiness('PawCare Livewire');
        $this->makeService($business, 75000, ['category' => 'Grooming']);

        $this->get(route('catalog'))
            ->assertOk()
            ->assertSeeLivewire(CatalogBrowser::class)
            ->assertSee($business->business_name);
    }

    public function test_livewire_catalog_browser_filters_reactively_by_keyword_category_species_and_sort(): void
    {
        [, $bizAlpha] = $this->makeOwnerWithBusiness('Alpha Pet Hotel');
        $bizAlpha->update([
            'address' => 'Jl. Ahmad Yani, Banyuwangi',
            'accepted_species' => ['Kucing', 'Kelinci'],
        ]);
        $this->makeService($bizAlpha, 60000, ['name' => 'Grooming Kucing', 'category' => 'Grooming']);

        [, $bizBeta] = $this->makeOwnerWithBusiness('Beta Klinik Anjing');
        $bizBeta->update([
            'address' => 'Jl. Sudirman, Surabaya',
            'accepted_species' => ['Anjing'],
        ]);
        $this->makeService($bizBeta, 180000, ['name' => 'Hydrotherapy Anjing', 'category' => 'Perawatan']);

        Livewire::test(CatalogBrowser::class)
            ->assertSee($bizAlpha->business_name)
            ->assertSee($bizBeta->business_name)
            ->set('q', 'Banyuwangi')
            ->assertSee($bizAlpha->business_name)
            ->assertDontSee($bizBeta->business_name)
            ->call('resetFilters')
            ->set('category', 'Perawatan')
            ->assertSee($bizBeta->business_name)
            ->assertDontSee($bizAlpha->business_name)
            ->call('resetFilters')
            ->set('species', 'Kelinci')
            ->assertSee($bizAlpha->business_name)
            ->assertDontSee($bizBeta->business_name)
            ->call('resetFilters')
            ->set('sort', 'price_asc')
            ->assertSeeInOrder([$bizAlpha->business_name, $bizBeta->business_name]);
    }
}
