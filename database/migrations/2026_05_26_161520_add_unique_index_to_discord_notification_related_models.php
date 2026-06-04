<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discord_notification_related_models', function (Blueprint $table) {
            if (! Schema::hasIndex('discord_notification_related_models', 'dnrm_notification_model_unique')) {
                $table->unique(
                    ['discord_notification_id', 'model_type', 'model_id'],
                    'dnrm_notification_model_unique'
                );
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the FK first in a separate statement — MySQL won't drop the unique index
        // while it's the only index covering discord_notification_id (needed by the FK).
        Schema::table('discord_notification_related_models', function (Blueprint $table) {
            if (collect(Schema::getForeignKeys('discord_notification_related_models'))->pluck('name')->contains('dnrm_discord_notification_id_foreign')) {
                $table->dropForeign('dnrm_discord_notification_id_foreign');
            }
        });

        Schema::table('discord_notification_related_models', function (Blueprint $table) {
            if (Schema::hasIndex('discord_notification_related_models', 'dnrm_notification_model_unique')) {
                $table->dropUnique('dnrm_notification_model_unique');
            }
        });

        Schema::table('discord_notification_related_models', function (Blueprint $table) {
            if (! collect(Schema::getForeignKeys('discord_notification_related_models'))->pluck('name')->contains('dnrm_discord_notification_id_foreign')) {
                $table->foreign('discord_notification_id', 'dnrm_discord_notification_id_foreign')
                    ->references('id')
                    ->on('discord_notifications')
                    ->cascadeOnDelete();
            }
        });
    }
};
