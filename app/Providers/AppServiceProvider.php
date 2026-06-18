<?php

namespace App\Providers;

use App\Models\User;
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
    public function register(): void {}

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
