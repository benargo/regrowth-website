<?php

namespace App\Http\Controllers;

use App\Enums\DailyQuestIcons;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Http\Requests\StoreDailyQuestsRequest;
use App\Models\DailyQuest;
use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use App\Services\Blizzard\BlizzardService;
use App\Services\Discord\Discord;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Payloads\MessagePayload;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DailyQuestsController extends Controller
{
    public function __construct(
        private Discord $discord,
        private RenderConnector $renderConnector,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    /**
     * Display the current daily quests.
     */
    public function index(): Response
    {
        $hasNotification = Cache::tags(['dailyquests'])->remember('daily_quests:today:exists', $this->resetTime(), function () {
            return DiscordNotification::where('type', DailyQuestsMessage::class)
                ->where('created_at', '>=', Carbon::yesterday()->setHour(4, 0, 0))
                ->where('created_at', '<=', Carbon::tomorrow()->setHour(3, 59, 59))
                ->exists();
        });

        return Inertia::render('DailyQuests/Index', [
            'hasNotification' => $hasNotification,
            'quests' => Inertia::defer(fn () => $this->buildQuestsData()),
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
    #[Authorize('view-officer-dashboard')]
    public function form(): Response
    {
        // $existingNotification = $this->getExistingNotification();

        $icons = [
            'cooking' => $this->iconUrl(DailyQuestIcons::Cooking->value),
            'fishing' => $this->iconUrl(DailyQuestIcons::Fishing->value),
            'dungeon' => $this->iconUrl(DailyQuestIcons::Dungeon->value),
            'heroic' => $this->iconUrl(DailyQuestIcons::HeroicDungeon->value),
            'pvp' => $this->iconUrl(DailyQuestIcons::PvP->value),
        ];

        $quests = DailyQuest::hydrate(
            Cache::tags(['dailyquests'])->remember('daily_quests:all', now()->addMonth(), function () {
                return DailyQuest::all()->map->getAttributes()->toArray();
            })
        )->groupBy('type');

        return Inertia::render('Dashboard/DailyQuests/Form', [
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
    #[Authorize('view-officer-dashboard')]
    public function store(StoreDailyQuestsRequest $request): RedirectResponse
    {
        $quests = [
            'Cooking' => $request->input('cooking_quest_id') ? DailyQuest::find($request->input('cooking_quest_id')) : null,
            'Fishing' => $request->input('fishing_quest_id') ? DailyQuest::find($request->input('fishing_quest_id')) : null,
            'Dungeon' => $request->input('dungeon_quest_id') ? DailyQuest::find($request->input('dungeon_quest_id')) : null,
            'Heroic' => $request->input('heroic_quest_id') ? DailyQuest::find($request->input('heroic_quest_id')) : null,
            'PvP' => $request->input('pvp_quest_id') ? DailyQuest::find($request->input('pvp_quest_id')) : null,
        ];

        $this->channel()->notify(
            (new DailyQuestsMessage($quests))
                ->updatesExisting($this->getExistingNotification())
                ->withSender($request->user())
                ->withRelatedModels(array_filter($quests))
        );

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
    #[Authorize('view-officer-dashboard')]
    #[Authorize('audit-daily-quests')]
    public function audit(Request $request): Response
    {
        $paginator = DiscordNotification::where('type', DailyQuestsMessage::class)
            ->latest()
            ->paginate(20)
            ->appends($request->query());

        return Inertia::render('Dashboard/DailyQuests/Audit', [
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
     * @todo Broken/incomplete — always returns null. Needs a follow-up task to implement.
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
     * @todo Broken/unused — depends on legacy BlizzardService and an unmigrated GetItemMediaRequest path. Needs a follow-up task.
     *
     * @param  array<int, array{item_id: int, quantity: int}>  $rewards
     * @return array<int, array<string, mixed>>
     */
    private function buildRewardsData(array $rewards, BlizzardService $blizzard): array
    {
        return array_map(function (array $reward) use ($blizzard) {
            $itemId = $reward['item_id'];
            $quantity = $reward['quantity'] ?? 1;

            try {
                $blizzardData = $blizzard->findItem($itemId);
                $iconUrl = app(BlizzardConnector::class)
                    ->send(new GetItemMediaRequest($itemId))
                    ->dto()
                    ?->assets[0]
                    ?->mirroredUrl();
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
     * Returns the mirrored Storage URL for the given Blizzard icon, or null if unavailable.
     */
    private function iconUrl(string $iconName): ?string
    {
        return $this->renderConnector->send(new FetchAssetRequest($iconName))->mirroredUrl();
    }
}
