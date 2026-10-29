<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
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
    public function it_returns_the_session_version(): void
    {
        GameVersion::factory()->create(['release_date' => now()->subDay()]);
        $unreleased = GameVersion::factory()->create(['release_date' => now()->addMonth()]);

        session()->put(SharesCurrentGameVersionSession::SESSION_KEY, $unreleased->slug);

        $this->assertTrue(ResolveCurrentGameVersion::run()->is($unreleased));
    }

    #[Test]
    public function it_defaults_to_the_most_recently_released_version(): void
    {
        GameVersion::factory()->create(['release_date' => now()->subYear()]);
        $latest = GameVersion::factory()->create(['release_date' => now()->subDay()]);
        GameVersion::factory()->create(['release_date' => now()->addMonth()]);

        $this->assertTrue(ResolveCurrentGameVersion::run()->is($latest));
    }

    #[Test]
    public function it_defaults_to_the_newest_version_when_none_is_released(): void
    {
        GameVersion::factory()->create(['release_date' => now()->addMonth()]);
        $newest = GameVersion::factory()->create(['release_date' => now()->addYear()]);

        $this->assertTrue(ResolveCurrentGameVersion::run()->is($newest));
    }

    #[Test]
    public function it_falls_back_to_the_default_and_keeps_a_stale_session_slug(): void
    {
        $default = GameVersion::factory()->create(['release_date' => now()->subDay()]);

        session()->put(SharesCurrentGameVersionSession::SESSION_KEY, 'gone');

        $this->assertTrue(ResolveCurrentGameVersion::run()->is($default));
        $this->assertSame('gone', session(SharesCurrentGameVersionSession::SESSION_KEY));
    }

    #[Test]
    public function it_follows_a_session_change_within_the_same_request(): void
    {
        $latest = GameVersion::factory()->create(['release_date' => now()->subDay()]);
        $older = GameVersion::factory()->create(['release_date' => now()->subYear()]);

        $this->assertTrue(ResolveCurrentGameVersion::run()->is($latest));

        session()->put(SharesCurrentGameVersionSession::SESSION_KEY, $older->slug);

        $this->assertTrue(ResolveCurrentGameVersion::run()->is($older));
    }

    #[Test]
    public function it_returns_null_when_there_are_no_versions(): void
    {
        $this->assertNull(ResolveCurrentGameVersion::run());
    }
}
