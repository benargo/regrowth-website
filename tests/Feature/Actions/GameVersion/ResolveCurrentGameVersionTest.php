<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class ResolveCurrentGameVersionTest extends TestCase
{
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_returns_the_session_version_when_it_is_selectable(): void
    {
        $defaultRoster = GameVersion::factory()->fetchableRoster()->create();
        $other = GameVersion::factory()->create(['release_date' => now()->addMonth()]);

        session()->put(ResolveCurrentGameVersion::SESSION_KEY, $other->slug);

        $this->assertTrue(ResolveCurrentGameVersion::run(collect([$defaultRoster, $other]))->is($other));
    }

    #[Test]
    public function it_returns_the_default_roster_when_the_session_is_empty(): void
    {
        $defaultRoster = GameVersion::factory()->fetchableRoster()->create();
        $other = GameVersion::factory()->create(['release_date' => now()->addMonth()]);

        $this->assertTrue(ResolveCurrentGameVersion::run(collect([$other, $defaultRoster]))->is($defaultRoster));
    }

    #[Test]
    public function it_returns_the_default_roster_when_the_session_version_is_not_selectable(): void
    {
        $defaultRoster = GameVersion::factory()->fetchableRoster()->create();
        $unselectable = GameVersion::factory()->create(['release_date' => now()->addMonth()]);

        session()->put(ResolveCurrentGameVersion::SESSION_KEY, $unselectable->slug);

        $this->assertTrue(ResolveCurrentGameVersion::run(collect([$defaultRoster]))->is($defaultRoster));
        $this->assertSame($unselectable->slug, session(ResolveCurrentGameVersion::SESSION_KEY));
    }

    #[Test]
    public function it_returns_the_first_selectable_version_when_the_default_roster_is_not_selectable(): void
    {
        GameVersion::factory()->fetchableRoster()->create();
        $first = GameVersion::factory()->create(['release_date' => now()->addMonth()]);
        $second = GameVersion::factory()->create(['release_date' => now()->addMonth()]);

        $this->assertTrue(ResolveCurrentGameVersion::run(collect([$first, $second]))->is($first));
    }

    #[Test]
    public function it_returns_null_when_nothing_is_selectable(): void
    {
        GameVersion::factory()->fetchableRoster()->create();

        $this->assertNull(ResolveCurrentGameVersion::run(collect()));
    }
}
