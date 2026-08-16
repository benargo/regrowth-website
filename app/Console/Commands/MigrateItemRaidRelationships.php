<?php

namespace App\Console\Commands;

use Database\Seeders\ItemSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
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
        $exitCode = $this->call('migrate', ['--path' => 'database/migrations/'.self::PIVOT_MIGRATION.'.php']);

        if ($exitCode !== self::SUCCESS) {
            $this->error('Creating pivot_items_raids failed.');
            $this->line('items.raid_id has not been touched. Fix the issue above and re-run this command.');

            return self::FAILURE;
        }

        $this->info('Seeding items so every raid link exists in the pivot...');
        $skippedItemIds = $this->runItemSeeder();

        if ($skippedItemIds !== []) {
            $this->error(sprintf('%d item(s) were skipped while seeding: %s', count($skippedItemIds), implode(', ', $skippedItemIds)));
            $this->line('items.raid_id has NOT been dropped — pivot rows for those items were not (re)written. Fix the issue above and re-run this command.');

            return self::FAILURE;
        }

        $this->info('Dropping items.raid_id...');
        $exitCode = $this->call('migrate', ['--path' => 'database/migrations/'.self::DROP_MIGRATION.'.php']);

        if ($exitCode !== self::SUCCESS) {
            $this->error('Dropping items.raid_id failed.');
            $this->line('The pivot is already populated. It is safe to re-run this command once the issue above is fixed.');

            return self::FAILURE;
        }

        $this->info('Done. Items now relate to raids through pivot_items_raids.');

        return self::SUCCESS;
    }

    /**
     * Resolve ItemSeeder from the container and run it directly, bypassing
     * `db:seed` — its exit code cannot distinguish "every item succeeded"
     * from "N items were silently skipped," so we need the seeder's own
     * skippedItemIds() afterward.
     *
     * @return array<int, int>
     */
    private function runItemSeeder(): array
    {
        $seeder = $this->laravel->make(ItemSeeder::class)
            ->setContainer($this->laravel)
            ->setCommand($this);

        Model::unguarded(fn () => $seeder->run());

        return $seeder->skippedItemIds();
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
