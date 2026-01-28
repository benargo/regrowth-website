<?php

namespace App\Providers;

use App\Policies\DashboardPolicy;
use App\Policies\LootPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::define('access-dashboard', [DashboardPolicy::class, 'access']);
        Gate::define('access-loot', [LootPolicy::class, 'access']);
        Gate::define('edit-loot-priorities', [LootPolicy::class, 'edit']);
    }
}
