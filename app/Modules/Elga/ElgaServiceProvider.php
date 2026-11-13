<?php

namespace App\Modules\Elga;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ElgaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (['Auth' => 'elga-auth', 'Business' => 'elga-business', 'Admin' => 'elga-admin'] as $directory => $namespace) {
            $root = __DIR__.DIRECTORY_SEPARATOR.$directory;
            if (is_dir($root.'/Resources/views')) {
                $this->loadViewsFrom($root.'/Resources/views', $namespace);
            }
            if (is_file($root.'/Routes/web.php') && ! $this->app->routesAreCached()) {
                Route::middleware('web')->group($root.'/Routes/web.php');
            }
        }
    }
}
