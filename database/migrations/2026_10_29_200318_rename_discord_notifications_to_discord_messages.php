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
        Schema::table('discord_notification_related_models', function (Blueprint $table): void {
            $table->dropForeign('dnrm_discord_notification_id_foreign');
            $table->dropUnique('dnrm_notification_model_unique');
            $table->dropIndex('dnrm_model_type_model_id_notification_index');
        });

        Schema::rename('discord_notifications', 'discord_messages');

        Schema::rename('discord_notification_related_models', 'discord_message_related_models');

        Schema::table('discord_message_related_models', function (Blueprint $table): void {
            $table->renameColumn('discord_notification_id', 'discord_message_id');
        });

        Schema::table('discord_message_related_models', function (Blueprint $table): void {
            $table->unique(['discord_message_id', 'model_type', 'model_id'], 'dmrm_message_model_unique');
            $table->index(['model_type', 'model_id', 'discord_message_id'], 'dmrm_model_type_model_id_message_index');
            $table->foreign('discord_message_id', 'dmrm_discord_message_id_foreign')
                ->references('id')
                ->on('discord_messages')
                ->cascadeOnDelete();
        });

        Schema::table('discord_messages', function (Blueprint $table): void {
            $table->dropForeign('discord_notifications_created_by_user_id_foreign');
        });

        if (Schema::hasIndex('discord_messages', 'discord_notifications_created_by_user_id_foreign')) {
            Schema::table('discord_messages', function (Blueprint $table): void {
                $table->renameIndex('discord_notifications_created_by_user_id_foreign', 'discord_messages_created_by_user_id_foreign');
            });
        }

        Schema::table('discord_messages', function (Blueprint $table): void {
            $table->foreign('created_by_user_id', 'discord_messages_created_by_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        if (Schema::hasIndex('discord_messages', 'discord_notifications_message_id_unique')) {
            Schema::table('discord_messages', function (Blueprint $table): void {
                $table->renameIndex('discord_notifications_message_id_unique', 'discord_messages_message_id_unique');
            });
        }
    }
};
