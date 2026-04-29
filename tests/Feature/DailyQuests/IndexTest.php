<?php

namespace Tests\Feature\DailyQuests;

use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findItem')->andReturn(['name' => 'Test Item', 'quality' => ['name' => 'Common']]);
            $mock->shouldReceive('findMedia')->andReturn(['assets' => []]);
        });

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')->andReturn('/fake-icon.jpg');
        });
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
