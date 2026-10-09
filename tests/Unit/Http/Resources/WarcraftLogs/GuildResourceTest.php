<?php

namespace Tests\Unit\Http\Resources\WarcraftLogs;

use App\Http\Integrations\WarcraftLogs\WarcraftLogsNamespace;
use App\Http\Resources\WarcraftLogs\GuildResource;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('warcraftlogs-integration')]
class GuildResourceTest extends TestCase
{
    #[Test]
    #[Group('resource')]
    public function it_returns_only_the_id_and_namespace_by_default(): void
    {
        $array = GuildResource::make($this->guild())->resolve(new Request);

        $this->assertSame([
            'id' => 774848,
            'namespace' => [
                'value' => WarcraftLogsNamespace::Anniversary,
                'label' => 'The Burning Crusade Classic Anniversary',
            ],
        ], $array);
    }

    #[Test]
    #[Group('resource')]
    public function it_embeds_loaded_game_versions_and_tags(): void
    {
        $guild = $this->guild();
        $guild->setRelation('gameVersions', new Collection([GameVersion::factory()->make(['id' => 7, 'title' => 'TBC Anniversary'])]));
        $guild->setRelation('guildTags', new Collection([GuildTag::factory()->make(['id' => 101, 'name' => 'Main Raid', 'count_attendance' => true, 'warcraft_logs_guild_id' => 774848])]));

        $array = GuildResource::make($guild)->resolve(new Request);

        $this->assertSame(7, $array['game_versions'][0]['id']);
        $this->assertSame('TBC Anniversary', $array['game_versions'][0]['title']);
        $this->assertSame([['id' => 101, 'name' => 'Main Raid', 'count_attendance' => true, 'guild_id' => 774848]], $array['guild_tags']);
    }

    #[Test]
    #[Group('resource')]
    public function it_includes_counts_only_when_they_are_loaded(): void
    {
        $guild = $this->guild();
        $guild->setAttribute('game_versions_count', 2);
        $guild->setAttribute('guild_tags_count', 5);
        $guild->setAttribute('reports_count', 40);

        $array = GuildResource::make($guild)->resolve(new Request);

        $this->assertSame(2, $array['game_versions_count']);
        $this->assertSame(5, $array['guild_tags_count']);
        $this->assertSame(40, $array['reports_count']);
    }

    // ==================== helpers ====================

    private function guild(): Guild
    {
        return Guild::factory()->make(['id' => 774848, 'namespace' => WarcraftLogsNamespace::Anniversary]);
    }
}
