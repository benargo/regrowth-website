<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->repointPivotItemsRaids();
        $this->repointPivotItemsPriorities();
        $this->repointPivotDailyquestRewards();
    }

    private function repointPivotItemsRaids(): void
    {
        $this->dropItemIdForeignKey('pivot_items_raids');

        Schema::table('pivot_items_raids', function (Blueprint $table): void {
            $table->dropPrimary(['item_id', 'raid_id']);
        });

        Schema::table('pivot_items_raids', function (Blueprint $table): void {
            $table->uuid('item_uuid')->nullable()->after('item_id');
        });

        DB::table('pivot_items_raids')
            ->join('items', 'pivot_items_raids.item_id', '=', 'items.blizzard_id')
            ->update(['pivot_items_raids.item_uuid' => DB::raw('items.id')]);

        $orphans = DB::table('pivot_items_raids')->whereNull('item_uuid')->count();

        if ($orphans > 0) {
            throw new RuntimeException("Cannot repoint pivot_items_raids: {$orphans} row(s) reference an item_id with no matching items.blizzard_id.");
        }

        Schema::table('pivot_items_raids', function (Blueprint $table): void {
            $table->dropColumn('item_id');
        });

        Schema::table('pivot_items_raids', function (Blueprint $table): void {
            $table->renameColumn('item_uuid', 'item_id');
        });

        Schema::table('pivot_items_raids', function (Blueprint $table): void {
            $table->uuid('item_id')->nullable(false)->change();
            $table->primary(['item_id', 'raid_id']);
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
        });
    }

    private function repointPivotItemsPriorities(): void
    {
        $this->dropItemIdForeignKey('pivot_items_priorities');

        Schema::table('pivot_items_priorities', function (Blueprint $table): void {
            $table->dropUnique('pivot_items_priorities_item_id_priority_id_unique');
        });

        Schema::table('pivot_items_priorities', function (Blueprint $table): void {
            $table->uuid('item_uuid')->nullable()->after('item_id');
        });

        DB::table('pivot_items_priorities')
            ->join('items', 'pivot_items_priorities.item_id', '=', 'items.blizzard_id')
            ->update(['pivot_items_priorities.item_uuid' => DB::raw('items.id')]);

        $orphans = DB::table('pivot_items_priorities')->whereNull('item_uuid')->count();

        if ($orphans > 0) {
            throw new RuntimeException("Cannot repoint pivot_items_priorities: {$orphans} row(s) reference an item_id with no matching items.blizzard_id.");
        }

        Schema::table('pivot_items_priorities', function (Blueprint $table): void {
            $table->dropColumn('item_id');
        });

        Schema::table('pivot_items_priorities', function (Blueprint $table): void {
            $table->renameColumn('item_uuid', 'item_id');
        });

        Schema::table('pivot_items_priorities', function (Blueprint $table): void {
            $table->uuid('item_id')->nullable(false)->change();
            $table->unique(['item_id', 'priority_id'], 'pivot_items_priorities_item_id_priority_id_unique');
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
        });
    }

    private function repointPivotDailyquestRewards(): void
    {
        $this->dropItemIdForeignKey('pivot_dailyquest_rewards');

        // The composite primary key (daily_quest_id, item_id) is currently the
        // only index covering daily_quest_id, so MariaDB refuses to drop it
        // while the live daily_quest_id FK still relies on it for support.
        // Add a throwaway index to keep that FK supported across the gap,
        // then drop it once the primary key is rebuilt below.
        Schema::table('pivot_dailyquest_rewards', function (Blueprint $table): void {
            $table->index('daily_quest_id', 'pivot_dailyquest_rewards_daily_quest_id_temp');
        });

        Schema::table('pivot_dailyquest_rewards', function (Blueprint $table): void {
            $table->dropPrimary(['daily_quest_id', 'item_id']);
        });

        Schema::table('pivot_dailyquest_rewards', function (Blueprint $table): void {
            $table->uuid('item_uuid')->nullable()->after('item_id');
        });

        DB::table('pivot_dailyquest_rewards')
            ->join('items', 'pivot_dailyquest_rewards.item_id', '=', 'items.blizzard_id')
            ->update(['pivot_dailyquest_rewards.item_uuid' => DB::raw('items.id')]);

        $orphans = DB::table('pivot_dailyquest_rewards')->whereNull('item_uuid')->count();

        if ($orphans > 0) {
            throw new RuntimeException("Cannot repoint pivot_dailyquest_rewards: {$orphans} row(s) reference an item_id with no matching items.blizzard_id.");
        }

        Schema::table('pivot_dailyquest_rewards', function (Blueprint $table): void {
            $table->dropColumn('item_id');
        });

        Schema::table('pivot_dailyquest_rewards', function (Blueprint $table): void {
            $table->renameColumn('item_uuid', 'item_id');
        });

        Schema::table('pivot_dailyquest_rewards', function (Blueprint $table): void {
            $table->uuid('item_id')->nullable(false)->change();
            $table->primary(['daily_quest_id', 'item_id']);
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
        });

        Schema::table('pivot_dailyquest_rewards', function (Blueprint $table): void {
            $table->dropIndex('pivot_dailyquest_rewards_daily_quest_id_temp');
        });
    }

    /**
     * Drop the FK constraint on `item_id` for the given table, looked up
     * dynamically rather than assuming the conventional Laravel name.
     *
     * Task 1's migration (2026_09_20_100000_convert_items_to_uuid_primary_key)
     * already dropped each of these tables' inbound `item_id` FK to allow the
     * `items.id` type change, so in most environments this will find nothing
     * to drop. This lookup also tolerates `pivot_items_priorities`, whose FK
     * carries a non-conventional name inherited from before it was renamed
     * from `lootcouncil_item_priorities` — the same reason Task 1 and the two
     * migrations referenced in this plan's Interfaces note look the name up
     * dynamically instead of hardcoding it.
     */
    private function dropItemIdForeignKey(string $table): void
    {
        $foreignKey = collect(Schema::getForeignKeys($table))
            ->first(fn (array $fk): bool => $fk['columns'] === ['item_id']);

        if ($foreignKey === null) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($foreignKey): void {
            $blueprint->dropForeign($foreignKey['name']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This migration is not reversible — items.blizzard_id values are not guaranteed unique once multiple game versions exist by the time this runs in an environment. Restore from a backup taken before this migration if you need to roll back.');
    }
};
