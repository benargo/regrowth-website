<?php

use App\Models\GameVersion;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const ITEMS_TABLE = 'items';

    private const ITEMS_FULLTEXT_INDEX = 'items_name_fulltext';

    /** @var array<int, string> */
    private array $gameVersionDatasetTables = ['bosses', 'phases', 'raids', 'wcl_guild_tags', 'wcl_zones'];

    /**
     * Tables with an inbound `item_id` foreign key to `items.id`.
     *
     * These FKs must be dropped before `items.id` can be converted to a
     * uuid — MariaDB refuses to ALTER a column that other tables still hold
     * a foreign key against — and are recreated pointing at the new uuid
     * values once the conversion is done.
     *
     * @var array<int, string>
     */
    private const INBOUND_ITEM_FK_TABLES = ['pivot_items_priorities', 'pivot_dailyquest_rewards', 'pivot_items_raids'];

    public function up(): void
    {
        $this->addGameVersionIdToDatasetTables();
        $this->createPivotGameVersionsPlayableRacesTable();
        $this->createPivotGameVersionsPlayableClassesTable();
        $this->convertItemsToUuidPrimaryKey();
        $this->repointItemForeignKeysToUuid();
        $this->widenMediaModelIdForUuidKeys();
        $this->addGameVersionIdToCharactersTable();
    }

    public function down(): void
    {
        $this->dropGameVersionIdFromCharactersTable();
        $this->restoreMediaModelIdWidth();

        // repointItemForeignKeysToUuid() is not reversible — items.blizzard_id
        // values are not guaranteed unique once multiple game versions exist
        // by the time this runs in an environment. Restore from a backup
        // taken before this migration if you need to roll back.
        throw new RuntimeException('This migration is not reversible past this point — items.blizzard_id values are not guaranteed unique once multiple game versions exist. Restore from a backup taken before this migration if you need to roll back.');
    }

    private function addGameVersionIdToDatasetTables(): void
    {
        foreach ($this->gameVersionDatasetTables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignIdFor(GameVersion::class)->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }
    }

    private function createPivotGameVersionsPlayableRacesTable(): void
    {
        Schema::create('pivot_game_versions_playable_races', function (Blueprint $table): void {
            $table->foreignIdFor(GameVersion::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(PlayableRace::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['game_version_id', 'playable_race_id']);
        });
    }

    private function createPivotGameVersionsPlayableClassesTable(): void
    {
        Schema::create('pivot_game_versions_playable_classes', function (Blueprint $table): void {
            $table->foreignIdFor(GameVersion::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(PlayableClass::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['game_version_id', 'playable_class_id']);
        });
    }

    private function convertItemsToUuidPrimaryKey(): void
    {
        $hasFullText = $this->hasFullTextSupport();

        if ($hasFullText) {
            Schema::table(self::ITEMS_TABLE, function (Blueprint $table): void {
                $table->dropFullText(self::ITEMS_FULLTEXT_INDEX);
            });
        }

        $this->dropInboundItemForeignKeys();

        // MariaDB/MySQL refuse to drop a primary key while its column is
        // still auto_increment (a table can never have an auto_increment
        // column with no key). Laravel compiles each of these into its own
        // ALTER TABLE statement, so ordering the calls this way — remove
        // auto_increment via the MODIFY first, then DROP PRIMARY KEY second —
        // keeps every intermediate statement valid on its own.
        Schema::table(self::ITEMS_TABLE, function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->change();
            $table->dropPrimary();
        });

        Schema::table(self::ITEMS_TABLE, function (Blueprint $table): void {
            $table->renameColumn('id', 'blizzard_id');
        });

        Schema::table(self::ITEMS_TABLE, function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->first();
        });

        DB::table(self::ITEMS_TABLE)->whereNull('uuid')->orderBy('blizzard_id')
            ->chunkById(500, function (Collection $items): void {
                foreach ($items as $item) {
                    DB::table(self::ITEMS_TABLE)
                        ->where('blizzard_id', $item->blizzard_id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            }, 'blizzard_id');

        Schema::table(self::ITEMS_TABLE, function (Blueprint $table): void {
            $table->uuid('uuid')->nullable(false)->change();
        });

        Schema::table(self::ITEMS_TABLE, function (Blueprint $table): void {
            $table->renameColumn('uuid', 'id');
        });

        Schema::table(self::ITEMS_TABLE, function (Blueprint $table): void {
            $table->primary('id');
        });

        Schema::table(self::ITEMS_TABLE, function (Blueprint $table): void {
            $table->foreignIdFor(GameVersion::class)->nullable()->after('blizzard_id')->constrained()->nullOnDelete();
            $table->unique(['game_version_id', 'blizzard_id']);
        });

        if ($hasFullText) {
            Schema::table(self::ITEMS_TABLE, function (Blueprint $table): void {
                $table->fullText('name', self::ITEMS_FULLTEXT_INDEX);
            });
        }
    }

    /**
     * Drop the FK constraint on `item_id` for every table in
     * INBOUND_ITEM_FK_TABLES, looked up dynamically rather than assuming the
     * conventional Laravel name — some of these tables carry FK names
     * inherited from before they were renamed (see `pivot_items_priorities`'s
     * history), so the constraint name can't be assumed to match the
     * current table/column names.
     */
    private function dropInboundItemForeignKeys(): void
    {
        foreach (self::INBOUND_ITEM_FK_TABLES as $table) {
            $this->dropItemIdForeignKey($table);
        }
    }

    private function repointItemForeignKeysToUuid(): void
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
     * dynamically rather than assuming the conventional Laravel name — some
     * tables carry FK names inherited from before they were renamed (e.g.
     * `pivot_items_priorities`, formerly `lootcouncil_item_priorities`).
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

    private function widenMediaModelIdForUuidKeys(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('model_id')->change();
        });
    }

    private function restoreMediaModelIdWidth(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->unsignedBigInteger('model_id')->change();
        });
    }

    private function addGameVersionIdToCharactersTable(): void
    {
        Schema::table('characters', function (Blueprint $table): void {
            $table->foreignIdFor(GameVersion::class)->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    private function dropGameVersionIdFromCharactersTable(): void
    {
        Schema::table('characters', function (Blueprint $table): void {
            $table->dropForeign(['game_version_id']);
            $table->dropColumn('game_version_id');
        });
    }

    private function hasFullTextSupport(): bool
    {
        return in_array(DB::connection()->getDriverName(), config('database.behaviours.full_text'), true);
    }
};
