<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDailyQuestsRequest;
use App\Models\TBC\DailyQuest;
use App\Models\TBC\DailyQuestNotification;
use App\Services\Blizzard\ItemService;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DailyQuestsController extends Controller
{
    const COOKING_QUEST_ICON = 'inv_misc_food_15';

    const FISHING_QUEST_ICON = 'trade_fishing';

    const DUNGEON_QUEST_ICON = 'inv_qiraj_jewelencased';

    const HEROIC_QUEST_ICON = 'spell_holy_championsbond';

    const PVP_QUEST_ICON = 'inv_bannerpvp_02';

    public function index(MediaService $mediaService): Response
    {
        return Inertia::render('DailyQuests/Index', [
            'hasNotification' => DailyQuestNotification::existsForToday(),
            'quests' => Inertia::defer(fn () => $this->buildQuestsData($mediaService)),
        ]);
    }

    public function form(MediaService $mediaService): Response
    {
        $existingNotification = DailyQuestNotification::getTodaysNotification();

        $icons = [
            'cooking' => $mediaService->getIconUrl(self::COOKING_QUEST_ICON),
            'fishing' => $mediaService->getIconUrl(self::FISHING_QUEST_ICON),
            'dungeon' => $mediaService->getIconUrl(self::DUNGEON_QUEST_ICON),
            'heroic' => $mediaService->getIconUrl(self::HEROIC_QUEST_ICON),
            'pvp' => $mediaService->getIconUrl(self::PVP_QUEST_ICON),
        ];

        $quests = Cache::remember('daily_quests.all', now()->addMonth(), function () {
            return DailyQuest::all();
        })->groupBy('type');

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
    protected function buildQuestsData(MediaService $mediaService): ?array
    {
        $nextReset = now('Europe/Paris')->hour < 3
            ? now('Europe/Paris')->setTime(3, 0, 0)
            : now('Europe/Paris')->addDay()->setTime(3, 0, 0);

        $ttl = (int) now()->diffInSeconds($nextReset);

        $notification = Cache::remember('daily_quest_notification.today', $ttl, function () {
            return DailyQuestNotification::with([
                'fishingQuest',
                'cookingQuest',
                'dungeonQuest',
                'heroicQuest',
                'pvpQuest',
            ])->where('date', DailyQuestNotification::currentDailyQuestDate())->first();
        });

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

        $itemService = app(ItemService::class);

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
                'icon' => $mediaService->getIconUrl($this->getIconForQuestType($entry['key'])),
                'type' => $quest->type,
                'instance' => $quest->instance?->value,
                'mode' => $quest->mode,
                'rewards' => $this->buildRewardsData($quest->rewards ?? [], $itemService, $mediaService),
            ];
        }

        return $quests;
    }

    /**
     * Build reward data with item details from Blizzard API.
     *
     * @param  array<int, array{item_id: int, quantity: int}>  $rewards
     * @return array<int, array<string, mixed>>
     */
    protected function buildRewardsData(array $rewards, ItemService $itemService, MediaService $mediaService): array
    {
        return array_map(function (array $reward) use ($itemService, $mediaService) {
            $itemId = $reward['item_id'];
            $quantity = $reward['quantity'] ?? 1;

            try {
                $blizzardData = $itemService->find($itemId);
                $media = $mediaService->find('item', $itemId);
                $assets = $media['assets'] ?? [];
                $iconUrl = null;

                if (! empty($assets)) {
                    $urls = $mediaService->getAssetUrls($assets);
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
            'fishingQuest' => self::FISHING_QUEST_ICON,
            'cookingQuest' => self::COOKING_QUEST_ICON,
            'dungeonQuest' => self::DUNGEON_QUEST_ICON,
            'heroicQuest' => self::HEROIC_QUEST_ICON,
            'pvpQuest' => self::PVP_QUEST_ICON,
            default => 'inv_misc_questionmark',
        };
    }
}
