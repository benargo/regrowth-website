<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Enums\SignupStatus;
use App\Jobs\RaidHelper\DeleteEvent;
use App\Models\Character;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
class DeleteEventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_an_event_by_raid_helper_event_id(): void
    {
        Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);

        DeleteEvent::dispatchSync('111222333444555001');

        $this->assertDatabaseMissing('events', ['raid_helper_event_id' => '111222333444555001']);
    }

    #[Test]
    public function it_does_nothing_when_the_event_does_not_exist(): void
    {
        Event::factory()->create();

        DeleteEvent::dispatchSync('nonexistent-id');

        $this->assertDatabaseCount('events', 1);
    }

    #[Test]
    public function it_detaches_characters_before_deleting(): void
    {
        $event = Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);
        $character = Character::factory()->create();
        $event->characters()->attach($character->id, [
            'slot_number' => null,
            'group_number' => null,
            'signup_status' => SignupStatus::Unconfirmed->value,
            'is_benched' => true,
        ]);

        $this->assertDatabaseHas('pivot_events_characters', ['event_id' => $event->id]);

        DeleteEvent::dispatchSync('111222333444555001');

        $this->assertDatabaseMissing('pivot_events_characters', ['event_id' => $event->id]);
    }

    #[Test]
    public function it_flushes_the_events_cache(): void
    {
        Cache::tags(['events'])->put('events:test', 'value', 60);
        Event::factory()->create(['raid_helper_event_id' => '111222333444555001']);

        DeleteEvent::dispatchSync('111222333444555001');

        $this->assertNull(Cache::tags(['events'])->get('events:test'));
    }
}
