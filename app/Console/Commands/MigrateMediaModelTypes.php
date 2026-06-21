<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:migrate-media-model-types')]
#[Description('Update stale media model_type values from App\Models\LootCouncil\Priority to App\Models\LootPriority.')]
class MigrateMediaModelTypes extends Command
{
    public function handle(): void
    {
        $this->info('Migrating media model types...');

        $updated = DB::table('media')
            ->where('model_type', 'App\\Models\\LootCouncil\\Priority')
            ->update(['model_type' => 'App\\Models\\LootPriority']);

        $this->info("Updated {$updated} row(s).");
    }
}
