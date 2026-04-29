<?php

namespace App\Http\Controllers;

use App\Enums\DailyQuestIcons;
use App\Http\Requests\StoreDailyQuestsRequest;
use App\Models\DailyQuest;
use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use App\Services\Discord\Discord;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Payloads\MessagePayload;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DailyQuestsController extends Controller
{
    public function __construct(
        private Discord $discord
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    /**
     * Display the current daily quests.
     */
    public function index(BlizzardService $blizzard, MediaService $media): Response
    {
        $hasNotification = Cache::tags(['dailyquests'])->remember('daily_quests:today:exists', $this->resetTime(), function () {
            return DiscordNotification::where('type', DailyQuestsMessage::class)
                ->where('created_at', '>=', Carbon::yesterday()->setHour(4, 0, 0))
                ->where('created_at', '<=', Carbon::tomorrow()->setHour(3, 59, 59))
                ->exists();
        });

        return Inertia::render('DailyQuests/Index', [
            'hasNotification' => $hasNotification,
            'quests' => Inertia::defer(fn () => $this->buildQuestsData($blizzard, $media)),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Form and Store
    |--------------------------------------------------------------------------
    */

    /**
     * Show the form to set or update daily quests, along with the existing quests if they exist.
     */
    public function form(MediaService $media): Response
    {
        // $existingNotification = $this->getExistingNotification();

        $icons = [
            'cooking' => $media->get(DailyQuestIcons::Cooking->value),
            'fishing' => $media->get(DailyQuestIcons::Fishing->value),
            'dungeon' => $media->get(DailyQuestIcons::Dungeon->value),
            'heroic' => $media->get(DailyQuestIcons::HeroicDungeon->value),
            'pvp' => $media->get(DailyQuestIcons::PvP->value),
        ];

        $quests = DailyQuest::hydrate(
            Cache::tags(['dailyquests'])->remember('daily_quests:all', now()->addMonth(), function () {
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
            // 'existingNotification' => $existingNotification ? [
            //     'id' => $existingNotification->id,
            //     'cooking_quest_id' => $existingNotification->cooking_quest_id,
            //     'fishing_quest_id' => $existingNotification->fishing_quest_id,
            //     'dungeon_quest_id' => $existingNotification->dungeon_quest_id,
            //     'heroic_quest_id' => $existingNotification->heroic_quest_id,
            //     'pvp_quest_id' => $existingNotification->pvp_quest_id,
            // ] : null,
        ]);
    }

    /**
     * Handle the form submission to set or update daily quests.
     */
    public function store(StoreDailyQuestsRequest $request): RedirectResponse
    {
        $this->channel()->notify(new DailyQuestsMessage(
            dailyQuests: [
                'Cooking' => $request->input('cooking_quest_id') ? DailyQuest::find($request->input('cooking_quest_id')) : null,
                'Fishing' => $request->input('fishing_quest_id') ? DailyQuest::find($request->input('fishing_quest_id')) : null,
                'Dungeon' => $request->input('dungeon_quest_id') ? DailyQuest::find($request->input('dungeon_quest_id')) : null,
                'Heroic' => $request->input('heroic_quest_id') ? DailyQuest::find($request->input('heroic_quest_id')) : null,
                'PvP' => $request->input('pvp_quest_id') ? DailyQuest::find($request->input('pvp_quest_id')) : null,
            ],
            sender: $request->user(),
            updates: $this->getExistingNotification(),
        ));

        return back()->with('success', 'Daily quests set and posted to Discord!');
    }

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */

    /**
     * Display an audit log of all daily quest notifications.
     */
    public function audit(Request $request): Response
    {
        $paginator = DiscordNotification::where('type', DailyQuestsMessage::class)
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        return Inertia::render('Dashboard/DailyQuestsAuditLog', [
            'entries' => $paginator,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Get the Discord channel instance for daily quests notifications.
     */
    private function channel(): NotifiableChannel
    {
        return NotifiableChannel::fromConfig('daily_quests', $this->discord);
    }

    /**
     * Calculate the number of seconds until the next reset time (3:59:59 AM Paris time) for caching purposes.
     */
    private function resetTime(): int
    {
        return Carbon::tomorrow()->setTime(3, 59, 59)->diffInSeconds(Carbon::now());
    }

    /**
     * Get today's notification, if one exists.
     */
    private function getExistingNotification(): ?DiscordNotification
    {
        return DiscordNotification::where('type', DailyQuestsMessage::class)
            ->where('created_at', '>=', Carbon::yesterday()->setTime(4, 0, 0))
            ->where('created_at', '<=', Carbon::tomorrow()->setTime(3, 59, 59))
            ->latest()
            ->first();
    }

    /**
     * Build the quests data for the public index page.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function buildQuestsData(): ?array
    {
        try {
            Cache::tags(['daily_quests'])->remember('daily_quests:today', $this->resetTime(), function () {
                $notification = DiscordNotification::where('type', DailyQuestsMessage::class)
                    ->where('created_at', '>=', Carbon::yesterday()->setTime(4, 0, 0))
                    ->where('created_at', '<=', Carbon::tomorrow()->setTime(3, 59, 59))
                    ->latest()
                    ->firstOrFail();

                // Make sure the payload is valid and can be parsed.
                if (! $notification->payload instanceof MessagePayload) {
                    throw new Exception('Invalid payload for daily quests notification');
                }

                $fields = $notification->payload->embeds[0]->fields ?? [];

                // Temporary
                return [];
            });
        } catch (ModelNotFoundException $e) {
            // It's fine if there's no notification for today, just return null and the frontend can handle it.
            // We use this try-catch to avoid caching a null value, which would prevent the system from picking up
            // a new notification if one is created later in the day.
            return null;
        }
    }

    /**
     * Build reward data with item details from Blizzard API.
     *
     * @param  array<int, array{item_id: int, quantity: int}>  $rewards
     * @return array<int, array<string, mixed>>
     */
    private function buildRewardsData(array $rewards, BlizzardService $blizzard, MediaService $media): array
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
            } catch (Exception) {
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

    /**
     * Get the appropriate icon URL for a given quest type.
     */
    private function getIconForQuestType(string $type): string
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
