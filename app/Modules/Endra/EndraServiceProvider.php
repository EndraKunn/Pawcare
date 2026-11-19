<?php

namespace App\Modules\Endra;

use App\Modules\Endra\CatalogInventory\Livewire\CatalogBrowser;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EndraServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningUnitTests()) {
            config(['broadcasting.default' => 'null']);
        }

        foreach ([
            'PetHealth' => 'endra-pet-health',
            'CatalogInventory' => 'endra-catalog',
        ] as $directory => $namespace) {
            $root = __DIR__.DIRECTORY_SEPARATOR.$directory;

            if (is_dir($root.'/Resources/views')) {
                $this->loadViewsFrom($root.'/Resources/views', $namespace);
            }

            if (is_file($root.'/Routes/web.php') && ! $this->app->routesAreCached()) {
                Route::middleware('web')->group($root.'/Routes/web.php');
            }
        }

        if (class_exists(\Livewire\Livewire::class) && class_exists(CatalogBrowser::class)) {
            \Livewire\Livewire::component('endra-catalog-browser', CatalogBrowser::class);
        }
    }
}
