<?php

namespace Tests\Feature\Actions\Navigation;

use App\Actions\Navigation\BuildNavigationData;
use App\Enums\SignupStatus;
use App\Models\Character;
use App\Models\Comment;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildNavigationDataTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $gameVersion;

    private User $user;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();

        $this->gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
        $this->user = User::factory()->create();
        Character::factory()->for($this->user)->create();
        $this->event = $this->eventStartingAt($this->gameVersion, now()->addDay());
    }

    #[Test]
    public function it_describes_the_next_raid_and_the_members_place_in_it(): void
    {
        $this->signUp($this->user);

        $nextRaid = BuildNavigationData::run($this->user, $this->gameVersion)->nextRaid;

        $this->assertSame($this->event->id, $nextRaid->id);
        $this->assertSame($this->event->title, $nextRaid->title);
        $this->assertTrue($nextRaid->startTime->equalTo($this->event->start_time));
        $this->assertSame(route('raiding.plans.show', $this->event), $nextRaid->planUrl);
        $this->assertSame("You're signed up.", $nextRaid->signup->label);
    }

    #[Test]
    public function a_guest_gets_the_next_raid_without_a_signup_line(): void
    {
        $nextRaid = BuildNavigationData::run(null, $this->gameVersion)->nextRaid;

        $this->assertSame($this->event->id, $nextRaid->id);
        $this->assertNull($nextRaid->signup);
    }

    #[Test]
    public function it_lists_the_latest_discussions(): void
    {
        $item = Item::factory()->withName()->create(['game_version_id' => $this->gameVersion->id]);
        Comment::factory()->for($item, 'commentable')->create();

        $latestDiscussions = BuildNavigationData::run($this->user, $this->gameVersion)->latestDiscussions;

        $this->assertCount(1, $latestDiscussions);
        $this->assertSame($item->name, $latestDiscussions[0]->itemName);
    }

    #[Test]
    public function it_is_empty_without_any_game_version(): void
    {
        $navigation = BuildNavigationData::run($this->user, null);

        $this->assertNull($navigation->nextRaid);
        $this->assertSame([], $navigation->latestDiscussions);
    }

    #[Test]
    public function it_caches_arrays_under_the_version_and_viewer(): void
    {
        BuildNavigationData::run($this->user, $this->gameVersion);
        BuildNavigationData::run(null, $this->gameVersion);

        $this->assertIsArray(Cache::tags(['navigation'])->get("navigation:{$this->gameVersion->id}:{$this->user->id}"));
        $this->assertIsArray(Cache::tags(['navigation'])->get("navigation:{$this->gameVersion->id}:guest"));

        BuildNavigationData::run($this->user, null);

        $this->assertIsArray(Cache::tags(['navigation'])->get("navigation:none:{$this->user->id}"));
    }

    #[Test]
    public function it_serves_the_cached_data_for_five_minutes(): void
    {
        $first = BuildNavigationData::run($this->user, $this->gameVersion);
        $sooner = $this->eventStartingAt($this->gameVersion, now()->addHour());

        $second = BuildNavigationData::run($this->user, $this->gameVersion);

        $this->assertSame($this->event->id, $second->nextRaid->id);
        $this->assertEquals($first->toArray(), $second->toArray());

        $this->travel(5)->minutes();
        $this->travel(1)->second();

        $this->assertSame($sooner->id, BuildNavigationData::run($this->user, $this->gameVersion)->nextRaid->id);
    }

    #[Test]
    public function each_member_has_their_own_cache_entry(): void
    {
        $this->signUp($this->user);

        BuildNavigationData::run($this->user, $this->gameVersion);
        $other = User::factory()->create();

        $this->assertSame("You haven't signed up yet.", BuildNavigationData::run($other, $this->gameVersion)->nextRaid->signup->label);
    }

    #[Test]
    public function a_guest_never_gets_a_members_cached_data(): void
    {
        $this->signUp($this->user);

        BuildNavigationData::run($this->user, $this->gameVersion);

        $this->assertNull(BuildNavigationData::run(null, $this->gameVersion)->nextRaid->signup);
    }

    #[Test]
    public function each_game_version_has_its_own_cache_entry(): void
    {
        $second = GameVersion::factory()->create(['release_date' => Carbon::parse('2023-01-01')]);
        $secondEvent = $this->eventStartingAt($second, now()->addDay());

        BuildNavigationData::run($this->user, $this->gameVersion);

        $this->assertSame($secondEvent->id, BuildNavigationData::run($this->user, $second)->nextRaid->id);
    }

    private function eventStartingAt(GameVersion $gameVersion, Carbon $startTime): Event
    {
        return Event::factory()->live()->forGameVersion($gameVersion)->create([
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHours(3),
        ]);
    }

    private function signUp(User $user): void
    {
        $character = Character::factory()->for($user)->create();

        $this->event->characters()->attach($character, ['signup_status' => SignupStatus::Confirmed]);
    }
}
