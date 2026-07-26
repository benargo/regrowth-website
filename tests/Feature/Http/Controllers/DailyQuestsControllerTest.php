<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\DailyQuest;
use App\Models\DiscordNotification;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel as ChannelResource;
use Carbon\Carbon;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('daily-quests')]
class DailyQuestsControllerTest extends DashboardTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        DiscordRole::find('829021769448816691')->givePermissionTo(
            Permission::firstOrCreate(['name' => 'set-daily-quests', 'guard_name' => 'web'])
        );
    }

    // ==================== form() authorization ====================

    #[Test]
    public function form_requires_authentication(): void
    {
        $response = $this->get(route('management.daily-quests.form'));

        $response->assertRedirect(route('login'));
    }

    #[Group('authorization')]
    #[Test]
    public function form_requires_set_daily_quests_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('management.daily-quests.form'));

        $response->assertForbidden();
    }

    // ==================== form() data ====================

    #[Test]
    public function form_groups_quests_by_type_and_keeps_heroic_separate_from_dungeon(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create();
        $fishing = DailyQuest::factory()->fishing()->create();
        $dungeon = DailyQuest::factory()->dungeon()->create();
        $heroic = DailyQuest::factory()->heroic()->create();
        $pvp = DailyQuest::factory()->pvp()->create();

        $response = $this->actingAs($this->officer)->get(route('management.daily-quests.form'));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/DailyQuests/Form')
            ->has('cookingQuests', 1)
            ->has('fishingQuests', 1)
            ->has('dungeonQuests', 1)
            ->has('heroicQuests', 1)
            ->has('pvpQuests', 1)
            ->where('dungeonQuests.0.id', $dungeon->id)
            ->where('heroicQuests.0.id', $heroic->id)
        );
    }

    #[Test]
    public function form_pre_populates_existing_selections_from_todays_notification(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create();
        $dungeon = DailyQuest::factory()->dungeon()->create();

        $this->notificationWithQuests([$cooking, $dungeon]);

        $response = $this->actingAs($this->officer)->get(route('management.daily-quests.form'));

        $response->assertInertia(fn ($page) => $page
            ->where('existingQuests.cooking_quest_id', $cooking->id)
            ->where('existingQuests.dungeon_quest_id', $dungeon->id)
            ->where('existingQuests.fishing_quest_id', null)
            ->where('existingQuests.heroic_quest_id', null)
            ->where('existingQuests.pvp_quest_id', null)
        );
    }

    #[Test]
    public function form_existing_selections_are_null_when_no_notification_exists_today(): void
    {
        $response = $this->actingAs($this->officer)->get(route('management.daily-quests.form'));

        $response->assertInertia(fn ($page) => $page
            ->where('existingQuests.cooking_quest_id', null)
            ->where('existingQuests.fishing_quest_id', null)
            ->where('existingQuests.dungeon_quest_id', null)
            ->where('existingQuests.heroic_quest_id', null)
            ->where('existingQuests.pvp_quest_id', null)
        );
    }

    #[Test]
    public function form_exposes_category_icons_from_quest_media(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create();
        $cooking->addMediaFromString('BINARY')
            ->usingFileName('inv_misc_food_15.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');

        $response = $this->actingAs($this->officer)->get(route('management.daily-quests.form'));

        $response->assertInertia(fn ($page) => $page
            ->where('icons.cooking', fn ($url) => $url !== null && str_contains($url, 'inv_misc_food_15.jpg'))
            ->where('icons.fishing', null)
        );
    }

    // ==================== store() authorization ====================

    #[Test]
    public function store_requires_authentication(): void
    {
        $response = $this->post(route('management.daily-quests.store'));

        $response->assertRedirect(route('login'));
    }

    #[Group('authorization')]
    #[Test]
    public function store_requires_set_daily_quests_permission(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('management.daily-quests.store'));

        $response->assertForbidden();
    }

    // ==================== store() behaviour ====================

    #[Test]
    public function store_creates_daily_quest_notification(): void
    {
        Queue::fake();

        $this->mock(Discord::class)
            ->shouldReceive('getChannel')
            ->once()
            ->andReturn(ChannelResource::from(['id' => '123456789']));

        $response = $this->actingAs($this->officer)->post(route('management.daily-quests.store'), [
            'cooking_quest_id' => DailyQuest::factory()->cooking()->create()->id,
            'fishing_quest_id' => DailyQuest::factory()->fishing()->create()->id,
            'dungeon_quest_id' => DailyQuest::factory()->dungeon()->create()->id,
            'heroic_quest_id' => DailyQuest::factory()->heroic()->create()->id,
            'pvp_quest_id' => DailyQuest::factory()->pvp()->create()->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Daily quests set and posted to Discord!');

        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof DailyQuestsMessage);
    }

    #[Group('validation')]
    #[Test]
    public function store_rejects_a_quest_submitted_under_the_wrong_type(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create();

        $response = $this->actingAs($this->officer)->post(route('management.daily-quests.store'), [
            'cooking_quest_id' => $cooking->id,
            'fishing_quest_id' => $cooking->id, // wrong type
            'dungeon_quest_id' => DailyQuest::factory()->dungeon()->create()->id,
            'heroic_quest_id' => DailyQuest::factory()->heroic()->create()->id,
            'pvp_quest_id' => DailyQuest::factory()->pvp()->create()->id,
        ]);

        $response->assertSessionHasErrors(['fishing_quest_id']);
    }

    // ==================== index() / buildQuestsData() ====================

    #[Test]
    public function index_does_not_require_authentication(): void
    {
        $response = $this->get(route('daily-quests.index'));

        $response->assertSuccessful();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
        );
    }

    #[Test]
    public function index_reports_no_notification_when_none_exists_today(): void
    {
        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', false)
            ->missing('quests')
        );
    }

    #[Test]
    public function index_reports_a_notification_when_one_exists_today(): void
    {
        DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => now(),
        ]);

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', true)
            ->missing('quests')
        );
    }

    #[Test]
    public function index_includes_a_notification_created_exactly_at_the_4am_lower_boundary(): void
    {
        $this->travelTo('2026-07-26 10:00:00');

        DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => Carbon::yesterday()->setTime(4, 0, 0),
        ]);

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', true)
            ->missing('quests')
        );

        $this->travelBack();
    }

    #[Test]
    public function index_excludes_a_notification_created_one_second_before_the_4am_lower_boundary(): void
    {
        $this->travelTo('2026-07-26 10:00:00');

        DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => Carbon::yesterday()->setTime(3, 59, 59),
        ]);

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', false)
            ->missing('quests')
        );

        $this->travelBack();
    }

    #[Test]
    public function index_includes_a_notification_created_exactly_at_the_upper_boundary(): void
    {
        $this->travelTo('2026-07-26 10:00:00');

        DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => Carbon::tomorrow()->setTime(3, 59, 59),
        ]);

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', true)
            ->missing('quests')
        );

        $this->travelBack();
    }

    #[Test]
    public function index_excludes_a_notification_created_one_second_after_the_upper_boundary(): void
    {
        $this->travelTo('2026-07-26 10:00:00');

        DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => Carbon::tomorrow()->setTime(4, 0, 0),
        ]);

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', false)
            ->missing('quests')
        );

        $this->travelBack();
    }

    #[Test]
    public function index_resolves_quests_from_the_latest_notifications_related_models(): void
    {
        $cooking = DailyQuest::factory()->cooking()->create(['name' => 'Soup for the Soul']);
        $item = Item::factory()->create();
        $cooking->rewards()->attach($item->id, ['quantity' => 2]);

        $this->notificationWithQuests([$cooking]);

        $version = $this->get(route('daily-quests.index'))->viewData('page')['version'];

        $response = $this->get(route('daily-quests.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'DailyQuests/Index',
            'X-Inertia-Partial-Data' => 'quests',
        ]);

        $response->assertOk();
        $response->assertJsonPath('props.quests.0.id', $cooking->id);
        $response->assertJsonPath('props.quests.0.label', 'Soup for the Soul');
        $response->assertJsonCount(1, 'props.quests');
        $response->assertJsonCount(1, 'props.quests.0.rewards');
    }

    #[Test]
    public function index_quests_are_null_when_no_notification_exists_today(): void
    {
        $version = $this->get(route('daily-quests.index'))->viewData('page')['version'];

        $response = $this->get(route('daily-quests.index'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'DailyQuests/Index',
            'X-Inertia-Partial-Data' => 'quests',
        ]);

        $response->assertOk();
        $response->assertJsonPath('props.quests', null);
    }

    // ==================== helpers ====================

    /**
     * Build a today notification whose related models are the given quests.
     *
     * @param  array<int, DailyQuest>  $quests
     */
    private function notificationWithQuests(array $quests): DiscordNotification
    {
        return DiscordNotification::factory()
            ->withRelatedModels(array_map(fn (DailyQuest $quest) => [
                'model_type' => DailyQuest::class,
                'model_id' => $quest->id,
            ], $quests))
            ->create(['type' => DailyQuestsMessage::class]);
    }
}
