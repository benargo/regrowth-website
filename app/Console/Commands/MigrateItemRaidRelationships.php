<?php

namespace App\Console\Commands;

use Database\Seeders\ItemSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:migrate-item-raid-relationships')]
#[Description('Move items onto the many-to-many raid pivot: create pivot_items_raids, reseed items, then drop items.raid_id.')]
class MigrateItemRaidRelationships extends Command
{
    /**
     * The migration that creates and backfills the pivot table.
     */
    private const PIVOT_MIGRATION = '2026_08_15_100000_create_pivot_items_raids_table';

    /**
     * The migration that drops the superseded items.raid_id column.
     */
    private const DROP_MIGRATION = '2026_08_15_100001_drop_raid_id_from_items_table';

    public function handle(): int
    {
        if ($applied = $this->alreadyAppliedMigration()) {
            $this->error("Migration {$applied} has already been applied.");
            $this->line('This command only runs on a database where neither migration has been applied. Run the remaining steps manually and verify the pivot is populated before dropping items.raid_id.');

            return self::FAILURE;
        }

        $this->info('Creating pivot_items_raids and backfilling from items.raid_id...');
        $this->call('migrate', ['--path' => 'database/migrations/'.self::PIVOT_MIGRATION.'.php']);

        $this->info('Seeding items so every raid link exists in the pivot...');
        $this->call('db:seed', ['--class' => ItemSeeder::class]);

        $this->info('Dropping items.raid_id...');
        $this->call('migrate', ['--path' => 'database/migrations/'.self::DROP_MIGRATION.'.php']);

        $this->info('Done. Items now relate to raids through pivot_items_raids.');

        return self::SUCCESS;
    }

    /**
     * Return the name of whichever migration has already run, or null if neither has.
     */
    private function alreadyAppliedMigration(): ?string
    {
        return DB::table('migrations')
            ->whereIn('migration', [self::PIVOT_MIGRATION, self::DROP_MIGRATION])
            ->orderBy('migration')
            ->value('migration');
    }
}
