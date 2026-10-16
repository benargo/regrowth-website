<?php

namespace App\Jobs;

use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Data\Attendance\CharacterAttendanceData;
use App\Enums\LootPriorityType;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\ItemPriority;
use App\Models\LootPriority;
use App\Models\Phase;
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
     * Queue a rebuild two minutes from now, so a burst of changes settles
     * before the export is built.
     */
    public static function schedule(): void
    {
        static::dispatch()->delay(now()->addSeconds(120));
    }

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
    public function handle(): void
    {
        $data = [
            'system' => [
                'date_generated' => Carbon::now()->unix(),
            ],
            'phases' => $this->buildPhases(),
            'priorities' => $this->buildPriorities(),
            'items' => $this->buildItems(),
            'players' => $this->buildPlayerAttendance(),
            'councillors' => $this->buildCouncillors(),
        ];

        Storage::disk('local')->put('addon/export.json', json_encode($data));

        Log::info('Addon export data file built successfully.');
    }

    /**
     * Build a list of raid phases with their start times.
     */
    protected function buildPhases(): Collection
    {
        return Phase::orderBy('number')->get()->map(function (Phase $phase) {
            return [
                'id' => $phase->id,
                'number' => $phase->number,
                'start_date' => $phase->start_date?->unix(),
            ];
        });
    }

    /**
     * Build a list of loot priorities with their icons.
     */
    protected function buildPriorities(): Collection
    {
        return LootPriority::has('items')->where('type', '!=', LootPriorityType::MEME->value)->with('media')->get()->map(function (LootPriority $priority) {
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
    protected function buildItems(): Collection
    {
        return Item::has('priorities')->select('id', 'notes')->get()->map(function (Item $item) {
            return [
                'item_id' => $item->id,
                'priorities' => ItemPriority::select('priority_id', 'weight')
                    ->where('item_id', $item->id)
                    ->whereRelation('priority', 'type', '!=', LootPriorityType::MEME->value)
                    ->get(),
                'notes' => $this->cleanNotes($item->notes),
            ];
        });
    }

    /**
     * Build player attendance statistics for the newest game version with counted attendance.
     */
    protected function buildPlayerAttendance(): Collection
    {
        $gameVersion = GameVersion::withCountedAttendance()->first();

        if ($gameVersion === null) {
            return collect();
        }

        if (! $gameVersion->guildRanks()->countsAttendance()->exists()) {
            Log::warning('BuildAddonExportFile: no counting ranks configured, skipping attendance data.', [
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
