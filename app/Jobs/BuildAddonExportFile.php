<?php

namespace App\Jobs;

use App\Exceptions\EmptyCollectionException;
use App\Models\Character;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\CharacterAttendanceStatsData;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BuildAddonExportFile implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Define the middleware for the job.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new RateLimitedWithRedis('build-addon-export'))->dontRelease(),
        ];
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['regrowth-addon', 'regrowth-addon:build'];
    }

    /**
     * Execute the job.
     */
    public function handle(Calculator $calculator): void
    {
        $data = [
            'system' => [
                'date_generated' => Carbon::now()->unix(),
            ],
            'priorities' => $this->buildPriorities(),
            'items' => $this->buildItems(),
            'players' => $this->buildPlayerAttendance($calculator),
            'councillors' => $this->buildCouncillors(),
        ];

        Storage::disk('local')->put('addon/export.json', json_encode($data));

        Log::info('Addon export data file built successfully.');
    }

    /**
     * Build a list of loot priorities with their icons.
     */
    protected function buildPriorities(): Collection
    {
        return Priority::has('items')->get()->map(function (Priority $priority) {
            return [
                'id' => $priority->id,
                'name' => $priority->title,
                'icon' => $priority->media['media_name'] ?? null,
            ];
        });
    }

    /**
     * Build a list of items with their priorities and cleaned notes.
     */
    protected function buildItems(): Collection
    {
        return Item::has('priorities')->select('id', 'notes')->get()->map(function (Item $item) {
            return [
                'item_id' => $item->id,
                'priorities' => ItemPriority::where('item_id', $item->id)
                    ->select('priority_id', 'weight')
                    ->get(),
                'notes' => $this->cleanNotes($item->notes),
            ];
        });
    }

    /**
     * Build player attendance statistics.
     */
    protected function buildPlayerAttendance(Calculator $calculator): Collection
    {
        try {
            return $calculator->wholeGuild()->map(fn (CharacterAttendanceStatsData $stats) => [
                'id' => $stats->character->id,
                'name' => $stats->character->name,
                'attendance' => [
                    'first_attendance' => $stats->firstAttendance->copy()->setTimezone(config('app.timezone'))->toIso8601String(),
                    'attended' => $stats->reportsAttended,
                    'total' => $stats->totalReports,
                    'percentage' => $stats->percentage,
                ],
            ]);
        } catch (EmptyCollectionException $e) {
            Log::warning('BuildAddonExportFile: no counting ranks configured, skipping attendance data.', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Build a list of loot councillors with their IDs, names, and ranks.
     */
    protected function buildCouncillors(): Collection
    {
        return Character::where('is_loot_councillor', true)
            ->with('rank')
            ->orderBy('name')
            ->get()
            ->map(function (Character $character) {
                return [
                    'id' => $character->id,
                    'name' => $character->name,
                    'rank' => $character->rank?->name,
                ];
            });
    }

    /**
     * Clean the notes by removing markdown and custom wowhead link syntax, leaving only plain text.
     */
    protected function cleanNotes(?string $notes): ?string
    {
        if ($notes === null) {
            return null;
        }

        // Remove custom wowhead links: !wh[text](item=12345) -> text
        $notes = preg_replace('/!wh\[([^\]]*)\]\([^)]+\)/', '$1', $notes);

        // Remove standard markdown links: [text](url) -> text
        $notes = preg_replace('/\[([^\]]*)\]\([^)]+\)/', '$1', $notes);

        // Remove bold/italic: **text**, *text*
        $notes = preg_replace('/(\*\*)(.*?)\1/', '$2', $notes);
        $notes = preg_replace('/(\*|_)(.*?)\1/', '$2', $notes);

        // Remove underline: __text__
        $notes = preg_replace('/(__)(.+?)\1/', '$2', $notes);

        // Remove inline code: `code`
        $notes = preg_replace('/`([^`]*)`/', '$1', $notes);

        // Remove headers: # Header
        $notes = preg_replace('/^#{1,6}\s*/m', '', $notes);

        // Remove strikethrough: ~~text~~
        $notes = preg_replace('/~~(.*?)~~/', '$1', $notes);

        // Normalize whitespace
        return trim(preg_replace('/\s+/', ' ', $notes));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BuildAddonExportFile job failed.', [
            'error' => $exception->getMessage(),
        ]);
    }
}
