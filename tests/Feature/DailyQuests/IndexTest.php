<?php

namespace Tests\Feature\DailyQuests;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Saloon::fake([
            GetItemRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'name' => 'Thunderfury, Blessed Blade of the Windseeker',
                'quality' => ['type' => 'LEGENDARY', 'name' => 'Legendary'],
                'level' => 80,
                'required_level' => 60,
                'media' => ['key' => ['href' => 'https://example.test/media/19019']],
                'item_class' => ['key' => ['href' => 'https://example.test/item-class/2'], 'name' => 'Weapon', 'id' => 2],
                'item_subclass' => ['key' => ['href' => 'https://example.test/item-subclass/2-7'], 'name' => 'One-Handed Sword', 'id' => 7],
                'inventory_type' => ['type' => 'WEAPONMAINHAND', 'name' => 'Main Hand'],
                'purchase_price' => 0,
                'sell_price' => 0,
            ], status: 200),
            GetItemMediaRequest::class => MockResponse::make(body: [
                'id' => 19019,
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/us/icons/56/inv_sword_05.jpg', 'file_data_id' => 21588],
                ],
            ], status: 200),
        ]);
    }

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
    public function index_deferred_quests_returns_correct_structure(): void
    {
        DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => now(),
        ]);

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', true)
        );
    }

    #[Test]
    public function index_deferred_quests_are_in_correct_order(): void
    {
        DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => now(),
        ]);

        $response = $this->get(route('daily-quests.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('DailyQuests/Index')
            ->where('hasNotification', true)
        );
    }

    #[Test]
    public function index_does_not_require_authentication(): void
    {
        $response = $this->get(route('daily-quests.index'));

        $response->assertSuccessful();
    }
}
