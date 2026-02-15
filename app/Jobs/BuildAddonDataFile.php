<?php

namespace App\Jobs;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use App\Services\Attendance\Calculators\GuildAttendanceCalculator;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService as BlizzardGuildService;
use App\Services\WarcraftLogs\Attendance;
use App\Services\WarcraftLogs\GuildTags;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BuildAddonDataFile implements ShouldQueue
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

    public function __construct()
    {
        //
    }

    /**
     * Prevent overlapping exports.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('addon-data-export'))->dontRelease(),
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(
        GuildTags $guildTags,
        Attendance $attendance,
    ): void {
        $data = [
            'system' => [
                'date_generated' => Carbon::now()->unix(),
            ],
            'priorities' => $this->buildPriorities(),
            'items' => $this->buildItems(),
            'players' => $this->buildPlayerAttendanceData($guildTags, $attendance),
            'councillors' => $this->buildCouncillors(),
        ];

        Storage::disk('local')->put('addon/export.json', json_encode($data));

        Log::info('Addon export data generated successfully.');
    }

    /**
     * Build the priorities data for the addon export file.
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
     * Build the items data for the addon export file, including their priorities and cleaned notes.
     */
    protected function buildItems(): Collection
    {
        $items = Item::has('priorities')->select('id', 'notes')->get();

        return $items->map(function (Item $item) {
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
        $notes = preg_replace('/(__)(.*?)\1/', '$2', $notes);

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
     * Build the player attendance data for the addon export file.
     */
    protected function buildPlayerAttendanceData(GuildTags $guildTags, Attendance $attendance): Collection
    {
        $tags = $guildTags->toCollection()->where('count_attendance', true);

        $ranks = GuildRank::where('count_attendance', true)->get();

        // If no tags and no ranks are configured for attendance tracking, return an empty collection.
        if ($tags->isEmpty() && $ranks->isEmpty()) {
            return collect();
        }

        $members = app(BlizzardGuildService::class)
            ->members()
            ->filter(function (GuildMember $member) use ($ranks) {
                return $member->rank instanceof GuildRank
                    && $ranks->pluck('id')->contains($member->rank->id);
            });

        $attendanceData = $attendance->tags($tags->pluck('id')->toArray())
            ->playerNames($members->pluck('character.name')->toArray())
            ->get();

        return app(GuildAttendanceCalculator::class)
            ->calculate($attendanceData)
            ->map(function ($stats) use ($members) {
                return [
                    'id' => $members->firstWhere('character.name', $stats->name)?->character['id'] ?? null,
                    'name' => $stats->name,
                    'attendance' => [
                        'first_attendance' => $stats->firstAttendance?->setTimezone(config('app.timezone'))->toIso8601String(),
                        'attended' => $stats->reportsAttended,
                        'total' => $stats->totalReports,
                        'percentage' => $stats->percentage,
                    ],
                ];
            });
    }

    /**
     * Build the list of loot councillors for the addon export file.
     */
    protected function buildCouncillors(): Collection
    {
        return Character::where('is_loot_councillor', true)
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
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BuildAddonDataFile failed.', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
