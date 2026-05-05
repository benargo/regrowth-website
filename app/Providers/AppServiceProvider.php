<?php

namespace App\Providers;

use App\Models\GuildRank;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\Raids\Report;
use App\Models\TBC\Phase;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Policies\CommentPolicy;
use App\Policies\DatasetPolicy;
use App\Policies\ItemPolicy;
use App\Policies\ReportPolicy;
use Illuminate\Cache\RateLimiting\Limit;
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
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(GuildRank::class, DatasetPolicy::class);
        Gate::policy(GuildTag::class, DatasetPolicy::class);
        Gate::policy(Item::class, ItemPolicy::class);
        Gate::policy(Phase::class, DatasetPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);

        /**
         * Authorization Gates
         */
        Gate::define('impersonate-roles', fn (User $user) => $user->hasPermissionViaDiscordRoles('impersonate-roles'));
        Gate::define('view-attendance', fn (User $user) => $user->hasPermissionViaDiscordRoles('view-attendance'));
        Gate::define('view-officer-dashboard', fn (User $user) => $user->hasPermissionViaDiscordRoles('view-officer-dashboard'));
        Gate::define('set-daily-quests', fn (User $user) => $user->hasPermissionViaDiscordRoles('set-daily-quests'));
        Gate::define('audit-daily-quests', fn (User $user) => $user->hasPermissionViaDiscordRoles('audit-daily-quests'));

        /**
         * Rate limiting
         */
        RateLimiter::for('build-addon-export', function (object $job) {
            return Limit::perMinutes(10, 1)->by(get_class($job)); // Allow 1 job per 10 minutes for each type of export job
        });
    }
}
