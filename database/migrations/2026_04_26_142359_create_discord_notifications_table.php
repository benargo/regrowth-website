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
        Schema::create('discord_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('channel_id');
            $table->string('message_id')->unique();
            $table->json('payload');
            $table->foreignId('replaces_notification_id')->nullable()->constrained('discord_notifications')->nullOnDelete();
            $table->string('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('discord_notifications');
    }
};
