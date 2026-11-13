<?php

namespace Tests\Unit\Traits\GuildRosterManager;

use App\Models\GameVersion;
use App\Traits\GuildRosterManager\ResolvesStoragePaths;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ResolvesStoragePathsTest extends TestCase
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
        config(['services.grm.upload_path' => 'custom/{game_version}/export.csv']);

        $path = $this->resolver()->resolve(new GameVersion(['slug' => 'classic']));

        $this->assertSame('custom/classic/export.csv', $path);
    }

    #[Test]
    public function it_fills_the_game_version_slug_into_the_archive_template(): void
    {
        $path = $this->resolver()->resolveArchive(new GameVersion(['slug' => 'tbc']));

        $this->assertSame('grm/archives/tbc', $path);
    }

    #[Test]
    public function it_returns_the_archive_root_when_no_game_version_is_given(): void
    {
        $this->assertSame('grm/archives', $this->resolver()->resolveArchive());
    }

    #[Test]
    public function it_follows_the_configured_archive_template(): void
    {
        config(['services.grm.archive_path' => 'custom/{game_version}']);

        $this->assertSame('custom/classic', $this->resolver()->resolveArchive(new GameVersion(['slug' => 'classic'])));
        $this->assertSame('custom', $this->resolver()->resolveArchive());
    }

    // ==================== helpers ====================

    private function resolver(): object
    {
        return new class
        {
            use ResolvesStoragePaths;

            public function resolve(GameVersion $gameVersion): string
            {
                return $this->grmUploadPath($gameVersion);
            }

            public function resolveArchive(?GameVersion $gameVersion = null): string
            {
                return $this->grmArchivePath($gameVersion);
            }
        };
    }
}
