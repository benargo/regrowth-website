<?php

namespace App\Jobs\WarcraftLogs;

use App\Services\WarcraftLogs\GuildTags;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\Log;

class FetchGuildTags implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    /**
     * Execute the job.
     */
    public function handle(GuildTags $guildTagsService): void
    {
        $tags = $guildTagsService->toCollection();

        Log::info('Synced '.$tags->count().' guild tags from Warcraft Logs.');
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['warcraftlogs', 'guild-tags'];
    }
}
