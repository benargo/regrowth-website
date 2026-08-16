<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('pivot_events_characters', 'signup_status')) {
            Schema::table('pivot_events_characters', function (Blueprint $table) {
                $table->string('signup_status')->default('unconfirmed')->after('group_number');
            });
        }

        if (Schema::hasColumn('pivot_events_characters', 'is_confirmed')) {
            DB::table('pivot_events_characters')->where('is_confirmed', true)->update(['signup_status' => 'confirmed']);
            DB::table('pivot_events_characters')->where('is_confirmed', false)->update(['signup_status' => 'unconfirmed']);

            Schema::table('pivot_events_characters', function (Blueprint $table) {
                $table->dropColumn('is_confirmed');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('pivot_events_characters', 'is_confirmed')) {
            Schema::table('pivot_events_characters', function (Blueprint $table) {
                $table->boolean('is_confirmed')->default(false)->after('group_number');
            });
        }

        if (Schema::hasColumn('pivot_events_characters', 'signup_status')) {
            DB::table('pivot_events_characters')->where('signup_status', 'confirmed')->update(['is_confirmed' => true]);
            DB::table('pivot_events_characters')->where('signup_status', '!=', 'confirmed')->update(['is_confirmed' => false]);

            Schema::table('pivot_events_characters', function (Blueprint $table) {
                $table->dropColumn('signup_status');
            });
        }
    }
};
