<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Delete daily quest notifications, media and permissions, then drop the daily quests tables.
     */
    public function up(): void
    {
        DB::table('discord_notification_related_models')
            ->where('model_type', 'App\Models\DailyQuest')
            ->delete();

        DB::table('discord_notifications')
            ->where('type', 'App\Notifications\DailyQuestsMessage')
            ->delete();

        DB::table('media')
            ->where('model_type', 'App\Models\DailyQuest')
            ->delete();

        DB::table('permissions')
            ->whereIn('name', ['set-daily-quests', 'view-daily-quests', 'audit-daily-quests'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('pivot_dailyquest_rewards');
        Schema::dropIfExists('daily_quests');
    }
};
