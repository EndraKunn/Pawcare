<?php

namespace App\Modules\Jeni\BookingCalendar;

use App\Models\Booking;
use App\Modules\Jeni\BookingCalendar\Services\PreorderPolicyService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Adds calendar routes and keeps preorder rules in one place for every booking flow. */
class BookingCalendarServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'jeni-booking');

        if (! $this->app->routesAreCached()) {
            Route::middleware('web')->group(__DIR__.'/Routes/web.php');
        }

        // Every booking path (the legacy form and Atomic Checkout) creates an Eloquent
        // Booking. Validate future preorder capacity under a shared business row lock.
        Booking::creating(function (Booking $booking): void {
            app(PreorderPolicyService::class)->validateAndMark($booking);
        });
    }
}
