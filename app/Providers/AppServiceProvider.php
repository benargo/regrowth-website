<?php

namespace App\Providers;

use App\Actions\EventBossResolver;
use App\Http\Integrations\ServedFromCache;
use App\Http\Resources\PermissionGroupsResource;
use App\Jobs\FetchGuildRoster;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
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
         * Saloon response cache hits
         */
        $this->app->singleton(ServedFromCache::class);

        /**
         * Event boss resolution
         */
        $this->app->singleton(EventBossResolver::class);
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
        Gate::define('manage-raid-information', fn (User $user) => $user->isAuthorizedTo('manage-raid-information'));
        Gate::define('view-attendance', fn (User $user) => $user->isAuthorizedTo('view-attendance'));
        Gate::define('view-officer-dashboard', fn (User $user) => $user->isAuthorizedTo('view-officer-dashboard'));
        Gate::define('view-priorities-page', fn (User $user) => $user->isAuthorizedTo('view-priorities-page'));
        Gate::define('view-raid-information', fn (User $user) => $user->isAuthorizedTo('view-raid-information'));

        /**
         * Rate limiting
         */
        RateLimiter::for('fetch-guild-roster-job', function (FetchGuildRoster $job) {
            return Limit::perHour(1)->by((string) $job->gameVersionId);
        });
    }
}
