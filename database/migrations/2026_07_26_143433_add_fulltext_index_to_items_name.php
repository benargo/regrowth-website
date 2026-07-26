<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Full-text indexes exist only on drivers listed in database.behaviours.full_text;
     * SQLite (used by the test suite) has no FULLTEXT support. Item::matchingName()
     * checks the same config and falls back to LIKE elsewhere.
     */
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), config('database.behaviours.full_text'), true)) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->fullText('name', 'items_name_fulltext');
        });
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), config('database.behaviours.full_text'), true)) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropFullText('items_name_fulltext');
        });
    }
};
