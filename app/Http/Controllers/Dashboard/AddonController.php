<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AddCouncillorRequest;
use App\Http\Requests\Dashboard\RemoveCouncillorRequest;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use App\Models\TBC\Phase;
use App\Models\WarcraftLogs\GuildTag;
use App\Services\Blizzard\Data\GuildMember;
use App\Services\Blizzard\GuildService as BlizzardGuildService;
use App\Services\WarcraftLogs\AttendanceService;
use App\Services\WarcraftLogs\GuildService as WarcraftLogsGuildService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AddonController extends Controller
{
    // ==========================================
    // Data export
    // ==========================================

    public function export(Request $request)
    {
        return Inertia::render('Dashboard/Addon/Base64', [
            'exportedData' => Inertia::defer(fn () => $this->getBase64ExportedData($request)),
            'grmFreshness' => Inertia::defer(fn () => $this->getGrmFreshness()),
        ]);
    }

    public function exportJson(Request $request)
    {
        return Inertia::render('Dashboard/Addon/JSON', [
            'exportedData' => Inertia::defer(fn () => $this->getJsonExportedData($request, JSON_PRETTY_PRINT)),
            'grmFreshness' => Inertia::defer(fn () => $this->getGrmFreshness()),
        ]);
    }

    protected function getBase64ExportedData(Request $request): string
    {
        return base64_encode($this->getJsonExportedData($request));
    }

    protected function getJsonExportedData(Request $request, $style = null): string
    {
        return json_encode($this->getExportedData($request), $style);
    }

    /**
     * Get the data to be exported.
     */
    protected function getExportedData(Request $request): array
    {
        return [
            'system' => [
                'date_generated' => Carbon::now()->unix(),
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->displayName,
                ],
            ],
            'priorities' => $this->buildPriorities(),
            'items' => $this->buildItems(),
            'players' => $this->buildPlayerAttendanceData(),
            'councillors' => $this->buildCouncillors(),
        ];
    }

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

    protected function buildPlayerAttendanceData(): Collection
    {
        $wclGuildService = app(WarcraftLogsGuildService::class);

        $tags = $wclGuildService->getGuildTags()->where('count_attendance', true);

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

        return app(AttendanceService::class)
            ->tags($tags->pluck('id')->toArray())
            ->playerNames($members->pluck('character.name')->toArray())
            ->calculate()
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
     * Get the freshness status of the GRM data.
     */
    protected function getGrmFreshness(): array
    {
        $dataIsStale = false;
        $timestamp = Carbon::createFromTimestamp(0);

        // Check the last modified time of the GRM upload file
        $disk = Storage::disk('local');

        if ($disk->exists('grm/uploads/latest.csv')) {
            $fileLastModifiedTime = $disk->lastModified('grm/uploads/latest.csv');
            $timestamp = Carbon::createFromTimestamp($fileLastModifiedTime);
        }

        // Check if the roster data is significantly different from the roster data at the time of the last GRM upload
        $members = app(BlizzardGuildService::class)->members();

        // Count the number of raiders in the official guild roster.
        $raiderRankIds = GuildRank::whereLike('name', '%Raider%')->pluck('id');
        $raiderCount = $members->filter(
            fn (GuildMember $member) => $member->rank instanceof GuildRank
                && $raiderRankIds->contains($member->rank->id)
        )->count();

        // Count the number of raiders in the GRM upload file.
        $grmRaidersCount = 0;
        if ($disk->exists('grm/uploads/latest.csv')) {
            $file = $disk->get('grm/uploads/latest.csv');

            // Find which column contains the rank information by reading the first line to find 'Rank'
            $lines = explode("\n", $file);
            $header = str_getcsv(array_shift($lines));
            $rankColumnIndex = null;
            foreach ($header as $index => $columnName) {
                if (stripos($columnName, 'Rank') !== false) {
                    $rankColumnIndex = $index;
                    break;
                }
            }

            // Count the number of individuals with 'Raider' in their rank
            foreach ($lines as $line) {
                $columns = str_getcsv($line);
                if ($rankColumnIndex !== null
                    && isset($columns[$rankColumnIndex])
                    && stripos($columns[$rankColumnIndex], 'Raider') !== false) {
                    $grmRaidersCount++;
                }
            }
        }

        // Compare the two counts
        if (abs($raiderCount - $grmRaidersCount) >= 3) {
            // If the difference is 3 or more, consider the GRM data stale
            $dataIsStale = true;
        }

        return [
            'lastModified' => $timestamp,
            'dataIsStale' => $dataIsStale,
            'blzRaiderCount' => $raiderCount,
            'grmRaiderCount' => $grmRaidersCount,
        ];
    }

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

    // ==========================================
    // Schema display
    // ==========================================

    public function exportSchema()
    {
        return Inertia::render('Dashboard/Addon/Schema', [
            'schema' => $this->getSchema(),
        ]);
    }

    protected function getSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => config('app.url').'/regrowth-loot-tool-schema.json?v=1.2.0',
            'title' => 'Regrowth Loot Tool Export Schema',
            'description' => 'Schema for the Regrowth Loot Tool addon data export format.',
            'type' => 'object',
            'properties' => [
                'system' => [
                    'type' => 'object',
                    'properties' => [
                        'date_generated' => ['type' => 'integer'],
                        'user' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'priorities' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'icon' => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'item_id' => ['type' => 'integer'],
                            'priorities' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'priority_id' => ['type' => 'integer'],
                                        'weight' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                            'notes' => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'players' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'attendance' => [
                                'type' => 'object',
                                'properties' => [
                                    'first_attendance' => ['type' => 'string', 'format' => 'date-time'],
                                    'attended' => ['type' => 'integer'],
                                    'total' => ['type' => 'integer'],
                                    'percentage' => ['type' => 'number'],
                                ],
                            ],
                        ],
                    ],
                ],
                'councillors' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'rank' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ==========================================
    // Settings management
    // ==========================================

    public function settings(Request $request)
    {
        $councillors = Character::where('is_loot_councillor', true)
            ->orderBy('name')
            ->get();

        $tags = app(WarcraftLogsGuildService::class)
            ->getGuildTags()
            ->map(function (GuildTag $tag) {
                $phase = null;
                if ($tag->phase instanceof Phase) {
                    $phase = $tag->phase->number;
                }

                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'count_attendance' => $tag->count_attendance,
                    'phaseNumber' => $phase,
                ];
            })
            ->toArray();

        return Inertia::render('Dashboard/Addon/Settings', [
            'settings' => [
                'councillors' => $councillors,
                'ranks' => GuildRank::orderBy('position')->get(),
                'tags' => $tags,
            ],
            'characters' => Inertia::defer(fn () => Character::with('rank')->orderBy('name')->get()),
        ]);
    }

    public function addCouncillor(AddCouncillorRequest $request): RedirectResponse
    {
        $character = Character::where('name', $request->validated('character_name'))->firstOrFail();

        $character->update(['is_loot_councillor' => true]);

        return back();
    }

    public function removeCouncillor(RemoveCouncillorRequest $request, Character $character): RedirectResponse
    {
        $character->update(['is_loot_councillor' => false]);

        return back();
    }
}
