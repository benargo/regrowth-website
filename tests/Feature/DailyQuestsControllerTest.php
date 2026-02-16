<?php

namespace Tests\Feature;

use App\Models\TBC\DailyQuest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyQuestsControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function form_requires_authentication(): void
    {
        $response = $this->get(route('dashboard.daily-quests.form'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function form_requires_dashboard_access(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard.daily-quests.form'));

        $response->assertForbidden();
    }

    #[Test]
    public function form_displays_quests_grouped_by_type(): void
    {
        $user = User::factory()->officer()->create();

        $cookingQuest = DailyQuest::factory()->cooking()->create();
        $fishingQuest = DailyQuest::factory()->fishing()->create();
        $dungeonQuest = DailyQuest::factory()->dungeon()->create();
        $heroicQuest = DailyQuest::factory()->heroic()->create();
        $pvpQuest = DailyQuest::factory()->pvp()->create();

        $response = $this->actingAs($user)->get(route('dashboard.daily-quests.form'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('DailyQuests/Form')
            ->has('cookingQuests', 1)
            ->has('fishingQuests', 1)
            ->has('dungeonQuests', 1)
            ->has('heroicQuests', 1)
            ->has('pvpQuests', 1)
        );
    }

    #[Test]
    public function store_creates_daily_quest_notification(): void
    {
        Queue::fake();

        $user = User::factory()->officer()->create();

        $cookingQuest = DailyQuest::factory()->cooking()->create();
        $fishingQuest = DailyQuest::factory()->fishing()->create();
        $dungeonQuest = DailyQuest::factory()->dungeon()->create();
        $heroicQuest = DailyQuest::factory()->heroic()->create();
        $pvpQuest = DailyQuest::factory()->pvp()->create();

        $response = $this->actingAs($user)->post(route('dashboard.daily-quests.store'), [
            'cooking_quest_id' => $cookingQuest->id,
            'fishing_quest_id' => $fishingQuest->id,
            'dungeon_quest_id' => $dungeonQuest->id,
            'heroic_quest_id' => $heroicQuest->id,
            'pvp_quest_id' => $pvpQuest->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Daily quests set and posted to Discord!');

        $this->assertDatabaseHas('tbc_daily_quest_notifications', [
            'cooking_quest_id' => $cookingQuest->id,
            'fishing_quest_id' => $fishingQuest->id,
            'dungeon_quest_id' => $dungeonQuest->id,
            'heroic_quest_id' => $heroicQuest->id,
            'pvp_quest_id' => $pvpQuest->id,
            'sent_by_user_id' => $user->id,
        ]);
    }

    #[Test]
    public function store_validates_quest_types(): void
    {
        $user = User::factory()->officer()->create();
        $cookingQuest = DailyQuest::factory()->cooking()->create();

        // Try to use a cooking quest for fishing (wrong type)
        $response = $this->actingAs($user)->post(route('dashboard.daily-quests.store'), [
            'cooking_quest_id' => $cookingQuest->id,
            'fishing_quest_id' => $cookingQuest->id, // Wrong type!
            'dungeon_quest_id' => DailyQuest::factory()->dungeon()->create()->id,
            'heroic_quest_id' => DailyQuest::factory()->heroic()->create()->id,
            'pvp_quest_id' => DailyQuest::factory()->pvp()->create()->id,
        ]);

        $response->assertSessionHasErrors(['fishing_quest_id']);
    }

    #[Test]
    public function store_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.daily-quests.store'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function store_requires_dashboard_access(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('dashboard.daily-quests.store'), [
            'cooking_quest_id' => 1,
            'fishing_quest_id' => 2,
            'dungeon_quest_id' => 3,
            'heroic_quest_id' => 4,
            'pvp_quest_id' => 5,
        ]);

        $response->assertForbidden();
    }
}
