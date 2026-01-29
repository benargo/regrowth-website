<?php

namespace App\Providers;

use App\Policies\DashboardPolicy;
use App\Policies\ItemCommentPolicy;
use App\Policies\ItemPolicy;
use App\Policies\ViewAsRolePolicy;
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

        /**
         * Authorization Gates
         */
        Gate::define('access-dashboard', [DashboardPolicy::class, 'access']);
        Gate::define('access-loot', [ItemPolicy::class, 'access']);
        Gate::define('edit-loot-items', [ItemPolicy::class, 'edit']);
        Gate::define('create-loot-comment', [ItemCommentPolicy::class, 'create']);
        Gate::define('delete-loot-comment', [ItemCommentPolicy::class, 'delete']);
        Gate::define('edit-loot-comment', [ItemCommentPolicy::class, 'update']);
        Gate::define('view-as-role', [ViewAsRolePolicy::class, 'viewAsRole']);
    }
}
