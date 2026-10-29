<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\PreselectGameVersion;
use App\Contracts\Actions\GameVersion\SharesCurrentGameVersionSession;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class PreselectGameVersionTest extends TestCase
{
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_prefers_the_requested_slug(): void
    {
        [$newer, $older] = $this->versions();
        session()->put(SharesCurrentGameVersionSession::SESSION_KEY, $newer->slug);

        $result = PreselectGameVersion::run(collect([$newer, $older]), $older->slug);

        $this->assertTrue($result->is($older));
    }

    #[Test]
    public function it_falls_back_to_the_current_version_when_selectable(): void
    {
        [$newer, $older] = $this->versions();
        session()->put(SharesCurrentGameVersionSession::SESSION_KEY, $older->slug);

        $result = PreselectGameVersion::run(collect([$newer, $older]), null);

        $this->assertTrue($result->is($older));
    }

    #[Test]
    public function it_falls_back_to_the_first_when_the_current_version_is_not_selectable(): void
    {
        [$newer, $older] = $this->versions();
        $other = GameVersion::factory()->create(['release_date' => now()->subYears(5)]);
        session()->put(SharesCurrentGameVersionSession::SESSION_KEY, $other->slug);

        $result = PreselectGameVersion::run(collect([$newer, $older]), null);

        $this->assertTrue($result->is($newer));
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_ignores_an_unknown_requested_slug(): void
    {
        [$newer, $older] = $this->versions();
        session()->put(SharesCurrentGameVersionSession::SESSION_KEY, $older->slug);

        $result = PreselectGameVersion::run(collect([$newer, $older]), 'nope');

        $this->assertTrue($result->is($older));
    }

    #[Group('edge-cases')]
    #[Test]
    public function it_returns_null_for_an_empty_list(): void
    {
        $this->assertNull(PreselectGameVersion::run(collect(), 'anything'));
    }

    /**
     * @return array{0: GameVersion, 1: GameVersion}
     */
    private function versions(): array
    {
        return [
            GameVersion::factory()->create(['release_date' => now()->subWeek()]),
            GameVersion::factory()->create(['release_date' => now()->subYear()]),
        ];
    }
}
