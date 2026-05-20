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
        Schema::create('discord_notification_related_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discord_notification_id')
                ->constrained('discord_notifications', indexName: 'dnrm_discord_notification_id_foreign')
                ->cascadeOnDelete();
            $table->string('model_type');
            $table->string('model_id');
        });

        Schema::table('discord_notifications', function (Blueprint $table) {
            $table->dropColumn('related_models');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_notification_related_models');

        Schema::table('discord_notifications', function (Blueprint $table) {
            $table->json('related_models')->nullable();
        });
    }
};
