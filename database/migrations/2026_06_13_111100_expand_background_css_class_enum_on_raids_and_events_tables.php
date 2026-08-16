<?php

use App\Enums\RaidBackground;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite has no enum validation
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        $values = collect(RaidBackground::cases())
            ->map(fn ($case) => "'{$case->value}'")
            ->implode(',');

        foreach (['raids', 'events'] as $table) {
            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `background_css_class` ENUM({$values}) NULL");
        }
    }

    public function down(): void
    {
        // Shrinking an ENUM is unsafe if rows already use the new values.
    }
};
