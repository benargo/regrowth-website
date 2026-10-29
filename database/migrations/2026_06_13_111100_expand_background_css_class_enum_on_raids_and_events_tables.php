<?php

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

        $values = collect(['bg-raid-karazhan', 'bg-raid-gruul', 'bg-raid-magtheridon', 'bg-raid-serpentshrine-cavern', 'bg-raid-tempest-keep', 'bg-raid-hyjal-summit', 'bg-raid-black-temple', 'bg-raid-zulaman', 'bg-raid-sunwell-plateau', 'bg-raid-gruul-magtheridon', 'bg-ssctk'])
            ->map(fn (string $value): string => "'{$value}'")
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
