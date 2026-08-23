<?php

namespace App\Providers;

use App\Http\Resources\PermissionGroupsResource;
use App\Models\User;
use App\Services\LootPriorities\HighestPriorityStats;
use Database\Seeders\PermissionSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /**
         * Permission groups
         */
        $this->app->bind(PermissionGroupsResource::class, function () {
            return new PermissionGroupsResource(collect(PermissionSeeder::groups()));
        });

        /**
         * Loot priorities
         */
        $this->app->singleton(HighestPriorityStats::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Builder::macro('whereNone', function (): static {
            /** @var Builder $this */
            return $this->whereRaw('1 = 0');
        });

        /**
         * Authorization Gates
         */
        Gate::define('edit-datasets', fn (User $user) => $user->isAuthorizedTo('edit-datasets'));
        Gate::define('impersonate-roles', fn (User $user) => $user->isAuthorizedTo('impersonate-roles'));
        Gate::define('view-attendance', fn (User $user) => $user->isAuthorizedTo('view-attendance'));
        Gate::define('view-officer-dashboard', fn (User $user) => $user->isAuthorizedTo('view-officer-dashboard'));
        Gate::define('view-priorities-page', fn (User $user) => $user->isAuthorizedTo('view-priorities-page'));
        Gate::define('set-daily-quests', fn (User $user) => $user->isAuthorizedTo('set-daily-quests'));
        Gate::define('audit-daily-quests', fn (User $user) => $user->isAuthorizedTo('audit-daily-quests'));

        /**
         * Rate limiting
         */
        RateLimiter::for('build-addon-export', function (object $job) {
            return Limit::perMinutes(10, 1)->by(get_class($job)); // Allow 1 job per 10 minutes for each type of export job
        });

        RateLimiter::for('icons', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
