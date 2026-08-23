<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'pivot_items_priorities';

    private const COLUMNS = ['item_id', 'priority_id'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->swapForeignKeys(cascade: true);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->swapForeignKeys(cascade: false);
    }

    /**
     * Drop the existing foreign keys on item_id and priority_id, then recreate
     * them with (or without) an ON DELETE CASCADE.
     *
     * The FK names are looked up dynamically rather than hardcoded — this
     * table's FKs still carry names from before it was renamed from
     * lootcouncil_item_priorities, so the conventional Laravel name doesn't
     * match what's actually in the database.
     */
    private function swapForeignKeys(bool $cascade): void
    {
        $fks = collect(Schema::getForeignKeys(self::TABLE))->filter(function ($fk) {
            $columns = data_get($fk, 'columns');

            return count($columns) === 1
                && in_array(head($columns), self::COLUMNS, true);
        });

        $fks->each(function ($fk) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($fk) {
                $table->dropForeign(data_get($fk, 'name'));
            });
        });

        $fks->each(function ($fk) use ($cascade) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($fk, $cascade) {
                $foreign = $table->foreign(head(data_get($fk, 'columns')))
                    ->references(head(data_get($fk, 'foreign_columns')))
                    ->on(data_get($fk, 'foreign_table'));

                if ($cascade) {
                    $foreign->cascadeOnDelete();
                }
            });
        });
    }
};
