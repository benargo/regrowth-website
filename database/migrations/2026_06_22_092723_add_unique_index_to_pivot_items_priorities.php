<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'pivot_items_priorities';

    private const COLUMNS = ['item_id', 'priority_id'];

    private const INDEX_NAME = 'pivot_items_priorities_item_id_priority_id_unique';

    public function up(): void
    {
        $duplicateCount = DB::table(self::TABLE)
            ->select(self::COLUMNS)
            ->groupBy(self::COLUMNS)
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicateCount > 0) {
            throw new RuntimeException(
                "Cannot add unique index: {$duplicateCount} duplicate (item_id, priority_id) combination(s) exist in pivot_items_priorities. Remove duplicates manually before running this migration."
            );
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique(self::COLUMNS, self::INDEX_NAME);
        });
    }

    /**
     * Reverse the migration.
     *
     * MySQL cannot drop the unique index while any FK references it.
     * Fetch only the FKs on the columns that back our unique index (item_id, priority_id).
     * We must not blindly drop all FKs on the table in case others are added in future.
     * Drop the FKs, then the unique index, then restore the FKs
     */
    public function down(): void
    {
        if (! Schema::hasIndex(self::TABLE, self::INDEX_NAME)) {
            return;
        }

        $fks = collect(Schema::getForeignKeys(self::TABLE))->filter(function ($fk) {
            $columns = data_get($fk, 'columns');

            return count($columns) === 1
                && in_array(head($columns), self::COLUMNS, true);
        });

        // Drop the foreign keys
        $fks->each(function ($fk) {
            $fkName = data_get($fk, 'name');

            if ($fkName !== null) {
                DB::statement('ALTER TABLE `'.self::TABLE.'` DROP FOREIGN KEY `'.$fkName.'`');
            }
        });

        // Drop the unique index.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });

        // Restore the foreign keys
        $fks->each(function ($fk): void {
            Schema::table(self::TABLE, function (Blueprint $table) use ($fk): void {
                $table->foreign(head(data_get($fk, 'columns')), data_get($fk, 'name'))
                    ->references(head(data_get($fk, 'foreign_columns')))
                    ->on(data_get($fk, 'foreign_table'));
            });
        });
    }
};
