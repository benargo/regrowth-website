<?php

use App\Models\GameVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TABLE = 'items';

    private const FULLTEXT_INDEX = 'items_name_fulltext';

    /**
     * Tables with an inbound `item_id` foreign key to `items.id`.
     *
     * Task 3 of this plan is responsible for repointing these columns to the
     * new uuid `items.id` (they stay plain, unconstrained bigint columns
     * referencing the old blizzard_id values until then). This migration
     * only needs to drop the FKs so MariaDB allows the type change on
     * `items.id` — it cannot ALTER a column that other tables still hold a
     * foreign key against.
     *
     * @var array<int, string>
     */
    private const INBOUND_FK_TABLES = ['pivot_items_priorities', 'pivot_dailyquest_rewards', 'pivot_items_raids'];

    public function up(): void
    {
        $hasFullText = in_array(DB::connection()->getDriverName(), config('database.behaviours.full_text'), true);

        if ($hasFullText) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropFullText(self::FULLTEXT_INDEX);
            });
        }

        $this->dropInboundItemForeignKeys();

        // MariaDB/MySQL refuse to drop a primary key while its column is
        // still auto_increment (a table can never have an auto_increment
        // column with no key). Laravel compiles each of these into its own
        // ALTER TABLE statement, so ordering the calls this way — remove
        // auto_increment via the MODIFY first, then DROP PRIMARY KEY second —
        // keeps every intermediate statement valid on its own.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->change();
            $table->dropPrimary();
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->renameColumn('id', 'blizzard_id');
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->first();
        });

        DB::table(self::TABLE)->whereNull('uuid')->orderBy('blizzard_id')
            ->chunkById(500, function (Collection $items): void {
                foreach ($items as $item) {
                    DB::table(self::TABLE)
                        ->where('blizzard_id', $item->blizzard_id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            }, 'blizzard_id');

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->renameColumn('uuid', 'id');
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->primary('id');
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->foreignIdFor(GameVersion::class)->nullable()->after('blizzard_id')->constrained()->nullOnDelete();
            $table->unique(['game_version_id', 'blizzard_id']);
        });

        if ($hasFullText) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->fullText('name', self::FULLTEXT_INDEX);
            });
        }
    }

    public function down(): void
    {
        $hasFullText = in_array(DB::connection()->getDriverName(), config('database.behaviours.full_text'), true);

        if ($hasFullText) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->dropFullText(self::FULLTEXT_INDEX);
            });
        }

        // The FK on game_version_id, the composite unique index backing it,
        // and the column itself must each be dropped in their own ALTER
        // TABLE statement and in this order — MariaDB refuses to drop an
        // index (or a column) that a foreign key constraint still relies on,
        // even across commands within the same batch.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropForeign(['game_version_id']);
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(['game_version_id', 'blizzard_id']);
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn('game_version_id');
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropPrimary();
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->renameColumn('id', 'uuid');
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->renameColumn('blizzard_id', 'id');
        });

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropColumn('uuid');
        });

        // MariaDB refuses to make a column auto_increment while it has no
        // key, so the primary key must be added back before (not after) the
        // MODIFY statement that adds auto_increment — the mirror image of
        // the ordering in up(), where auto_increment is removed before the
        // primary key is dropped.
        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->primary('id');
            $table->unsignedBigInteger('id')->autoIncrement()->change();
        });

        $this->restoreInboundItemForeignKeys();

        if ($hasFullText) {
            Schema::table(self::TABLE, function (Blueprint $table): void {
                $table->fullText('name', self::FULLTEXT_INDEX);
            });
        }
    }

    /**
     * Drop the FK constraint on `item_id` for every table in
     * INBOUND_FK_TABLES, looked up dynamically rather than assuming the
     * conventional Laravel name — some of these tables carry FK names
     * inherited from before they were renamed (see
     * `pivot_items_priorities`'s history), so the constraint name can't be
     * assumed to match the current table/column names.
     */
    private function dropInboundItemForeignKeys(): void
    {
        foreach (self::INBOUND_FK_TABLES as $table) {
            $foreignKey = collect(Schema::getForeignKeys($table))
                ->first(fn (array $fk): bool => $fk['columns'] === ['item_id']);

            if ($foreignKey === null) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($foreignKey): void {
                $blueprint->dropForeign($foreignKey['name']);
            });
        }
    }

    /**
     * Reverse dropInboundItemForeignKeys(): recreate the original bigint FKs
     * from `item_id` to `items.id`, now that `items.id` is a bigint
     * auto-increment column again.
     *
     * This intentionally restores the same cascadeOnDelete() shape each
     * table had before this migration ran (see the Interfaces note on the
     * originating migrations for each table).
     */
    private function restoreInboundItemForeignKeys(): void
    {
        foreach (self::INBOUND_FK_TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table): void {
                $blueprint->foreign('item_id', $table.'_item_id_foreign')
                    ->references('id')->on(self::TABLE)
                    ->cascadeOnDelete();
            });
        }
    }
};
