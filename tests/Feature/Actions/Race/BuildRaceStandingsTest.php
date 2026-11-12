<?php

namespace Tests\Feature\Actions\Race;

use App\Actions\Race\BuildRaceStandings;
use App\Contracts\HasShadowAvatars;
use App\Enums\Gender;
use App\Enums\Medal;
use App\Enums\RaceState;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\RacePlacement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class BuildRaceStandingsTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->version = GameVersion::factory()->create();
    }

    #[Test]
    public function it_is_running_without_bronze(): void
    {
        $standings = $this->standings();

        $this->assertSame(RaceState::Running, $standings['state']);
        $this->assertNull($standings['closes_at']);
        $this->assertSame(60, $standings['target_level']);
    }

    #[Test]
    public function it_is_closing_within_forty_eight_hours_of_bronze(): void
    {
        $reachedAt = now()->subHours(2)->startOfSecond();
        $this->place($this->racer(60, 'Aa'), 1, now()->subHours(4));
        $this->place($this->racer(60, 'Bb'), 2, now()->subHours(3));
        $this->place($this->racer(60, 'Cc'), 3, $reachedAt);

        $standings = $this->standings();

        $this->assertSame(RaceState::Closing, $standings['state']);
        $this->assertSame($reachedAt->copy()->addHours(48)->toIso8601String(), $standings['closes_at']);
    }

    #[Test]
    public function it_is_finished_after_forty_eight_hours(): void
    {
        $this->place($this->racer(60, 'Cc'), 3, now()->subHours(49));

        $this->assertSame(RaceState::Finished, $this->standings()['state']);
    }

    // ==================== leader ====================

    #[Test]
    public function it_picks_the_highest_unplaced_character_as_leader(): void
    {
        $this->place($this->racer(60, 'Placed'), 1);
        $this->racer(41, 'Leader');
        $this->racer(38, 'Other');

        $leader = $this->standings()['leader'];

        $this->assertSame('Leader', $leader->resolve()['name']);
    }

    #[Test]
    public function it_has_no_leader_without_unplaced_characters(): void
    {
        $standings = $this->standings();

        $this->assertNull($standings['leader']);
        $this->assertSame([], $standings['pack']->resolve());
    }

    #[Test]
    public function it_builds_the_pack_from_the_leader_window(): void
    {
        $first = $this->racer(41, 'A');
        $second = $this->racer(36, 'B');
        $this->racer(35, 'C');
        $this->racer(20, 'D');

        $pack = $this->standings()['pack']->resolve();

        $this->assertSame([$first->id, $second->id], array_column($pack, 'id'));
    }

    #[Test]
    public function it_loads_only_the_pack_when_racers_are_omitted(): void
    {
        foreach ([40, 38, 30, 12] as $level) {
            $this->racer($level, 'Racer'.$level);
        }

        $retrieved = 0;
        Event::listen('eloquent.retrieved: '.Character::class, function () use (&$retrieved): void {
            $retrieved++;
        });

        $standings = $this->standings(withRacers: false);

        $this->assertSame(2, $retrieved);
        $this->assertSame(['Racer40', 'Racer38'], array_column($standings['pack']->resolve(), 'name'));
    }

    // ==================== racers ====================

    #[Test]
    public function it_lists_every_racer_by_level_then_name(): void
    {
        $placed = $this->racer(60, 'Zed');
        $this->place($placed, 1);
        $b = $this->racer(30, 'Bob');
        $a = $this->racer(30, 'Amy');
        $this->racer(59, 'Other', GameVersion::factory()->create());

        $racers = $this->standings()['racers']->resolve();

        $this->assertSame([$placed->id, $a->id, $b->id], array_column($racers, 'id'));
    }

    #[Test]
    public function it_gives_each_racer_a_slug_and_portrait(): void
    {
        $racer = $this->racer(30, 'Amy');

        $racers = $this->standings()['racers']->resolve();

        $this->assertSame($racer->slug, $racers[0]['slug']);
        $this->assertArrayHasKey('portrait_url', $racers[0]);
        $this->assertNull($racers[0]['portrait_url']);
    }

    #[Test]
    public function it_omits_racers_when_asked(): void
    {
        $this->assertArrayNotHasKey('racers', $this->standings(withRacers: false));
    }

    // ==================== placements ====================

    #[Test]
    public function it_lists_placements_in_position_order_with_medals(): void
    {
        foreach ([4, 2, 1, 3] as $position) {
            $this->place($this->racer(60, "P{$position}"), $position);
        }

        $placements = $this->standings()['placements']->resolve();

        $this->assertSame([1, 2, 3, 4], array_column($placements, 'position'));
        $this->assertSame([Medal::Gold, Medal::Silver, Medal::Bronze, null], array_column($placements, 'medal'));
        $this->assertSame('P1', $placements[0]['character']->resolve()['name']);
    }

    #[Test]
    public function it_lists_tied_placements_together_by_character_name(): void
    {
        $this->place($this->racer(60, 'Zed'), 2);
        $this->place($this->racer(60, 'Borin'), 1);
        $this->place($this->racer(60, 'Aelin'), 1);

        $placements = $this->standings()['placements']->resolve();

        $this->assertSame(
            ['Aelin', 'Borin', 'Zed'],
            array_map(fn (array $placement): string => $placement['character']->resolve()['name'], $placements),
        );
        $this->assertSame([1, 1, 2], array_column($placements, 'position'));
    }

    #[Test]
    public function it_breaks_placement_ties_by_surname(): void
    {
        $huntington = Character::factory()->forGameVersion($this->version)->create(['id' => 9001, 'level' => 60, 'name' => 'Awak', 'surname' => 'Huntington']);
        $blastington = Character::factory()->forGameVersion($this->version)->create(['id' => 9002, 'level' => 60, 'name' => 'Awak', 'surname' => 'Blastington']);
        $this->place($huntington, 1);
        $this->place($blastington, 1);

        $placements = $this->standings()['placements']->resolve();

        $this->assertSame(
            [$blastington->id, $huntington->id],
            array_map(fn (array $placement): int => $placement['character']->resolve()['id'], $placements),
        );
    }

    #[Test]
    public function it_breaks_pack_and_racer_level_ties_by_surname(): void
    {
        $huntington = Character::factory()->forGameVersion($this->version)->create(['id' => 9001, 'level' => 30, 'name' => 'Awak', 'surname' => 'Huntington']);
        $blastington = Character::factory()->forGameVersion($this->version)->create(['id' => 9002, 'level' => 30, 'name' => 'Awak', 'surname' => 'Blastington']);

        $standings = $this->standings();

        $this->assertSame([$blastington->id, $huntington->id], array_column($standings['pack']->resolve(), 'id'));
        $this->assertSame([$blastington->id, $huntington->id], array_column($standings['racers']->resolve(), 'id'));
    }

    #[Test]
    public function it_serialises_the_reach_time_as_an_iso_8601_string(): void
    {
        $this->freezeSecond();
        $this->place($this->racer(60, 'Zed'), 1);

        $placements = $this->standings()['placements']->resolve();

        $this->assertSame(now()->toIso8601String(), $placements[0]['reached_at']);
    }

    // ==================== checked_at ====================

    #[Test]
    public function it_reads_checked_at_from_the_cache(): void
    {
        Cache::forever('race-to-60:checked-at', 'X');

        $this->assertSame('X', $this->standings()['checked_at']);
    }

    // ==================== run — lazy loading ====================

    #[Test]
    public function it_does_not_lazy_load_classes_races_or_media(): void
    {
        config(['media-library.force_lazy_loading' => false]);

        Storage::fake('s3');

        foreach ([30, 31, 32] as $level) {
            Character::factory()->forGameVersion($this->version)->withUniqueName()->withPlayableClass(PlayableClass::factory()->create())->withPlayableRace()->create(['level' => $level]);
        }
        $leaderClass = PlayableClass::factory()->create();
        $leaderClass->addMediaFromString('BINARY')
            ->usingFileName('classicon_7.jpg')
            ->withCustomProperties(['size' => 56])
            ->toMediaCollection('blizzard_icons');
        $leaderRace = PlayableRace::factory()->create(['id' => 6]);
        $leaderRace->addMediaFromString('AVATAR')
            ->usingFileName('6-1.jpg')
            ->withCustomProperties(['gender' => Gender::FEMALE->id()])
            ->toMediaCollection(HasShadowAvatars::MEDIA_COLLECTION);
        Character::factory()->forGameVersion($this->version)->withUniqueName()->withPlayableClass($leaderClass)->withPlayableRace($leaderRace)->create(['level' => 33, 'gender' => Gender::FEMALE]);
        $this->place($this->racer(60, 'Placed'), 1);

        Model::preventLazyLoading();

        try {
            $standings = $this->standings();
            $leader = $standings['leader']->resolve();
            $standings['pack']->resolve();
            $standings['racers']->resolve();
            $standings['placements']->resolve();
        } finally {
            Model::preventLazyLoading(false);
        }

        $this->assertSame(RaceState::Running, $standings['state']);
        $this->assertSame(Storage::disk('s3')->url('blizzard-cdn/icons/56/classicon_7.jpg'), $leader['playable_class']['icon_url']);
        $this->assertSame(Storage::disk('s3')->url('blizzard-cdn/shadow/avatar/6-1.jpg'), $leader['shadow_avatar_url']);
    }

    // ==================== helpers ====================

    private function racer(int $level, string $name, ?GameVersion $version = null): Character
    {
        return Character::factory()->forGameVersion($version ?? $this->version)->create(['level' => $level, 'name' => $name]);
    }

    private function place(Character $character, int $position, ?CarbonInterface $reachedAt = null): RacePlacement
    {
        return RacePlacement::factory()->create([
            'game_version_id' => $this->version->id,
            'character_id' => $character->id,
            'position' => $position,
            'reached_at' => $reachedAt ?? now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function standings(bool $withRacers = true): array
    {
        return BuildRaceStandings::run($this->version, $withRacers);
    }
}
