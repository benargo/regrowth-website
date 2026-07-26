<?php

namespace App\Http\Controllers;

use App\Enums\DailyQuestType;
use App\Http\Requests\StoreDailyQuestsRequest;
use App\Http\Resources\DailyQuestResource;
use App\Models\DailyQuest;
use App\Models\DiscordNotification;
use App\Models\DiscordNotificationRelatedModel;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Discord;
use App\Services\Discord\Notifications\NotifiableChannel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DailyQuestsController extends Controller
{
    public function __construct(
        private Discord $discord,
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
                ->where('created_at', '>=', Carbon::yesterday()->setTime(4, 0, 0))
                ->where('created_at', '<=', Carbon::tomorrow()->setTime(3, 59, 59))
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
    #[Authorize('set-daily-quests')]
    public function form(): Response
    {
        $quests = DailyQuest::hydrate(
            Cache::tags(['dailyquests'])->remember('daily_quests:all', now()->addMonth(), function () {
                return DailyQuest::all()->map->getAttributes()->toArray();
            })
        )->groupBy(fn (DailyQuest $quest) => $quest->type->value);

        return Inertia::render('Manage/DailyQuests/Form', [
            'cookingQuests' => $quests->get(DailyQuestType::Cooking->value, collect())->values()->toArray(),
            'fishingQuests' => $quests->get(DailyQuestType::Fishing->value, collect())->values()->toArray(),
            'dungeonQuests' => $quests->get(DailyQuestType::Dungeon->value, collect())->values()->toArray(),
            'heroicQuests' => $quests->get(DailyQuestType::Heroic->value, collect())->values()->toArray(),
            'pvpQuests' => $quests->get(DailyQuestType::PvP->value, collect())->values()->toArray(),
            'icons' => $this->categoryIcons(),
            'existingQuests' => $this->existingQuestSelections(),
        ]);
    }

    /**
     * Handle the form submission to set or update daily quests.
     */
    #[Authorize('set-daily-quests')]
    public function store(StoreDailyQuestsRequest $request): RedirectResponse
    {
        $quests = [
            DailyQuestType::Cooking->name => $request->input('cooking_quest_id') ? DailyQuest::find($request->input('cooking_quest_id')) : null,
            DailyQuestType::Fishing->name => $request->input('fishing_quest_id') ? DailyQuest::find($request->input('fishing_quest_id')) : null,
            DailyQuestType::Dungeon->name => $request->input('dungeon_quest_id') ? DailyQuest::find($request->input('dungeon_quest_id')) : null,
            DailyQuestType::Heroic->name => $request->input('heroic_quest_id') ? DailyQuest::find($request->input('heroic_quest_id')) : null,
            DailyQuestType::PvP->name => $request->input('pvp_quest_id') ? DailyQuest::find($request->input('pvp_quest_id')) : null,
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
     * Build the category icon URLs keyed by the form's category keys.
     *
     * Each icon is sourced from a representative quest's media (the UrlGenerator
     * signs blizzard_icons URLs automatically), falling back to null when no
     * quest of that type has an attached icon.
     *
     * @return array<string, string|null>
     */
    private function categoryIcons(): array
    {
        return Cache::tags(['dailyquests'])->remember('daily_quests:category_icons', now()->addMonth(), function () {
            $representatives = DailyQuest::query()
                ->whereHas('media', fn ($query) => $query->where('collection_name', 'blizzard_icons'))
                ->with('media')
                ->get()
                ->keyBy(fn (DailyQuest $quest) => $quest->type->value);

            return [
                'cooking' => $this->iconFor($representatives, DailyQuestType::Cooking),
                'fishing' => $this->iconFor($representatives, DailyQuestType::Fishing),
                'dungeon' => $this->iconFor($representatives, DailyQuestType::Dungeon),
                'heroic' => $this->iconFor($representatives, DailyQuestType::Heroic),
                'pvp' => $this->iconFor($representatives, DailyQuestType::PvP),
            ];
        });
    }

    /**
     * Resolve the icon URL for a quest type from the representative quests.
     *
     * @param  Collection<string, DailyQuest>  $representatives
     */
    private function iconFor($representatives, DailyQuestType $type): ?string
    {
        $quest = $representatives->get($type->value);

        return $quest ? ($quest->getFirstMediaUrl('blizzard_icons') ?: null) : null;
    }

    /**
     * Build the currently-selected quest id per category from today's notification,
     * so the form can pre-populate existing values for correction.
     *
     * @return array<string, int|null>
     */
    private function existingQuestSelections(): array
    {
        $selections = [
            'cooking_quest_id' => null,
            'fishing_quest_id' => null,
            'dungeon_quest_id' => null,
            'heroic_quest_id' => null,
            'pvp_quest_id' => null,
        ];

        $notification = $this->getExistingNotification();

        if (! $notification) {
            return $selections;
        }

        // The related-model morph is resolved lazily per row rather than eager-loaded:
        // model_id is stored as a string (morph keys are polymorphic), which breaks the
        // strict type matching Eloquent uses when eager-loading morphTo relations.
        foreach ($notification->relatedModels as $related) {
            $quest = $related->relatedModel;

            if (! $quest instanceof DailyQuest) {
                continue;
            }

            $key = $this->categoryKeyForType($quest->type);

            if ($key) {
                $selections[$key] = $quest->id;
            }
        }

        return $selections;
    }

    /**
     * Map a DailyQuestType to its form field key.
     */
    private function categoryKeyForType(DailyQuestType $type): ?string
    {
        return match ($type) {
            DailyQuestType::Cooking => 'cooking_quest_id',
            DailyQuestType::Fishing => 'fishing_quest_id',
            DailyQuestType::Dungeon => 'dungeon_quest_id',
            DailyQuestType::Heroic => 'heroic_quest_id',
            DailyQuestType::PvP => 'pvp_quest_id',
        };
    }

    /**
     * Build the quests data for the public index page from the latest notification's
     * related models.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function buildQuestsData(): ?array
    {
        try {
            return Cache::tags(['dailyquests'])->remember('daily_quests:today', $this->resetTime(), function () {
                $notification = DiscordNotification::where('type', DailyQuestsMessage::class)
                    ->where('created_at', '>=', Carbon::yesterday()->setTime(4, 0, 0))
                    ->where('created_at', '<=', Carbon::tomorrow()->setTime(3, 59, 59))
                    ->with('relatedModels')
                    ->latest()
                    ->firstOrFail();

                // The related-model morph is resolved lazily per row rather than eager-loaded:
                // model_id is stored as a string (morph keys are polymorphic), which breaks the
                // strict type matching Eloquent uses when eager-loading morphTo relations.
                $quests = $notification->relatedModels
                    ->map(fn (DiscordNotificationRelatedModel $related) => $related->relatedModel)
                    ->filter(fn ($model) => $model instanceof DailyQuest)
                    ->values();

                $quests->loadMissing('rewards.media');

                return DailyQuestResource::collection($quests)->resolve();
            });
        } catch (ModelNotFoundException) {
            // It's fine if there's no notification for today, just return null and the frontend can handle it.
            // We use this try-catch to avoid caching a null value, which would prevent the system from picking up
            // a new notification if one is created later in the day.
            return null;
        }
    }
}
