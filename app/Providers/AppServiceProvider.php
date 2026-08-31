<?php

namespace App\Providers;

use App\Support\SlotSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The slot grid is derived from config/venue.php, so one instance per
        // request is plenty and every consumer agrees on the same grid.
        $this->app->singleton(SlotSchedule::class, fn () => SlotSchedule::fromConfig());
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // A booking that silently drops an attribute because of a typo is a
        // booking that quietly loses a customer's phone number.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}
