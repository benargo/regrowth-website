<?php

namespace App\Providers;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\TBC\Phase;
use App\Models\WarcraftLogs\GuildTag;
use App\Policies\CommentPolicy;
use App\Policies\DashboardPolicy;
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
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Phase::class, PhasePolicy::class);

        /**
         * Authorization Gates
         */
        Gate::define('access-dashboard', [DashboardPolicy::class, 'access']);
        Gate::define('view-as-role', [ViewAsRolePolicy::class, 'viewAsRole']);
    }
}
