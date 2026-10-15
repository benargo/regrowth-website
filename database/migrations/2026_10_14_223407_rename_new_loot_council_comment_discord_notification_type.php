<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Point stored NewLootCouncilComment notifications at CommentPostedToItem.
     */
    public function up(): void
    {
        DB::table('discord_notifications')
            ->where('type', 'App\Notifications\NewLootCouncilComment')
            ->update(['type' => 'App\Notifications\CommentPostedToItem']);
    }
};
