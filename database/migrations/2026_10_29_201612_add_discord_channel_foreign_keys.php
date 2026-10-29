<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->backfillStubChannels();
        $this->addForeignKeys();
    }

    /**
     * Insert an ID-only stub channel for every distinct channel ID on events and Discord messages that is not stored.
     */
    private function backfillStubChannels(): void
    {
        $now = now();

        DB::table('events')->whereNotNull('channel_id')->distinct()->pluck('channel_id')
            ->merge(DB::table('discord_messages')->whereNotNull('channel_id')->distinct()->pluck('channel_id'))
            ->unique()
            ->map(fn (string $channelId): array => ['id' => $channelId, 'created_at' => $now, 'updated_at' => $now])
            ->chunk(500)
            ->each(fn ($rows) => DB::table('discord_channels')->insertOrIgnore($rows->values()->all()));
    }

    /**
     * Point events and Discord messages at their stored channel.
     */
    private function addForeignKeys(): void
    {
        foreach (['events', 'discord_messages'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreign('channel_id')->references('id')->on('discord_channels')->restrictOnDelete();
            });
        }
    }
};
