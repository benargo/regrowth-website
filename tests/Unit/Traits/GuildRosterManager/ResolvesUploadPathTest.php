<?php

namespace Tests\Unit\Traits\GuildRosterManager;

use App\Models\GameVersion;
use App\Traits\GuildRosterManager\ResolvesUploadPath;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ResolvesUploadPathTest extends TestCase
{
    #[Test]
    public function it_fills_the_game_version_slug_into_the_configured_template(): void
    {
        $path = $this->resolver()->resolve(new GameVersion(['slug' => 'tbc']));

        $this->assertSame('grm/uploads/tbc/latest.csv', $path);
    }

    #[Test]
    public function it_follows_the_configured_template(): void
    {
        config(['services.guild_roster_manager.upload_path' => 'custom/{game_version}/export.csv']);

        $path = $this->resolver()->resolve(new GameVersion(['slug' => 'classic']));

        $this->assertSame('custom/classic/export.csv', $path);
    }

    // ==================== helpers ====================

    private function resolver(): object
    {
        return new class
        {
            use ResolvesUploadPath;

            public function resolve(GameVersion $gameVersion): string
            {
                return $this->grmUploadPath($gameVersion);
            }
        };
    }
}
