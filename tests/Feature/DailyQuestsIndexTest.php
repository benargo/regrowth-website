<?php

namespace Tests\Feature;

use App\Models\TBC\DailyQuestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DailyQuestsIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_returns_successful_response(): void
    {
        $response = $this->get(route('daily-quests.index'));

        $response->assertSuccessful();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
        );
    }

    #[Test]
    public function index_shows_no_notification_when_none_exists(): void
    {
        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', false)
            ->missing('quests')
        );
    }

    #[Test]
    public function index_shows_notification_when_one_exists(): void
    {
        $notification = DailyQuestNotification::factory()
            ->forDate(DailyQuestNotification::currentDailyQuestDate())
            ->create();

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', true)
            ->missing('quests')
        );
    }

    #[Test]
    public function index_deferred_quests_returns_correct_structure(): void
    {
        $notification = DailyQuestNotification::factory()
            ->forDate(DailyQuestNotification::currentDailyQuestDate())
            ->create();

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', true)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('quests', 5)
                ->has('quests.0', fn (Assert $quest) => $quest
                    ->where('type', 'Fishing')
                    ->has('label')
                    ->has('name')
                    ->has('icon')
                    ->has('instance')
                    ->has('mode')
                    ->has('rewards')
                    ->has('rewards.0', fn (Assert $reward) => $reward
                        ->has('item_id')
                        ->has('quantity')
                        ->has('name')
                        ->has('quality')
                        ->has('icon')
                        ->has('wowhead_url')
                    )
                )
            )
        );
    }

    #[Test]
    public function index_deferred_quests_are_in_correct_order(): void
    {
        $notification = DailyQuestNotification::factory()
            ->forDate(DailyQuestNotification::currentDailyQuestDate())
            ->create();

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('quests', 5)
                ->where('quests.0.type', 'Fishing')
                ->where('quests.1.type', 'Cooking')
                ->where('quests.2.type', 'Dungeon')
                ->where('quests.2.mode', 'Normal')
                ->where('quests.3.type', 'Dungeon')
                ->where('quests.3.mode', 'Heroic')
                ->where('quests.4.type', 'PvP')
            )
        );
    }

    #[Test]
    public function index_does_not_require_authentication(): void
    {
        $response = $this->get(route('daily-quests.index'));

        $response->assertSuccessful();
    }
}
