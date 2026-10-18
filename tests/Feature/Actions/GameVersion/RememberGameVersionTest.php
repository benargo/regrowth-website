<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\RememberGameVersion;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Models\GameVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class RememberGameVersionTest extends TestCase
{
    use RefreshDatabase;

    #[Group('happy-path')]
    #[Test]
    public function it_writes_the_slug_into_the_session(): void
    {
        RememberGameVersion::run('some-slug');

        $this->assertSame('some-slug', session(ResolveCurrentGameVersion::SESSION_KEY));
    }

    #[Test]
    public function it_resolves_the_remembered_version_after_a_switch_in_the_same_request(): void
    {
        $a = GameVersion::factory()->create(['release_date' => now()->subDay()]);
        $b = GameVersion::factory()->create(['release_date' => now()->subYear()]);

        $this->assertTrue(ResolveCurrentGameVersion::run()->is($a));

        RememberGameVersion::run($b->slug);

        $this->assertTrue(ResolveCurrentGameVersion::run()->is($b));
    }
}
