<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, string> */
    private const FULL_TEXT_DRIVERS = ['mysql', 'mariadb', 'pgsql'];

    /**
     * Full-text indexes exist only on MariaDB, MySQL and PostgreSQL; SQLite (used by
     * the test suite) has no FULLTEXT support. Item::matchingName() branches on the
     * same driver list and falls back to LIKE elsewhere.
     */
    public function up(): void
    {
        if (! in_array(DB::connection()->getDriverName(), self::FULL_TEXT_DRIVERS, true)) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->fullText('name', 'items_name_fulltext');
        });
    }

    public function down(): void
    {
        if (! in_array(DB::connection()->getDriverName(), self::FULL_TEXT_DRIVERS, true)) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropFullText('items_name_fulltext');
        });
    }
};
