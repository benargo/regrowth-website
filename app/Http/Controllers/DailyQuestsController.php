<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDailyQuestsRequest;
use App\Models\TBC\DailyQuest;
use App\Models\TBC\DailyQuestNotification;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DailyQuestsController extends Controller
{
    public function form(MediaService $mediaService): Response
    {
        $existingNotification = DailyQuestNotification::getTodaysNotification();

        $icons = [
            'cooking' => $mediaService->getIconUrl('inv_misc_food_15'),
            'fishing' => $mediaService->getIconUrl('trade_fishing'),
            'dungeon' => $mediaService->getIconUrl('inv_qiraj_jewelencased'),
            'heroic' => $mediaService->getIconUrl('spell_holy_championsbond'),
            'pvp' => $mediaService->getIconUrl('inv_bannerpvp_02'),
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
}
