<?php

namespace Tests\Feature\Http;

use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('platform')]
class RecordGameVersionDeepLinksTest extends TestCase
{
    use MocksBlizzardServices;
    use MocksDiscordService;
    use RefreshDatabase;

    private GameVersion $recordVersion;

    private GameVersion $sessionVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recordVersion = GameVersion::factory()->forGuild()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $this->sessionVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2025-01-01')]);
    }

    #[Test]
    public function opening_another_versions_event_switches_to_that_version(): void
    {
        $this->fakeDiscordChannel();
        $event = Event::factory()->live()->forGameVersion($this->recordVersion)->create();

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $this->sessionVersion->slug])
            ->get(route('raiding.plans.show', $event));

        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $this->recordVersion->slug);
    }

    #[Test]
    #[Group('authorization')]
    public function a_forbidden_event_leaves_the_session_alone(): void
    {
        $event = Event::factory()->live()->forGameVersion($this->recordVersion)->create([
            'start_time' => now()->subWeeks(3)->subHours(3),
            'end_time' => now()->subWeeks(3),
        ]);

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $this->sessionVersion->slug])
            ->get(route('raiding.plans.show', $event));

        $response->assertForbidden();
        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $this->sessionVersion->slug);
    }

    #[Test]
    public function opening_another_versions_item_switches_to_that_version(): void
    {
        $this->mockGetItem();
        $this->applyBlizzardMocks();
        $item = Item::factory()->withName('Gloves of the Searing Grip')->create(['game_version_id' => $this->recordVersion->id]);

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $this->sessionVersion->slug])
            ->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $this->recordVersion->slug);
    }

    #[Test]
    public function opening_another_versions_report_switches_to_that_version(): void
    {
        $report = Report::factory()->forGameVersion($this->recordVersion)->create();

        $response = $this->withSession([SharesCurrentGameVersionSession::SESSION_KEY => $this->sessionVersion->slug])
            ->get(route('raiding.reports.show', $report));

        $response->assertSessionHas(SharesCurrentGameVersionSession::SESSION_KEY, $this->recordVersion->slug);
    }
}
