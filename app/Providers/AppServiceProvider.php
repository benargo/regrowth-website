<?php

namespace App\Providers;

use App\Models\LootCouncil\ItemComment;
use App\Models\WarcraftLogs\GuildTag;
use App\Policies\DashboardPolicy;
use App\Policies\ItemCommentPolicy;
use App\Policies\ItemPolicy;
use App\Policies\PhasePolicy;
use App\Policies\ViewAsRolePolicy;
use App\Policies\WclGuildTagPolicy;
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
         * Policies
         */
        Gate::policy(GuildTag::class, WclGuildTagPolicy::class);
        Gate::policy(ItemComment::class, ItemCommentPolicy::class);

        /**
         * Authorization Gates
         */
        Gate::define('access-dashboard', [DashboardPolicy::class, 'access']);
        Gate::define('access-loot', [ItemPolicy::class, 'access']);
        Gate::define('edit-loot-items', [ItemPolicy::class, 'edit']);
        Gate::define('view-as-role', [ViewAsRolePolicy::class, 'viewAsRole']);
        Gate::define('update-phase', [PhasePolicy::class, 'update']);
    }
}
