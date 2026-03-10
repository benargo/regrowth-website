<?php

namespace App\Providers;

use App\Http\Resources\PermissionGroupsResource;
use App\Models\TBC\DailyQuestNotification;
use App\Models\GuildRank;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\TBC\Phase;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Report;
use App\Policies\CommentPolicy;
use App\Policies\DailyQuestNotificationPolicy;
use App\Policies\DatasetPolicy;
use App\Policies\ItemPolicy;
use App\Policies\ReportPolicy;
use Database\Seeders\PermissionSeeder;
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
        /**
         * Permission groups
         */
        $this->app->bind(PermissionGroupsResource::class, function () {
            return new PermissionGroupsResource(collect(PermissionSeeder::groups()));
        });
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
        Gate::policy(DailyQuestNotification::class, DailyQuestNotificationPolicy::class);
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
    }
}
