<?php

namespace App\Http\Controllers;

use App\Enums\DailyQuestIcons;
use App\Http\Requests\StoreDailyQuestsRequest;
use App\Models\DailyQuest;
use App\Models\TBC\DailyQuestNotification;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DailyQuestsController extends Controller
{
    public function index(BlizzardService $blizzard, MediaService $media): Response
    {
        return Inertia::render('DailyQuests/Index', [
            'hasNotification' => DailyQuestNotification::existsForToday(),
            'quests' => Inertia::defer(fn () => $this->buildQuestsData($blizzard, $media)),
        ]);
    }

    public function form(MediaService $media): Response
    {
        $existingNotification = DailyQuestNotification::getTodaysNotification();

        $icons = [
            'cooking' => $media->get(DailyQuestIcons::Cooking->value),
            'fishing' => $media->get(DailyQuestIcons::Fishing->value),
            'dungeon' => $media->get(DailyQuestIcons::Dungeon->value),
            'heroic' => $media->get(DailyQuestIcons::HeroicDungeon->value),
            'pvp' => $media->get(DailyQuestIcons::PvP->value),
        ];

        $quests = DailyQuest::hydrate(
            Cache::remember('daily_quests:all', now()->addMonth(), function () {
                return DailyQuest::all()->map->getAttributes()->toArray();
            })
        )->groupBy('type');

        return Inertia::render('DailyQuests/Form', [
            'cookingQuests' => $quests->get('Cooking', collect())->toArray(),
            'fishingQuests' => $quests->get('Fishing', collect())->toArray(),
            'dungeonQuests' => $quests->get('Dungeon', collect())->where('mode', 'Normal')->values()->toArray(),
            'heroicQuests' => $quests->get('Dungeon', collect())->where('mode', 'Heroic')->values()->toArray(),
            'pvpQuests' => $quests->get('PvP', collect())->toArray(),
            'icons' => $icons,
            'existingNotification' => $existingNotification ? [
                'id' => $existingNotification->id,
                'cooking_quest_id' => $existingNotification->cooking_quest_id,
                'fishing_quest_id' => $existingNotification->fishing_quest_id,
                'dungeon_quest_id' => $existingNotification->dungeon_quest_id,
                'heroic_quest_id' => $existingNotification->heroic_quest_id,
                'pvp_quest_id' => $existingNotification->pvp_quest_id,
            ] : null,
        ]);
    }

    public function store(StoreDailyQuestsRequest $request): RedirectResponse
    {
        $existingNotification = DailyQuestNotification::getTodaysNotification();

        $questData = [
            'cooking_quest_id' => $request->validated('cooking_quest_id'),
            'fishing_quest_id' => $request->validated('fishing_quest_id'),
            'dungeon_quest_id' => $request->validated('dungeon_quest_id'),
            'heroic_quest_id' => $request->validated('heroic_quest_id'),
            'pvp_quest_id' => $request->validated('pvp_quest_id'),
        ];

        if ($existingNotification) {
            $questData['updated_by_user_id'] = $request->user()->id;
            $existingNotification->update($questData);

            return back()->with('success', 'Daily quests updated and Discord message edited!');
        }

        $questData['date'] = DailyQuestNotification::currentDailyQuestDate();
        $questData['sent_by_user_id'] = $request->user()->id;

        DailyQuestNotification::create($questData);

        DailyQuestNotification::where('date', '<', $questData['date'])
            ->whereNull('deleted_at')
            ->each(fn ($old) => $old->delete());

        return back()->with('success', 'Daily quests set and posted to Discord!');
    }

    public function audit(Request $request): Response
    {
        $logPath = storage_path('logs/daily-quests.log');
        $entries = collect();

        if (file_exists($logPath)) {
            $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            $entries = collect($lines)
                ->map(function (string $line) {
                    if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.\w+: (.+)$/', $line, $matches)) {
                        return null;
                    }

                    $timestamp = $matches[1];
                    $message = $matches[2];

                    if (! preg_match('/^(.+?) (posted|updated|deleted) daily quests for (\d{4}-\d{2}-\d{2})\.$/', $message, $parts)) {
                        return null;
                    }

                    return [
                        'timestamp' => $timestamp,
                        'user' => $parts[1],
                        'action' => $parts[2],
                        'date' => $parts[3],
                    ];
                })
                ->filter()
                ->reverse()
                ->values();
        }

        $page = (int) $request->input('page', 1);
        $perPage = 50;
        $paginator = new LengthAwarePaginator(
            $entries->forPage($page, $perPage)->values(),
            $entries->count(),
            $perPage,
            $page,
            ['path' => $request->url()]
        );

        return Inertia::render('Dashboard/DailyQuestsAuditLog', [
            'entries' => $paginator,
        ]);
    }

    /**
     * Build the quests data for the public index page.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function buildQuestsData(BlizzardService $blizzard, MediaService $media): ?array
    {
        $nextReset = now('Europe/Paris')->hour < 3
            ? now('Europe/Paris')->setTime(3, 0, 0)
            : now('Europe/Paris')->addDay()->setTime(3, 0, 0);

        $ttl = (int) now()->diffInSeconds($nextReset);

        return Cache::remember('daily_quest_notification:today', $ttl, function () use ($blizzard, $media) {
            $notification = DailyQuestNotification::with([
                'fishingQuest',
                'cookingQuest',
                'dungeonQuest',
                'heroicQuest',
                'pvpQuest',
            ])->where('date', DailyQuestNotification::currentDailyQuestDate())->first();

            if (! $notification) {
                return null;
            }

            $questOrder = [
                ['key' => 'fishingQuest', 'label' => 'Fishing'],
                ['key' => 'cookingQuest', 'label' => 'Cooking'],
                ['key' => 'dungeonQuest', 'label' => 'Dungeon'],
                ['key' => 'heroicQuest', 'label' => 'Heroic'],
                ['key' => 'pvpQuest', 'label' => 'PvP'],
            ];

            $quests = [];

            foreach ($questOrder as $entry) {
                $quest = $notification->{$entry['key']};

                if (! $quest) {
                    continue;
                }

                $label = $entry['label'];
                if (in_array($entry['key'], ['dungeonQuest', 'heroicQuest']) && $quest->instance) {
                    $label .= " ({$quest->mode})";
                }

                $quests[] = [
                    'label' => $label,
                    'name' => $quest->name,
                    'icon' => $media->get($this->getIconForQuestType($entry['key'])),
                    'type' => $quest->type,
                    'instance' => $quest->instance?->value,
                    'mode' => $quest->mode,
                    'rewards' => $this->buildRewardsData($quest->rewards ?? [], $blizzard, $media),
                ];
            }

            return $quests;
        });
    }

    /**
     * Build reward data with item details from Blizzard API.
     *
     * @param  array<int, array{item_id: int, quantity: int}>  $rewards
     * @return array<int, array<string, mixed>>
     */
    protected function buildRewardsData(array $rewards, BlizzardService $blizzard, MediaService $media): array
    {
        return array_map(function (array $reward) use ($blizzard, $media) {
            $itemId = $reward['item_id'];
            $quantity = $reward['quantity'] ?? 1;

            try {
                $blizzardData = $blizzard->findItem($itemId);
                $mediaData = $blizzard->findMedia('item', $itemId);
                $assets = Arr::get($mediaData, 'assets', []);
                $iconUrl = null;

                if (! empty($assets)) {
                    $urls = $media->get($assets);
                    $iconUrl = array_values($urls)[0] ?? null;
                }
            } catch (\Exception) {
                $blizzardData = [];
                $iconUrl = null;
            }

            return [
                'item_id' => $itemId,
                'quantity' => $quantity,
                'name' => $blizzardData['name'] ?? "Item #{$itemId}",
                'quality' => strtolower($blizzardData['quality']['name'] ?? 'common'),
                'icon' => $iconUrl,
                'wowhead_url' => 'https://www.wowhead.com/tbc/item='.$itemId.'/'.Str::slug($blizzardData['name'] ?? ''),
            ];
        }, $rewards);
    }

    protected function getIconForQuestType(string $type): string
    {
        return match ($type) {
            'fishingQuest' => DailyQuestIcons::Fishing->value,
            'cookingQuest' => DailyQuestIcons::Cooking->value,
            'dungeonQuest' => DailyQuestIcons::Dungeon->value,
            'heroicQuest' => DailyQuestIcons::HeroicDungeon->value,
            'pvpQuest' => DailyQuestIcons::PvP->value,
            default => DailyQuestIcons::Default->value,
        };
    }
}
