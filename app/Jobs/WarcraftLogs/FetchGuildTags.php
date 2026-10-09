<?php

namespace App\Jobs\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\Data\GuildTags\GuildTagData;
use App\Http\Integrations\WarcraftLogs\Requests\GetGuildTagsRequest;
use App\Http\Integrations\WarcraftLogs\WarcraftLogsConnector;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

#[DeleteWhenMissingModels]
class FetchGuildTags implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public Guild $guild) {}

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
     * Fetch the guild's tags from Warcraft Logs and upsert them into the guild.
     *
     * Request exceptions and rate-limit exceptions propagate: the
     * fetch:warcraft-logs command handles them when it runs the job
     * synchronously, and the queue retries the job when an officer adds a guild.
     */
    public function handle(WarcraftLogsConnector $warcraftLogs): void
    {
        $tags = $warcraftLogs->send(new GetGuildTagsRequest($this->guild->id, $this->guild->namespace))->dto();

        DB::transaction(function () use ($tags): void {
            foreach ($tags as $tag) {
                $this->storeTag($tag);
            }
        });

        $count = count($tags);

        Log::info("Synced {$count} guild tags from Warcraft Logs for guild {$this->guild->id}.");
    }

    /**
     * Create or rename the tag in this guild. Tag IDs are assumed unique
     * across Warcraft Logs sites; if one already belongs to another guild, it
     * is left alone rather than moved, so a clash never re-points that
     * guild's reports.
     */
    private function storeTag(GuildTagData $tag): void
    {
        $guildTag = GuildTag::firstOrNew(['id' => $tag->id]);

        $ownerId = $guildTag->warcraft_logs_guild_id;

        if ($ownerId === null || $ownerId === $this->guild->id) {
            $guildTag->fill(['name' => $tag->name, 'warcraft_logs_guild_id' => $this->guild->id])->save();

            return;
        }

        Log::warning("Skipping Warcraft Logs tag {$tag->id} for guild {$this->guild->id}: it already belongs to guild {$ownerId}.");
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['warcraftlogs', 'guild-tags', "warcraft-logs-guild:{$this->guild->id}"];
    }
}
