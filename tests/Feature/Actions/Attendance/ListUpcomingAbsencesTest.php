<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\ListUpcomingAbsences;
use App\Enums\PlannedAbsenceReason;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\PlannedAbsence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ListUpcomingAbsencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeTime();
    }

    #[Test]
    public function it_lists_the_versions_upcoming_absences_in_start_order(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $character = Character::factory()->forGameVersion($gameVersion)->create();
        $later = PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => today()->addDays(5)]);
        $today = PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => today()]);
        PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => today()->subDay()]);
        PlannedAbsence::factory()->create([
            'character_id' => Character::factory()->forGameVersion(GameVersion::factory()->create())->create()->id,
            'start_date' => today()->addDay(),
        ]);

        $this->assertSame([$today->id, $later->id], ListUpcomingAbsences::run($gameVersion)->modelKeys());
    }

    #[Test]
    public function it_loads_each_characters_playable_class(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $character = Character::factory()->forGameVersion($gameVersion)->withPlayableClass()->create();
        PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => today()->addDay()]);

        $this->assertTrue(ListUpcomingAbsences::run($gameVersion)->first()->character->relationLoaded('playableClass'));
    }

    #[Test]
    public function it_caches_each_versions_absences_separately(): void
    {
        $first = GameVersion::factory()->create();
        $second = GameVersion::factory()->create();
        $absence = PlannedAbsence::factory()->create([
            'character_id' => Character::factory()->forGameVersion($second)->create()->id,
            'start_date' => today()->addDay(),
        ]);

        ListUpcomingAbsences::run($first);

        $this->assertSame([$absence->id], ListUpcomingAbsences::run($second)->modelKeys());
        $this->assertTrue(Cache::tags(['attendance'])->has("planned_absences:upcoming:{$first->id}"));
    }

    #[Test]
    public function it_lists_an_absence_created_after_the_list_was_cached(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $character = Character::factory()->forGameVersion($gameVersion)->create();

        ListUpcomingAbsences::run($gameVersion);
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => today()->addDay()]);

        $this->assertSame([$absence->id], ListUpcomingAbsences::run($gameVersion)->modelKeys());
    }

    #[Test]
    public function it_returns_cast_absences_from_a_serializing_cache_store(): void
    {
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');

        $gameVersion = GameVersion::factory()->create();
        PlannedAbsence::factory()->create([
            'character_id' => Character::factory()->forGameVersion($gameVersion)->create()->id,
            'start_date' => '2026-10-20 00:00:00',
            'reason' => PlannedAbsenceReason::Holiday,
        ]);

        ListUpcomingAbsences::run($gameVersion);
        $absence = ListUpcomingAbsences::run($gameVersion)->first();

        $this->assertInstanceOf(PlannedAbsence::class, $absence);
        $this->assertSame('2026-10-20', $absence->start_date->toDateString());
        $this->assertSame(PlannedAbsenceReason::Holiday, $absence->reason->key);
    }
}
