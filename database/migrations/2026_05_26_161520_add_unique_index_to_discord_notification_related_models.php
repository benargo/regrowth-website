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
            $table->unique(
                ['discord_notification_id', 'model_type', 'model_id'],
                'dnrm_notification_model_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discord_notification_related_models', function (Blueprint $table) {
            $table->dropUnique('dnrm_notification_model_unique');
        });
    }
};
