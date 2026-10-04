<?php

namespace App\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Requests\GetGuildTagsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchGuildTags implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public GameVersion $gameVersion) {}

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, SkipIfBatchCancelled>
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    /**
     * Fetch the game version's guild tags from Warcraft Logs and upsert them.
     *
     * Request exceptions and rate-limit exceptions propagate: the job only runs
     * synchronously from the fetch:warcraft-logs command, which handles them.
     */
    public function handle(WarcraftLogsConnector $warcraftLogs): void
    {
        if ($this->gameVersion->warcraftlogs_guild === null) {
            Log::warning("Skipping guild tags for game version {$this->gameVersion->id}: no Warcraft Logs guild.");

            return;
        }

        if ($this->gameVersion->warcraftlogs_namespace === null) {
            Log::warning("Skipping guild tags for game version {$this->gameVersion->id}: no Warcraft Logs namespace.");

            return;
        }

        $tags = $warcraftLogs->send(new GetGuildTagsRequest(
            $this->gameVersion->warcraftlogs_guild,
            $this->gameVersion->warcraftlogs_namespace,
        ))->dto();

        DB::transaction(function () use ($tags): void {
            foreach ($tags as $tag) {
                GuildTag::updateOrCreate(['id' => $tag->id], ['name' => $tag->name]);
            }
        });

        $count = count($tags);

        Log::info("Synced {$count} guild tags from Warcraft Logs for game version {$this->gameVersion->id}.");
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['warcraftlogs', 'guild-tags', "game-version:{$this->gameVersion->id}"];
    }
}
