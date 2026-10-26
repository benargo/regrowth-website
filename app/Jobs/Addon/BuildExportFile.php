<?php

namespace App\Jobs\Addon;

use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Data\Attendance\CharacterAttendanceData;
use App\Enums\LootPriorityType;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\ItemPriority;
use App\Models\LootPriority;
use App\Models\Phase;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BuildExportFile implements ShouldBeUniqueUntilProcessing, ShouldQueue
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
     * The number of seconds the unique lock is held.
     */
    public int $uniqueFor = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $gameVersionId) {}

    /**
     * Queue a build for the given game version after a short delay.
     */
    public static function schedule(int $gameVersionId): void
    {
        static::dispatch($gameVersionId)->delay(now()->addSeconds(120));
    }

    /**
     * Schedule a build for each of the given game versions that own a current roster, ignoring nulls and duplicates.
     *
     * @param  iterable<int, int|string|null>  $gameVersionIds
     */
    public static function scheduleForGameVersionIds(iterable $gameVersionIds): void
    {
        $currentRosterIds = GameVersion::currentRosters()->modelKeys();

        foreach (collect($gameVersionIds)->filter()->map(intval(...))->unique() as $gameVersionId) {
            if (in_array($gameVersionId, $currentRosterIds, true)) {
                static::schedule($gameVersionId);
            }
        }
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return (string) $this->gameVersionId;
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['regrowth-addon', 'regrowth-addon:build', "game-version:{$this->gameVersionId}"];
    }

    /**
     * Execute the job.
     */
    public function handle(FilesystemManager $storage): void
    {
        $gameVersion = GameVersion::find($this->gameVersionId);

        if ($gameVersion === null) {
            Log::info('BuildExportFile: game version no longer exists, skipping export.', [
                'game_version_id' => $this->gameVersionId,
            ]);

            return;
        }

        $data = [
            'system' => [
                'date_generated' => now()->unix(),
                'game_version' => [
                    'slug' => $gameVersion->slug,
                    'name' => $gameVersion->title,
                ],
            ],
            'phases' => $this->buildPhases($gameVersion),
            'priorities' => $this->buildPriorities($gameVersion),
            'items' => $this->buildItems($gameVersion),
            'players' => $this->buildPlayerAttendance($gameVersion),
            'councillors' => $this->buildCouncillors($gameVersion),
        ];

        $written = $storage->disk('addon')->put("{$gameVersion->id}/export.json", json_encode($data));

        if ($written === false) {
            throw new RuntimeException("Unable to write the addon export for game version {$gameVersion->id} to disk [addon].");
        }

        Log::info('Addon export data file built successfully.', [
            'game_version_id' => $gameVersion->id,
        ]);
    }

    /**
     * Build a list of raid phases with their start times.
     */
    protected function buildPhases(GameVersion $gameVersion): Collection
    {
        return $gameVersion->phases()->inReleaseOrder()->get()->map(fn (Phase $phase): array => [
            'id' => $phase->id,
            'number' => $phase->number,
            'start_date' => $phase->start_date?->unix(),
        ]);
    }

    /**
     * Build a list of loot priorities with their icons.
     */
    protected function buildPriorities(GameVersion $gameVersion): Collection
    {
        return LootPriority::whereHas('items', fn (Builder $query) => $query->whereBelongsTo($gameVersion))
            ->where('type', '!=', LootPriorityType::MEME->value)
            ->with('media')
            ->get()
            ->map(function (LootPriority $priority): array {
                $fileName = $priority->getFirstMedia('blizzard_icons')?->file_name;

                return [
                    'id' => $priority->id,
                    'name' => $priority->title,
                    'icon' => $fileName ? pathinfo($fileName, PATHINFO_FILENAME) : null,
                ];
            });
    }

    /**
     * Build a list of items with their priorities and cleaned notes.
     */
    protected function buildItems(GameVersion $gameVersion): Collection
    {
        return $gameVersion->items()->has('priorities')->select('id', 'blizzard_id', 'notes')->get()->map(fn (Item $item): array => [
            'item_id' => $item->blizzard_id,
            'priorities' => ItemPriority::select('priority_id', 'weight')
                ->where('item_id', $item->id)
                ->whereRelation('priority', 'type', '!=', LootPriorityType::MEME->value)
                ->get(),
            'notes' => $this->cleanNotes($item->notes),
        ]);
    }

    /**
     * Build player attendance statistics for the given game version, which must have counted attendance.
     *
     * @return Collection<int, array{id: int, name: string, attendance: array{first_attendance: int, attended: int, total: int, percentage: float}}>
     */
    protected function buildPlayerAttendance(GameVersion $gameVersion): Collection
    {
        if (! $gameVersion->hasCountedAttendance()) {
            return collect();
        }

        if (! $gameVersion->guildRanks()->countsAttendance()->exists()) {
            Log::warning('BuildExportFile: no counting ranks configured, skipping attendance data.', [
                'game_version_id' => $gameVersion->id,
            ]);

            return collect();
        }

        return BuildAttendanceMatrix::run($gameVersion)->rows->map(fn (CharacterAttendanceData $row): array => [
            'id' => $row->character->id,
            'name' => $row->character->name,
            'attendance' => [
                'first_attendance' => $row->firstAttendance->unix(),
                'attended' => $row->reportsAttended,
                'total' => $row->totalReports,
                'percentage' => $row->percentage,
            ],
        ]);
    }

    /**
     * Build a list of loot councillors with their IDs, names, and ranks.
     */
    protected function buildCouncillors(GameVersion $gameVersion): Collection
    {
        return $gameVersion->characters()->where('is_loot_councillor', true)
            ->with('rank')
            ->orderBy('name')
            ->get()
            ->map(fn (Character $character): array => [
                'id' => $character->id,
                'name' => $character->name,
                'rank' => $character->rank?->name,
            ]);
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
    public function failed(Throwable $exception): void
    {
        Log::error('BuildExportFile job failed.', [
            'game_version_id' => $this->gameVersionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
