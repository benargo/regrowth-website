<?php

namespace App\Providers;

use App\Actions\EventBossResolver;
use App\Enums\GuildRosterManager\UploadSource;
use App\Http\Integrations\ServedFromCache;
use App\Jobs\FetchGuildRoster;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /**
         * Saloon response cache hits
         */
        $this->app->singleton(ServedFromCache::class);

        /**
         * Event boss resolution
         */
        $this->app->singleton(EventBossResolver::class);

        /**
         * Passport OAuth server routes
         */
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        /**
         * Passport scopes and token lifetimes
         */
        Passport::tokensCan([
            'grm:upload' => 'Upload Guild Roster Manager data',
            'grm:targets' => 'Read the rosters to sync',
        ]);
        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(90));
        Passport::authorizationView(fn (array $parameters) => Inertia::render('Auth/Desktop/Authorize', [
            'clientName' => $parameters['client']->name,
            'scopes' => collect($parameters['scopes'])
                ->map(fn ($scope) => ['id' => $scope->id, 'description' => $scope->description])
                ->values()
                ->all(),
            'authToken' => $parameters['authToken'],
            'csrfToken' => csrf_token(),
        ]));

        Builder::macro('whereNone', function (): static {
            /** @var Builder $this */
            return $this->whereRaw('1 = 0');
        });

        /**
         * Authorization Gates
         */
        Gate::define('edit-datasets', fn (User $user) => $user->isAuthorizedTo('edit-datasets'));
        Gate::define('impersonate-roles', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-handbook', fn (User $user) => $user->isAuthorizedTo('manage-handbook'));
        Gate::define('manage-permissions', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-attendance', fn (User $user) => $user->isAuthorizedTo('view-attendance'));
        Gate::define('view-handbook', fn (User $user) => $user->isAuthorizedTo('view-handbook'));
        Gate::define('view-officer-dashboard', fn (User $user) => $user->isAuthorizedTo('view-officer-dashboard'));
        Gate::define('view-priorities-page', fn (User $user) => $user->isAuthorizedTo('view-priorities-page'));

        Route::bind('sync', fn (string $value) => Upload::query()
            ->whereKey($value)
            ->where('source', UploadSource::Desktop)
            ->where('user_id', request()->user()?->getAuthIdentifier())
            ->first() ?? abort(404));

        /**
         * Rate limiting
         */
        RateLimiter::for('grm-syncs', fn (Request $request) => Limit::perHour(24)->by((string) $request->user()?->id));

        RateLimiter::for('grm-sync-requests', fn (Request $request) => Limit::perMinute(300)->by((string) $request->user()?->id));

        RateLimiter::for('fetch-guild-roster-job', function (FetchGuildRoster $job) {
            return Limit::perHour(1)->by((string) $job->gameVersionId);
        });
    }
}
